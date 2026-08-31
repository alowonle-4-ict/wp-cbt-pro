<?php

declare(strict_types=1);

namespace WPCBTPro\Database;

/**
 * Full baseline schema (§4 of the architecture spec). Defined up front because
 * these are pure CREATE TABLE statements — later phases build behavior on top
 * of tables that already exist, rather than layering migrations per feature.
 */
final class Schema
{
    /**
     * @return array<string, string> table name (without prefix) => dbDelta-compatible SQL
     */
    public static function definitions(string $prefix, string $charsetCollate): array
    {
        $p = $prefix . 'cbt_';

        return [
            'institutions' => "CREATE TABLE {$p}institutions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                plan VARCHAR(32) NOT NULL DEFAULT 'free',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) {$charsetCollate};",

            'candidates' => "CREATE TABLE {$p}candidates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id BIGINT UNSIGNED NOT NULL,
                wp_user_id BIGINT UNSIGNED NULL,
                candidate_ref VARCHAR(64) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(32) NULL,
                photo_attachment_id BIGINT UNSIGNED NULL,
                department VARCHAR(191) NULL,
                class VARCHAR(191) NULL,
                registration_number VARCHAR(100) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY candidate_ref (candidate_ref),
                KEY institution_id (institution_id)
            ) {$charsetCollate};",

            'exams' => "CREATE TABLE {$p}exams (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                description LONGTEXT NULL,
                instructions LONGTEXT NULL,
                subject VARCHAR(191) NULL,
                duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
                start_at DATETIME NULL,
                end_at DATETIME NULL,
                attempt_limit SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                pass_mark DECIMAL(6,2) NULL,
                randomize_questions TINYINT(1) NOT NULL DEFAULT 0,
                randomize_options TINYINT(1) NOT NULL DEFAULT 0,
                negative_marking TINYINT(1) NOT NULL DEFAULT 0,
                camera_required TINYINT(1) NOT NULL DEFAULT 0,
                microphone_mode VARCHAR(20) NOT NULL DEFAULT 'off',
                identity_verification TINYINT(1) NOT NULL DEFAULT 0,
                snapshot_interval_seconds INT UNSIGNED NULL,
                fullscreen_required TINYINT(1) NOT NULL DEFAULT 0,
                result_visibility VARCHAR(20) NOT NULL DEFAULT 'immediate',
                restrict_to_roster TINYINT(1) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY institution_id (institution_id),
                KEY status (status)
            ) {$charsetCollate};",

