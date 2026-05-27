import { describe, it } from 'node:test';
import assert from 'node:assert';
import { initBootstrapUi } from '../initBootstrap.js';

describe('initBootstrapUi', () => {
    it('does not throw when bootstrap is unavailable', () => {
        const previous = globalThis.window;

        globalThis.window = {};

        assert.doesNotThrow(() => initBootstrapUi());

        globalThis.window = previous;
    });

    it('initializes dropdown and tooltip instances when bootstrap is present', () => {
        const previous = globalThis.window;
        const dropdownInstances = [];
        const tooltipInstances = [];

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
