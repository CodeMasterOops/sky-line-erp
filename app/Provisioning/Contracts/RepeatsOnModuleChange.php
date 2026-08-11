<?php

namespace App\Provisioning\Contracts;

/**
 * Marks a provisioning step that spans several modules and must run again
 * whenever any module is switched on.
 *
 * `ModuleAwareStep` covers a step that belongs to exactly one module and is
 * skipped or replayed with it. This covers the other shape: a step like
 * DocumentSequencesStep, which creates a little for each of several modules and
 * would otherwise leave a newly enabled module without its share — the company
 * would run Purchase with no bill numbering, because the step that would have
 * created it belongs to no single module and so was never replayed.
 *
 * Implementations must be idempotent: they run at provisioning and again on
 * every later module activation.
 */
interface RepeatsOnModuleChange {}
