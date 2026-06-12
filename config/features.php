<?php

return [
    /*
     * Enforce per-user branch access checks in SetTenantContext middleware.
     * Set to true once branch assignments are configured for all users.
     */
    'branch_isolation' => (bool) env('BRANCH_ISOLATION_ENABLED', false),
];
