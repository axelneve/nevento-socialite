<?php

return [
    /*
     * Per-app shared secret the IDP presents (Bearer) when calling this app's
     * provisioning endpoints. Must match the value stored on the app record in
     * the IDP (superadmin wizard credentials block). Fail-closed: when empty,
     * all provisioning calls are rejected.
     */
    'secret' => env('APP_PROVISIONING_SECRET'),

    /*
     * Whether to register the internal provisioning routes. Turn off for apps
     * that are not tenant-provisioned.
     */
    'enabled' => (bool) env('NEVENTO_PROVISIONING_ENABLED', true),
];
