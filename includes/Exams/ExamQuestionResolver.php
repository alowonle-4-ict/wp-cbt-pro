<?php

declare(strict_types=1);

namespace WPCBTPro\Exams;

/**
 * Turns an exam's stored question/pool configuration plus one attempt's seed
 * into the exact ordered question list that attempt will see (§8, §31).
 * Never persisted — re-running resolve() with the same seed always
 * reproduces the same paper, which is what makes result review possible
 * without storing a redundant copy of the question order.
 */
final class ExamQuestionResolver
{
    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly RandomizationService $randomizer,
    ) {
    }

    /**
     * @param array<string, mixed> $exam
     * @return int[] final ordered, de-duplicated question ids
     */
    public function resolve(array $exam, string $seed): array
    {
        $examId = (int) $exam['id'];
        $assignments = $this->examRepository->questionsForExam($examId);
        $pools = $this->examRepository->poolsForExam($examId);

        $directIds = [];
        $byPool = [];

        foreach ($assignments as $assignment) {
            $questionId = (int) $assignment['question_id'];
            if (empty($assignment['pool_id'])) {
                $directIds[] = $questionId;
                continue;
            }
            $byPool[$assignment['pool_id']][] = $questionId;
        }

        $drawnFromPools = [];
        foreach ($byPool as $poolKey => $ids) {
            $drawCount = isset($pools[$poolKey]) ? (int) $pools[$poolKey]['draw_count'] : count($ids);
            $drawnFromPools = array_merge(
                $drawnFromPools,
                $this->randomizer->drawFromPool($ids, $drawCount, $seed, (string) $poolKey)
            );
        }

        $finalIds = array_values(array_unique(array_map('intval', array_merge($directIds, $drawnFromPools))));

        if (!empty($exam['randomize_questions'])) {
            $finalIds = $this->randomizer->seededShuffle($finalIds, $seed, 'question_order');
        }

        return $finalIds;
    }

    /**
     * @param array<int, array<string, mixed>> $options
     * @return array<int, array<string, mixed>>
     */
    public function resolveOptionOrder(array $options, string $seed, int $questionId, bool $randomize): array
    {
        if (!$randomize) {
            return $options;
        }

        return $this->randomizer->seededShuffle($options, $seed, 'options:' . $questionId);
    }
}
