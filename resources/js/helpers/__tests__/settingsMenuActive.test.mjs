import { describe, it } from 'node:test';
import assert from 'node:assert';
import { isSettingsRouteActive, normalizeSettingsPath } from '../settingsMenuActive.js';

describe('normalizeSettingsPath', () => {
    it('strips trailing slash, query string and hash', () => {
        assert.strictEqual(normalizeSettingsPath('/admin/settings/branches/'), '/admin/settings/branches');
        assert.strictEqual(normalizeSettingsPath('/admin/settings/branches?page=2'), '/admin/settings/branches');
        assert.strictEqual(normalizeSettingsPath('/admin/settings/branches#top'), '/admin/settings/branches');
        assert.strictEqual(normalizeSettingsPath(''), '');
        assert.strictEqual(normalizeSettingsPath(undefined), '');
    });
});

describe('isSettingsRouteActive', () => {
    it('matches the exact route', () => {
        assert.strictEqual(
            isSettingsRouteActive('/admin/settings/branches', '/admin/settings/branches'),
            true,
        );
    });

    it('matches child pages nested beneath the route', () => {
        assert.strictEqual(
            isSettingsRouteActive('/admin/settings/branches/1/users', '/admin/settings/branches'),
            true,
        );
    });

    it('does not match sibling routes that merely share a prefix', () => {
        assert.strictEqual(
            isSettingsRouteActive('/admin/settings/branches-archive', '/admin/settings/branches'),
            false,
        );
    });

    it('does not match unrelated routes', () => {
        assert.strictEqual(
            isSettingsRouteActive('/admin/settings/security-settings', '/admin/settings/branches'),
            false,
        );
    });

    it('ignores query strings and trailing slashes', () => {
        assert.strictEqual(
            isSettingsRouteActive('/admin/settings/branches/1/users?tab=roles', '/admin/settings/branches'),
            true,
        );
    });

    it('returns false for an empty route', () => {
        assert.strictEqual(isSettingsRouteActive('/admin/settings/branches', ''), false);
    });
});
