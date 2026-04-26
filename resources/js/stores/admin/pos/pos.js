import { defineStore } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

export const usePosStore = defineStore('pos', {
    state: () => ({
        // product browser
        categories: [],
        products: [],
        productsLoading: false,
        searchQuery: '',
        activeCategoryId: null,

        // cart items: [{variantId, productId, name, sku, unitId, rate, taxId, taxRate, taxAmount, discountAmount, quantity, stock, image}]
        cart: [],

        // order-level settings
        orderTaxId: null,
        orderTaxRate: 0,
        shippingAmount: 0,
        discountPercent: 0,

        // customer
        selectedCustomer: null,
        customers: [],
        customersLoading: false,

        // warehouses / store selector
        warehouses: [],
        selectedWarehouseId: null,

        // taxes from API
        taxes: [],

        // payment modes from API
        paymentModes: [],

        // held orders
        heldOrders: [],

        // today summary
        todaySummary: { sale_count: 0, sale_total: 0, profit: 0, cogs: 0 },

        // last completed sale (for receipt printing)
        lastSale: null,

        // loading flags
        checkoutLoading: false,
        holdLoading: false,
        initialized: false,
    }),

    getters: {
        subtotal(state) {
            return state.cart.reduce((sum, item) => sum + item.rate * item.quantity, 0);
        },
        discountTotal(state) {
            return state.cart.reduce((sum, item) => sum + (item.discountAmount ?? 0), 0);
        },
        taxTotal(state) {
            return state.cart.reduce((sum, item) => sum + (item.taxAmount ?? 0), 0);
        },
        grandTotal(state) {
            const sub = this.subtotal - this.discountTotal + this.taxTotal + Number(state.shippingAmount);
            return Math.max(sub, 0);
        },
        cartCount(state) {
            return state.cart.reduce((sum, item) => sum + item.quantity, 0);
        },
        filteredProducts(state) {
            let list = state.products;
            if (state.activeCategoryId) {
                list = list.filter(p => p.category_id === state.activeCategoryId);
            }
            if (state.searchQuery.trim()) {
                const q = state.searchQuery.trim().toLowerCase();
                list = list.filter(p =>
                    p.name.toLowerCase().includes(q) ||
                    (p.sku && p.sku.toLowerCase().includes(q))
                );
            }
            return list;
        },
        // returns tax objects safe for a dropdown
        taxOptions(state) {
            return state.taxes.map(t => ({
                label: `${t.name} (${t.rate}%)`,
                value: t.id,
                rate: t.rate,
                tax: t,
            }));
        },
    },

    actions: {
        // ── Bootstrap ────────────────────────────────────────────────
        async init() {
            if (this.initialized) return;
            // fetch warehouses first so selectedWarehouseId is ready for products
            await this.fetchWarehouses();
            await Promise.all([
                this.fetchCategories(),
                this.fetchProducts(),
                this.fetchCustomers(),
                this.fetchTaxes(),
                this.fetchPaymentModes(),
                this.fetchTodaySummary(),
            ]);
            this.initialized = true;
        },

        // ── Warehouses ──────────────────────────────────────────────
        async fetchWarehouses() {
            try {
                const res = await apiAdmin('pos/warehouses');
                this.warehouses = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },

        setWarehouse(warehouseId) {
            this.selectedWarehouseId = warehouseId;
            this.fetchProducts();
        },

        // ── Categories ──────────────────────────────────────────────
        async fetchCategories() {
            try {
                const res = await apiAdmin('product-category');
                this.categories = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },

        // ── Products ────────────────────────────────────────────────
        async fetchProducts() {
            this.productsLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.selectedWarehouseId) params.set('warehouse_id', this.selectedWarehouseId);
                if (this.searchQuery.trim()) params.set('search', this.searchQuery.trim());
                const res = await apiAdmin(`pos/products?${params.toString()}`);
                this.products = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
                this.products = [];
            } finally {
                this.productsLoading = false;
            }
        },

        setSearchQuery(q) {
            this.searchQuery = q;
        },

        setActiveCategory(categoryId) {
            this.activeCategoryId = categoryId;
        },

        // ── Taxes ────────────────────────────────────────────────────
        async fetchTaxes() {
            try {
                const res = await apiAdmin('tax?for=line_item');
                this.taxes = res.data.data ?? [];
            } catch (err) {
                showErrors(err);
            }
        },

        // ── Payment Modes ────────────────────────────────────────────
        async fetchPaymentModes() {
            try {
                const res = await apiAdmin('payment-mode');
                this.paymentModes = (res.data.data ?? []).filter(m => m.is_active);
            } catch (err) {
                showErrors(err);
            }
        },

        // ── Customers ───────────────────────────────────────────────
        async fetchCustomers(search = '') {
            this.customersLoading = true;
            try {
                const params = new URLSearchParams();
                if (search) params.set('search', search);
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
        },

        // ── Cart ────────────────────────────────────────────────────
        addToCart(product) {
            const existing = this.cart.find(i => i.variantId === product.id);
            if (existing) {
                existing.quantity += 1;
                this.recalcItemTax(existing);
            } else {
                const item = {
                    variantId: product.id,
                    productId: product.product_id,
                    name: product.name,
                    sku: product.sku,
                    unitId: product.unit_id ?? null,
                    rate: product.sales_price,
                    taxId: this.orderTaxId,
                    taxRate: this.orderTaxRate,
                    taxAmount: 0,
                    discountAmount: 0,
                    quantity: 1,
                    stock: product.stock,
                    image: product.image,
                };
                this.recalcItemTax(item);
                this.cart.push(item);
            }
        },

        updateCartItem(variantId, fields) {
            const item = this.cart.find(i => i.variantId === variantId);
            if (!item) return;
            Object.assign(item, fields);
            this.recalcItemTax(item);
        },

        setCartItemQty(variantId, qty) {
            const item = this.cart.find(i => i.variantId === variantId);
            if (!item) return;
            item.quantity = Math.max(1, qty);
            this.recalcItemTax(item);
        },

        removeFromCart(variantId) {
            this.cart = this.cart.filter(i => i.variantId !== variantId);
        },

        clearCart() {
            this.cart = [];
            this.selectedCustomer = null;
            this.orderTaxId = null;
            this.orderTaxRate = 0;
            this.shippingAmount = 0;
            this.discountPercent = 0;
        },

        recalcItemTax(item) {
            const lineSubtotal = item.rate * item.quantity;
            const taxable = Math.max(lineSubtotal - (item.discountAmount ?? 0), 0);
            item.taxAmount = parseFloat((taxable * (item.taxRate / 100)).toFixed(4));
        },

        applyOrderTax(taxOption) {
            if (!taxOption) {
                this.orderTaxId = null;
                this.orderTaxRate = 0;
            } else {
                this.orderTaxId = taxOption.value;
                this.orderTaxRate = taxOption.rate;
            }
            // re-apply to all cart items
            this.cart.forEach(item => {
                item.taxId = this.orderTaxId;
                item.taxRate = this.orderTaxRate;
                this.recalcItemTax(item);
            });
        },

        applyDiscountPercent(percent) {
            this.discountPercent = percent;
            const sub = this.cart.reduce((s, i) => s + i.rate * i.quantity, 0);
            const totalDiscount = sub * (percent / 100);
            this.cart.forEach(item => {
                const ratio = sub > 0 ? (item.rate * item.quantity) / sub : 0;
                item.discountAmount = parseFloat((totalDiscount * ratio).toFixed(4));
            });
        },

        // ── Checkout ────────────────────────────────────────────────
        async checkout(paymentMethod) {
            this.checkoutLoading = true;
            try {
                const items = this.cart.map(item => ({
                    product_variant_id: item.variantId,
                    unit_id: item.unitId,
                    quantity: item.quantity,
                    rate: item.rate,
                    tax_id: item.taxId,
                    tax_amount: item.taxAmount,
                    discount_amount: item.discountAmount,
                }));

                const res = await apiAdmin('pos/checkout', 'post', {
                    warehouse_id: this.selectedWarehouseId,
                    party_id: this.selectedCustomer?.id ?? null,
                    payment_method: paymentMethod,
                    shipping_amount: this.shippingAmount,
                    items,
                    remarks: 'POS Sale',
                });

                this.lastSale = res.data.data;
                this.fetchTodaySummary();
                return res.data;
            } catch (err) {
                throw err;
            } finally {
                this.checkoutLoading = false;
            }
        },

        // ── Hold Orders ─────────────────────────────────────────────
        async holdCurrentOrder(label) {
            if (!this.cart.length) return;
            this.holdLoading = true;
            try {
                const orderData = {
                    items: this.cart.map(i => ({ ...i })),
                    customer: this.selectedCustomer,
                    discountPercent: this.discountPercent,
                    shippingAmount: this.shippingAmount,
                    orderTaxId: this.orderTaxId,
                    orderTaxRate: this.orderTaxRate,
                    warehouseId: this.selectedWarehouseId,
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
                this.heldOrders = this.heldOrders.filter(o => o.id !== id);
            } catch (err) {
                showErrors(err);
            }
        },

        restoreHeldOrder(order) {
            const data = order.order_data;
            this.cart = data.items ?? [];
            this.selectedCustomer = data.customer ?? null;
            this.discountPercent = data.discountPercent ?? 0;
            this.shippingAmount = data.shippingAmount ?? 0;
            this.orderTaxId = data.orderTaxId ?? null;
            this.orderTaxRate = data.orderTaxRate ?? 0;
            if (data.warehouseId) this.selectedWarehouseId = data.warehouseId;
        },

        // ── Today Summary ───────────────────────────────────────────
        async fetchTodaySummary() {
            try {
                const res = await apiAdmin('pos/today-summary');
                this.todaySummary = res.data.data;
            } catch (err) {
                showErrors(err);
            }
        },
    },
});