            'questions' => "CREATE TABLE {$p}questions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                institution_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                subject VARCHAR(191) NULL,
                topic VARCHAR(191) NULL,
                difficulty VARCHAR(20) NULL,
                content LONGTEXT NOT NULL,
                media_attachment_id BIGINT UNSIGNED NULL,
                marks DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                negative_marks DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                correct_answer LONGTEXT NULL,
                explanation LONGTEXT NULL,
                author_id BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY institution_id (institution_id),
                KEY type (type),
                KEY status (status)
            ) {$charsetCollate};",

            'question_options' => "CREATE TABLE {$p}question_options (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                question_id BIGINT UNSIGNED NOT NULL,
                label LONGTEXT NOT NULL,
                is_correct TINYINT(1) NOT NULL DEFAULT 0,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY question_id (question_id)
            ) {$charsetCollate};",

            'exam_questions' => "CREATE TABLE {$p}exam_questions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                exam_id BIGINT UNSIGNED NOT NULL,
                question_id BIGINT UNSIGNED NOT NULL,
                pool_id VARCHAR(64) NULL,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY exam_id (exam_id),
                KEY question_id (question_id)
            ) {$charsetCollate};",

            'exam_pools' => "CREATE TABLE {$p}exam_pools (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                exam_id BIGINT UNSIGNED NOT NULL,
                pool_key VARCHAR(64) NOT NULL,
                name VARCHAR(191) NOT NULL,
                draw_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                PRIMARY KEY  (id),
                UNIQUE KEY exam_pool (exam_id, pool_key)
            ) {$charsetCollate};",

            'attempts' => "CREATE TABLE {$p}attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                exam_id BIGINT UNSIGNED NOT NULL,
                candidate_id BIGINT UNSIGNED NOT NULL,
                seed VARCHAR(64) NOT NULL,
                server_start DATETIME NOT NULL,
                server_end DATETIME NOT NULL,
                submitted_at DATETIME NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY exam_id (exam_id),
                KEY candidate_id (candidate_id),
                KEY status (status)
            ) {$charsetCollate};",

            'answers' => "CREATE TABLE {$p}answers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attempt_id BIGINT UNSIGNED NOT NULL,
                question_id BIGINT UNSIGNED NOT NULL,
                value LONGTEXT NULL,
                marked_for_review TINYINT(1) NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY attempt_question (attempt_id, question_id)
            ) {$charsetCollate};",

            'results' => "CREATE TABLE {$p}results (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attempt_id BIGINT UNSIGNED NOT NULL,
                score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                grade VARCHAR(10) NULL,
                pass_status VARCHAR(10) NULL,
                correct_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                incorrect_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                unanswered_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                pending_review_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'final',
                time_used_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                released_at DATETIME NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY attempt_id (attempt_id)
            ) {$charsetCollate};",

            'monitoring_events' => "CREATE TABLE {$p}monitoring_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attempt_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                payload LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY attempt_id (attempt_id),
                KEY event_type (event_type)
            ) {$charsetCollate};",

            'camera_sessions' => "CREATE TABLE {$p}camera_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attempt_id BIGINT UNSIGNED NOT NULL,
                state VARCHAR(20) NOT NULL DEFAULT 'not_started',
                connected_at DATETIME NULL,
                disconnected_at DATETIME NULL,
                PRIMARY KEY  (id),
                KEY attempt_id (attempt_id)
            ) {$charsetCollate};",

            'verification_records' => "CREATE TABLE {$p}verification_records (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                attempt_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'not_performed',
                reviewer_id BIGINT UNSIGNED NULL,
                captured_image_attachment_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY attempt_id (attempt_id)
            ) {$charsetCollate};",

            'programming_questions' => "CREATE TABLE {$p}programming_questions (
                question_id BIGINT UNSIGNED NOT NULL,
                language VARCHAR(32) NOT NULL,
                starter_code LONGTEXT NULL,
                entry_point VARCHAR(191) NULL,
                time_limit_ms INT UNSIGNED NOT NULL DEFAULT 2000,
                memory_limit_mb INT UNSIGNED NOT NULL DEFAULT 128,
                PRIMARY KEY  (question_id)
            ) {$charsetCollate};",

            'programming_test_cases' => "CREATE TABLE {$p}programming_test_cases (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                question_id BIGINT UNSIGNED NOT NULL,
                input LONGTEXT NULL,
                expected_output LONGTEXT NULL,
                weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                is_hidden TINYINT(1) NOT NULL DEFAULT 1,
                sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY  (id),
                KEY question_id (question_id)
            ) {$charsetCollate};",

            'code_submissions' => "CREATE TABLE {$p}code_submissions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                answer_id BIGINT UNSIGNED NOT NULL,
                language VARCHAR(32) NOT NULL,
                source LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                compile_error LONGTEXT NULL,
                graded_at DATETIME NULL,
                submitted_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY answer_id (answer_id),
                KEY status (status)
            ) {$charsetCollate};",

            'code_execution_results' => "CREATE TABLE {$p}code_execution_results (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                submission_id BIGINT UNSIGNED NOT NULL,
                test_case_id BIGINT UNSIGNED NOT NULL,
                passed TINYINT(1) NOT NULL DEFAULT 0,
                stdout LONGTEXT NULL,
                stderr LONGTEXT NULL,
                exit_code SMALLINT NULL,
                runtime_ms INT UNSIGNED NULL,
                memory_kb INT UNSIGNED NULL,
                verdict VARCHAR(20) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY submission_id (submission_id),
                KEY test_case_id (test_case_id)
            ) {$charsetCollate};",

            'dsa_questions' => "CREATE TABLE {$p}dsa_questions (
                question_id BIGINT UNSIGNED NOT NULL,
                structure VARCHAR(32) NOT NULL,
                mode VARCHAR(24) NOT NULL,
                operations_json LONGTEXT NULL,
                PRIMARY KEY  (question_id)
            ) {$charsetCollate};",

            'dsa_states' => "CREATE TABLE {$p}dsa_states (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                answer_id BIGINT UNSIGNED NOT NULL,
                state_json LONGTEXT NOT NULL,
                is_valid TINYINT(1) NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY answer_id (answer_id)
            ) {$charsetCollate};",

            'audit_logs' => "CREATE TABLE {$p}audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_id BIGINT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                object_type VARCHAR(64) NOT NULL,
                object_id BIGINT UNSIGNED NULL,
                context LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY object_type (object_type, object_id)
            ) {$charsetCollate};",

            'candidate_exam_overrides' => "CREATE TABLE {$p}candidate_exam_overrides (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                exam_id BIGINT UNSIGNED NOT NULL,
                candidate_id BIGINT UNSIGNED NOT NULL,
                extra_minutes INT UNSIGNED NOT NULL DEFAULT 0,
                extra_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY exam_candidate (exam_id, candidate_id)
            ) {$charsetCollate};",

            'exam_candidates' => "CREATE TABLE {$p}exam_candidates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                exam_id BIGINT UNSIGNED NOT NULL,
                candidate_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY exam_candidate (exam_id, candidate_id),
                KEY candidate_id (candidate_id)
            ) {$charsetCollate};",
        ];
    }
}
