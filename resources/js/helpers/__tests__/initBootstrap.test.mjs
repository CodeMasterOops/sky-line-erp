import { describe, it, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert';
import { initBootstrapUi } from '../initBootstrap.js';

describe('initBootstrapUi', () => {
    let originalBootstrap;

    beforeEach(() => {
        originalBootstrap = globalThis.window?.bootstrap;
    });

    afterEach(() => {
        if (globalThis.window) {
            globalThis.window.bootstrap = originalBootstrap;
        }
    });

    it('does not throw when window.bootstrap is unavailable', () => {
        const previous = globalThis.window;
        globalThis.window = {};
        assert.doesNotThrow(() => initBootstrapUi());
        globalThis.window = previous;
    });

    it('initializes dropdown and tooltip instances when bootstrap is present', () => {
        const dropdownInstances = [];
        const tooltipInstances = [];

        const previous = globalThis.window;

        globalThis.window = {
            bootstrap: {
                Dropdown: {
                    getOrCreateInstance: (element) => {
                        dropdownInstances.push(element);
                        return { dispose: () => {} };
                    },
                },
                Tooltip: {
                    getOrCreateInstance: (element) => {
                        tooltipInstances.push(element);
                        return { dispose: () => {} };
                    },
                },
            },
        };

        const container = {
            querySelectorAll: (selector) => {
                if (selector.includes('dropdown')) {
                    return [{ id: 'profile-toggle' }];
                }
                if (selector.includes('tooltip')) {
                    return [{ id: 'refresh-tooltip' }];
                }
                return [];
            },
        };

        initBootstrapUi(container);

        assert.strictEqual(dropdownInstances.length, 1);
        assert.strictEqual(tooltipInstances.length, 1);

        globalThis.window = previous;
    });
});
