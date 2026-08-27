<?php
/**
 * @var array<string,mixed>|null $question
 * @var array<string,string> $errors
 * @var string $action
 * @var \WPCBTPro\Questions\Types\Dsa\DsaAdminEditor $editor
 */
if (!defined('ABSPATH')) {
    exit;
}

$listUrl = add_query_arg(['page' => 'wpcbtpro-dsa'], admin_url('admin.php'));
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php echo $action === 'edit' ? esc_html__('Edit DSA Question', 'wp-cbt-pro') : esc_html__('Add DSA Question', 'wp-cbt-pro'); ?></h1>

    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Question saved.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <div class="notice notice-error"><p><?php foreach ($errors as $error): ?><?php echo esc_html($error); ?><br><?php endforeach; ?></p></div>
    <?php endif; ?>

    <form method="post" class="wpcbtpro-form">
        <?php wp_nonce_field('wpcbtpro_save_dsa', 'wpcbtpro_dsa_nonce'); ?>
        <input type="hidden" name="question_id" value="<?php echo esc_attr((string) ($question['id'] ?? 0)); ?>">

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="content"><?php esc_html_e('Question prompt', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><textarea id="content" name="content" rows="4" class="large-text"><?php echo esc_textarea($question['content'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="subject"><?php esc_html_e('Subject', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="subject" name="subject" class="regular-text" value="<?php echo esc_attr($question['subject'] ?? 'Data Structures'); ?>"></td>
            </tr>
            <tr>
                <th><label for="topic"><?php esc_html_e('Topic', 'wp-cbt-pro'); ?></label></th>
                <td><input type="text" id="topic" name="topic" class="regular-text" value="<?php echo esc_attr($question['topic'] ?? ''); ?>"></td>
            </tr>
            <tr>
                <th><label for="marks"><?php esc_html_e('Marks', 'wp-cbt-pro'); ?> <span class="required">*</span></label></th>
                <td><input type="number" min="0.5" step="0.5" id="marks" name="marks" value="<?php echo esc_attr((string) ($question['marks'] ?? 5)); ?>"></td>
            </tr>

            <?php $editor->render($question, $errors); ?>
        </table>

        <?php submit_button($action === 'edit' ? __('Save Changes', 'wp-cbt-pro') : __('Create Question', 'wp-cbt-pro')); ?>
        <a href="<?php echo esc_url($listUrl); ?>" class="wpcbtpro-cancel-link"><?php esc_html_e('Cancel', 'wp-cbt-pro'); ?></a>
    </form>
</div>
