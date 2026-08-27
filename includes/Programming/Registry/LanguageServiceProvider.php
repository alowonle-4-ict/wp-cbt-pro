<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Registry;

use WPCBTPro\Programming\Languages\CLanguage;
use WPCBTPro\Programming\Languages\CppLanguage;
use WPCBTPro\Programming\Languages\JavaLanguage;
use WPCBTPro\Programming\Languages\JavaScriptLanguage;
use WPCBTPro\Programming\Languages\Python3Language;

final class LanguageServiceProvider
{
    public function __construct(private readonly LanguageRegistry $registry)
    {
    }

    public function register(): void
    {
        $this->registry->register(new Python3Language());
        $this->registry->register(new CLanguage());
        $this->registry->register(new CppLanguage());
        $this->registry->register(new JavaLanguage());
        $this->registry->register(new JavaScriptLanguage());

        /**
         * A self-hosted or specialized execution backend (§16, §23) adds a
         * new supported language here rather than editing this class.
         *
         * @param LanguageRegistry $registry
         */
        do_action('wpcbtpro_register_languages', $this->registry);
    }
}
