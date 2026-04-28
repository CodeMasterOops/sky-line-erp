<template>
  <!-- ═══════════════════════════════════════════════════════════════
       PAYMENT — CASH
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="payment-cash" aria-labelledby="payment-cash">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cash Payment</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="bg-light br-10 p-4 text-center mb-3">
            <p class="mb-1 text-muted">Amount Due</p>
            <h2 class="display-1">{{ fmt(grandTotal) }}</h2>
          </div>
          <div class="mb-3">
            <label class="form-label">Amount Tendered <span class="text-danger">*</span></label>
            <div class="input-icon-start position-relative">
              <span class="input-icon-addon text-gray-9"><i class="ti ti-currency-dollar"></i></span>
              <input
                type="number"
                class="form-control"
                v-model.number="cashTendered"
                min="0"
                step="0.01"
                :placeholder="grandTotal.toFixed(2)"
              />
            </div>
          </div>
          <div v-if="cashTendered > 0" class="alert alert-success">
            <strong>Change:</strong> {{ fmt(Math.max(cashTendered - grandTotal, 0)) }}
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md btn-success"
            :disabled="cashTendered < grandTotal"
            @click="doCheckout('cash')"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            Confirm Payment
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       PAYMENT — CARD
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="payment-card" aria-labelledby="payment-card">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Card Payment</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="bg-light br-10 p-4 text-center mb-3">
            <p class="mb-1 text-muted">Amount to Charge</p>
            <h2 class="display-1">{{ fmt(grandTotal) }}</h2>
          </div>
          <p class="text-muted text-center">Present card to the terminal to complete payment.</p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-md btn-primary" @click="doCheckout('card')">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            <i class="ti ti-credit-card me-1"></i>Charge Card
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       PAYMENT — SCAN / QR
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="scan-payment" aria-labelledby="scan-payment">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">QR / Scan Payment</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body text-center">
          <div class="bg-light br-10 p-4 mb-3">
            <p class="mb-1 text-muted">Amount</p>
            <h2 class="display-1">{{ fmt(grandTotal) }}</h2>
          </div>
          <i class="ti ti-qrcode fs-64 text-muted"></i>
          <p class="text-muted mt-2">Ask customer to scan the QR code to complete payment.</p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-md btn-info" @click="doCheckout('scan')">
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            <i class="ti ti-scan me-1"></i>Confirm Scan
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       PAYMENT COMPLETED
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="payment-completed" aria-labelledby="payment-completed">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="success-wrap text-center">
            <div class="icon-success bg-success text-white mb-2">
              <i class="ti ti-check"></i>
            </div>
            <h3 class="mb-2">Payment Completed</h3>
            <p class="mb-1">Invoice: <strong>{{ lastSale?.invoice_no }}</strong></p>
            <p class="mb-1">Total: <strong>{{ fmt(lastSale?.grand_total) }}</strong></p>
            <p class="mb-3">Customer: <strong>{{ lastSale?.party_name ?? 'Walk-in Customer' }}</strong></p>
            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
              <button
                type="button"
                class="btn btn-md btn-secondary"
                data-bs-toggle="modal"
                data-bs-target="#print-receipt"
              >Print Receipt <i class="feather-arrow-right-circle icon-me-5 ms-2"></i></button>
              <button
                type="button"
                class="btn btn-md btn-primary"
                data-bs-dismiss="modal"
                @click="$emit('clear-cart')"
              >Next Order</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       PRINT RECEIPT
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="print-receipt" aria-labelledby="print-receipt">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body" id="receipt-print-area">
          <div class="icon-head text-center invoice-logo d-flex justify-content-center">
            <a href="javascript:void(0);">
              <img src="@/assets/images/logo.svg" width="130" class="img-fluid dark-logo" alt="logo" />
              <img src="@/assets/images/logo-white.svg" width="130" class="img-fluid white-logo" alt="logo" />
            </a>
          </div>
          <div class="text-center info">
            <h6 class="mb-0">{{ companyName }}</h6>
            <p class="mb-0">Phone: {{ companyPhone }}</p>
          </div>
          <div class="tax-invoice mt-2">
            <h6 class="text-center">Tax Invoice</h6>
            <div class="row">
              <div class="col-6">
                <div class="invoice-user-name"><span>Customer: </span>{{ lastSale?.party_name ?? 'Walk-in Customer' }}</div>
                <div class="invoice-user-name"><span>Invoice No: </span>{{ lastSale?.invoice_no }}</div>
              </div>
              <div class="col-6">
                <div class="invoice-user-name"><span>Date: </span>{{ lastSale?.invoice_date }}</div>
                <div class="invoice-user-name"><span>Payment: </span>{{ lastSale?.payment_method }}</div>
              </div>
            </div>
          </div>
          <table class="table-borderless w-100 table-fit mt-2">
            <thead>
              <tr>
                <th># Item</th>
                <th>Price</th>
                <th>Qty</th>
                <th class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in (lastSale?.items ?? [])" :key="idx">
                <td>{{ idx + 1 }}. {{ item.name }}</td>
                <td>{{ fmt(item.rate) }}</td>
                <td>{{ item.quantity }}</td>
                <td class="text-end">{{ fmt(item.total) }}</td>
              </tr>
              <tr>
                <td colspan="4">
                  <table class="table-borderless w-100 table-fit">
                    <tbody>
                      <tr>
                        <td class="fw-bold">Total Bill:</td>
                        <td class="text-end">{{ fmt(lastSale?.grand_total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="text-center invoice-bar mt-2">
            <div class="border-bottom border-dashed pb-2 mb-2">
              <p class="mb-0">Thank you for your business!</p>
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-md btn-primary" @click="printReceipt">
            <i class="ti ti-printer me-1"></i>Print
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       HOLD ORDER
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default pos-modal" id="hold-order" aria-labelledby="hold-order">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Hold Order</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="bg-light br-10 p-4 text-center mb-3">
            <p class="mb-1 text-muted">Order Total</p>
            <h2 class="display-1">{{ fmt(grandTotal) }}</h2>
          </div>
          <div class="mb-3">
            <label class="form-label">Order Reference (optional)</label>
            <input
              class="form-control"
              type="text"
              v-model="holdLabel"
              placeholder="e.g. Table 5, John"
            />
          </div>
          <p class="text-muted small">
            The current order will be put on hold. You can retrieve it from the "View Orders" button.
          </p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md btn-primary"
            data-bs-dismiss="modal"
            @click="doHold"
          >Confirm Hold</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       VIEW HELD ORDERS
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="orders" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Held Orders</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="!heldOrders.length" class="text-center py-4 text-muted">
            <i class="ti ti-inbox fs-32 d-block mb-2"></i>
            No held orders
          </div>
          <div v-else class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Customer</th>
                  <th>Items</th>
                  <th>Time</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="order in heldOrders" :key="order.id">
                  <td>{{ order.label || `Order #${order.id}` }}</td>
                  <td>{{ order.party_name }}</td>
                  <td>{{ order.order_data?.items?.length ?? 0 }} items</td>
                  <td>{{ formatTime(order.created_at) }}</td>
                  <td>
                    <button
                      class="btn btn-sm btn-success me-1"
                      data-bs-dismiss="modal"
                      @click="$emit('restore-held-order', order)"
                    >Restore</button>
                    <button
                      class="btn btn-sm btn-danger"
                      @click="$emit('delete-held-order', order.id)"
                    >Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       RESET
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="reset" aria-labelledby="reset">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="success-wrap text-center">
            <div class="icon-success bg-purple-transparent text-purple mb-2">
              <i class="ti ti-transition-top"></i>
            </div>
            <h3 class="mb-2">Confirm Reset</h3>
            <p class="fs-16 mb-3">This will clear all items from the current order.</p>
            <div class="d-flex align-items-center justify-content-center gap-2 flex-wrap">
              <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">No, Cancel</button>
              <button
                type="button"
                class="btn btn-md btn-primary"
                data-bs-dismiss="modal"
                @click="$emit('clear-cart')"
              >Yes, Reset</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       RECENT TRANSACTIONS
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="recents" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Recent Transactions</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="recentLoading" class="text-center py-4">
            <div class="spinner-border text-primary"></div>
          </div>
          <div v-else-if="!recentTransactions.length" class="text-center py-4 text-muted">
            <i class="ti ti-receipt-off fs-32 d-block mb-2"></i>
            No recent transactions today
          </div>
          <div v-else class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Invoice No</th>
                  <th>Customer</th>
                  <th>Date</th>
                  <th class="text-end">Total</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="txn in recentTransactions" :key="txn.id">
                  <td>{{ txn.invoice_no }}</td>
                  <td>{{ txn.party_name || 'Walk-in Customer' }}</td>
                  <td>{{ txn.invoice_date }}</td>
                  <td class="text-end">{{ fmt(txn.grand_total) }}</td>
                  <td><span class="badge bg-success">{{ txn.status }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       CREATE CUSTOMER
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade" id="create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Create Customer</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <form @submit.prevent="createCustomer">
          <div class="modal-body pb-1">
            <div class="row">
              <div class="col-lg-6 col-sm-12">
                <div class="mb-3">
                  <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" v-model="newCustomer.name" required />
                </div>
              </div>
              <div class="col-lg-6 col-sm-12">
                <div class="mb-3">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" v-model="newCustomer.phone" />
                </div>
              </div>
              <div class="col-lg-12">
                <div class="mb-3">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" v-model="newCustomer.email" />
                </div>
              </div>
              <div class="col-lg-12">
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input type="text" class="form-control" v-model="newCustomer.address" />
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer d-flex justify-content-end gap-2 flex-wrap">
            <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-md btn-primary" :disabled="customerSaving">
              <span v-if="customerSaving" class="spinner-border spinner-border-sm me-1"></span>
              Save Customer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       TODAY'S SALES
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="today-sale" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Today's Sales</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body text-center">
          <div class="row g-3">
            <div class="col-6">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-receipt fs-32 text-primary d-block mb-2"></i>
                <h4>{{ todaySummary.sale_count }}</h4>
                <p class="text-muted mb-0">Total Sales</p>
              </div>
            </div>
            <div class="col-6">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-currency-dollar fs-32 text-success d-block mb-2"></i>
                <h4>{{ fmt(todaySummary.sale_total) }}</h4>
                <p class="text-muted mb-0">Revenue</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       TODAY'S PROFIT
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="today-profit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Today's Profit</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body text-center">
          <div class="row g-3">
            <div class="col-4">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-currency-dollar fs-28 text-primary d-block mb-1"></i>
                <h5>{{ fmt(todaySummary.sale_total) }}</h5>
                <p class="text-muted mb-0 small">Revenue</p>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-minus-circle fs-28 text-danger d-block mb-1"></i>
                <h5>{{ fmt(todaySummary.cogs) }}</h5>
                <p class="text-muted mb-0 small">COGS</p>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-trending-up fs-28 text-success d-block mb-1"></i>
                <h5>{{ fmt(todaySummary.profit) }}</h5>
                <p class="text-muted mb-0 small">Profit</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       CASH REGISTER
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="cash-register" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cash Register</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <table class="table table-borderless">
            <tbody>
              <tr>
                <td>Today's Cash Sales</td>
                <td class="text-end fw-bold">{{ fmt(todaySummary.sale_total) }}</td>
              </tr>
              <tr>
                <td>Number of Transactions</td>
                <td class="text-end fw-bold">{{ todaySummary.sale_count }}</td>
              </tr>
              <tr>
                <td>Profit</td>
                <td class="text-end fw-bold text-success">{{ fmt(todaySummary.profit) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Modal } from 'bootstrap';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { useToast } from 'vue-toastification';

export default {
  props: {
    grandTotal:      { type: Number, default: 0 },
    cart:            { type: Array, default: () => [] },
    selectedCustomer:{ type: Object, default: null },
    paymentMethod:   { type: String, default: 'cash' },
    lastSale:        { type: Object, default: null },
    heldOrders:      { type: Array, default: () => [] },
    todaySummary:    { type: Object, default: () => ({ sale_count: 0, sale_total: 0, profit: 0, cogs: 0 }) },
    paymentModes:    { type: Array, default: () => [] },
  },

  emits: ['checkout', 'clear-cart', 'hold', 'restore-held-order', 'delete-held-order', 'customer-created'],

  data() {
    return {
      cashTendered: 0,
      holdLabel: '',
      loading: false,
      customerSaving: false,
      recentLoading: false,
      recentTransactions: [],
      newCustomer: { name: '', phone: '', email: '', address: '' },
      companyName: '',
      companyPhone: '',
    };
  },

  mounted() {
    // Load today's sales when recents modal opens
    const recentsEl = document.getElementById('recents');
    if (recentsEl) {
      recentsEl.addEventListener('show.bs.modal', () => this.loadRecentTransactions());
    }
    // Load company info for receipt
    this.loadCompanyInfo();
  },

  methods: {
    fmt(val) {
      return '$' + Number(val ?? 0).toFixed(2);
    },

    formatTime(datetime) {
      if (!datetime) return '';
      return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },

    async doCheckout(method) {
      if (this.loading) return;
      this.loading = true;
      try {
        await this.$emit('checkout', method);
        // Close this payment modal
        ['#payment-cash', '#payment-card', '#scan-payment'].forEach(sel => {
          const el = document.querySelector(sel);
          if (el) Modal.getInstance(el)?.hide();
        });
        this.cashTendered = 0;
      } finally {
        this.loading = false;
      }
    },

    doHold() {
      this.$emit('hold', this.holdLabel.trim() || null);
      this.holdLabel = '';
    },

    printReceipt() {
      const area = document.getElementById('receipt-print-area');
      if (!area) return;
      const win = window.open('', '_blank', 'width=400,height=600');
      win.document.write(`<html><head><title>Receipt</title>
        <style>
          body { font-family: monospace; font-size: 12px; padding: 10px; }
          table { width: 100%; }
          th, td { padding: 2px 4px; }
          .text-end { text-align: right; }
        </style>
        </head><body>${area.innerHTML}</body></html>`);
      win.document.close();
      win.print();
    },

    async createCustomer() {
      if (!this.newCustomer.name) return;
      this.customerSaving = true;
      try {
        const res = await apiAdmin('party', 'post', {
          type: 'customer',
          code: 'CUST-' + Date.now(),
          name: this.newCustomer.name,
          phone: this.newCustomer.phone || null,
          email: this.newCustomer.email || null,
          address: this.newCustomer.address || null,
          is_active: true,
        });
        const customer = res.data.data;
        this.$emit('customer-created', customer);
        useToast().success('Customer created successfully');
        // close modal
        const el = document.getElementById('create');
        Modal.getInstance(el)?.hide();
        this.newCustomer = { name: '', phone: '', email: '', address: '' };
      } catch (err) {
        showErrors(err);
      } finally {
        this.customerSaving = false;
      }
    },

    async loadRecentTransactions() {
      this.recentLoading = true;
      try {
        const today = new Date().toISOString().slice(0, 10);
        const res = await apiAdmin(`invoice?status=approved&date_from=${today}&date_to=${today}&limit=50`);
        this.recentTransactions = res.data.data ?? [];
      } catch (err) {
        showErrors(err);
      } finally {
        this.recentLoading = false;
      }
    },

    async loadCompanyInfo() {
      try {
        const res = await apiAdmin('setting');
        const settings = res.data.data ?? res.data ?? {};
        this.companyName = settings.company_name ?? settings.name ?? '';
        this.companyPhone = settings.phone ?? settings.company_phone ?? '';
      } catch {
        // silently ignore — company info is cosmetic
      }
    },
  },
};
</script>
