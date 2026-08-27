<?php
/**
 * @var \WPCBTPro\Candidates\CandidatesListTable $table
 * @var string $addUrl
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Candidates', 'wp-cbt-pro'); ?></h1>
    <a href="<?php echo esc_url($addUrl); ?>" class="page-title-action"><?php esc_html_e('Add Candidate', 'wp-cbt-pro'); ?></a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['created'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Candidate added.', 'wp-cbt-pro'); ?></p></div>
    <?php elseif (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Candidate updated.', 'wp-cbt-pro'); ?></p></div>
    <?php elseif (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Candidate removed.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <form method="get">
        <input type="hidden" name="page" value="wpcbtpro-candidates">
        <?php $table->search_box(__('Search candidates', 'wp-cbt-pro'), 'wpcbtpro-candidate-search'); ?>
        <?php $table->display(); ?>
    </form>
</div>
