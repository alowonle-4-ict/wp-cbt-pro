<?php

declare(strict_types=1);

namespace WPCBTPro\Programming\Contracts;

/**
 * The one boundary candidate code ever crosses to actually run (§16). The
 * plugin never executes code itself — every implementation of this
 * interface is a network client to a separate, isolated service. A call
 * here blocks until the sandbox finishes or the backend's own timeout
 * budget is exhausted; it is always invoked from a WP-Cron worker, never
 * from a candidate-facing request.
 */
interface ExecutionClient
{
    /** @throws ExecutionClientException on transport or protocol failure — never for a compile/runtime/test outcome, which belongs in the returned report */
    public function execute(ExecutionJob $job): ExecutionReport;
}
