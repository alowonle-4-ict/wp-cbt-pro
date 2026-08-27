<?php

declare(strict_types=1);

namespace WPCBTPro\DSA\Contracts;

/**
 * Only two of the architecture's four DSA modes (§17, §25–§28) need new
 * engine code. "Theory" is just an MCQ/Essay question tagged with a DSA
 * topic — the existing objective/written types already handle it.
 * "Programming implementation" is just a Programming question (§15–§16)
 * with the same tagging — Phase 10/11's engine already handles it. Only
 * Simulation and Interactive need a structure-aware grader, which is what
 * this whole namespace exists for.
 */
enum DsaMode: string
{
    case Simulation = 'simulation';
    case Interactive = 'interactive';
}
