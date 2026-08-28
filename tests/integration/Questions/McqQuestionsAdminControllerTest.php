<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Questions;

use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Questions\McqQuestionsAdminController;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Security\Capabilities;
use WPCBTPro\Tests\Integration\RestTestCase;

/**
 * Proves the previously-missing MCQ/True-False admin builder actually works:
 * a question created the way the controller creates one (QuestionRepository
 * insert/update/replaceOptions) is a real, gradable exam question, and the
 * controller enforces its capability check. The controller's own POST-save
 * path isn't driven directly here because a successful save ends in
 * wp_safe_redirect()+exit(), same as every other *AdminController in this
 * codebase — none of them are exercised past their permission check for
 * that reason.
 */
final class McqQuestionsAdminControllerTest extends RestTestCase
{
    public function testRenderDiesWithoutCapability(): void
    {
        $userId = self::factory()->user->create(['role' => 'subscriber']);
        wp_set_current_user($userId);

        $controller = \WPCBTPro\Core\Plugin::instance()->container()->get(McqQuestionsAdminController::class);

        $this->expectException(\WPDieException::class);
        $controller->render();
    }

    public function testQuestionBuiltTheControllerWayIsGradableInAnExam(): void
    {
        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $questions = new QuestionRepository();

        // Mirrors McqQuestionsAdminController::handleSave() for a new 'true_false' question:
        // the editor extracts fixed True/False options, the repository inserts core data + options together.
        $editor = new \WPCBTPro\Questions\Types\Mcq\TrueFalseAdminEditor();
        $extracted = $editor->extract(['true_false_answer' => 'false']);

        $questionId = $questions->insert([
            'institution_id' => $institutionId,
            'type' => 'true_false',
            'content' => 'The sky is green.',
            'subject' => 'General Knowledge',
            'marks' => 2.0,
            'negative_marks' => 0.5,
            'status' => 'active',
        ], $extracted['options']);

        $saved = $questions->find($questionId);
        self::assertSame('true_false', $saved['type']);
        self::assertCount(2, $saved['options']);

        $falseOption = current(array_filter($saved['options'], static fn (array $o): bool => $o['label'] === 'False'));
        self::assertNotFalse($falseOption);
        self::assertSame('1', (string) $falseOption['is_correct']);

        // Now edit it through replaceOptions(), the update-path counterpart to insert()'s $options handling.
        $reExtracted = $editor->extract(['true_false_answer' => 'true']);
        $questions->update($questionId, ['marks' => 3.0]);
        $questions->replaceOptions($questionId, $reExtracted['options']);

        $reSaved = $questions->find($questionId);
        self::assertCount(2, $reSaved['options'], 'replaceOptions() must not leave stale rows behind.');
        $trueOption = current(array_filter($reSaved['options'], static fn (array $o): bool => $o['label'] === 'True'));
        self::assertSame('1', (string) $trueOption['is_correct']);

        // Prove it end to end: attach to a real exam and answer it correctly via the same REST flow a candidate uses.
        $examRepository = new ExamRepository();
        $examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'MCQ Builder Integration Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
        ]);
        $examRepository->setQuestions($examId, [['question_id' => $questionId]]);

        $userId = self::factory()->user->create();
        wp_set_current_user($userId);
        (new \WPCBTPro\Candidates\CandidateRepository())->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $userId,
            'candidate_ref' => 'CBT-2026-000960',
            'first_name' => 'Builder',
            'last_name' => 'Test',
            'status' => 'active',
        ]);

        $start = $this->dispatch('POST', '/wp-cbt-pro/v1/start-exam', ['exam_id' => $examId]);
        self::assertSame(201, $start->get_status());
        $attemptId = (int) $start->get_data()['attempt_id'];

        $answer = $this->dispatch('POST', '/wp-cbt-pro/v1/answer', [
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'value' => (string) $trueOption['id'],
        ]);
        self::assertSame(200, $answer->get_status());

        $submit = $this->dispatch('POST', '/wp-cbt-pro/v1/submit-exam', ['attempt_id' => $attemptId]);
        self::assertSame(200, $submit->get_status());

        $result = $this->dispatch('GET', '/wp-cbt-pro/v1/result', ['attempt_id' => $attemptId]);
        self::assertSame(3.0, $result->get_data()['score'], 'The 3.0 marks set via update() must be what the attempt was graded against.');
        self::assertSame(1, $result->get_data()['correct_count']);
    }
}
