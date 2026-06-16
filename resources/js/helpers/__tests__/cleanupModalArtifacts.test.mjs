import { describe, it } from 'node:test';
import assert from 'node:assert';
import { cleanupModalArtifacts } from '../cleanupModalArtifacts.js';

function makeBody() {
    const classes = new Set();
    const removedProps = [];

    return {
        classList: {
            remove: (name) => classes.delete(name),
            add: (name) => classes.add(name),
            has: (name) => classes.has(name),
        },
        style: {
            removeProperty: (name) => removedProps.push(name),
        },
        removedProps,
    };
}

function makeDoc({ backdrops = 0, body = makeBody() } = {}) {
    let removed = 0;
    const backdropElements = Array.from({ length: backdrops }, () => ({
        remove: () => {
            removed += 1;
        },
    }));

    return {
        body,
        querySelectorAll: (selector) => {
            if (selector === '.modal-backdrop') {
                return backdropElements;
            }
            return [];
        },
        get removedBackdrops() {
            return removed;
        },
    };
}

describe('cleanupModalArtifacts', () => {
    it('does not throw when no document is available', () => {
        assert.doesNotThrow(() => cleanupModalArtifacts(null));
    });

    it('removes every orphaned modal-backdrop element', () => {
        const doc = makeDoc({ backdrops: 2 });

        cleanupModalArtifacts(doc);

        assert.strictEqual(doc.removedBackdrops, 2);
    });

    it('unlocks the body by clearing modal-open and inline styles', () => {
        const body = makeBody();
        body.classList.add('modal-open');
        const doc = makeDoc({ backdrops: 1, body });

        cleanupModalArtifacts(doc);

        assert.strictEqual(body.classList.has('modal-open'), false);
        assert.deepStrictEqual(body.removedProps, ['overflow', 'padding-right']);
    });

    it('is a no-op when there are no artifacts', () => {
        const doc = makeDoc({ backdrops: 0 });

        cleanupModalArtifacts(doc);

        assert.strictEqual(doc.removedBackdrops, 0);
    });
});
