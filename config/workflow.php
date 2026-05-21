<?php

declare(strict_types=1);

return [
    'http_timeout' => (int) env('WORKFLOW_HTTP_TIMEOUT', 30),
    'run_timeout_seconds' => (int) env('WORKFLOW_RUN_TIMEOUT_SECONDS', 1800),
];
