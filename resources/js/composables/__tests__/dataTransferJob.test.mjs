import { describe, it } from 'node:test';
import assert from 'node:assert';
import {
    isActiveStatus,
    isTerminalStatus,
    pollShouldStop,
} from '../dataTransferStatus.js';

describe('useDataTransferJob status helpers', () => {
    it('isTerminalStatus recognises every finished state', () => {
        ['completed', 'completed_with_errors', 'failed', 'cancelled', 'rolled_back'].forEach((s) => {
            assert.strictEqual(isTerminalStatus(s), true);
        });
        assert.strictEqual(isTerminalStatus('processing'), false);
        assert.strictEqual(isTerminalStatus('validated'), false);
    });

    it('isActiveStatus excludes validated and terminal states', () => {
        assert.strictEqual(isActiveStatus('processing'), true);
        assert.strictEqual(isActiveStatus('parsing'), true);
        assert.strictEqual(isActiveStatus('validated'), false);
        assert.strictEqual(isActiveStatus('completed'), false);
    });
});

describe('pollShouldStop', () => {
    it('keeps polling when no job is available yet', () => {
        assert.strictEqual(pollShouldStop(null, () => true), false);
    });

    it('always stops on a terminal status even if the matcher is unmet', () => {
        assert.strictEqual(pollShouldStop({ status: 'failed' }, () => false), true);
        assert.strictEqual(pollShouldStop({ status: 'completed' }, () => false), true);
    });

    it('does not stop on the transient validated status while importing', () => {
        const matches = (job) => isTerminalStatus(job.status);
        assert.strictEqual(pollShouldStop({ status: 'validated' }, matches), false);
        assert.strictEqual(pollShouldStop({ status: 'processing' }, matches), false);
        assert.strictEqual(pollShouldStop({ status: 'completed' }, matches), true);
    });

    it('stops once the caller target matches', () => {
        const matches = (job) => job.status === 'validated';
        assert.strictEqual(pollShouldStop({ status: 'validating' }, matches), false);
        assert.strictEqual(pollShouldStop({ status: 'validated' }, matches), true);
    });
});
