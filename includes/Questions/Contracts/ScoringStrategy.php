<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

/**
 * Server-side grading only (§19, §34) — the candidate's browser never
 * computes or transmits a score, so nothing here ever trusts client input
 * beyond the already-validated, already-processed stored answer.
 *
 * score() is the only source of truth for "resolved vs. still pending":
 * a Score with isCorrect === null (Score::pendingManualReview()) means
 * grading hasn't finished yet — e.g. a programming answer awaiting the
 * execution service (§16, Phase 11). AttemptService calls score() for
 * every answered question unconditionally and reads the result rather
 * than asking the strategy to self-declare "manual review" up front,
 * because that declaration can only be evaluated per-answer, not once
 * per type — the same programming question is pending before execution
 * completes and resolved after.
 */
interface ScoringStrategy
{
    /**
     * @param array<string, mixed> $question
     * @param array<string, mixed> $answerRow the stored wp_cbt_answers row — never called for an unanswered question
     */
    public function score(array $question, array $answerRow): Score;
}
