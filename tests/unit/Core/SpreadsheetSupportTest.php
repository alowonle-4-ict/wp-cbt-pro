<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPCBTPro\Core\SpreadsheetSupport;

final class SpreadsheetSupportTest extends TestCase
{
    public function testAvailableIsTrueWhenComposerDependenciesAreInstalled(): void
    {
        self::assertTrue(SpreadsheetSupport::available());
    }
}
