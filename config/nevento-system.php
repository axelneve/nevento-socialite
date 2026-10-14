<?php

declare(strict_types=1);

/*
 * Reporting to nevento-system (system.nevento.nl): heartbeats of scheduled tasks (per tenant), failed jobs, mail failures
 * and tenant health. Off until SYSTEM_TOKEN is set; the token is made on the Apps page of the system.
 */
return [
    'url' => env('SYSTEM_URL', 'https://system.nevento.nl'),

    'token' => env('SYSTEM_TOKEN'),

    // Scheduled tasks or commands that are never reported: exact names or fnmatch patterns ("queue:*").
    'ignore' => [],

    // Report tenant database health every hour (needs stancl/tenancy; skipped when it is not installed).
    'report_tenants' => true,
];
