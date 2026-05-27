import {describe, it} from 'node:test';
import assert from 'node:assert';
import {formattedRequest} from '../apiRequest.js';

function createMockClient() {
    return {
        get: (url, config) => ({method: 'get', url, config}),
        post: (url, body, config) => ({method: 'post', url, body, config}),
        put: (url, body, config) => ({method: 'put', url, body, config}),
        delete: (url, config) => ({method: 'delete', url, config}),
    };
}

describe('formattedRequest', () => {
    it('performs GET without params', () => {
        const client = createMockClient();
        const result = formattedRequest(client, 'get', 'products');

        assert.strictEqual(result.method, 'get');
        assert.strictEqual(result.url, 'products');
        assert.deepStrictEqual(result.config, {});
    });

    it('performs GET with query params', () => {
        const client = createMockClient();
        const result = formattedRequest(client, 'get', 'products', {page: 2});

        assert.strictEqual(result.method, 'get');
        assert.deepStrictEqual(result.config.params, {page: 2});
    });

    it('performs POST with body', () => {
        const client = createMockClient();
        const body = {name: 'Widget'};
        const result = formattedRequest(client, 'post', 'products', body);

        assert.strictEqual(result.method, 'post');
        assert.deepStrictEqual(result.body, body);
    });

    it('performs PUT and DELETE', () => {
        const client = createMockClient();

        const putResult = formattedRequest(client, 'put', 'products/1', {name: 'A'});
        const deleteResult = formattedRequest(client, 'delete', 'products/1');

        assert.strictEqual(putResult.method, 'put');
        assert.strictEqual(deleteResult.method, 'delete');
    });

    it('merges per-request axios config', () => {
        const client = createMockClient();
        const result = formattedRequest(client, 'get', 'export', null, {responseType: 'blob'});

        assert.strictEqual(result.config.responseType, 'blob');
    });
});
