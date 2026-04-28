<template>
  <pos-header @warehouse-changed="onWarehouseChanged"></pos-header>

  <div class="page-wrapper pos-pg-wrapper ms-0">
    <div class="content pos-design p-0">
      <div class="row g-0 pos-wrapper" style="min-height: calc(100vh - 60px);">

        <!-- ── Left: Product Search + Line Items ───────────────── -->
        <div class="col-lg-8 col-xl-9 d-flex flex-column" style="overflow-y:auto; max-height:calc(100vh - 60px);">
          <div class="p-3 flex-grow-1 d-flex flex-column">

            <!-- Top action bar -->
            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
              <a class="btn btn-teal btn-sm"
                 data-bs-toggle="modal" data-bs-target="#orders"
                 @click="posStore.fetchHeldOrders()">
                <i class="ti ti-shopping-cart me-1"></i>Held Orders
                <span v-if="posStore.heldOrders.length" class="badge bg-white text-teal ms-1">{{ posStore.heldOrders.length }}</span>
              </a>
              <a class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#recents">
                <i class="ti ti-refresh-dot me-1"></i>Transactions
              </a>
              <a class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#reset"
                 :class="{ disabled: !posStore.cart.length }">
                <i class="ti ti-reload me-1"></i>Reset
              </a>
            </div>

            <!-- Product Search -->
            <div class="mb-3 position-relative">
              <ProductVariantSearchInput
                ref="productSearch"
                placeholder="Search product by name, SKU or scan barcode — Enter to add"
                @select="onVariantSelected"
              />
            </div>

            <!-- Line Items Table -->
            <div class="table-responsive flex-grow-1">
              <table class="table table-bordered table-sm mb-0 align-middle">
                <thead class="table-light text-nowrap">
                  <tr>
                    <th style="width:38px">#</th>
                    <th>Product</th>
                    <th style="width:80px">Qty</th>
                    <th style="width:105px">Rate</th>
                    <th style="width:105px">Discount</th>
                    <th style="width:150px">Tax</th>
                    <th style="width:85px" class="text-end">Tax Amt</th>
                    <th style="width:105px" class="text-end">Line Total</th>
                    <th style="width:42px"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!posStore.cart.length">
                    <td colspan="9" class="text-center text-muted py-5">
                      <i class="ti ti-barcode fs-40 d-block mb-2 opacity-40"></i>
                      Search or scan a product above to add it to the order
                    </td>
                  </tr>
                  <tr v-for="(item, index) in posStore.cart" :key="item.variantId">
                    <td class="text-muted small">{{ index + 1 }}</td>
                    <td>
                      <div class="fw-medium lh-sm">{{ item.name }}</div>
                      <div v-if="item.sku" class="small text-muted">{{ item.sku }}</div>
                    </td>
                    <td>
                      <input
                        type="number"
                        class="form-control form-control-sm text-center"
                        :value="item.quantity"
                        min="1"
                        style="width:68px"
                        @change="e => posStore.setCartItemQty(item.variantId, parseInt(e.target.value) || 1)"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        class="form-control form-control-sm text-end"
                        :value="item.rate"
                        min="0"
                        step="0.01"
                        style="width:92px"
                        @change="e => posStore.updateCartItem(item.variantId, { rate: parseFloat(e.target.value) || 0 })"
                      />
                    </td>
                    <td>
                      <input
                        type="number"
                        class="form-control form-control-sm text-end"
                        :value="item.discountAmount"
                        min="0"
                        step="0.01"
                        style="width:92px"
                        @change="e => posStore.updateCartItem(item.variantId, { discountAmount: parseFloat(e.target.value) || 0 })"
                      />
                    </td>
                    <td>
                      <select
                        class="form-select form-select-sm"
                        :value="item.taxId ?? ''"
                        @change="e => onLineTaxChange(item.variantId, e.target.value)"
                      >
                        <option value="">— None —</option>
                        <option v-for="t in posStore.taxes" :key="t.id" :value="t.id">
                          {{ t.name }} ({{ t.rate }}%)
                        </option>
                      </select>
                    </td>
                    <td class="text-end small">{{ fmt(item.taxAmount) }}</td>
                    <td class="text-end fw-medium">{{ fmt(calcLineTotal(item)) }}</td>
                    <td class="text-center">
                      <button
                        type="button"
                        class="btn btn-sm btn-outline-danger p-1 lh-1"
                        @click="posStore.removeFromCart(item.variantId)"
                      ><i class="ti ti-trash" style="font-size:13px"></i></button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="posStore.cart.length" class="border-top-2">
                  <tr class="table-light">
                    <td colspan="7" class="text-end text-muted">Subtotal</td>
                    <td class="text-end fw-medium">{{ fmt(posStore.subtotal) }}</td>
                    <td></td>
                  </tr>
                  <tr v-if="posStore.discountTotal > 0" class="text-danger">
                    <td colspan="7" class="text-end">Discount</td>
                    <td class="text-end">-{{ fmt(posStore.discountTotal) }}</td>
                    <td></td>
                  </tr>
                  <tr v-if="posStore.taxTotal > 0">
                    <td colspan="7" class="text-end text-muted">Tax</td>
                    <td class="text-end">{{ fmt(posStore.taxTotal) }}</td>
                    <td></td>
                  </tr>
                  <tr v-if="Number(posStore.shippingAmount) > 0">
                    <td colspan="7" class="text-end text-muted">Shipping</td>
                    <td class="text-end">{{ fmt(posStore.shippingAmount) }}</td>
                    <td></td>
                  </tr>
                  <tr class="table-primary">
                    <td colspan="7" class="text-end fw-bold">Grand Total</td>
                    <td class="text-end fw-bold fs-15">{{ fmt(posStore.grandTotal) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>

          </div>
        </div>
        <!-- /Left -->

        <!-- ── Right: Customer + Summary + Payment ─────────────── -->
        <div class="col-lg-4 col-xl-3 border-start bg-white d-flex flex-column"
             style="max-height:calc(100vh - 60px); overflow-y:auto; position:sticky; top:60px;">
          <div class="p-3 d-flex flex-column gap-3">

            <!-- Customer -->
            <div>
              <label class="form-label fw-semibold mb-1 small text-uppercase text-muted ls-1">Customer</label>
              <div class="d-flex gap-2 align-items-start">
                <div class="flex-grow-1">
                  <vue-select
                    :options="customerOptions"
                    v-model="selectedCustomerOption"
                    placeholder="Walk-in Customer"
                    @search="onCustomerSearch"
                    @update:modelValue="onCustomerSelect"
                  />
                </div>
                <a class="btn btn-primary btn-icon flex-shrink-0"
                   data-bs-toggle="modal" data-bs-target="#create"
                   title="Add Customer">
                  <vue-feather type="user-plus" class="feather-16"></vue-feather>
                </a>
              </div>
            </div>

            <!-- Warehouse -->
            <div>
              <label class="form-label fw-semibold mb-1 small text-uppercase text-muted ls-1">Warehouse</label>
              <select class="form-select form-select-sm" :value="posStore.selectedWarehouseId" @change="onWarehouseSelectChange">
                <option :value="null">All Warehouses</option>
                <option v-for="wh in posStore.warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>

            <!-- Shipping -->
            <div>
              <label class="form-label fw-semibold mb-1 small text-uppercase text-muted ls-1">Shipping</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="ti ti-truck"></i></span>
                <input type="number" class="form-control" v-model.number="posStore.shippingAmount" min="0" step="0.01" placeholder="0.00" />
              </div>
            </div>

            <!-- Order Summary -->
            <div class="rounded border p-3 bg-light">
              <p class="fw-semibold mb-2 small text-uppercase text-muted ls-1">Order Summary</p>
              <table class="table table-borderless table-sm mb-0">
                <tbody>
                  <tr>
                    <td class="ps-0 text-muted py-1">Subtotal</td>
                    <td class="pe-0 text-end py-1">{{ fmt(posStore.subtotal) }}</td>
                  </tr>
                  <tr v-if="posStore.discountTotal > 0">
                    <td class="ps-0 text-danger py-1">Discount</td>
                    <td class="pe-0 text-danger text-end py-1">-{{ fmt(posStore.discountTotal) }}</td>
                  </tr>
                  <tr v-if="posStore.taxTotal > 0">
                    <td class="ps-0 text-muted py-1">Tax</td>
                    <td class="pe-0 text-end py-1">{{ fmt(posStore.taxTotal) }}</td>
                  </tr>
                  <tr v-if="Number(posStore.shippingAmount) > 0">
                    <td class="ps-0 text-muted py-1">Shipping</td>
                    <td class="pe-0 text-end py-1">{{ fmt(posStore.shippingAmount) }}</td>
                  </tr>
                  <tr class="border-top">
                    <td class="ps-0 fw-bold pt-2">Total</td>
                    <td class="pe-0 fw-bold text-end text-primary fs-16 pt-2">{{ fmt(posStore.grandTotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Items count badge -->
            <div v-if="posStore.cart.length" class="d-flex justify-content-between align-items-center">
              <span class="small text-muted">{{ posStore.cartCount }} item(s) in order</span>
              <a href="javascript:void(0);" class="small text-danger" @click="posStore.clearCart()">
                <i class="ti ti-x me-1"></i>Clear all
              </a>
            </div>

            <!-- Payment Method -->
            <div>
              <p class="fw-semibold mb-2 small text-uppercase text-muted ls-1">Payment Method</p>
              <div class="row g-2">
                <template v-if="posStore.paymentModes.length">
                  <div v-for="mode in posStore.paymentModes" :key="mode.id" class="col-6">
                    <a
                      href="javascript:void(0);"
                      class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none"
                      :class="selectedPaymentMethod === mode.name
                        ? 'bg-primary border-primary text-white'
                        : 'bg-white text-body'"
                      style="cursor:pointer; transition:all .15s; min-height:58px;"
                      @click="selectedPaymentMethod = mode.name"
                    >
                      <i :class="paymentModeIcon(mode.name)" class="fs-20 mb-1"></i>
                      <span class="small lh-sm text-center">{{ mode.name }}</span>
                    </a>
                  </div>
                </template>
                <template v-else>
                  <div class="col-6">
                    <a href="javascript:void(0);"
                       class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none"
                       :class="selectedPaymentMethod === 'cash' ? 'bg-primary border-primary text-white' : 'bg-white text-body'"
                       style="cursor:pointer; transition:all .15s; min-height:58px;"
                       @click="selectedPaymentMethod = 'cash'">
                      <i class="ti ti-cash-banknote fs-20 mb-1"></i>
                      <span class="small">Cash</span>
                    </a>
                  </div>
                  <div class="col-6">
                    <a href="javascript:void(0);"
                       class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none"
                       :class="selectedPaymentMethod === 'card' ? 'bg-primary border-primary text-white' : 'bg-white text-body'"
                       style="cursor:pointer; transition:all .15s; min-height:58px;"
                       @click="selectedPaymentMethod = 'card'">
                      <i class="ti ti-credit-card fs-20 mb-1"></i>
                      <span class="small">Card</span>
                    </a>
                  </div>
                  <div class="col-6">
                    <a href="javascript:void(0);"
                       class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none"
                       :class="selectedPaymentMethod === 'scan' ? 'bg-primary border-primary text-white' : 'bg-white text-body'"
                       style="cursor:pointer; transition:all .15s; min-height:58px;"
                       @click="selectedPaymentMethod = 'scan'">
                      <i class="ti ti-scan fs-20 mb-1"></i>
                      <span class="small">Scan / QR</span>
                    </a>
                  </div>
                </template>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex flex-column gap-2 pb-2">
              <button
                type="button"
                class="btn btn-success w-100 btn-lg fw-bold"
                :disabled="!posStore.cart.length || posStore.checkoutLoading"
                @click="openPaymentModal"
              >
                <span v-if="posStore.checkoutLoading" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="ti ti-cash-banknote me-1"></i>
                {{ posStore.checkoutLoading ? 'Processing...' : `Pay  ${fmt(posStore.grandTotal)}` }}
              </button>
              <div class="d-flex gap-2">
                <button
                  type="button"
                  class="btn btn-purple flex-fill"
                  data-bs-toggle="modal"
                  data-bs-target="#hold-order"
                  :disabled="!posStore.cart.length"
                >
                  <i class="ti ti-player-pause me-1"></i>Hold
                </button>
                <button
                  type="button"
                  class="btn btn-danger flex-fill"
                  :disabled="!posStore.cart.length"
                  @click="voidOrder"
                >
                  <i class="ti ti-trash me-1"></i>Void
                </button>
              </div>
            </div>

          </div>
        </div>
        <!-- /Right -->

      </div>
    </div>
  </div>

  <pos-two-modal
    :grand-total="posStore.grandTotal"
    :cart="posStore.cart"
    :selected-customer="posStore.selectedCustomer"
    :payment-method="selectedPaymentMethod"
    :last-sale="posStore.lastSale"
    :held-orders="posStore.heldOrders"
    :today-summary="posStore.todaySummary"
    :payment-modes="posStore.paymentModes"
    @checkout="processCheckout"
    @clear-cart="posStore.clearCart()"
    @hold="onHold"
    @restore-held-order="onRestoreHeldOrder"
    @delete-held-order="onDeleteHeldOrder"
    @customer-created="onCustomerCreated"
  ></pos-two-modal>
</template>

<script>
import { Modal } from 'bootstrap';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import PosHeader from '@/layouts/pos-header.vue';
import PosTwoModal from '@/components/modal/pos-two-modal.vue';
import { usePosStore } from '@/stores/admin/pos/pos.js';
import { useToast } from 'vue-toastification';
import showErrors from '@/helpers/showErrors.js';

export default {
  components: { PosHeader, PosTwoModal, ProductVariantSearchInput },

  setup() {
    const posStore = usePosStore();
    return { posStore };
  },

  data() {
    return {
      selectedCustomerOption: null,
      selectedPaymentMethod: 'cash',
    };
  },

  computed: {
    customerOptions() {
      return this.posStore.customers.map(c => ({
        label: c.name + (c.phone ? ` (${c.phone})` : ''),
        value: c.id,
        customer: c,
      }));
    },
  },

  async mounted() {
    await this.posStore.init();
  },

  methods: {
    fmt(val) {
      return Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    calcLineTotal(item) {
      const sub = item.rate * item.quantity;
      return Math.max(sub - (item.discountAmount ?? 0), 0) + (item.taxAmount ?? 0);
    },

    onVariantSelected(variant) {
      const existing = this.posStore.cart.find(i => i.variantId === variant.id);
      if (existing) {
        this.posStore.setCartItemQty(existing.variantId, existing.quantity + 1);
        return;
      }
      this.posStore.cart.push({
        variantId: variant.id,
        productId: variant.product_id ?? null,
        name: variant.name,
        sku: variant.sku ?? '',
        unitId: variant.unit_id ?? null,
        rate: Number(variant.sales_price ?? variant.purchase_price ?? 0),
        taxId: null,
        taxRate: 0,
        taxAmount: 0,
        discountAmount: 0,
        quantity: 1,
        stock: variant.stock ?? null,
        image: variant.image ?? null,
      });
    },

    onLineTaxChange(variantId, taxId) {
      const parsed = taxId ? parseInt(taxId, 10) : null;
      const tax = parsed ? this.posStore.taxes.find(t => t.id === parsed) : null;
      this.posStore.updateCartItem(variantId, {
        taxId: parsed,
        taxRate: tax ? Number(tax.rate) : 0,
      });
    },

    onCustomerSearch(query) {
      this.posStore.fetchCustomers(query);
    },

    onCustomerSelect(option) {
      this.posStore.setCustomer(option ? option.customer : null);
    },

    onWarehouseChanged(warehouseId) {
      this.posStore.setWarehouse(warehouseId);
    },

    onWarehouseSelectChange(e) {
      const val = e.target.value;
      this.posStore.setWarehouse(val === 'null' || val === '' ? null : parseInt(val, 10));
    },

    paymentModeIcon(name) {
      const n = (name || '').toLowerCase();
      if (n.includes('cash')) return 'ti ti-cash-banknote';
      if (n.includes('card') || n.includes('debit') || n.includes('credit')) return 'ti ti-credit-card';
      if (n.includes('scan') || n.includes('qr')) return 'ti ti-scan';
      if (n.includes('bank') || n.includes('transfer')) return 'ti ti-building-bank';
      return 'ti ti-wallet';
    },

    openPaymentModal() {
      if (!this.posStore.cart.length) {
        useToast().warning('Cart is empty');
        return;
      }
      const name = (this.selectedPaymentMethod || 'cash').toLowerCase();
      let targetId = '#payment-cash';
      if (name.includes('card') || name.includes('debit') || name.includes('credit')) {
        targetId = '#payment-card';
      } else if (name.includes('scan') || name.includes('qr')) {
        targetId = '#scan-payment';
      }
      const el = document.querySelector(targetId);
      if (el) Modal.getOrCreateInstance(el).show();
    },

    async processCheckout(paymentMethod) {
      if (!this.posStore.cart.length) return;
      try {
        await this.posStore.checkout(paymentMethod ?? this.selectedPaymentMethod);
        useToast().success('Sale completed successfully!');
        const el = document.getElementById('payment-completed');
        if (el) Modal.getOrCreateInstance(el).show();
        this.posStore.clearCart();
      } catch (err) {
        showErrors(err);
      }
    },

    voidOrder() {
      if (!this.posStore.cart.length) return;
      this.posStore.clearCart();
      useToast().info('Order voided');
    },

    async onHold(label) {
      try {
        await this.posStore.holdCurrentOrder(label);
        useToast().success('Order held successfully');
      } catch (err) {
        showErrors(err);
      }
    },

    onRestoreHeldOrder(order) {
      this.posStore.restoreHeldOrder(order);
      this.posStore.deleteHeldOrder(order.id);
      useToast().info('Order restored');
    },

    onDeleteHeldOrder(id) {
      this.posStore.deleteHeldOrder(id);
    },

    onCustomerCreated(customer) {
      this.posStore.customers.unshift(customer);
      this.posStore.setCustomer(customer);
      this.selectedCustomerOption = {
        label: customer.name,
        value: customer.id,
        customer,
      };
    },
  },
};
</script>
