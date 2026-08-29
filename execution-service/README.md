# Execution service — reference implementation

`server.php` is a minimal, working implementation of the HTTP contract
`WPCBTPro\Programming\HttpExecutionClient` talks to
(`wpcbtpro_settings['execution_service_url']`). It exists to document and
prove the wire contract against something real, and as a starting point for
a genuinely isolated implementation — **it is not a sandbox**, and must
never be pointed at by a production install.

## What it actually does

It shells out to the language runtime already installed on the machine
(`python3`, `node`) with no isolation: no container, no seccomp/cgroups, no
network restriction, no filesystem restriction. Candidate-submitted code
runs with the same privileges as the PHP process itself. This is fine for
local development against a throwaway database, and is exactly what makes
it useful as a reference — but it is the one piece of this plugin's
architecture (§16) that explicitly must never run untrusted input in
production. A real deployment needs actual isolation (a container per run,
gVisor, Firecracker, a dedicated worker fleet with no access to anything
sensitive) — this file is not that.

## Run it

```
php -S 127.0.0.1:8090 execution-service/server.php
```

Then, in wp-admin → CBT → Settings, set:
- **Execution Service URL**: `http://127.0.0.1:8090`
- **Execution Service API Key**: anything, or leave blank

(Leaving `WPCBTPRO_EXEC_API_KEY` unset in the server's environment makes it
accept any key, including none — set it to require a matching
`Authorization: Bearer <key>` header.)

Programming questions with `language` set to `python3` or `javascript` will
then grade for real the next time `wpcbtpro_process_code_grading` runs (WP-Cron,
every 5 minutes) — or immediately by calling
`CodeGradingService::processPending()` directly, which is what the plugin's
own cron hook does.

## Contract

**Request** — `POST /execute`, `Authorization: Bearer <api_key>`:

```json
{
  "submission_id": 123,
  "language": "python3",
  "source": "...",
  "entry_point": null,
  "time_limit_ms": 2000,
  "memory_limit_mb": 128,
  "test_cases": [
    { "id": 1, "input": "5\n", "expected_output": "10" }
  ]
}
```

**Response** — `200`:

```json
{
  "compiled": true,
  "compile_error": null,
  "test_case_results": [
    {
      "id": 1,
      "passed": true,
      "stdout": "10\n",
      "stderr": "",
      "exit_code": 0,
      "runtime_ms": 42,
      "memory_kb": null,
      "verdict": "passed"
    }
  ]
}
```

`verdict` is one of `passed`, `wrong_answer`, `runtime_error`,
`time_limit_exceeded`, `memory_limit_exceeded` (this reference
implementation never reports the last one — it doesn't measure memory).
Anything other than a 2xx status, or a body that doesn't parse to this
shape, is treated by `HttpExecutionClient` as a transport failure, not a
grading outcome — the submission is marked `failed` (held for a re-run,
not scored as wrong) rather than given a score.
