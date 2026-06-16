import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {
    buildOrderAllocations,
    lineDiscountMoneyFromItem,
    lineNetFromItem,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';
import {fetchVariantWarehouses as fetchVariantWarehousesApi} from '@/composables/useVariantWarehousePicker.js';

function itemForCalc(item) {
    return {
        quantity: item.quantity,
        rate: item.rate,
        line_discount_type: item.line_discount_type || 'fixed',
        line_discount_value: item.line_discount_value ?? '0',
    };
}

export function cartLineKey(variantId, warehouseId) {
    return `${variantId}-${warehouseId ?? 'service'}`;
}

export const usePosStore = defineStore('pos', {
    state: () => ({
        cart: [],

        order_discount_type: 'fixed',
        order_discount_value: '0',

        selectedCustomer: null,
        customers: [],
        customersLoading: false,

        warehouses: [],
        categories: [],
        taxes: [],
        paymentModes: [],
        heldOrders: [],
        todaySummary: { sale_count: 0, sale_total: 0, profit: 0, cogs: 0 },
        lastSale: null,

        tillSession: null,
        tillSessionLoading: false,

        gridProducts: [],
        gridLoading: false,

        checkoutLoading: false,
        holdLoading: false,
        initialized: false,
    }),

    getters: {
        subtotal(state) {
            return state.cart.reduce((sum, item) => sum + item.rate * item.quantity, 0);
        },

        lineDiscountTotal(state) {
            return state.cart.reduce(
                (sum, item) => sum + lineDiscountMoneyFromItem(itemForCalc(item)),
                0,
            );
        },

        orderDiscountTotal(state) {
            const nets = state.cart.map((item) => lineNetFromItem(itemForCalc(item)));
            const sumLineNet = nets.reduce((a, b) => a + b, 0);

            return orderDiscountMoney(
                sumLineNet,
                state.order_discount_type || 'fixed',
                state.order_discount_value,
            );
        },

        discountTotal() {
            return this.lineDiscountTotal + this.orderDiscountTotal;
        },

        taxTotal(state) {
            return state.cart.reduce((sum, item) => sum + (item.taxAmount ?? 0), 0);
        },

        hasVat(state) {
            return state.cart.some((item) => item.taxId !== null);
        },

        grandTotal() {
            const nets = this.cart.map((item) => lineNetFromItem(itemForCalc(item)));
            const sumLineNet = nets.reduce((a, b) => a + b, 0);
            const orderDisc = this.orderDiscountTotal;

            return Math.max(sumLineNet - orderDisc + this.taxTotal, 0);
        },

        cartCount(state) {
            return state.cart.reduce((sum, item) => sum + item.quantity, 0);
        },

        taxOptions(state) {
            return state.taxes.map((t) => ({
                label: `${t.name} (${t.rate}%)`,
                value: t.id,
                rate: t.rate,
                tax: t,
            }));
        },

        /** Unique warehouses used in the current cart with line counts. */
        cartWarehouseSummary(state) {
            const map = new Map();
            for (const item of state.cart) {
                if (!item.warehouseId) {
                    continue;
                }
                const existing = map.get(item.warehouseId);
                if (existing) {
                    existing.lineCount += 1;
                } else {
                    map.set(item.warehouseId, {
                        warehouseId: item.warehouseId,
                        warehouseName: item.warehouseName ?? '',
                        lineCount: 1,
                    });
                }
            }

            return [...map.values()];
        },

        usesMultipleWarehouses() {
            return this.cartWarehouseSummary.length > 1;
        },

        canCheckout(state) {
            return state.cart.length > 0
                && state.cart.every((item) => item.warehouseId != null);
        },
    },

    actions: {
        async init() {
            if (this.initialized) {
                return;
            }

            await this.fetchWarehouses();
            await Promise.all([
                this.fetchCustomers(),
                this.fetchTaxes(),
                this.fetchPaymentModes(),
                this.fetchTodaySummary(),
                this.fetchTillSession(),
                this.fetchCategories(),
            ]);
            this.initialized = true;
        },

        findCartLine(lineKey) {
            return this.cart.find((i) => i.lineKey === lineKey);
        },

        getTaxRate(taxId) {
            if (!taxId) {
                return 0;
            }
            const tax = this.taxes.find((t) => t.id === taxId);

            return tax ? Number(tax.rate) : 0;
        },

        calcLineTax(item, index) {
            const nets = this.cart.map((i) => lineNetFromItem(itemForCalc(i)));
            const sumLineNet = nets.reduce((a, b) => a + b, 0);
            const orderDisc = orderDiscountMoney(
                sumLineNet,
                this.order_discount_type || 'fixed',
                this.order_discount_value,
            );
            const allocs = buildOrderAllocations(nets, orderDisc);
            const lineNet = nets[index] ?? 0;
            const alloc = allocs[index] || 0;
            const taxable = Math.max(0, lineNet - alloc);
            const taxRate = this.getTaxRate(item.taxId);

            return parseFloat((taxable * (taxRate / 100)).toFixed(4));
        },

        syncAllTaxes() {
            this.cart.forEach((item, index) => {
                item.discountAmount = lineDiscountMoneyFromItem(itemForCalc(item));
                item.taxAmount = this.calcLineTax(item, index);
            });
        },

        async fetchWarehouses() {
            try {
                const res = await apiAdmin('pos/warehouses');
                this.warehouses = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },

        async fetchCategories() {
            try {
                const res = await apiAdmin('pos/categories');
                this.categories = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },

        async fetchGridProducts(categoryId = null) {
            this.gridLoading = true;
            try {
                const params = new URLSearchParams({ limit: '32' });
                if (categoryId) { params.set('category_id', String(categoryId)); }
                const res = await apiAdmin(`pos/products?${params.toString()}`);
                this.gridProducts = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
            } finally {
                this.gridLoading = false;
            }
        },

        async fetchVariantWarehouses(variantId) {
            return fetchVariantWarehousesApi(variantId);
        },

        async fetchCustomers(search = '') {
            this.customersLoading = true;
            try {
                const params = new URLSearchParams();
                if (search) {
                    params.set('search', search);
                }
                const res = await apiAdmin(`pos/customers?${params.toString()}`);
                this.customers = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
            } finally {
                this.customersLoading = false;
            }
        },

        setCustomer(customer) {
            this.selectedCustomer = customer;

            if (!customer) {
                this.order_discount_type = 'fixed';
                this.order_discount_value = '0';
                this.syncAllTaxes();

                return;
            }

            this.applyCustomerDefaultDiscount(customer);
        },

        applyCustomerDefaultDiscount(customer) {
            if (!customer) {
                return;
            }

            const value = parseFloat(customer.discount_value);
            if (!Number.isFinite(value) || value <= 0) {
                return;
            }

            const discountType = customer.discount_type === 'percent' ? 'percent' : 'fixed';
            this.order_discount_type = discountType;
            this.order_discount_value = String(value);
            this.syncAllTaxes();
        },

        setOrderDiscount(type, value) {
            this.order_discount_type = type || 'fixed';
            this.order_discount_value = value ?? '0';
            this.syncAllTaxes();
        },

        updateLineDiscount(lineKey, type, value) {
            const item = this.findCartLine(lineKey);
            if (!item) {
                return;
            }
            item.line_discount_type = type || 'fixed';
            item.line_discount_value = value ?? '0';
            this.syncAllTaxes();
        },

        async addVariantToCart(variant) {
            if (variant.is_service) {
                return { success: false, error: 'service_not_allowed' };
            }

            let warehouseOptions;

            try {
                warehouseOptions = await this.fetchVariantWarehouses(variant.id);
            } catch (err) {
                showErrors(err);

                return { success: false, error: 'fetch_failed' };
            }

            if (warehouseOptions.length === 0) {
                return { success: false, error: 'out_of_stock' };
            }

            if (warehouseOptions.length === 1) {
                const wh = warehouseOptions[0];

                return this.commitAddToCart(
                    variant,
                    wh.warehouse_id,
                    wh.warehouse_name,
                    wh.quantity,
                );
            }

            return {
                needsWarehousePick: true,
                variant,
                options: warehouseOptions,
            };
        },

        completeAddWithWarehouse(variant, warehouseId, warehouseName, stockQty) {
            return this.commitAddToCart(variant, warehouseId, warehouseName, stockQty);
        },

        commitAddToCart(variant, warehouseId, warehouseName, stockQty) {
            const lineKey = cartLineKey(variant.id, warehouseId);
            const existing = this.findCartLine(lineKey);

            if (existing) {
                if (existing.stock !== null && existing.quantity + 1 > existing.stock) {
                    return { success: false, error: 'insufficient_stock', stock: stockQty };
                }
                existing.quantity += 1;
                this.syncAllTaxes();

                return { success: true };
            }

            const item = {
                lineKey,
                variantId: variant.id,
                warehouseId,
                warehouseName: warehouseName ?? '',
                productId: variant.product_id ?? null,
                name: variant.name,
                sku: variant.sku ?? '',
                unitId: variant.unit_id ?? null,
                rate: Number(variant.sales_price ?? variant.purchase_price ?? 0),
                taxId: null,
                taxRate: 0,
                taxAmount: 0,
                line_discount_type: 'fixed',
                line_discount_value: '0',
                discountAmount: 0,
                quantity: 1,
                isService: variant.is_service ?? false,
                stock: stockQty,
                image: variant.image ?? null,
            };

            this.cart.push(item);
            this.syncAllTaxes();

            if (this.selectedCustomer) {
                this.applyCustomerDefaultDiscount(this.selectedCustomer);
            }

            return { success: true };
        },

        updateCartItem(lineKey, fields) {
            const item = this.findCartLine(lineKey);
            if (!item) {
                return;
            }
            Object.assign(item, fields);
            this.syncAllTaxes();
        },

        setCartItemQty(lineKey, qty) {
            const item = this.findCartLine(lineKey);
            if (!item) {
                return;
            }

            const nextQty = Math.max(1, qty);
            if (item.stock !== null && nextQty > item.stock) {
                item.quantity = item.stock;
            } else {
                item.quantity = nextQty;
            }
            this.syncAllTaxes();
        },

        removeFromCart(lineKey) {
            this.cart = this.cart.filter((i) => i.lineKey !== lineKey);
            if (this.cart.length) {
                this.syncAllTaxes();
            }
        },

        clearCart() {
            this.cart = [];
            this.selectedCustomer = null;
            this.order_discount_type = 'fixed';
            this.order_discount_value = '0';
        },

        onLineTaxChange(lineKey, taxId) {
            const parsed = taxId ? parseInt(taxId, 10) : null;
            const tax = parsed ? this.taxes.find((t) => t.id === parsed) : null;
            this.updateCartItem(lineKey, {
                taxId: parsed,
                taxRate: tax ? Number(tax.rate) : 0,
            });
        },

        toggleVat() {
            const addVat = !this.hasVat;
            // Find the first 13% VAT tax (standard Nepal VAT)
            const vatTax = this.taxes.find((t) => Number(t.rate) === 13) ?? this.taxes[0] ?? null;
            this.cart.forEach((item) => {
                if (addVat && vatTax) {
                    item.taxId = vatTax.id;
                    item.taxRate = Number(vatTax.rate);
                } else {
                    item.taxId = null;
                    item.taxRate = 0;
                    item.taxAmount = 0;
                }
            });
            this.syncAllTaxes();
        },

        async fetchTaxes() {
            try {
                const res = await apiAdmin('tax?for=line_item');
                this.taxes = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
            }
        },

        async fetchPaymentModes() {
            try {
                const res = await apiAdmin('payment-mode');
                this.paymentModes = (res.data.data ?? []).filter((m) => m.is_active);
            } catch (err) {
                showErrors(err);
            }
        },

        buildCheckoutItems() {
            return this.cart.map((item) => ({
                product_variant_id: item.variantId,
                warehouse_id: item.warehouseId ?? null,
                unit_id: item.unitId,
                quantity: item.quantity,
                rate: item.rate,
                tax_id: item.taxId,
                tax_amount: item.taxAmount,
                discount_amount: lineDiscountMoneyFromItem(itemForCalc(item)),
                line_discount_type: item.line_discount_type || 'fixed',
                line_discount_value: parseFloat(item.line_discount_value) || 0,
            }));
        },

        async checkout(paymentMethod, splitPayments = null) {
            this.checkoutLoading = true;
            try {
                const body = {
                    party_id: this.selectedCustomer?.id ?? null,
                    payment_method: paymentMethod,
                    order_discount_type: this.order_discount_type || 'fixed',
                    order_discount_value: parseFloat(this.order_discount_value) || 0,
                    items: this.buildCheckoutItems(),
                    remarks: 'POS Sale',
                };
                if (splitPayments?.length) { body.payments = splitPayments; }
                const res = await apiAdmin('pos/checkout', 'post', body);

                this.lastSale = res.data.data;
                this.fetchTodaySummary();

                return res.data;
            } catch (err) {
                throw err;
            } finally {
                this.checkoutLoading = false;
            }
        },

        async holdCurrentOrder(label) {
            if (!this.cart.length) {
                return;
            }
            this.holdLoading = true;
            try {
                const orderData = {
                    items: this.cart.map((i) => ({ ...i })),
                    customer: this.selectedCustomer,
                    order_discount_type: this.order_discount_type,
                    order_discount_value: this.order_discount_value,
                };
                const res = await apiAdmin('pos/hold', 'post', {
                    party_id: this.selectedCustomer?.id ?? null,
                    label: label || null,
                    order_data: orderData,
                });
                this.heldOrders.unshift(res.data.data);
                this.clearCart();

                return res.data;
            } catch (err) {
                throw err;
            } finally {
                this.holdLoading = false;
            }
        },

        async fetchHeldOrders() {
            try {
                const res = await apiAdmin('pos/held-orders');
                this.heldOrders = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
            }
        },

        async deleteHeldOrder(id) {
            try {
                await apiAdmin(`pos/held-orders/${id}`, 'delete');
                this.heldOrders = this.heldOrders.filter((o) => o.id !== id);
            } catch (err) {
                showErrors(err);
            }
        },

        restoreHeldOrder(order) {
            const data = order.order_data;
            const fallbackWarehouseId = data.warehouseId ?? null;
            const fallbackWarehouseName = data.warehouseName ?? '';

            this.cart = (data.items ?? []).map((item) => {
                const warehouseId = item.warehouseId ?? fallbackWarehouseId;
                const warehouseName = item.warehouseName ?? fallbackWarehouseName;
                const variantId = item.variantId;

                return {
                    ...item,
                    warehouseId,
                    warehouseName,
                    lineKey: item.lineKey ?? cartLineKey(variantId, warehouseId),
                };
            });
            this.selectedCustomer = data.customer ?? null;
            this.order_discount_type = data.order_discount_type ?? 'fixed';
            this.order_discount_value = data.order_discount_value ?? '0';
            this.syncAllTaxes();
        },

        async fetchTodaySummary() {
            try {
                const res = await apiAdmin('pos/today-summary');
                this.todaySummary = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },

        async fetchTillSession() {
            this.tillSessionLoading = true;
            try {
                const res = await apiAdmin('pos/till/current');
                this.tillSession = res.data.data;
            } catch (err) {
                showErrors(err);
            } finally {
                this.tillSessionLoading = false;
            }
        },

        async openTill(openingCash) {
            const res = await apiAdmin('pos/till/open', 'post', { opening_cash: openingCash });
            this.tillSession = res.data.data;
            return res.data;
        },

        async closeTill(closingCash, notes = '') {
            const res = await apiAdmin('pos/till/close', 'post', { closing_cash: closingCash, notes });
            this.tillSession = null;
            return res.data;
        },

        async addCashMovement(type, amount, reason = '') {
            const res = await apiAdmin('pos/till/cash-movement', 'post', { type, amount, reason });
            return res.data;
        },

        async fetchTillSummary() {
            const res = await apiAdmin('pos/till/summary');
            return res.data.data;
        },

        async processReturn(invoiceId, items, reason = '') {
            const res = await apiAdmin('pos/return', 'post', { invoice_id: invoiceId, items, reason });
            return res.data.data;
        },
    },
});
