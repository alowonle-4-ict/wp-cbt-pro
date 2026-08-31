<?php

declare(strict_types=1);

namespace WPCBTPro\Candidates;

use PhpOffice\PhpSpreadsheet\IOFactory;
use WPCBTPro\Core\SpreadsheetSupport;

/**
 * Upload -> preview -> confirm (same rhythm as the Word question import):
 * parseFile() only ever reads the spreadsheet and validates each row —
 * nothing reaches wp_cbt_candidates or wp_users until import() runs against
 * a row an admin actually confirmed. A password column is optional per row;
 * when present, import() creates a real WP account (via wp_create_user())
 * before creating the candidate record, so the candidate can sign in
 * immediately through the candidate login page.
 */
final class CandidateBulkImportService
{
    /** @var array<string, string[]> canonical field => recognized header aliases */
    private const COLUMN_ALIASES = [
        'first_name' => ['first name', 'firstname', 'first'],
        'last_name' => ['last name', 'lastname', 'surname', 'last'],
        'email' => ['email', 'email address'],
        'phone' => ['phone', 'phone number', 'mobile', 'mobile number'],
        'department' => ['department', 'dept'],
        'class' => ['class', 'level'],
        'registration_number' => ['registration number', 'reg number', 'reg no', 'registration no', 'matric number', 'matric no'],
        'password' => ['password', 'pass'],
        // Recognized here (so it survives the shared parseFile()/buildPreviewRow()
        // pipeline every candidate spreadsheet already goes through) but only
        // acted on by ExamAssignmentImportService — CandidateService ignores
        // unknown $input keys, so this is a harmless no-op for plain candidate
        // import and the per-exam roster import, which don't read it.
        'exam' => ['exam', 'exam name', 'exam code'],
    ];

    public function __construct(
        private readonly CandidateService $candidateService,
        private readonly CandidateRepository $candidateRepository,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>> preview rows, one per spreadsheet row
     */
    public function parseFile(string $filePath, int $institutionId): array
    {
        if (!SpreadsheetSupport::available()) {
            throw new \RuntimeException(SpreadsheetSupport::missingMessage());
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheetRows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if ($sheetRows === []) {
            return [];
        }

        $header = array_map(
            static fn ($cell): string => strtolower(trim((string) $cell)),
            array_shift($sheetRows)
        );
        $columns = $this->resolveColumns($header);

        $rows = [];
        $rowNumber = 1; // header was row 1
        foreach ($sheetRows as $cells) {
            $rowNumber++;
            if ($this->isBlankRow($cells)) {
                continue;
            }
            $rows[] = $this->buildPreviewRow($rowNumber, $cells, $columns, $institutionId);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row one of parseFile()'s preview rows
     * @return array{candidate_id:int}|array{error:string}
     */
    public function import(array $row): array
    {
        $input = $row['input'];
        $password = (string) $row['password'];

        if ($password !== '') {
            $userId = $this->createWpUser($input, $password);
            if ($userId instanceof \WP_Error) {
                return ['error' => $userId->get_error_message()];
            }
            $input['wp_user_id'] = $userId;
        }

        $candidateId = $this->candidateService->create($input);

        return ['candidate_id' => $candidateId];
    }

    /** @param string[] $header @return array<string, int> canonical field => column index */
    private function resolveColumns(array $header): array
    {
        $columns = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($header as $index => $label) {
                if (in_array($label, $aliases, true)) {
                    $columns[$field] = $index;
                    break;
                }
            }
        }
        return $columns;
    }

    /** @param array<int, mixed> $cells */
    private function isBlankRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<int, mixed> $cells
     * @param array<string, int> $columns
     * @return array<string, mixed>
     */
    private function buildPreviewRow(int $rowNumber, array $cells, array $columns, int $institutionId): array
    {
        $get = static function (string $field) use ($cells, $columns): string {
            return isset($columns[$field]) ? trim((string) ($cells[$columns[$field]] ?? '')) : '';
        };

        $input = [
            'institution_id' => $institutionId,
            'first_name' => $get('first_name'),
            'last_name' => $get('last_name'),
            'email' => $get('email'),
            'phone' => $get('phone'),
            'department' => $get('department'),
            'class' => $get('class'),
            'registration_number' => $get('registration_number'),
            'exam' => $get('exam'),
        ];
        $password = $get('password');

        $errors = $this->candidateService->validate($input);

        $warnings = [];
        if ($input['email'] !== '' && $this->candidateRepository->findByEmail($input['email']) !== null) {
            $warnings[] = __('A candidate with this email already exists; importing this row will create a duplicate.', 'wp-cbt-pro');
        }

        return [
            'row_number' => $rowNumber,
            'input' => $input,
            'password' => $password,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /** @param array<string, mixed> $input */
    private function createWpUser(array $input, string $password): int|\WP_Error
    {
        $username = $this->generateUsername((string) $input['first_name'], (string) $input['last_name']);
        // A candidate with no email still gets a real, sign-in-able account —
        // the .invalid TLD is IANA-reserved specifically for placeholders
        // like this that must look like an email but will never be mailed.
        $email = $input['email'] !== '' ? $input['email'] : $username . '@candidates.wpcbtpro.invalid';

        $userId = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'display_name' => trim($input['first_name'] . ' ' . $input['last_name']),
            'role' => 'subscriber',
        ]);

        return is_wp_error($userId) ? $userId : (int) $userId;
    }

    private function generateUsername(string $firstName, string $lastName): string
    {
        $base = sanitize_user(strtolower($firstName . '.' . $lastName), true);
        if ($base === '') {
            $base = 'candidate';
        }

        $username = $base;
        $suffix = 1;
        while (username_exists($username)) {
            $suffix++;
            $username = $base . $suffix;
        }

        return $username;
    }
}
