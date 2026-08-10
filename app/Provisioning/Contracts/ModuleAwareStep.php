<?php

namespace App\Provisioning\Contracts;

/**
 * Marks a provisioning step as belonging to one module. The pipeline skips the
 * step for companies that do not run that module, and replays it — idempotently
 * — when the module is switched on later.
 *
 * Deliberately a separate interface rather than a method on ProvisioningStep:
 * the twenty-odd existing steps are core and need no change.
 */
interface ModuleAwareStep
{
    /** The module key from config/modules.php that owns this step. */
    public function module(): string;
}
