<?php
/**
 * @var array<string,mixed> $exam
 * @var array<string,mixed> $candidate
 * @var array<string,mixed> $attempt
 * @var int $index
 * @var int $total
 * @var int[] $resolvedIds
 * @var array<string,mixed>|null $question
 * @var \WPCBTPro\Questions\Contracts\QuestionType|null $type
 * @var array<int,array<string,mixed>> $answers
 * @var string|null $currentAnswer
 * @var bool $markedForReview
 * @var string|null $error
 */
if (!defined('ABSPATH')) {
    exit;
}

$photoId = (int) ($candidate['photo_attachment_id'] ?? 0);
$serverNow = current_time('timestamp');
$serverEnd = strtotime($attempt['server_end']);
$cameraRequired = !empty($exam['camera_required']);
?>
<script type="application/json" id="wpcbtpro-camera-config">
<?php echo wp_json_encode([
    'attemptId' => (int) $attempt['id'],
    'cameraRequired' => $cameraRequired,
    'microphoneMode' => $exam['microphone_mode'] ?? 'off',
    'snapshotIntervalSeconds' => !empty($exam['snapshot_interval_seconds']) ? (int) $exam['snapshot_interval_seconds'] : 0,
]); ?>
</script>
<div class="wpcbtpro-exam wpcbtpro-exam--running"
     data-attempt-id="<?php echo esc_attr((string) $attempt['id']); ?>"
     data-server-now="<?php echo esc_attr((string) $serverNow); ?>"
     data-server-end="<?php echo esc_attr((string) $serverEnd); ?>">

    <header class="wpcbtpro-exam__header">
        <div class="wpcbtpro-candidate-card">
            <?php if ($photoId): ?>
                <?php echo wp_get_attachment_image($photoId, [48, 48], false, ['class' => 'wpcbtpro-candidate-card__photo wpcbtpro-candidate-card__photo--small']); ?>
            <?php else: ?>
                <span class="wpcbtpro-candidate-card__photo wpcbtpro-candidate-card__photo--empty wpcbtpro-candidate-card__photo--small" aria-hidden="true"></span>
            <?php endif; ?>
            <div>
                <strong><?php echo esc_html(trim($candidate['first_name'] . ' ' . $candidate['last_name'])); ?></strong>
                <div class="wpcbtpro-candidate-card__ref"><?php echo esc_html($candidate['candidate_ref']); ?></div>
            </div>
        </div>
        <div class="wpcbtpro-exam__title"><?php echo esc_html($exam['name']); ?></div>
        <?php if ($cameraRequired): ?>
            <div class="wpcbtpro-camera-status">
                <video data-wpcbtpro-camera-preview class="wpcbtpro-camera-preview wpcbtpro-camera-preview--small" autoplay muted playsinline></video>
                <span data-wpcbtpro-check="camera" class="wpcbtpro-check-status"><?php esc_html_e('Connecting…', 'wp-cbt-pro'); ?></span>
            </div>
        <?php endif; ?>
        <div class="wpcbtpro-timer" aria-live="polite">
            <span class="wpcbtpro-timer__label"><?php esc_html_e('Time remaining', 'wp-cbt-pro'); ?></span>
            <span class="wpcbtpro-timer__value" data-wpcbtpro-timer>--:--</span>
        </div>
    </header>

    <nav class="wpcbtpro-palette" aria-label="<?php esc_attr_e('Question palette', 'wp-cbt-pro'); ?>">
        <?php foreach ($resolvedIds as $i => $qid):
            $ans = $answers[$qid] ?? null;
            $isAnswered = $ans !== null && trim((string) $ans['value']) !== '';
            $isMarked = !empty($ans['marked_for_review']);
            $stateClass = $isMarked ? 'is-marked' : ($isAnswered ? 'is-answered' : 'is-unanswered');
            $url = add_query_arg(['q' => $i]);
        ?>
            <a href="<?php echo esc_url($url); ?>"
               class="wpcbtpro-palette__item <?php echo esc_attr($stateClass); ?> <?php echo $i === $index ? 'is-current' : ''; ?>">
                <?php echo esc_html((string) ($i + 1)); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($error !== null): ?>
        <div class="wpcbtpro-notice wpcbtpro-notice--error"><?php echo esc_html($error); ?></div>
    <?php endif; ?>

    <?php if ($question === null || $type === null): ?>
        <p class="wpcbtpro-notice wpcbtpro-notice--error"><?php esc_html_e('This question could not be loaded.', 'wp-cbt-pro'); ?></p>
    <?php else: ?>
        <form method="post" class="wpcbtpro-question-form" data-wpcbtpro-autosave>
            <?php wp_nonce_field('wpcbtpro_save_answer_' . $attempt['id'], 'wpcbtpro_answer_nonce'); ?>
            <input type="hidden" name="question_id" value="<?php echo esc_attr((string) $question['id']); ?>">
            <input type="hidden" name="wpcbtpro_nav" value="" data-wpcbtpro-nav-field>

            <div class="wpcbtpro-question">
                <div class="wpcbtpro-question__meta">
                    <?php echo esc_html(sprintf(
                        /* translators: 1: current question number, 2: total questions */
                        __('Question %1$d of %2$d', 'wp-cbt-pro'),
                        $index + 1,
                        $total
                    )); ?>
                    &middot;
                    <?php echo esc_html(sprintf(
                        /* translators: %s: marks available for this question */
                        __('%s marks', 'wp-cbt-pro'),
                        rtrim(rtrim(number_format((float) $question['marks'], 2), '0'), '.')
                    )); ?>
                </div>

                <div class="wpcbtpro-question__prompt">
                    <?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderPrompt() runs the content through wp_kses() (see RendersHtmlContent).
                    echo $type->renderer()->renderPrompt($question);
                    ?>
                </div>

                <div class="wpcbtpro-question__answer">
                    <?php $type->candidateUi()->render($question, $currentAnswer); ?>
                </div>

                <label class="wpcbtpro-mark-review">
                    <input type="checkbox" name="wpcbtpro_marked_for_review" value="1" <?php checked($markedForReview); ?>>
                    <?php esc_html_e('Mark for review', 'wp-cbt-pro'); ?>
                </label>
                <span class="wpcbtpro-save-status" data-wpcbtpro-save-status aria-live="polite"></span>
            </div>

            <div class="wpcbtpro-question__nav">
                <button type="submit" name="wpcbtpro_nav" value="prev" class="wpcbtpro-btn" <?php disabled($index === 0); ?>>
                    <?php esc_html_e('Previous', 'wp-cbt-pro'); ?>
                </button>
                <button type="submit" name="wpcbtpro_nav" value="clear" class="wpcbtpro-btn wpcbtpro-btn--ghost">
                    <?php esc_html_e('Clear Answer', 'wp-cbt-pro'); ?>
                </button>
                <button type="submit" name="wpcbtpro_nav" value="save" class="wpcbtpro-btn">
                    <?php esc_html_e('Save', 'wp-cbt-pro'); ?>
                </button>
                <button type="submit" name="wpcbtpro_nav" value="next" class="wpcbtpro-btn wpcbtpro-btn--primary" <?php disabled($index === $total - 1); ?>>
                    <?php esc_html_e('Save & Next', 'wp-cbt-pro'); ?>
                </button>
            </div>
        </form>
    <?php endif; ?>

    <form method="post" class="wpcbtpro-submit-form"
          onsubmit="return confirm('<?php echo esc_js(__('Submit the exam now? You will not be able to change your answers afterward.', 'wp-cbt-pro')); ?>');">
        <?php wp_nonce_field('wpcbtpro_submit_exam_' . $attempt['id'], 'wpcbtpro_submit_exam_nonce'); ?>
        <button type="submit" class="wpcbtpro-btn wpcbtpro-btn--danger"><?php esc_html_e('Submit Exam', 'wp-cbt-pro'); ?></button>
    </form>
</div>
