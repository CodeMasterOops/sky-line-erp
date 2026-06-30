import {describe, it} from 'node:test';
import assert from 'node:assert';
import {ADMIN_BRANCH_SELECT_PATH} from '../adminPaths.js';

describe('adminPaths', () => {
    it('ADMIN_BRANCH_SELECT_PATH matches the Vue router path', () => {
        assert.strictEqual(ADMIN_BRANCH_SELECT_PATH, '/admin/select-branch');
    });
});
