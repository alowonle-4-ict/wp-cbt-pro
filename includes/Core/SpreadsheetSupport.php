<?php

declare(strict_types=1);

namespace WPCBTPro\Core;

/**
 * The one thing every Excel-dependent feature (candidate bulk import, exam
 * roster import, results export) checks before offering itself. Composer is
 * optional for the rest of the plugin (see wp-cbt-pro.php's fallback
 * autoloader) but phpoffice/phpspreadsheet has no such fallback, so a site
 * that never ran `composer install` must still get a clear message here
 * instead of a fatal error the moment one of those screens loads.
 */
final class SpreadsheetSupport
{
    public static function available(): bool
    {
        return class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
    }

    public static function missingMessage(): string
    {
        return __(
            'This feature needs the phpoffice/phpspreadsheet library, which isn\'t installed. Run `composer install` in the plugin directory, then reload this page.',
            'wp-cbt-pro'
        );
    }
}
