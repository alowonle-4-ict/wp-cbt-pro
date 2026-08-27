<?php
/**
 * @var \WPCBTPro\Exams\ExamsListTable $table
 * @var string $addUrl
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Exams', 'wp-cbt-pro'); ?></h1>
    <a href="<?php echo esc_url($addUrl); ?>" class="page-title-action"><?php esc_html_e('Add Exam', 'wp-cbt-pro'); ?></a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Exam deleted.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="wpcbtpro-exams">
        <?php $table->search_box(__('Search exams', 'wp-cbt-pro'), 'wpcbtpro-exam-search'); ?>
        <?php $table->display(); ?>
    </form>
</div>
