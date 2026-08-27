<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

use WPCBTPro\Attempts\AnswerRepository;
use WPCBTPro\Attempts\AttemptOwnershipGuard;
use WPCBTPro\Attempts\AttemptRepository;
use WPCBTPro\Attempts\AttemptService;
use WPCBTPro\Attempts\AttemptsRestController;
use WPCBTPro\Attempts\ExamRuntimeController;
use WPCBTPro\Camera\Base64ImageUploader;
use WPCBTPro\Camera\CameraRestController;
use WPCBTPro\Camera\CameraSessionRepository;
use WPCBTPro\Camera\Contracts\CameraVerificationService;
use WPCBTPro\Camera\Providers\DefaultCameraVerificationService;
use WPCBTPro\Camera\VerificationAdminController;
use WPCBTPro\Camera\VerificationRepository;
use WPCBTPro\Candidates\CandidateRefGenerator;
use WPCBTPro\Candidates\CandidateRepository;
use WPCBTPro\Candidates\CandidateService;
use WPCBTPro\Candidates\CandidatesAdminController;
use WPCBTPro\Candidates\CurrentCandidateResolver;
use WPCBTPro\Candidates\PhotoUploader;
use WPCBTPro\Database\Migrator;
use WPCBTPro\DSA\DsaQuestionRepository;
use WPCBTPro\DSA\DsaQuestionsAdminController;
use WPCBTPro\DSA\DsaStateRepository;
use WPCBTPro\DSA\OperationParser;
use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\DSA\Registry\StructureServiceProvider;
use WPCBTPro\Exams\ExamQuestionResolver;
use WPCBTPro\Exams\ExamRepository;
use WPCBTPro\Exams\ExamService;
use WPCBTPro\Exams\ExamsAdminController;
use WPCBTPro\Exams\RandomizationService;
use WPCBTPro\Import\Word\WordImportAdminController;
use WPCBTPro\Import\Word\WordImportService;
use WPCBTPro\Institutions\InstitutionContext;
use WPCBTPro\Institutions\InstitutionRepository;
use WPCBTPro\Monitoring\InvigilatorDashboardController;
use WPCBTPro\Monitoring\MonitoringEventRepository;
use WPCBTPro\Privacy\PrivacyEraseService;
use WPCBTPro\Privacy\PrivacyExportService;
use WPCBTPro\Privacy\RetentionCleanupService;
use WPCBTPro\Programming\CodeExecutionResultRepository;
use WPCBTPro\Programming\CodeGradingService;
use WPCBTPro\Programming\CodeSubmissionRepository;
use WPCBTPro\Programming\CodeSubmissionRestController;
use WPCBTPro\Programming\Contracts\ExecutionClient;
use WPCBTPro\Programming\ExecutionSettingsController;
use WPCBTPro\Programming\HttpExecutionClient;
use WPCBTPro\Programming\ProgrammingQuestionRepository;
use WPCBTPro\Programming\ProgrammingQuestionsAdminController;
use WPCBTPro\Programming\Registry\LanguageRegistry;
use WPCBTPro\Programming\Registry\LanguageServiceProvider;
use WPCBTPro\Questions\QuestionRepository;
use WPCBTPro\Questions\Registry\QuestionTypeRegistry;
use WPCBTPro\Questions\Registry\QuestionTypeServiceProvider;
use WPCBTPro\Questions\Types\Dsa\DsaAdminEditor;
use WPCBTPro\Questions\Types\Programming\ProgrammingAdminEditor;
use WPCBTPro\REST\RestServiceProvider;
use WPCBTPro\Results\DelayedResultsReleaseService;
use WPCBTPro\Results\ResultRepository;
use WPCBTPro\Results\ResultsAdminController;
use WPCBTPro\Results\ResultsExportController;

final class Plugin
{
    private static ?Plugin $instance = null;

    private ServiceContainer $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new ServiceContainer();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain(
            WPCBTPRO_TEXT_DOMAIN,
            false,
            dirname(plugin_basename(WPCBTPRO_FILE)) . '/languages'
        );

        CronSchedules::register();

        $this->registerServices();

        add_action('admin_init', [Migrator::class, 'maybeUpgrade']);

        add_action('wpcbtpro_expire_attempts', function (): void {
            $this->container->get(AttemptService::class)->expireOverdueAttempts();
        });

        add_action('wpcbtpro_cleanup_retention', function (): void {
            $this->container->get(RetentionCleanupService::class)->run();
        });

        add_action('wpcbtpro_process_code_grading', function (): void {
            $this->container->get(CodeGradingService::class)->processPending();
        });

        add_action('wpcbtpro_release_delayed_results', function (): void {
            $this->container->get(DelayedResultsReleaseService::class)->run();
        });

