<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Types\Mcq;

use WPCBTPro\Questions\Contracts\AdminEditorView;
use WPCBTPro\Questions\Contracts\ImportHandler;

/**
 * True/False is structurally a single-choice question restricted to two
 * fixed options — it reuses SingleChoice's renderer, validator, answer
 * processor, scoring, and candidate UI outright, and only replaces the
 * admin editor (fixed labels, no free-text options).
 */
final class TrueFalseType extends SingleChoiceType
{
    public function id(): string
    {
        return 'true_false';
    }

    public function label(): string
    {
        return __('True / False', 'wp-cbt-pro');
    }

    public function adminEditor(): AdminEditorView
    {
        return new TrueFalseAdminEditor();
    }

    public function importHandler(): ?ImportHandler
    {
        return new TrueFalseImportHandler();
    }
}
