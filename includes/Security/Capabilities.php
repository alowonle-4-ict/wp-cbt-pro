<?php

declare(strict_types=1);

namespace WPCBTPro\Security;

/**
 * Roles/capabilities from §21 of the architecture spec. Capabilities are
 * assigned at the narrowest scope each role needs — nothing gets manage_cbt
 * just to unlock one narrower ability.
 */
final class Capabilities
{
    public const MANAGE_CBT = 'manage_cbt';
    public const MANAGE_CBT_EXAMS = 'manage_cbt_exams';
    public const MANAGE_CBT_QUESTIONS = 'manage_cbt_questions';
    public const MANAGE_CBT_CANDIDATES = 'manage_cbt_candidates';
    public const VIEW_CBT_RESULTS = 'view_cbt_results';
    public const MANAGE_CBT_SETTINGS = 'manage_cbt_settings';
    public const VIEW_MONITORING = 'view_monitoring';
    public const REVIEW_ATTEMPTS = 'review_attempts';
    public const MANAGE_PROGRAMMING_QUESTIONS = 'manage_programming_questions';
    public const MANAGE_DSA_QUESTIONS = 'manage_dsa_questions';

    public const ROLE_ADMINISTRATOR = 'cbt_administrator';
    public const ROLE_EXAM_MANAGER = 'cbt_exam_manager';
    public const ROLE_EXAMINER = 'cbt_examiner';
    public const ROLE_INVIGILATOR = 'cbt_invigilator';

    /** @return array<string, array<string, bool>> role => capability map */
    private static function roleMap(): array
    {
        $all = array_fill_keys([
            self::MANAGE_CBT,
            self::MANAGE_CBT_EXAMS,
            self::MANAGE_CBT_QUESTIONS,
            self::MANAGE_CBT_CANDIDATES,
            self::VIEW_CBT_RESULTS,
            self::MANAGE_CBT_SETTINGS,
            self::VIEW_MONITORING,
            self::REVIEW_ATTEMPTS,
            self::MANAGE_PROGRAMMING_QUESTIONS,
            self::MANAGE_DSA_QUESTIONS,
        ], true);

        return [
            self::ROLE_ADMINISTRATOR => $all,
            self::ROLE_EXAM_MANAGER => array_fill_keys([
                self::MANAGE_CBT_EXAMS,
                self::MANAGE_CBT_CANDIDATES,
                self::VIEW_CBT_RESULTS,
            ], true),
            self::ROLE_EXAMINER => array_fill_keys([
                self::MANAGE_CBT_QUESTIONS,
                self::MANAGE_PROGRAMMING_QUESTIONS,
                self::MANAGE_DSA_QUESTIONS,
                self::VIEW_CBT_RESULTS,
            ], true),
            self::ROLE_INVIGILATOR => array_fill_keys([
                self::VIEW_MONITORING,
                self::REVIEW_ATTEMPTS,
            ], true),
        ];
    }

    public static function register(): void
    {
        foreach (self::roleMap() as $role => $caps) {
            $displayName = ucwords(str_replace('_', ' ', str_replace('cbt_', '', $role)));
            if (!get_role($role)) {
                add_role($role, $displayName, $caps);
            } else {
                remove_role($role);
                add_role($role, $displayName, $caps);
            }
        }

        $administrator = get_role('administrator');
        if ($administrator !== null) {
            foreach (array_keys(self::roleMap()[self::ROLE_ADMINISTRATOR]) as $cap) {
                $administrator->add_cap($cap);
            }
        }
    }

    public static function deregister(): void
    {
        foreach (array_keys(self::roleMap()) as $role) {
            remove_role($role);
        }
    }
}
