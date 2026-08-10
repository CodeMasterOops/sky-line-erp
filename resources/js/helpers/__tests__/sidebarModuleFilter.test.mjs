import { describe, it } from 'node:test';
import assert from 'node:assert';
import { filterMenuItems, filterSidebarSections } from '../sidebarMenu.js';

/*
 * Phase 3 of docs/saas-modular-platform-and-gym-module-plan.md — the sidebar
 * hides what the company does not run, on top of the existing permission
 * filtering. Both predicates must compose: an item survives only if the user
 * may see it AND the company runs its module.
 */

const allow = () => true;
const enabled = (keys) => (moduleKey) => keys.includes(moduleKey);

describe('sidebar module filtering', () => {
    it('keeps an item whose module is enabled', () => {
        const items = [{ menuValue: 'Contacts', module: 'crm', route: { name: 'admin.crm-contacts' } }];

        assert.strictEqual(filterMenuItems(items, allow, enabled(['crm'])).length, 1);
    });

    it('drops an item whose module is disabled', () => {
        const items = [{ menuValue: 'Contacts', module: 'crm', route: { name: 'admin.crm-contacts' } }];

        assert.deepStrictEqual(filterMenuItems(items, allow, enabled([])), []);
    });

    it('keeps an item with no module, which belongs to core', () => {
        const items = [{ menuValue: 'Dashboard', route: { name: 'admin.dashboard' } }];

        assert.strictEqual(filterMenuItems(items, allow, enabled([])).length, 1);
    });

    it('drops a whole submenu group when its module is off', () => {
        const items = [
            {
                menuValue: 'Sales',
                module: 'sales',
                hasSubRoute: true,
                subMenus: [
                    { menuValue: 'Invoices', route: { name: 'admin.invoice-list' } },
                    { menuValue: 'Receipts', route: { name: 'admin.receipt-list' } },
                ],
            },
        ];

        assert.deepStrictEqual(filterMenuItems(items, allow, enabled(['crm'])), []);
    });

    it('cascades a group module down to leaves that name none', () => {
        const items = [
            {
                menuValue: 'Sales',
                module: 'sales',
                hasSubRoute: true,
                subMenus: [{ menuValue: 'Invoices', route: { name: 'admin.invoice-list' } }],
            },
        ];

        assert.strictEqual(filterMenuItems(items, allow, enabled(['sales'])).length, 1);
    });

    it('lets a leaf name a sub-module its parent does not have', () => {
        // Fixed Assets sits under Accounting but is its own module, exactly as
        // the route group inside api_accounting.php is gated separately.
        const items = [
            {
                menuValue: 'Accounting',
                module: 'accounting',
                hasSubRoute: true,
                subMenus: [
                    { menuValue: 'Chart of Accounts', route: { name: 'admin.chart-of-accounts' } },
                    { menuValue: 'Fixed Assets', module: 'fixed-assets', route: { name: 'admin.fixed-asset-list' } },
                ],
            },
        ];

        const [group] = filterMenuItems(items, allow, enabled(['accounting']));

        assert.strictEqual(group.subMenus.length, 1);
        assert.strictEqual(group.subMenus[0].menuValue, 'Chart of Accounts');
    });

    it('keeps the sub-module leaf when its own module is on', () => {
        const items = [
            {
                menuValue: 'Accounting',
                module: 'accounting',
                hasSubRoute: true,
                subMenus: [
                    { menuValue: 'Fixed Assets', module: 'fixed-assets', route: { name: 'admin.fixed-asset-list' } },
                ],
            },
        ];

        assert.strictEqual(
            filterMenuItems(items, allow, enabled(['accounting', 'fixed-assets']))[0].subMenus.length,
            1,
        );
    });

    it('applies the module and permission checks together', () => {
        const items = [
            { menuValue: 'Invoices', module: 'sales', permission: 'list_invoice', route: { name: 'admin.invoice-list' } },
        ];

        const hasInvoicePermission = (p) => p === 'list_invoice';

        assert.strictEqual(filterMenuItems(items, hasInvoicePermission, enabled(['sales'])).length, 1);
        assert.deepStrictEqual(filterMenuItems(items, () => false, enabled(['sales'])), []);
        assert.deepStrictEqual(filterMenuItems(items, hasInvoicePermission, enabled([])), []);
    });

    it('removes a section left empty by module filtering', () => {
        const sections = [
            {
                title: 'Modules',
                menu: [{ menuValue: 'Contacts', module: 'crm', route: { name: 'admin.crm-contacts' } }],
            },
        ];

        assert.deepStrictEqual(filterSidebarSections(sections, allow, enabled([])), []);
    });

    it('filters nothing when no module check is supplied', () => {
        // Existing callers pass two arguments; they must keep working unchanged.
        const items = [{ menuValue: 'Contacts', module: 'crm', route: { name: 'admin.crm-contacts' } }];

        assert.strictEqual(filterMenuItems(items, allow).length, 1);
        assert.strictEqual(filterSidebarSections([{ title: 'M', menu: items }], allow).length, 1);
    });

    it('handles a three-level menu', () => {
        const items = [
            {
                menuValue: 'Inventory',
                module: 'inventory',
                hasSubRouteTwo: true,
                subMenus: [
                    {
                        menuValue: 'Manufacturing',
                        module: 'manufacturing',
                        customSubmenuTwo: true,
                        subMenusTwo: [{ menuValue: 'BOM', route: { name: 'admin.bom-list' } }],
                    },
                    {
                        menuValue: 'Products',
                        route: { name: 'admin.product-list' },
                    },
                ],
            },
        ];

        const [group] = filterMenuItems(items, allow, enabled(['inventory']));

        assert.strictEqual(group.subMenus.length, 1);
        assert.strictEqual(group.subMenus[0].menuValue, 'Products');
    });
});
