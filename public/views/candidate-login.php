<?php
/**
 * @var string|null $error
 * @var string $redirectTo
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wpcbtpro-exam wpcbtpro-exam--login">
    <h2><?php esc_html_e('Candidate Sign In', 'wp-cbt-pro'); ?></h2>

    <?php if ($error !== null): ?>
        <div class="wpcbtpro-notice wpcbtpro-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <form method="post" class="wpcbtpro-form">
        <?php wp_nonce_field('wpcbtpro_candidate_login', 'wpcbtpro_login_nonce'); ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>">

        <p>
            <label for="wpcbtpro_login_user"><?php esc_html_e('Registration Number', 'wp-cbt-pro'); ?></label><br>
            <input type="text" id="wpcbtpro_login_user" name="wpcbtpro_login_user" class="regular-text" required autocomplete="username">
        </p>
        <p>
            <label for="wpcbtpro_login_pass"><?php esc_html_e('Password', 'wp-cbt-pro'); ?></label><br>
            <input type="password" id="wpcbtpro_login_pass" name="wpcbtpro_login_pass" class="regular-text" required autocomplete="current-password">
        </p>

        <button type="submit" class="wpcbtpro-btn wpcbtpro-btn--primary"><?php esc_html_e('Sign In', 'wp-cbt-pro'); ?></button>
    </form>
</div>