        add_filter('wp_privacy_personal_data_exporters', function (array $exporters): array {
            $exporters['wp-cbt-pro'] = [
                'exporter_friendly_name' => __('WP CBT Pro', 'wp-cbt-pro'),
                'callback' => fn (string $email, int $page = 1) => $this->container->get(PrivacyExportService::class)->export($email),
            ];
            return $exporters;
        });

        add_filter('wp_privacy_personal_data_erasers', function (array $erasers): array {
            $erasers['wp-cbt-pro'] = [
                'eraser_friendly_name' => __('WP CBT Pro', 'wp-cbt-pro'),
                'callback' => fn (string $email, int $page = 1) => $this->container->get(PrivacyEraseService::class)->erase($email),
            ];
            return $erasers;
        });

        add_filter('wpcbtpro_rest_controllers', function (array $controllers): array {
            $controllers[] = $this->container->get(AttemptsRestController::class);
            $controllers[] = $this->container->get(CameraRestController::class);
            $controllers[] = $this->container->get(CodeSubmissionRestController::class);
            return $controllers;
        });

        $this->container->get(RestServiceProvider::class)->register();
        $this->container->get(LanguageServiceProvider::class)->register();
        $this->container->get(StructureServiceProvider::class)->register();
        $this->container->get(QuestionTypeServiceProvider::class)->register();
        $this->container->get(AdminMenu::class)->register();
        $this->container->get(ExamRuntimeController::class)->register();
        $this->container->get(ResultsExportController::class)->register();
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    private function registerServices(): void
    {
        $this->container->set(InstitutionContext::class, static fn () => new InstitutionContext());
        $this->container->set(InstitutionRepository::class, static fn () => new InstitutionRepository());
        $this->container->set(RestServiceProvider::class, static fn () => new RestServiceProvider());

        $this->container->set(CandidateRepository::class, static fn () => new CandidateRepository());
        $this->container->set(
            CandidateRefGenerator::class,
            fn (ServiceContainer $c) => new CandidateRefGenerator($c->get(CandidateRepository::class))
        );
        $this->container->set(
            CandidateService::class,
            fn (ServiceContainer $c) => new CandidateService(
                $c->get(CandidateRepository::class),
                $c->get(CandidateRefGenerator::class)
            )
        );
        $this->container->set(PhotoUploader::class, static fn () => new PhotoUploader());

        $this->container->set(QuestionTypeRegistry::class, static fn () => new QuestionTypeRegistry());
        $this->container->set(LanguageRegistry::class, static fn () => new LanguageRegistry());
        $this->container->set(
            LanguageServiceProvider::class,
            fn (ServiceContainer $c) => new LanguageServiceProvider($c->get(LanguageRegistry::class))
        );
        $this->container->set(ProgrammingQuestionRepository::class, static fn () => new ProgrammingQuestionRepository());
        $this->container->set(CodeSubmissionRepository::class, static fn () => new CodeSubmissionRepository());
        $this->container->set(CodeExecutionResultRepository::class, static fn () => new CodeExecutionResultRepository());
        $this->container->set(
            ProgrammingAdminEditor::class,
            fn (ServiceContainer $c) => new ProgrammingAdminEditor($c->get(LanguageRegistry::class))
        );
        $this->container->set(StructureRegistry::class, static fn () => new StructureRegistry());
        $this->container->set(
            StructureServiceProvider::class,
            fn (ServiceContainer $c) => new StructureServiceProvider($c->get(StructureRegistry::class))
        );
        $this->container->set(OperationParser::class, static fn () => new OperationParser());
        $this->container->set(DsaQuestionRepository::class, static fn () => new DsaQuestionRepository());
        $this->container->set(DsaStateRepository::class, static fn () => new DsaStateRepository());
        $this->container->set(
            DsaAdminEditor::class,
            fn (ServiceContainer $c) => new DsaAdminEditor($c->get(StructureRegistry::class), $c->get(OperationParser::class))
        );

        $this->container->set(
            QuestionTypeServiceProvider::class,
            fn (ServiceContainer $c) => new QuestionTypeServiceProvider(
                $c->get(QuestionTypeRegistry::class),
                $c->get(LanguageRegistry::class),
                $c->get(CodeSubmissionRepository::class),
                $c->get(CodeExecutionResultRepository::class),
                $c->get(StructureRegistry::class),
                $c->get(OperationParser::class),
                $c->get(DsaStateRepository::class)
            )
        );

        // Bound to the interface, not the concrete class — a self-hosted or
        // third-party execution backend re-registers this same key (§16,
        // §23) without CodeGradingService or ProgrammingType changing.
        $this->container->set(ExecutionClient::class, static fn () => new HttpExecutionClient());

        $this->container->set(
            CandidatesAdminController::class,
            fn (ServiceContainer $c) => new CandidatesAdminController(
                $c->get(CandidateRepository::class),
                $c->get(CandidateService::class),
                $c->get(PhotoUploader::class),
                $c->get(InstitutionContext::class),
                $c->get(InstitutionRepository::class)
            )
        );

        $this->container->set(QuestionRepository::class, static fn () => new QuestionRepository());
        $this->container->set(
            WordImportService::class,
            fn (ServiceContainer $c) => new WordImportService($c->get(QuestionTypeRegistry::class))
        );
        $this->container->set(
            WordImportAdminController::class,
            fn (ServiceContainer $c) => new WordImportAdminController(
                $c->get(WordImportService::class),
                $c->get(QuestionRepository::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(ExamRepository::class, static fn () => new ExamRepository());
        $this->container->set(RandomizationService::class, static fn () => new RandomizationService());
        $this->container->set(
            ExamQuestionResolver::class,
            fn (ServiceContainer $c) => new ExamQuestionResolver(
                $c->get(ExamRepository::class),
                $c->get(RandomizationService::class)
            )
        );
        $this->container->set(
            ExamService::class,
            fn (ServiceContainer $c) => new ExamService($c->get(ExamRepository::class))
        );
        $this->container->set(
            ExamsAdminController::class,
            fn (ServiceContainer $c) => new ExamsAdminController(
                $c->get(ExamRepository::class),
                $c->get(ExamService::class),
                $c->get(QuestionRepository::class),
                $c->get(InstitutionContext::class),
                $c->get(InstitutionRepository::class)
            )
        );

        $this->container->set(ExecutionSettingsController::class, static fn () => new ExecutionSettingsController());

        $this->container->set(
            AdminMenu::class,
            fn (ServiceContainer $c) => new AdminMenu(
                $c->get(CandidatesAdminController::class),
                $c->get(WordImportAdminController::class),
                $c->get(ExamsAdminController::class),
                $c->get(InvigilatorDashboardController::class),
                $c->get(VerificationAdminController::class),
                $c->get(ProgrammingQuestionsAdminController::class),
                $c->get(ExecutionSettingsController::class),
                $c->get(DsaQuestionsAdminController::class),
                $c->get(ResultsAdminController::class)
            )
        );

        $this->container->set(
            ProgrammingQuestionsAdminController::class,
            fn (ServiceContainer $c) => new ProgrammingQuestionsAdminController(
                $c->get(QuestionRepository::class),
                $c->get(ProgrammingQuestionRepository::class),
                $c->get(ProgrammingAdminEditor::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(
            DsaQuestionsAdminController::class,
            fn (ServiceContainer $c) => new DsaQuestionsAdminController(
                $c->get(QuestionRepository::class),
                $c->get(DsaQuestionRepository::class),
                $c->get(DsaAdminEditor::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(CurrentCandidateResolver::class, fn (ServiceContainer $c) => new CurrentCandidateResolver(
            $c->get(CandidateRepository::class)
        ));

        $this->container->set(AttemptRepository::class, static fn () => new AttemptRepository());
        $this->container->set(AnswerRepository::class, static fn () => new AnswerRepository());
        $this->container->set(ResultRepository::class, static fn () => new ResultRepository());

        $this->container->set(
            AttemptService::class,
            fn (ServiceContainer $c) => new AttemptService(
                $c->get(AttemptRepository::class),
                $c->get(AnswerRepository::class),
                $c->get(ResultRepository::class),
                $c->get(ExamRepository::class),
                $c->get(ExamQuestionResolver::class),
                $c->get(RandomizationService::class),
                $c->get(QuestionRepository::class),
                $c->get(QuestionTypeRegistry::class)
            )
        );

        $this->container->set(
            AttemptOwnershipGuard::class,
            fn (ServiceContainer $c) => new AttemptOwnershipGuard(
                $c->get(AttemptRepository::class),
                $c->get(ExamRepository::class),
                $c->get(CurrentCandidateResolver::class)
            )
        );

        $this->container->set(
            AttemptsRestController::class,
            fn (ServiceContainer $c) => new AttemptsRestController(
                $c->get(AttemptService::class),
                $c->get(ResultRepository::class),
                $c->get(CurrentCandidateResolver::class),
                $c->get(AttemptOwnershipGuard::class)
            )
        );

        $this->container->set(CameraSessionRepository::class, static fn () => new CameraSessionRepository());
        $this->container->set(VerificationRepository::class, static fn () => new VerificationRepository());
        $this->container->set(MonitoringEventRepository::class, static fn () => new MonitoringEventRepository());
        $this->container->set(Base64ImageUploader::class, static fn () => new Base64ImageUploader());

        // Bound to the interface, not the concrete class, so a Pro/Enterprise
        // AI-assisted provider can re-register this same key (§11–§12, §23)
        // without anything else in the plugin knowing it changed.
        $this->container->set(
            CameraVerificationService::class,
            fn (ServiceContainer $c) => new DefaultCameraVerificationService(
                $c->get(CameraSessionRepository::class),
                $c->get(VerificationRepository::class),
                $c->get(MonitoringEventRepository::class),
                $c->get(Base64ImageUploader::class)
            )
        );

        $this->container->set(
            CameraRestController::class,
            fn (ServiceContainer $c) => new CameraRestController(
                $c->get(CameraVerificationService::class),
                $c->get(AttemptService::class),
                $c->get(AttemptOwnershipGuard::class),
                $c->get(CurrentCandidateResolver::class),
                $c->get(CandidateRepository::class)
            )
        );

        $this->container->set(
            ExamRuntimeController::class,
            fn (ServiceContainer $c) => new ExamRuntimeController(
                $c->get(CurrentCandidateResolver::class),
                $c->get(AttemptService::class),
                $c->get(AttemptRepository::class),
                $c->get(ExamRepository::class),
                $c->get(AnswerRepository::class),
                $c->get(ResultRepository::class),
                $c->get(QuestionTypeRegistry::class),
                $c->get(VerificationRepository::class),
                $c->get(MonitoringEventRepository::class)
            )
        );

        $this->container->set(
            VerificationAdminController::class,
            fn (ServiceContainer $c) => new VerificationAdminController(
                $c->get(VerificationRepository::class),
                $c->get(AttemptRepository::class),
                $c->get(ExamRepository::class),
                $c->get(CandidateRepository::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(
            InvigilatorDashboardController::class,
            fn (ServiceContainer $c) => new InvigilatorDashboardController(
                $c->get(AttemptRepository::class),
                $c->get(AttemptService::class),
                $c->get(AnswerRepository::class),
                $c->get(ExamRepository::class),
                $c->get(CandidateRepository::class),
                $c->get(CameraSessionRepository::class),
                $c->get(MonitoringEventRepository::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(
            RetentionCleanupService::class,
            fn (ServiceContainer $c) => new RetentionCleanupService(
                $c->get(MonitoringEventRepository::class),
                $c->get(VerificationRepository::class)
            )
        );

        $this->container->set(
            PrivacyExportService::class,
            fn (ServiceContainer $c) => new PrivacyExportService(
                $c->get(CandidateRepository::class),
                $c->get(AttemptRepository::class),
                $c->get(ExamRepository::class),
                $c->get(ResultRepository::class),
                $c->get(VerificationRepository::class)
            )
        );

        $this->container->set(
            PrivacyEraseService::class,
            fn (ServiceContainer $c) => new PrivacyEraseService(
                $c->get(CandidateRepository::class),
                $c->get(AttemptRepository::class),
                $c->get(VerificationRepository::class),
                $c->get(MonitoringEventRepository::class)
            )
        );

        $this->container->set(
            CodeGradingService::class,
            fn (ServiceContainer $c) => new CodeGradingService(
                $c->get(CodeSubmissionRepository::class),
                $c->get(CodeExecutionResultRepository::class),
                $c->get(AnswerRepository::class),
                $c->get(QuestionRepository::class),
                $c->get(ExecutionClient::class),
                $c->get(AttemptService::class),
                $c->get(AttemptRepository::class),
                $c->get(ExamRepository::class)
            )
        );

        $this->container->set(
            CodeSubmissionRestController::class,
            fn (ServiceContainer $c) => new CodeSubmissionRestController(
                $c->get(AttemptOwnershipGuard::class),
                $c->get(AnswerRepository::class),
                $c->get(CodeSubmissionRepository::class)
            )
        );

        $this->container->set(
            ResultsAdminController::class,
            fn (ServiceContainer $c) => new ResultsAdminController(
                $c->get(ResultRepository::class),
                $c->get(ExamRepository::class),
                $c->get(CandidateRepository::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(
            ResultsExportController::class,
            fn (ServiceContainer $c) => new ResultsExportController(
                $c->get(ResultRepository::class),
                $c->get(ExamRepository::class),
                $c->get(CandidateRepository::class),
                $c->get(InstitutionContext::class)
            )
        );

        $this->container->set(
            DelayedResultsReleaseService::class,
            fn (ServiceContainer $c) => new DelayedResultsReleaseService(
                $c->get(ExamRepository::class),
                $c->get(ResultRepository::class)
            )
        );
    }
}
