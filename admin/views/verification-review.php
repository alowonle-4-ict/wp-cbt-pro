<?php
/** @var array<int, array{record: array, attempt: array, exam: array, candidate: array}> $rows */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpcbtpro-wrap">
    <h1><?php esc_html_e('Identity Verification Review', 'wp-cbt-pro'); ?></h1>

    <?php if (isset($_GET['reviewed'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Review recorded.', 'wp-cbt-pro'); ?></p></div>
    <?php endif; ?>

    <p><?php esc_html_e('Each capture below is routed here automatically — the system never decides a match on its own (§12). Compare the captured image against the photo on file and record your decision.', 'wp-cbt-pro'); ?></p>

    <?php if (empty($rows)): ?>
        <p><em><?php esc_html_e('Nothing is waiting for review.', 'wp-cbt-pro'); ?></em></p>
    <?php endif; ?>

    <?php foreach ($rows as $row):
        $record = $row['record'];
        $attempt = $row['attempt'];
        $exam = $row['exam'];
        $candidate = $row['candidate'];
        $referencePhotoId = (int) ($candidate['photo_attachment_id'] ?? 0);
        $capturedId = (int) ($record['captured_image_attachment_id'] ?? 0);
    ?>
        <div class="wpcbtpro-verify-row">
            <div class="wpcbtpro-verify-row__images">
                <figure>
                    <figcaption><?php esc_html_e('On file', 'wp-cbt-pro'); ?></figcaption>
                    <?php if ($referencePhotoId): ?>
                        <?php echo wp_get_attachment_image($referencePhotoId, [140, 140], false, ['class' => 'wpcbtpro-verify-row__img']); ?>
                    <?php else: ?>
                        <span class="wpcbtpro-verify-row__img wpcbtpro-verify-row__img--empty"></span>
                    <?php endif; ?>
                </figure>
                <figure>
                    <figcaption><?php esc_html_e('Captured at exam start', 'wp-cbt-pro'); ?></figcaption>
                    <?php if ($capturedId): ?>
                        <?php echo wp_get_attachment_image($capturedId, [140, 140], false, ['class' => 'wpcbtpro-verify-row__img']); ?>
                    <?php else: ?>
                        <span class="wpcbtpro-verify-row__img wpcbtpro-verify-row__img--empty"></span>
                    <?php endif; ?>
                </figure>
            </div>

            <div class="wpcbtpro-verify-row__meta">
                <strong><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></strong>
                <div><?php echo esc_html($candidate['candidate_ref']); ?></div>
                <div><?php echo esc_html($exam['name']); ?></div>
                <div class="description"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $record['created_at'])); ?></div>

                <form method="post" class="wpcbtpro-verify-row__actions">
                    <?php wp_nonce_field('wpcbtpro_review_verification_' . $record['id'], 'wpcbtpro_review_nonce'); ?>
                    <input type="hidden" name="record_id" value="<?php echo esc_attr((string) $record['id']); ?>">
                    <button type="submit" name="decision" value="verify" class="button button-primary"><?php esc_html_e('Mark Verified', 'wp-cbt-pro'); ?></button>
                    <button type="submit" name="decision" value="fail" class="button wpcbtpro-btn-danger"><?php esc_html_e('Mark Failed', 'wp-cbt-pro'); ?></button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
