<?php

declare(strict_types=1);

namespace WPCBTPro\Programming;

use WPCBTPro\Programming\Contracts\ExecutionClient;
use WPCBTPro\Programming\Contracts\ExecutionClientException;
use WPCBTPro\Programming\Contracts\ExecutionJob;
use WPCBTPro\Programming\Contracts\ExecutionReport;
use WPCBTPro\Programming\Contracts\TestCaseResult;

/**
 * Talks to whatever sandbox service is configured at
 * wpcbtpro_settings['execution_service_url'] over HTTPS, authenticated with
 * a shared secret (never embedded in any candidate-facing request). Uses
 * wp_remote_post() rather than curl directly, per WordPress conventions —
 * this also means the request is transparently mockable via the
 * 'pre_http_request' filter in tests, exactly like core HTTP calls.
 *
 * See execution-service/README.md for the reference implementation of the
 * other end of this contract.
 */
final class HttpExecutionClient implements ExecutionClient
{
    public function execute(ExecutionJob $job): ExecutionReport
    {
        $settings = get_option('wpcbtpro_settings', []);
        $baseUrl = rtrim((string) ($settings['execution_service_url'] ?? ''), '/');
        $apiKey = (string) ($settings['execution_service_api_key'] ?? '');

        if ($baseUrl === '') {
            throw new ExecutionClientException('No execution service URL is configured.');
        }

        $payload = [
            'submission_id' => $job->submissionId,
            'language' => $job->language,
            'source' => $job->source,
            'entry_point' => $job->entryPoint,
            'time_limit_ms' => $job->timeLimitMs,
            'memory_limit_mb' => $job->memoryLimitMb,
            'test_cases' => $job->testCases,
        ];

        $response = wp_remote_post($baseUrl . '/execute', [
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new ExecutionClientException($response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ExecutionClientException("Execution service returned HTTP {$statusCode}.");
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !isset($body['test_case_results']) || !is_array($body['test_case_results'])) {
            throw new ExecutionClientException('Execution service returned an unrecognized response shape.');
        }

        $results = [];
        foreach ($body['test_case_results'] as $result) {
            $results[] = new TestCaseResult(
                (int) ($result['id'] ?? 0),
                !empty($result['passed']),
                (string) ($result['stdout'] ?? ''),
                (string) ($result['stderr'] ?? ''),
                isset($result['exit_code']) ? (int) $result['exit_code'] : null,
                (int) ($result['runtime_ms'] ?? 0),
                isset($result['memory_kb']) ? (int) $result['memory_kb'] : null,
                (string) ($result['verdict'] ?? TestCaseResult::VERDICT_RUNTIME_ERROR)
            );
        }

        return new ExecutionReport(
            !empty($body['compiled']),
            $body['compile_error'] ?? null,
            $results
        );
    }
}
