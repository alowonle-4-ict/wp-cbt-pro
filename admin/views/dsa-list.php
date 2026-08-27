<?php
/**
 * @var array<int, array<string, mixed>> $questions
 * @var string $addUrl
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('DSA Questions', 'wp-cbt-pro'); ?></h1>
    <a href="<?php echo esc_url($addUrl); ?>" class="page-title-action"><?php esc_html_e('Add Question', 'wp-cbt-pro'); ?></a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Question deleted.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($questions)): ?>
        <p><em><?php esc_html_e('No DSA questions yet.', 'wp-cbt-pro'); ?></em></p>
    <?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Question', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Subject', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Marks', 'wp-cbt-pro'); ?></th>
                <th><?php esc_html_e('Status', 'wp-cbt-pro'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($questions as $question):
                $editUrl = add_query_arg(['page' => 'wpcbtpro-dsa', 'action' => 'edit', 'id' => $question['id']], admin_url('admin.php'));
                $deleteUrl = wp_nonce_url(add_query_arg(['page' => 'wpcbtpro-dsa', 'action' => 'delete', 'id' => $question['id']], admin_url('admin.php')), 'wpcbtpro_delete_dsa_' . $question['id']);
            ?>
            <tr>
                <td><a href="<?php echo esc_url($editUrl); ?>"><strong><?php echo esc_html(wp_trim_words(wp_strip_all_tags($question['content']), 12)); ?></strong></a></td>
                <td><?php echo esc_html($question['subject']); ?></td>
                <td class="num"><?php echo esc_html($question['marks']); ?></td>
                <td><span class="wpcbtpro-pill wpcbtpro-pill--<?php echo esc_attr($question['status']); ?>"><?php echo esc_html(ucfirst($question['status'])); ?></span></td>
                <td>
                    <a href="<?php echo esc_url($editUrl); ?>"><?php esc_html_e('Edit', 'wp-cbt-pro'); ?></a>
                    &middot;
                    <a href="<?php echo esc_url($deleteUrl); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this question?', 'wp-cbt-pro')); ?>');"><?php esc_html_e('Delete', 'wp-cbt-pro'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
