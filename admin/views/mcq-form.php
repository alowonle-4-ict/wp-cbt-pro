<?php
/**
 * @var array<string,mixed>|null $question
 * @var array<string,string> $errors
 * @var string $action
 * @var string $type
 * @var string $typeLabel
 * @var \WPCBTPro\Questions\Contracts\AdminEditorView $editor
 */
if (!defined('ABSPATH')) {
    exit;
}

$listUrl = add_query_arg(['page' => 'wpcbtpro-questions'], admin_url('admin.php'));
?>
<div class="wrap wpcbtpro-wrap">
    <h1>
        <?php if ($action === 'edit'): ?>
            <?php
            printf(
                /* translators: %s: question type label, e.g. "Single Choice (MCQ)" */
                esc_html__('Edit %s Question', 'wp-cbt-pro'),
                esc_html($typeLabel)
            );
            ?>
        <?php else: ?>
            <?php
            printf(
                /* translators: %s: question type label, e.g. "True / False" */
                esc_html__('Add %s Question', 'wp-cbt-pro'),
                esc_html($typeLabel)
            );
            ?>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Question saved.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error"><p><?php foreach ($errors as $error): ?><?php echo esc_html($error); ?><br><?php endforeach; ?></p></div>
    <?php endif; ?>

    <form method="post" class="wpcbtpro-form">
        <?php wp_nonce_field('wpcbtpro_save_mcq', 'wpcbtpro_mcq_nonce'); ?>
        <input type="hidden" name="question_id" value="<?php echo esc_attr((string) ($question['id'] ?? 0)); ?>">
        <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="content"><?php esc_html_e('Question prompt', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><textarea id="content" name="content" rows="4" class="large-text"><?php echo esc_textarea($question['content'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="subject"><?php esc_html_e('Subject', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="subject" name="subject" class="regular-text" value="<?php echo esc_attr($question['subject'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="topic"><?php esc_html_e('Topic', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="topic" name="topic" class="regular-text" value="<?php echo esc_attr($question['topic'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="marks"><?php esc_html_e('Marks', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="number" min="0.5" step="0.5" id="marks" name="marks" value="<?php echo esc_attr((string) ($question['marks'] ?? 1)); ?>"></td>
            </tr>
            <tr>
                <th><label for="negative_marks"><?php esc_html_e('Negative marks', 'wp-cbt-pro'); ?></label></th>
                <td>
                    <input type="number" min="0" step="0.25" id="negative_marks" name="negative_marks" value="<?php echo esc_attr((string) ($question['negative_marks'] ?? 0)); ?>">
                    <p class="description"><?php esc_html_e('Deducted from the score for a wrong answer, when the exam has negative marking enabled.', 'wp-cbt-pro'); ?></p>
                </td>
            </tr>

            <?php $editor->render($question, $errors); ?>
        </table>

        <?php submit_button($action === 'edit' ? __('Save Changes', 'wp-cbt-pro') : __('Create Question', 'wp-cbt-pro')); ?>
        <a href="<?php echo esc_url($listUrl); ?>" class="wpcbtpro-cancel-link"><?php esc_html_e('Cancel', 'wp-cbt-pro'); ?></a>
    </form>
</div>
