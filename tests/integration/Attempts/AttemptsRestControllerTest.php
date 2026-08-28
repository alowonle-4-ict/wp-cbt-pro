<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Attempts;

use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Tests\Integration\RestTestCase;

/**
 * Exercises the candidate exam runtime the way a real browser would: through
 * the registered REST routes, permission callbacks, and DB repositories
 * together, rather than calling AttemptService directly. This is the path
 * unit tests (which only cover pure logic) never touch.
 */
final class AttemptsRestControllerTest extends RestTestCase
{
    private int $correctOptionId;
    private int $examId;

    protected function setUp(): void
    {
        parent::setUp();

        $institutionId = (int) get_option('wpcbtpro_default_institution_id');
        $userId = self::factory()->user->create();
        wp_set_current_user($userId);

        $candidateRepository = new CandidateRepository();
        $candidateRepository->insert([
            'institution_id' => $institutionId,
            'wp_user_id' => $userId,
            'candidate_ref' => 'CBT-2026-000900',
            'first_name' => 'Test',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $questionRepository = new QuestionRepository();
        $questionId = $questionRepository->insert(
            [
                'institution_id' => $institutionId,
                'type' => 'mcq_single',
                'content' => 'What is 2 + 2?',
                'marks' => 1.0,
                'negative_marks' => 0.0,
                'status' => 'active',
            ],
            [
                ['label' => '3', 'is_correct' => false, 'sort_order' => 0],
                ['label' => '4', 'is_correct' => true, 'sort_order' => 1],
            ]
        );

        $question = $questionRepository->find($questionId);
        $this->correctOptionId = (int) array_values(array_filter(
            $question['options'],
            static fn (array $option): bool => (bool) $option['is_correct']
        ))[0]['id'];

        $examRepository = new ExamRepository();
        $this->examId = $examRepository->insert([
            'institution_id' => $institutionId,
            'name' => 'REST Integration Exam',
            'duration_minutes' => 30,
            'attempt_limit' => 1,
            'status' => 'active',
            'result_visibility' => 'immediate',
            'pass_mark' => 50.0,
        ]);
        $examRepository->setQuestions($this->examId, [['question_id' => $questionId]]);
    }

    public function testFullExamRuntimeFlow(): void
    {
        $start = $this->dispatch('POST', '/wp-cbt-pro/v1/start-exam', ['exam_id' => $this->examId]);
        self::assertSame(201, $start->get_status());
        $attemptId = (int) $start->get_data()['attempt_id'];
        self::assertGreaterThan(0, $attemptId);

        $answer = $this->dispatch('POST', '/wp-cbt-pro/v1/answer', [
            'attempt_id' => $attemptId,
            'question_id' => $this->examQuestionId(),
            'value' => (string) $this->correctOptionId,
        ]);
        self::assertSame(200, $answer->get_status());
        self::assertTrue($answer->get_data()['saved']);

        $submit = $this->dispatch('POST', '/wp-cbt-pro/v1/submit-exam', ['attempt_id' => $attemptId]);
        self::assertSame(200, $submit->get_status());
        self::assertTrue($submit->get_data()['submitted']);
        self::assertTrue($submit->get_data()['result_released']);

        $result = $this->dispatch('GET', '/wp-cbt-pro/v1/result', ['attempt_id' => $attemptId]);
        self::assertSame(200, $result->get_status());
        $data = $result->get_data();
        self::assertSame(1.0, $data['score']);
        self::assertSame(1, $data['correct_count']);
        self::assertSame('pass', $data['pass_status']);
    }

    public function testStartExamRejectsLoggedOutRequest(): void
    {
        wp_set_current_user(0);

        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/start-exam', ['exam_id' => $this->examId]);

        self::assertSame(401, $response->get_status());
    }

    public function testAnswerRejectsAttemptBelongingToAnotherCandidate(): void
    {
        $start = $this->dispatch('POST', '/wp-cbt-pro/v1/start-exam', ['exam_id' => $this->examId]);
        $attemptId = (int) $start->get_data()['attempt_id'];

        $otherUserId = self::factory()->user->create();
        wp_set_current_user($otherUserId);
        (new CandidateRepository())->insert([
            'institution_id' => (int) get_option('wpcbtpro_default_institution_id'),
            'wp_user_id' => $otherUserId,
            'candidate_ref' => 'CBT-2026-000901',
            'first_name' => 'Other',
            'last_name' => 'Candidate',
            'status' => 'active',
        ]);

        $response = $this->dispatch('POST', '/wp-cbt-pro/v1/answer', [
            'attempt_id' => $attemptId,
            'question_id' => $this->examQuestionId(),
            'value' => (string) $this->correctOptionId,
        ]);

        self::assertSame(403, $response->get_status());
    }

    private function examQuestionId(): int
    {
        return (int) (new ExamRepository())->questionsForExam($this->examId)[0]['question_id'];
    }
}
