<?php

declare(strict_types=1);

namespace WPCBTPro\Institutions;

/**
 * Single resolution point for "which institution am I scoped to" (§22).
 * Every later domain (Candidates, Exams, Questions, ...) is expected to call
 * this rather than read wp_usermeta directly, so the isolation rule lives in
 * one place instead of being re-implemented per screen.
 */
final class InstitutionContext
{
    public function currentId(): ?int
    {
        $userId = get_current_user_id();
        if ($userId === 0) {
            return null;
        }

        $institutionId = get_user_meta($userId, 'wpcbtpro_institution_id', true);
        if ($institutionId === '') {
            $default = get_option('wpcbtpro_default_institution_id');
            $institutionId = $default ? (int) $default : null;
        } else {
            $institutionId = (int) $institutionId;
        }

        /**
         * Allows a multisite or SSO integration to resolve institution
         * membership from something other than user meta.
         */
        return apply_filters('wpcbtpro_current_institution_id', $institutionId, $userId);
    }

    public function requireCurrentId(): int
    {
        $id = $this->currentId();
        if ($id === null) {
            throw new \RuntimeException('No institution scope resolved for the current user.');
        }

        return $id;
    }
}
