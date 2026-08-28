<?php
/**
 * @var array<string,mixed>|null $exam
 * @var array<string,string> $errors
 * @var bool $showInstitutionField
 * @var array<int,array<string,mixed>> $institutions
 * @var array<int,array<string,mixed>> $availableQuestions
 * @var array<int,array<string,mixed>> $assignedByQuestionId
 * @var array<string,array<string,mixed>> $pools
 * @var string $action
 */
if (!defined('ABSPATH')) {
    exit;
}

// Redisplay-only fallbacks, reached only after handleSave() already ran check_admin_referer(); esc_attr() covers output safety, and $checkedField() only ever feeds a boolean into checked().
// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$field = static fn (string $key, string $default = ''): string => esc_attr(wp_unslash((string) ($exam[$key] ?? $_POST[$key] ?? $default)));

// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$checkedField = static fn (string $key): bool => !empty($exam[$key] ?? (wp_unslash($_POST[$key] ?? false)));

$toLocalDatetime = static function (?string $mysqlDatetime): string {
    if (empty($mysqlDatetime)) {
        return '';
    }
    $ts = strtotime($mysqlDatetime);
    return $ts ? gmdate('Y-m-d\TH:i', $ts) : '';
};

$listUrl = add_query_arg(['page' => 'wpcbtpro-exams'], admin_url('admin.php'));
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php echo $action === 'edit' ? esc_html__('Edit Exam', 'wp-cbt-pro') : esc_html__('Add Exam', 'wp-cbt-pro'); ?></h1>

    <?php if (isset($_GET['created'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Exam created.', 'wp-cbt-pro'); ?></p></div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Exam updated.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error">
            <p><?php foreach ($errors as $error): ?><?php echo esc_html($error); ?><br><?php endforeach; ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="wpcbtpro-form">
        <?php wp_nonce_field('wpcbtpro_save_exam', 'wpcbtpro_exam_nonce'); ?>
        <input type="hidden" name="exam_id" value="<?php echo esc_attr((string) ($exam['id'] ?? 0)); ?>">

        <h2><?php esc_html_e('Basic Information', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <?php if ($showInstitutionField): ?>
            <tr>
                <th><label for="institution_id"><?php esc_html_e('Institution', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="institution_id" name="institution_id" <?php echo $action === 'edit' ? 'disabled' : ''; ?>>
                        <?php foreach ($institutions as $institution): ?>
                            <option value="<?php echo esc_attr((string) $institution['id']); ?>"
                                <?php selected((int) ($exam['institution_id'] ?? 0), (int) $institution['id']); ?>>
                                <?php echo esc_html($institution['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><label for="name"><?php esc_html_e('Exam name', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo $field('name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>" required></td>
            </tr>
            <tr>
                <th><label for="subject"><?php esc_html_e('Subject', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="subject" name="subject" class="regular-text" value="<?php echo $field('subject'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="description"><?php esc_html_e('Description', 'wp-cbt-pro'); ?></label></th>
                <td><textarea id="description" name="description" rows="3" class="large-text"><?php echo esc_textarea($exam['description'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="instructions"><?php esc_html_e('Instructions shown before the exam starts', 'wp-cbt-pro'); ?></label></th>
                <td><textarea id="instructions" name="instructions" rows="4" class="large-text"><?php echo esc_textarea($exam['instructions'] ?? ''); ?></textarea></td>
            </tr>
            <?php if ($action === 'edit'): ?>
            <tr>
                <th><label for="status"><?php esc_html_e('Status', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="status" name="status">
                        <?php foreach (['draft' => __('Draft', 'wp-cbt-pro'), 'active' => __('Active', 'wp-cbt-pro'), 'closed' => __('Closed', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($exam['status'] ?? 'draft', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <h2><?php esc_html_e('Scheduling & Attempts', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="duration_minutes"><?php esc_html_e('Duration (minutes)', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="number" min="1" id="duration_minutes" name="duration_minutes" value="<?php echo $field('duration_minutes', '60'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>" required></td>
            </tr>
            <tr>
                <th><label for="start_at"><?php esc_html_e('Start', 'wp-cbt-pro'); ?></label></th>
                <td><input type="datetime-local" id="start_at" name="start_at" value="<?php echo esc_attr($toLocalDatetime($exam['start_at'] ?? null)); ?>"></td>
            </tr>
            <tr>
                <th><label for="end_at"><?php esc_html_e('End', 'wp-cbt-pro'); ?></label></th>
                <td><input type="datetime-local" id="end_at" name="end_at" value="<?php echo esc_attr($toLocalDatetime($exam['end_at'] ?? null)); ?>"></td>
            </tr>
            <tr>
                <th><label for="attempt_limit"><?php esc_html_e('Attempt limit', 'wp-cbt-pro'); ?></label></th>
                <td><input type="number" min="1" id="attempt_limit" name="attempt_limit" value="<?php echo $field('attempt_limit', '1'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
            <tr>
                <th><label for="pass_mark"><?php esc_html_e('Pass mark (%)', 'wp-cbt-pro'); ?></label></th>
                <td><input type="number" min="0" max="100" step="0.01" id="pass_mark" name="pass_mark" value="<?php echo $field('pass_mark'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>"></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Randomization & Grading', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('Randomize questions', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="randomize_questions" value="1" <?php checked($checkedField('randomize_questions')); ?>> <?php esc_html_e('Order is reshuffled per candidate, deterministically from their attempt seed.', 'wp-cbt-pro'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Randomize options', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="randomize_options" value="1" <?php checked($checkedField('randomize_options')); ?>> <?php esc_html_e('Answer option order is reshuffled per candidate.', 'wp-cbt-pro'); ?></label></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Negative marking', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="negative_marking" value="1" <?php checked($checkedField('negative_marking')); ?>> <?php esc_html_e('Apply each question\'s negative mark for wrong answers.', 'wp-cbt-pro'); ?></label></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Camera & Monitoring', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><?php esc_html_e('Camera required', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="camera_required" value="1" <?php checked($checkedField('camera_required')); ?>> <?php esc_html_e('Candidate must grant camera access before starting (§11).', 'wp-cbt-pro'); ?></label></td>
            </tr>
            <tr>
                <th><label for="microphone_mode"><?php esc_html_e('Microphone', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="microphone_mode" name="microphone_mode">
                        <?php foreach (['off' => __('Off', 'wp-cbt-pro'), 'camera_only' => __('Camera only', 'wp-cbt-pro'), 'camera_and_mic' => __('Camera + microphone', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($exam['microphone_mode'] ?? 'off', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Identity verification', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="identity_verification" value="1" <?php checked($checkedField('identity_verification')); ?>> <?php esc_html_e('Verify candidate photo before the exam starts (§12).', 'wp-cbt-pro'); ?></label></td>
            </tr>
            <tr>
                <th><label for="snapshot_interval_seconds"><?php esc_html_e('Snapshot interval (seconds)', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <input type="number" min="0" id="snapshot_interval_seconds" name="snapshot_interval_seconds" value="<?php echo $field('snapshot_interval_seconds'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $field() escapes via esc_attr(), see top of file ?>">
                    <p class="description"><?php esc_html_e('Leave blank to disable periodic snapshots.', 'wp-cbt-pro'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Fullscreen required', 'wp-cbt-pro'); ?></th>
                <td><label><input type="checkbox" name="fullscreen_required" value="1" <?php checked($checkedField('fullscreen_required')); ?>></label></td>
            </tr>
        </table>

        <h2><?php esc_html_e('Results', 'wp-cbt-pro'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="result_visibility"><?php esc_html_e('Result visibility', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <select id="result_visibility" name="result_visibility">
                        <?php foreach (['immediate' => __('Immediate', 'wp-cbt-pro'), 'delayed' => __('Delayed', 'wp-cbt-pro'), 'manual' => __('Manual release', 'wp-cbt-pro')] as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($exam['result_visibility'] ?? 'immediate', $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e('Questions & Pools', 'wp-cbt-pro'); ?></h2>
        <p class="description">
            <?php esc_html_e('Check a question to include it. Leave "Pool" blank for a fixed question every candidate sees. Give questions the same pool key to draw a random subset from them per attempt — define how many to draw for each pool key below.', 'wp-cbt-pro'); ?>
        </p>

        <?php if (empty($availableQuestions)): ?>
            <p><em><?php esc_html_e('No active questions found for this institution yet. Add questions via the question bank or Word import first.', 'wp-cbt-pro'); ?></em></p>
        <?php else: ?>
            <div class="wpcbtpro-table-scroll">
                <table class="widefat striped wpcbtpro-question-picker">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Include', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Question', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Type', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Pool key', 'wp-cbt-pro'); ?></th>
                            <th><?php esc_html_e('Order', 'wp-cbt-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availableQuestions as $question):
                            $qid = (int) $question['id'];
                            $assigned = $assignedByQuestionId[$qid] ?? null;
                        ?>
                            <tr>
                                <td><input type="checkbox" name="assign[<?php echo esc_attr((string) $qid); ?>][include]" value="1" <?php checked($assigned !== null); ?>></td>
                                <td><?php echo wp_kses_post(wp_trim_words(wp_strip_all_tags($question['content']), 16)); ?></td>
                                <td><?php echo esc_html($question['type']); ?></td>
                                <td><input type="text" name="assign[<?php echo esc_attr((string) $qid); ?>][pool]" value="<?php echo esc_attr($assigned['pool_id'] ?? ''); ?>" class="small-text"></td>
                                <td><input type="number" name="assign[<?php echo esc_attr((string) $qid); ?>][order]" value="<?php echo esc_attr((string) ($assigned['sort_order'] ?? 0)); ?>" class="small-text"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h3><?php esc_html_e('Pool draw counts', 'wp-cbt-pro'); ?></h3>
        <?php
        $poolKeys = array_keys($pools);
        for ($i = 0; $i < 5; $i++):
            $key = $poolKeys[$i] ?? '';
            $pool = $key !== '' ? $pools[$key] : ['pool_key' => '', 'name' => '', 'draw_count' => 1];
        ?>
            <p class="wpcbtpro-pool-row">
                <input type="text" name="pools[<?php echo (int) $i; ?>][key]" placeholder="<?php esc_attr_e('pool key (e.g. algebra)', 'wp-cbt-pro'); ?>" value="<?php echo esc_attr($pool['pool_key']); ?>" class="regular-text">
                <input type="text" name="pools[<?php echo (int) $i; ?>][name]" placeholder="<?php esc_attr_e('Display name', 'wp-cbt-pro'); ?>" value="<?php echo esc_attr($pool['name']); ?>" class="regular-text">
                <?php esc_html_e('Draw', 'wp-cbt-pro'); ?>
                <input type="number" min="1" name="pools[<?php echo (int) $i; ?>][draw_count]" value="<?php echo esc_attr((string) $pool['draw_count']); ?>" class="small-text">
            </p>
        <?php endfor; ?>

        <?php submit_button($action === 'edit' ? __('Save Changes', 'wp-cbt-pro') : __('Create Exam', 'wp-cbt-pro')); ?>
        <a href="<?php echo esc_url($listUrl); ?>" class="wpcbtpro-cancel-link"><?php esc_html_e('Cancel', 'wp-cbt-pro'); ?></a>
    </form>
</div>
