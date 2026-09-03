<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Candidates;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WPCBTPro\Candidates\CandidateBulkImportService;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Core\Plugin;
use WPCBTPro\Institutions\InstitutionRepository;

/**
 * Exercises the real spreadsheet-reading -> preview -> confirm pipeline
 * against a genuine .xlsx built with PhpSpreadsheet's own writer (not a
 * hand-typed fixture), proving parseFile()'s column-alias resolution and
 * import()'s "create a WP account only when a password was given" branch
 * both work against the real library, not a mock of it.
 */
final class CandidateBulkImportTest extends \WP_UnitTestCase
{
    private function writeSheet(array $header, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'wpcbtpro-candidates-') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function testAValidRowWithAPasswordCreatesAWpAccountAndACandidate(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $email = 'bulk-' . wp_generate_password(8, false) . '@example.org';

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Email', 'Password'],
            [['Ada', 'Lovelace', $email, 'Sup3rSecret!']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertCount(1, $rows);
        self::assertSame([], $rows[0]['errors']);
        self::assertSame('Sup3rSecret!', $rows[0]['password']);

        $result = $service->import($rows[0]);
        self::assertArrayHasKey('candidate_id', $result);

        $candidate = (new CandidateRepository())->find($result['candidate_id']);
        self::assertNotNull($candidate);
        self::assertSame('Ada', $candidate['first_name']);
        self::assertSame($email, $candidate['email']);
        self::assertGreaterThan(0, (int) $candidate['wp_user_id']);

        $user = get_user_by('id', (int) $candidate['wp_user_id']);
        self::assertNotFalse($user);
        self::assertSame($email, $user->user_email);
        self::assertContains('subscriber', $user->roles);
        self::assertTrue(wp_check_password('Sup3rSecret!', $user->user_pass, $user->ID));
    }

    public function testARowWithNoPasswordCreatesOnlyACandidateNoWpAccount(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $path = $this->writeSheet(
            ['First Name', 'Last Name'],
            [['Grace', 'Hopper']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame('', $rows[0]['password']);

        $result = $service->import($rows[0]);
        $candidate = (new CandidateRepository())->find($result['candidate_id']);

        self::assertNotNull($candidate);
        self::assertEmpty($candidate['wp_user_id']);
    }

    public function testAMissingLastNameIsFlaggedAsAnErrorAndSkippedByConfirm(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $path = $this->writeSheet(
            ['First Name', 'Last Name'],
            [['NoSurname', '']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertCount(1, $rows);
        self::assertArrayHasKey('last_name', $rows[0]['errors']);
    }

    public function testColumnHeaderAliasesAreRecognized(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $path = $this->writeSheet(
            ['First', 'Surname', 'Reg No'],
            [['Marie', 'Curie', 'REG-001']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame('Marie', $rows[0]['input']['first_name']);
        self::assertSame('Curie', $rows[0]['input']['last_name']);
        self::assertSame('REG-001', $rows[0]['input']['registration_number']);
    }

    public function testADuplicateEmailIsFlaggedAsANonBlockingWarning(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $email = 'dup-' . wp_generate_password(8, false) . '@example.org';

        (new CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-DUP-' . wp_generate_password(6, false, false),
            'first_name' => 'Existing',
            'last_name' => 'Candidate',
            'email' => $email,
            'status' => 'active',
        ]);

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Email'],
            [['New', 'Candidate', $email]]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertSame([], $rows[0]['errors'], 'A duplicate email should warn, not block, the row.');
        self::assertNotEmpty($rows[0]['warnings']);
    }

    public function testRegistrationNumberBecomesTheLoginUsernameNotEmail(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $email = 'bulk-username-' . wp_generate_password(8, false) . '@example.org';

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Email', 'Registration Number', 'Password'],
            [['Ada', 'Lovelace', $email, '2024/CS/REG-001', 'Sup3rSecret!']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        $result = $service->import($rows[0]);
        $candidate = (new CandidateRepository())->find($result['candidate_id']);
        $user = get_user_by('id', (int) $candidate['wp_user_id']);

        self::assertNotFalse($user);
        self::assertSame('2024csreg-001', $user->user_login, 'sanitize_user() strips the slashes but the registration number is otherwise used as-is.');
        self::assertNotSame($email, $user->user_login, 'The username should be the registration number, not the email.');
    }

    public function testMultipleCandidatesCanShareTheSameEmailAddress(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();
        $sharedEmail = 'family-' . wp_generate_password(8, false) . '@example.org';

        $path = $this->writeSheet(
            ['First Name', 'Last Name', 'Email', 'Registration Number', 'Password'],
            [
                ['First', 'Sibling', $sharedEmail, 'SHARED-REG-001', 'Sup3rSecret1!'],
                ['Second', 'Sibling', $sharedEmail, 'SHARED-REG-002', 'Sup3rSecret2!'],
            ]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        $firstResult = $service->import($rows[0]);
        $secondResult = $service->import($rows[1]);

        self::assertArrayHasKey('candidate_id', $firstResult);
        self::assertArrayHasKey('candidate_id', $secondResult, 'The second account, sharing the same email, must still be created successfully.');

        $firstCandidate = (new CandidateRepository())->find($firstResult['candidate_id']);
        $secondCandidate = (new CandidateRepository())->find($secondResult['candidate_id']);
        $firstUser = get_user_by('id', (int) $firstCandidate['wp_user_id']);
        $secondUser = get_user_by('id', (int) $secondCandidate['wp_user_id']);

        self::assertNotFalse($firstUser);
        self::assertNotFalse($secondUser);
        self::assertSame($sharedEmail, $firstUser->user_email);
        self::assertSame($sharedEmail, $secondUser->user_email);
        self::assertNotSame($firstUser->ID, $secondUser->ID, 'Two genuinely distinct WordPress accounts.');
    }

    public function testBlankRowsAreSkipped(): void
    {
        $institutionId = (new InstitutionRepository())->ensureDefault();

        $path = $this->writeSheet(
            ['First Name', 'Last Name'],
            [['Ada', 'Lovelace'], ['', ''], ['Grace', 'Hopper']]
        );

        /** @var CandidateBulkImportService $service */
        $service = Plugin::instance()->container()->get(CandidateBulkImportService::class);

        try {
            $rows = $service->parseFile($path, $institutionId);
        } finally {
            unlink($path);
        }

        self::assertCount(2, $rows);
    }
}
