<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

use WPCBTPro\Camera\VerificationAdminController;
use WPCBTPro\Candidates\CandidateBulkImportAdminController;
use WPCBTPro\Candidates\CandidatesAdminController;
use WPCBTPro\DSA\DsaQuestionsAdminController;
use WPCBTPro\Exams\ExamAssignmentAdminController;
use WPCBTPro\Exams\ExamRosterAdminController;
use WPCBTPro\Exams\ExamsAdminController;
use WPCBTPro\Import\Word\WordImportAdminController;
use WPCBTPro\Monitoring\InvigilatorDashboardController;
use WPCBTPro\Programming\ExecutionSettingsController;
use WPCBTPro\Programming\ProgrammingQuestionsAdminController;
use WPCBTPro\Questions\McqQuestionsAdminController;
use WPCBTPro\Results\ResultsAdminController;
use WPCBTPro\Security\Capabilities;

final class AdminMenu
{
    public function __construct(
        private readonly CandidatesAdminController $candidatesController,
        private readonly CandidateBulkImportAdminController $candidateBulkImportController,
        private readonly WordImportAdminController $wordImportController,
        private readonly ExamsAdminController $examsController,
        private readonly ExamRosterAdminController $examRosterController,
        private readonly ExamAssignmentAdminController $examAssignmentController,
        private readonly InvigilatorDashboardController $invigilatorController,
        private readonly VerificationAdminController $verificationController,
        private readonly ProgrammingQuestionsAdminController $programmingController,
        private readonly ExecutionSettingsController $settingsController,
        private readonly DsaQuestionsAdminController $dsaController,
        private readonly ResultsAdminController $resultsController,
        private readonly McqQuestionsAdminController $mcqController,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Each of these processes its own page's POST/delete requests on
        // admin_init — before WordPress admin-header.php streams the page's
        // HTML — because a redirect from inside the add_submenu_page()
        // render callback itself is always too late (see each controller's
        // own register()/maybeProcessRequest() docblock).
        $this->candidatesController->register();
        $this->candidateBulkImportController->register();
        $this->wordImportController->register();
        $this->examsController->register();
        $this->examRosterController->register();
        $this->examAssignmentController->register();
        $this->verificationController->register();
        $this->programmingController->register();
        $this->dsaController->register();
        $this->resultsController->register();
        $this->mcqController->register();
        $this->invigilatorController->register();
    }

    public function addMenuPages(): void
    {
        add_menu_page(
            __('WP CBT Pro', 'wp-cbt-pro'),
            __('CBT', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_EXAMS,
            'wpcbtpro-exams',
            [$this->examsController, 'render'],
            'dashicons-welcome-learn-more',
            26
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Exams', 'wp-cbt-pro'),
            __('Exams', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_EXAMS,
            'wpcbtpro-exams',
            [$this->examsController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Candidates', 'wp-cbt-pro'),
            __('Candidates', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_CANDIDATES,
            'wpcbtpro-candidates',
            [$this->candidatesController, 'render']
        );

        // A null parent registers the page (so admin.php?page=wpcbtpro-exam-roster
        // is a valid, capability-checked destination) without adding a visible
        // nav item — this is only ever reached via the "Manage this exam's
        // roster" link on a specific exam's edit screen, never browsed directly.
        add_submenu_page(
            null,
            __('Exam Roster', 'wp-cbt-pro'),
            __('Exam Roster', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_EXAMS,
            'wpcbtpro-exam-roster',
            [$this->examRosterController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Import Candidates', 'wp-cbt-pro'),
            __('Import Candidates', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_CANDIDATES,
            'wpcbtpro-import-candidates',
            [$this->candidateBulkImportController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Assign Candidates to Exams', 'wp-cbt-pro'),
            __('Assign to Exam', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_EXAMS,
            'wpcbtpro-exam-assignment',
            [$this->examAssignmentController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Import Questions', 'wp-cbt-pro'),
            __('Import Questions', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_QUESTIONS,
            'wpcbtpro-import-questions',
            [$this->wordImportController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Invigilator Dashboard', 'wp-cbt-pro'),
            __('Invigilator', 'wp-cbt-pro'),
            Capabilities::VIEW_MONITORING,
            'wpcbtpro-invigilator',
            [$this->invigilatorController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Identity Verification Review', 'wp-cbt-pro'),
            __('Verification Review', 'wp-cbt-pro'),
            Capabilities::REVIEW_ATTEMPTS,
            'wpcbtpro-verification',
            [$this->verificationController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Questions', 'wp-cbt-pro'),
            __('Questions', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT_QUESTIONS,
            'wpcbtpro-questions',
            [$this->mcqController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Programming Questions', 'wp-cbt-pro'),
            __('Programming Questions', 'wp-cbt-pro'),
            Capabilities::MANAGE_PROGRAMMING_QUESTIONS,
            'wpcbtpro-programming',
            [$this->programmingController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('DSA Questions', 'wp-cbt-pro'),
            __('DSA Questions', 'wp-cbt-pro'),
            Capabilities::MANAGE_DSA_QUESTIONS,
            'wpcbtpro-dsa',
            [$this->dsaController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('Results', 'wp-cbt-pro'),
            __('Results', 'wp-cbt-pro'),
            Capabilities::VIEW_CBT_RESULTS,
            'wpcbtpro-results',
            [$this->resultsController, 'render']
        );

        add_submenu_page(
            'wpcbtpro-exams',
            __('CBT Settings', 'wp-cbt-pro'),
            __('Settings', 'wp-cbt-pro'),
            Capabilities::MANAGE_CBT,
            'wpcbtpro-settings',
            [$this->settingsController, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, 'wpcbtpro')) {
            return;
        }

        wp_enqueue_style(
            'wpcbtpro-admin',
            WPCBTPRO_URL . 'admin/css/admin.css',
            [],
            WPCBTPRO_VERSION
        );
        wp_enqueue_media();

        if (str_contains($hook, 'wpcbtpro-import-questions')) {
            wp_enqueue_script(
                'wpcbtpro-mathjax',
                apply_filters('wpcbtpro_mathjax_src', 'https://cdn.jsdelivr.net/npm/mathjax@3/es5/mml-chtml.js'),
                [],
                null,
                true
            );
        }

        if (str_contains($hook, 'wpcbtpro-exams')) {
            wp_enqueue_script(
                'wpcbtpro-exam-question-picker',
                WPCBTPRO_URL . 'admin/js/exam-question-picker.js',
                [],
                WPCBTPRO_VERSION,
                true
            );
        }
    }
}
