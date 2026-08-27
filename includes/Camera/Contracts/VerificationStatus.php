<?php

declare(strict_types=1);

namespace WPCBTPro\Camera\Contracts;

/** §12 — never auto-declares misconduct; REVIEW_REQUIRED is where a human always ends up by default. */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case Failed = 'failed';
    case ReviewRequired = 'review_required';
    case NotPerformed = 'not_performed';
}
