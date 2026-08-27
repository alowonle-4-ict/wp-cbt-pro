<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Registry;

use WPCBTPro\DSA\DsaStateRepository;
use WPCBTPro\DSA\OperationParser;
use WPCBTPro\DSA\Registry\StructureRegistry;
use WPCBTPro\Programming\CodeExecutionResultRepository;
use WPCBTPro\Programming\CodeSubmissionRepository;
use WPCBTPro\Programming\Registry\LanguageRegistry;
use WPCBTPro\Questions\Types\Dsa\DsaType;
use WPCBTPro\Questions\Types\Mcq\SingleChoiceType;
use WPCBTPro\Questions\Types\Mcq\TrueFalseType;
use WPCBTPro\Questions\Types\Programming\ProgrammingType;

final class QuestionTypeServiceProvider
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly LanguageRegistry $languages,
        private readonly CodeSubmissionRepository $codeSubmissions,
        private readonly CodeExecutionResultRepository $codeExecutionResults,
        private readonly StructureRegistry $structures,
        private readonly OperationParser $operationParser,
        private readonly DsaStateRepository $dsaStates,
    ) {
    }

    public function register(): void
    {
        $this->registry->register(new SingleChoiceType());
        $this->registry->register(new TrueFalseType());
        $this->registry->register(new ProgrammingType($this->languages, $this->codeSubmissions, $this->codeExecutionResults));
        $this->registry->register(new DsaType($this->structures, $this->operationParser, $this->dsaStates));

        /**
         * Math and third-party extensions register here — never by editing
         * this class (§5, §23). Feature-gating a type by license tier
         * belongs in the FeatureGate (§23, not yet built), not here.
         *
         * @param QuestionTypeRegistry $registry
         */
        do_action('wpcbtpro_register_question_types', $this->registry);
    }
}
