<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

/** Transport or protocol failure talking to the execution service — never thrown for a grading outcome (that's an ExecutionReport). */
final class ExecutionClientException extends \RuntimeException
{
}
