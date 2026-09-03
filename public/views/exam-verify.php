<?php
/**
 * @var array<string,mixed> $exam
 * @var array<string,mixed> $candidate
 * @var array<string,mixed> $attempt
 */
if (!defined('ABSPATH')) {
    exit;
}

$photoId = (int) ($candidate['photo_attachment_id'] ?? 0);
?>
<div class="wpcbtpro-exam wpcbtpro-exam--verify" data-wpcbtpro-verify data-attempt-id="<?php echo esc_attr((string) $attempt['id']); ?>">
    <h2><?php esc_html_e('Identity Verification', 'wp-cbt-pro'); ?></h2>
    <p><?php esc_html_e('Position your face in the frame below and capture a photo to confirm your identity before starting.', 'wp-cbt-pro'); ?></p>

    <?php if ($photoId): ?>
        <div class="wpcbtpro-verify__reference">
            <span class="wpcbtpro-verify__reference-label"><?php esc_html_e('On file', 'wp-cbt-pro'); ?></span>
            <?php echo wp_get_attachment_image($photoId, [112, 112], false, ['class' => 'wpcbtpro-candidate-card__photo']); ?>
        </div>
    <?php endif; ?>

    <video data-wpcbtpro-camera-preview class="wpcbtpro-camera-preview" autoplay muted playsinline></video>
    <canvas data-wpcbtpro-capture-canvas class="wpcbtpro-hidden"></canvas>

    <p class="wpcbtpro-verify__status" data-wpcbtpro-verify-status aria-live="polite"></p>

    <p>
        <button type="button" class="wpcbtpro-btn wpcbtpro-btn--primary" data-wpcbtpro-capture-verify>
            <?php esc_html_e('Capture & Verify', 'wp-cbt-pro'); ?>
        </button>
    </p>

    <p class="wpcbtpro-notice"><?php esc_html_e('This image is reviewed by your institution and is not used to make an automatic pass/fail decision.', 'wp-cbt-pro'); ?></p>
</div>
