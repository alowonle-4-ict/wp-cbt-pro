<?php
/**
 * @var array<string, mixed> $exam
 * @var array<int, array<string, mixed>> $candidates
 */
if (!defined('ABSPATH')) {
    exit;
}

$examId = (int) $exam['id'];
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php echo esc_html(sprintf(
        /* translators: %s: exam name */
        __('Roster: %s', 'wp-cbt-pro'),
        $exam['name']
    )); ?></h1>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success banner, not a state change; values are cast to int below. ?>
    <?php if (isset($_GET['added'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(sprintf(
                /* translators: 1: number added, 2: number selected */
                __('Added %1$d of %2$d selected candidates to this exam\'s roster.', 'wp-cbt-pro'),
                absint($_GET['added']),
                isset($_GET['total']) ? absint($_GET['total']) : 0
            )); ?></p>
        </div>
    <?php elseif (isset($_GET['removed'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Candidate removed from the roster.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($exam['restrict_to_roster'])): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('This exam is not currently restricted to its roster — any eligible candidate can start it. Turn on "Restrict to roster" on the exam\'s edit screen to enforce this list.', 'wp-cbt-pro'); ?></p>
        </div>
    <?php endif; ?>

    <h2><?php esc_html_e('Current roster', 'wp-cbt-pro'); ?></h2>
    <?php if ($candidates === []): ?>
        <p><em><?php esc_html_e('No candidates on this roster yet.', 'wp-cbt-pro'); ?></em></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Reg. Number', 'wp-cbt-pro'); ?></th>
                    <th><?php esc_html_e('Email', 'wp-cbt-pro'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $candidate): $candidateId = (int) $candidate['id']; ?>
                    <tr>
                        <td><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></td>
                        <td><?php echo esc_html((string) $candidate['registration_number']); ?></td>
                        <td><?php echo esc_html((string) $candidate['email']); ?></td>
                        <td>
                            <a
                                href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'page' => 'wpcbtpro-exam-roster',
                                    'exam_id' => $examId,
                                    'candidate_id' => $candidateId,
                                    'action' => 'remove',
                                ], admin_url('admin.php')), 'wpcbtpro_exam_roster_remove_' . $examId . '_' . $candidateId)); ?>"
                                onclick="return confirm('<?php echo esc_js(__('Remove this candidate from the roster?', 'wp-cbt-pro')); ?>');"
                            ><?php esc_html_e('Remove', 'wp-cbt-pro'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2><?php esc_html_e('Upload more candidates', 'wp-cbt-pro'); ?></h2>
    <p><?php esc_html_e('Upload a spreadsheet of candidates for this exam. A row matching an existing candidate (by registration number or email) is added to the roster without creating a duplicate; otherwise a new candidate is created.', 'wp-cbt-pro'); ?></p>

    <details class="wpcbtpro-txt-format-help">
        <summary><?php esc_html_e('Spreadsheet format', 'wp-cbt-pro'); ?></summary>
        <p><?php esc_html_e('The first row must be a header. These columns are recognized (case-insensitive); only First Name and Last Name are required:', 'wp-cbt-pro'); ?></p>
        <ul>
            <li><?php esc_html_e('First Name, Last Name (required)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Email, Phone, Department, Class, Registration Number (optional)', 'wp-cbt-pro'); ?></li>
            <li><?php esc_html_e('Password (optional) — when given, a WordPress account is created for a newly created candidate so they can sign in.', 'wp-cbt-pro'); ?></li>
        </ul>
    </details>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpcbtpro_exam_roster_upload_' . $examId, 'wpcbtpro_exam_roster_upload_nonce'); ?>
        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) $examId); ?>">
        <p><input type="file" name="wpcbtpro_roster" accept=".xlsx,.xls,.csv" required></p>
        <?php submit_button(__('Upload & Preview', 'wp-cbt-pro')); ?>
    </form>

    <p><a href="<?php echo esc_url(add_query_arg(['page' => 'wpcbtpro-exams', 'action' => 'edit', 'id' => $examId], admin_url('admin.php'))); ?>"><?php esc_html_e('Back to exam', 'wp-cbt-pro'); ?></a></p>
</div>
