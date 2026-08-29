<?php
/**
 * Reference implementation of the execution service contract HttpExecutionClient
 * talks to (see includes/Programming/HttpExecutionClient.php). This is NOT a
 * sandbox — it shells out to the language runtime on the host with no
 * isolation, no resource caps beyond a wall-clock timeout, and no protection
 * against malicious code. It exists to prove and document the wire contract
 * (request/response shape, auth header, verdicts) against something real,
 * and as a starting point for a genuinely isolated implementation (containers,
 * gVisor, Firecracker, etc.) — never run this against untrusted code, and
 * never expose it to anything but the plugin's own execution-service settings.
 *
 * Run: php -S 127.0.0.1:8090 execution-service/server.php
 * Point the plugin at it: wp-admin → CBT → Settings → Execution Service URL
 * = http://127.0.0.1:8090, API key = whatever WPCBTPRO_EXEC_API_KEY is set to
 * (or left blank; the auth check below only rejects a *wrong* key, not a
 * missing one, since this is a dev tool).
 */

declare(strict_types=1);

const RUNNERS = [
    'python3' => ['bin' => 'python3', 'ext' => '.py', 'args' => []],
    'javascript' => ['bin' => 'node', 'ext' => '.js', 'args' => []],
];

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

function run_one(string $bin, array $binArgs, string $sourceFile, ?string $stdin, int $timeLimitMs): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $cmd = array_merge([$bin], $binArgs, [$sourceFile]);

    $start = microtime(true);
    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'Could not start process.', 'exit_code' => -1, 'timed_out' => false];
    }

    fwrite($pipes[0], $stdin ?? '');
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $timedOut = false;
    $deadline = $start + ($timeLimitMs / 1000);

    while (true) {
        $status = proc_get_status($process);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        if (!$status['running']) {
            break;
        }
        if (microtime(true) > $deadline) {
            $timedOut = true;
            proc_terminate($process, 9);
            break;
        }
        usleep(10000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $runtimeMs = (int) round((microtime(true) - $start) * 1000);

    return ['stdout' => $stdout, 'stderr' => $stderr, 'exit_code' => $timedOut ? null : $exitCode, 'runtime_ms' => $runtimeMs, 'timed_out' => $timedOut];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['REQUEST_URI'] ?? '') !== '/execute') {
    respond(404, ['error' => 'Only POST /execute is implemented.']);
}

$configuredKey = getenv('WPCBTPRO_EXEC_API_KEY');
if ($configuredKey !== false && $configuredKey !== '') {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header !== 'Bearer ' . $configuredKey) {
        respond(401, ['error' => 'Invalid API key.']);
    }
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    respond(400, ['error' => 'Invalid JSON body.']);
}

$language = (string) ($payload['language'] ?? '');
if (!isset(RUNNERS[$language])) {
    respond(200, [
        'compiled' => false,
        'compile_error' => "Unsupported language '{$language}' — this reference service only implements: " . implode(', ', array_keys(RUNNERS)),
        'test_case_results' => [],
    ]);
}

$runner = RUNNERS[$language];
$source = (string) ($payload['source'] ?? '');
$timeLimitMs = (int) ($payload['time_limit_ms'] ?? 2000);
$testCases = is_array($payload['test_cases'] ?? null) ? $payload['test_cases'] : [];

$tmpFile = tempnam(sys_get_temp_dir(), 'wpcbtpro_exec_') . $runner['ext'];
file_put_contents($tmpFile, $source);

$results = [];
foreach ($testCases as $testCase) {
    $run = run_one($runner['bin'], $runner['args'], $tmpFile, $testCase['input'] ?? null, $timeLimitMs);

    if ($run['timed_out']) {
        $verdict = 'time_limit_exceeded';
        $passed = false;
    } elseif ($run['exit_code'] !== 0) {
        $verdict = 'runtime_error';
        $passed = false;
    } else {
        $expected = rtrim((string) ($testCase['expected_output'] ?? ''));
        $actual = rtrim($run['stdout']);
        $passed = $actual === $expected;
        $verdict = $passed ? 'passed' : 'wrong_answer';
    }

    $results[] = [
        'id' => (int) ($testCase['id'] ?? 0),
        'passed' => $passed,
        'stdout' => $run['stdout'],
        'stderr' => $run['stderr'],
        'exit_code' => $run['exit_code'],
        'runtime_ms' => $run['runtime_ms'] ?? 0,
        'memory_kb' => null,
        'verdict' => $verdict,
    ];
}

unlink($tmpFile);

respond(200, [
    'compiled' => true,
    'compile_error' => null,
    'test_case_results' => $results,
]);
