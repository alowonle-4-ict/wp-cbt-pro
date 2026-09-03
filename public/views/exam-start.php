<?php
/**
 * @var array<string,mixed> $exam
 * @var array<string,mixed> $candidate
 * @var int $attemptsUsed
 * @var string|null $error
 */
if (!defined('ABSPATH')) {
    exit;
}

$photoId = (int) ($candidate['photo_attachment_id'] ?? 0);
$attemptsRemaining = max(0, (int) $exam['attempt_limit'] - $attemptsUsed);
$needsCamera = !empty($exam['camera_required']);
$needsMic = ($exam['microphone_mode'] ?? 'off') === 'camera_and_mic';
$needsSystemCheck = $needsCamera || !empty($exam['identity_verification']);
$isSecure = is_ssl() || in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
?>
<div class="wpcbtpro-exam wpcbtpro-exam--start">
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ExamWatermark::render() escapes its dynamic content internally (esc_attr() on the built style attribute).
    echo \WPCBTPro\Attempts\ExamWatermark::render($candidate);
    ?>
    <div class="wpcbtpro-candidate-card">
        <?php if ($photoId): ?>
            <?php echo wp_get_attachment_image($photoId, [112, 112], false, ['class' => 'wpcbtpro-candidate-card__photo']); ?>
        <?php else: ?>
            <span class="wpcbtpro-candidate-card__photo wpcbtpro-candidate-card__photo--empty" aria-hidden="true"></span>
        <?php endif; ?>
        <div>
            <strong><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></strong>
            <div class="wpcbtpro-candidate-card__ref"><?php echo esc_html($candidate['candidate_ref']); ?></div>
        </div>
    </div>

    <h2><?php echo esc_html($exam['name']); ?></h2>

    <?php if (!empty($exam['description'])): ?>
        <div class="wpcbtpro-exam__description"><?php echo wp_kses_post($exam['description']); ?></div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="wpcbtpro-notice wpcbtpro-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <ul class="wpcbtpro-exam__facts">
        <li><?php echo esc_html(sprintf(
            /* translators: %d: exam duration in minutes */
            __('Duration: %d minutes', 'wp-cbt-pro'),
            (int) $exam['duration_minutes']
        )); ?></li>
        <li><?php echo esc_html(sprintf(
            /* translators: 1: attempts remaining, 2: attempt limit */
            __('Attempts remaining: %1$d of %2$d', 'wp-cbt-pro'),
            $attemptsRemaining,
            (int) $exam['attempt_limit']
        )); ?></li>
    </ul>

    <?php if (!empty($exam['instructions'])): ?>
        <div class="wpcbtpro-exam__instructions">
            <h3><?php esc_html_e('Instructions', 'wp-cbt-pro'); ?></h3>
            <?php echo wp_kses_post($exam['instructions']); ?>
        </div>
    <?php endif; ?>

    <?php if ($attemptsRemaining > 0): ?>

        <?php if ($needsSystemCheck): ?>
        <div class="wpcbtpro-system-check" data-wpcbtpro-system-check>
            <h3><?php esc_html_e('System Check', 'wp-cbt-pro'); ?></h3>

            <?php if (!$isSecure): ?>
                <div class="wpcbtpro-notice wpcbtpro-notice--error"><?php esc_html_e('Camera access requires a secure (HTTPS) connection. Contact your institution if this warning does not go away.', 'wp-cbt-pro'); ?></div>
            <?php endif; ?>

            <ul class="wpcbtpro-check-list">
                <li>
                    <span class="wpcbtpro-check-list__label"><?php esc_html_e('Browser', 'wp-cbt-pro'); ?></span>
                    <span data-wpcbtpro-check="browser" class="wpcbtpro-check-status">—</span>
                </li>
                <li>
                    <span class="wpcbtpro-check-list__label"><?php esc_html_e('Camera', 'wp-cbt-pro'); ?></span>
                    <span data-wpcbtpro-check="camera" class="wpcbtpro-check-status"><?php esc_html_e('Not checked', 'wp-cbt-pro'); ?></span>
                </li>
                <?php if ($needsMic): ?>
                <li>
                    <span class="wpcbtpro-check-list__label"><?php esc_html_e('Microphone', 'wp-cbt-pro'); ?></span>
                    <span data-wpcbtpro-check="microphone" class="wpcbtpro-check-status"><?php esc_html_e('Not checked', 'wp-cbt-pro'); ?></span>
                </li>
                <?php endif; ?>
            </ul>

            <video data-wpcbtpro-camera-preview class="wpcbtpro-camera-preview" autoplay muted playsinline></video>

            <p>
                <button type="button" class="wpcbtpro-btn" data-wpcbtpro-request-camera>
                    <?php esc_html_e('Allow Camera & Continue', 'wp-cbt-pro'); ?>
                </button>
            </p>

            <label class="wpcbtpro-consent">
                <input type="checkbox" data-wpcbtpro-consent>
                <?php esc_html_e('I understand this exam is monitored and consent to camera-based proctoring for its duration.', 'wp-cbt-pro'); ?>
            </label>
        </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('wpcbtpro_start_exam_' . $exam['id'], 'wpcbtpro_start_nonce'); ?>
            <input type="hidden" name="consent_given" value="<?php echo $needsSystemCheck ? '0' : '1'; ?>" data-wpcbtpro-consent-field>
            <button type="submit" id="wpcbtpro-start-btn" class="wpcbtpro-btn wpcbtpro-btn--primary" <?php disabled($needsSystemCheck); ?>>
                <?php esc_html_e('Start Exam', 'wp-cbt-pro'); ?>
            </button>
        </form>
    <?php else: ?>
        <p class="wpcbtpro-notice"><?php esc_html_e('You have no attempts remaining for this exam.', 'wp-cbt-pro'); ?></p>
        <p class="wpcbtpro-submitted__logout">
            <a href="<?php echo esc_url(wp_logout_url(\WPCBTPro\Candidates\CandidateLoginController::loginUrl(''))); ?>" class="wpcbtpro-btn"><?php esc_html_e('Log out', 'wp-cbt-pro'); ?></a>
        </p>
    <?php endif; ?>
</div>
