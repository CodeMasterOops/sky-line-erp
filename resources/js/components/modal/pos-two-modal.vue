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
            <h2 class="display-1">{{ formatMoney(grandTotal) }}</h2>
          </div>
          <div class="mb-3">
            <label class="form-label">Amount Tendered <span class="text-danger">*</span></label>
            <div class="input-icon-start position-relative">
              <span class="input-icon-addon text-gray-9"><i class="ti ti-currency-rupee"></i></span>
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
            <strong>Change:</strong> {{ formatMoney(Math.max(cashTendered - grandTotal, 0)) }}
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md btn-success"
            :disabled="cashTendered < grandTotal"
            @click="doCheckout()"
          >
            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
            Confirm Payment
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       PAYMENT — GENERIC (dynamic payment modes)
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="payment-generic" aria-labelledby="payment-generic">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">{{ paymentMethod }} Payment</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="bg-light br-10 p-4 text-center mb-3">
            <p class="mb-1 text-muted">Amount Due</p>
            <h2 class="display-1">{{ formatMoney(grandTotal) }}</h2>
          </div>
          <p class="text-muted text-center mb-0">Confirm payment via {{ paymentMethod }}.</p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-md btn-primary" @click="doCheckout()">
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
            <h2 class="display-1">{{ formatMoney(grandTotal) }}</h2>
          </div>
          <p class="text-muted text-center">Present card to the terminal to complete payment.</p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-md btn-primary" @click="doCheckout()">
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
            <h2 class="display-1">{{ formatMoney(grandTotal) }}</h2>
          </div>
          <i class="ti ti-qrcode fs-64 text-muted"></i>
          <p class="text-muted mt-2">Ask customer to scan the QR code to complete payment.</p>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-md btn-info" @click="doCheckout()">
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
            <p class="mb-1">Total: <strong>{{ formatMoney(lastSale?.grand_total) }}</strong></p>
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
  <div class="modal fade modal-default pos-receipt-modal" id="print-receipt" aria-labelledby="print-receipt">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div id="receipt-print-area" class="pos-receipt">
            <header class="pos-receipt__header">
              <img
                v-if="receiptLogoUrl"
                :src="receiptLogoUrl"
                alt="Company logo"
                class="pos-receipt__logo"
              />
              <h6 v-if="companyName" class="pos-receipt__company">{{ companyName }}</h6>
              <p v-if="companyPhone" class="pos-receipt__meta">Tel: {{ companyPhone }}</p>
            </header>

            <div class="pos-receipt__divider"></div>
            <p class="pos-receipt__title">Tax Invoice</p>
            <div class="pos-receipt__divider"></div>

            <section class="pos-receipt__info">
              <div class="pos-receipt__info-row">
                <span>Customer</span>
                <strong>{{ lastSale?.party_name ?? 'Walk-in Customer' }}</strong>
              </div>
              <div class="pos-receipt__info-row">
                <span>Invoice No</span>
                <strong>{{ lastSale?.invoice_no ?? '—' }}</strong>
              </div>
              <div class="pos-receipt__info-row">
                <span>Date</span>
                <strong>{{ lastSale?.invoice_date ?? '—' }}</strong>
              </div>
              <div class="pos-receipt__info-row">
                <span>Payment</span>
                <strong>{{ lastSale?.payment_method ?? '—' }}</strong>
              </div>
              <div v-if="lastSale?.warehouse_name" class="pos-receipt__info-row">
                <span>Warehouse{{ (lastSale?.warehouses?.length ?? 0) > 1 ? 's' : '' }}</span>
                <strong>{{ lastSale.warehouse_name }}</strong>
              </div>
            </section>

            <table class="pos-receipt__items">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Rate</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in (lastSale?.items ?? [])" :key="idx">
                  <td>
                    <span class="pos-receipt__item-name">{{ item.name }}</span>
                    <span v-if="item.warehouse_name" class="pos-receipt__item-wh">{{ item.warehouse_name }}</span>
                  </td>
                  <td class="text-end">{{ item.quantity }}</td>
                  <td class="text-end">{{ formatMoney(item.rate) }}</td>
                  <td class="text-end">{{ formatMoney(item.total) }}</td>
                </tr>
              </tbody>
            </table>

            <section class="pos-receipt__totals">
              <div v-if="lastSale?.subtotal != null" class="pos-receipt__total-row">
                <span>Subtotal</span>
                <span>{{ formatMoney(lastSale.subtotal) }}</span>
              </div>
              <div v-if="Number(lastSale?.line_discount_total) > 0" class="pos-receipt__total-row is-discount">
                <span>Line discount</span>
                <span>-{{ formatMoney(lastSale.line_discount_total) }}</span>
              </div>
              <div v-if="Number(lastSale?.order_discount_amount) > 0" class="pos-receipt__total-row is-discount">
                <span>Order discount</span>
                <span>-{{ formatMoney(lastSale.order_discount_amount) }}</span>
              </div>
              <div v-if="Number(lastSale?.tax_total) > 0" class="pos-receipt__total-row">
                <span>Tax</span>
                <span>{{ formatMoney(lastSale.tax_total) }}</span>
              </div>
              <div class="pos-receipt__total-row is-grand">
                <span>Total</span>
                <span>{{ formatMoney(lastSale?.grand_total) }}</span>
              </div>
            </section>

            <footer class="pos-receipt__footer">
              <p>Thank you for your business!</p>
            </footer>
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
            <h2 class="display-1">{{ formatMoney(grandTotal) }}</h2>
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
                  <td class="text-end">{{ formatMoney(txn.grand_total) }}</td>
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
                <i class="ti ti-currency-rupee fs-32 text-success d-block mb-2"></i>
                <h4>{{ formatMoney(todaySummary.sale_total) }}</h4>
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
                <i class="ti ti-currency-rupee fs-28 text-primary d-block mb-1"></i>
                <h5>{{ formatMoney(todaySummary.sale_total) }}</h5>
                <p class="text-muted mb-0 small">Revenue</p>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-minus-circle fs-28 text-danger d-block mb-1"></i>
                <h5>{{ formatMoney(todaySummary.cogs) }}</h5>
                <p class="text-muted mb-0 small">COGS</p>
              </div>
            </div>
            <div class="col-4">
              <div class="bg-light p-3 br-10">
                <i class="ti ti-trending-up fs-28 text-success d-block mb-1"></i>
                <h5>{{ formatMoney(todaySummary.profit) }}</h5>
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
                <td class="text-end fw-bold">{{ formatMoney(todaySummary.sale_total) }}</td>
              </tr>
              <tr>
                <td>Number of Transactions</td>
                <td class="text-end fw-bold">{{ todaySummary.sale_count }}</td>
              </tr>
              <tr>
                <td>Profit</td>
                <td class="text-end fw-bold text-success">{{ formatMoney(todaySummary.profit) }}</td>
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
import {formatMoney, formatMoneyPlain} from '@/helpers/formatMoney.js';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { useToast } from 'vue-toastification';
import defaultLogoUrl from '@/assets/images/logo.svg';

const RECEIPT_PRINT_STYLES = `
  * { box-sizing: border-box; }
  html, body {
    margin: 0;
    padding: 0;
    background: #fff;
    color: #111;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 12px;
    line-height: 1.4;
  }
  .pos-receipt {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    padding: 16px 14px 20px;
  }
  .pos-receipt__header { text-align: center; margin-bottom: 10px; }
  .pos-receipt__logo {
    display: block;
    max-width: 120px;
    max-height: 48px;
    width: auto;
    height: auto;
    margin: 0 auto 8px;
    object-fit: contain;
  }
  .pos-receipt__company {
    margin: 0 0 4px;
    font-size: 14px;
    font-weight: 700;
  }
  .pos-receipt__meta {
    margin: 0;
    color: #555;
    font-size: 11px;
  }
  .pos-receipt__divider {
    border-top: 1px dashed #bbb;
    margin: 10px 0;
  }
  .pos-receipt__title {
    margin: 0;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .pos-receipt__info { margin-bottom: 10px; }
  .pos-receipt__info-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
    font-size: 11px;
  }
  .pos-receipt__info-row span { color: #666; }
  .pos-receipt__info-row strong {
    font-weight: 600;
    text-align: right;
    color: #111;
  }
  .pos-receipt__items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
  }
  .pos-receipt__items th {
    border-top: 1px dashed #bbb;
    border-bottom: 1px dashed #bbb;
    padding: 6px 2px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #333;
  }
  .pos-receipt__items td {
    padding: 6px 2px;
    vertical-align: top;
    border-bottom: 1px dotted #e5e5e5;
    font-size: 11px;
  }
  .pos-receipt__item-name { display: block; font-weight: 600; }
  .pos-receipt__item-wh {
    display: block;
    margin-top: 2px;
    color: #777;
    font-size: 10px;
  }
  .text-end { text-align: right; }
  .pos-receipt__totals {
    border-top: 1px dashed #bbb;
    padding-top: 8px;
    margin-bottom: 12px;
  }
  .pos-receipt__total-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 4px;
    font-size: 11px;
    color: #444;
  }
  .pos-receipt__total-row.is-discount { color: #c0392b; }
  .pos-receipt__total-row.is-grand {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px dashed #bbb;
    font-size: 14px;
    font-weight: 700;
    color: #111;
  }
  .pos-receipt__footer {
    border-top: 1px dashed #bbb;
    padding-top: 10px;
    text-align: center;
  }
  .pos-receipt__footer p {
    margin: 0;
    font-size: 11px;
    color: #555;
  }
  @page { margin: 8mm; size: auto; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
`;

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
      companyLogoUrl: '',
    };
  },

  computed: {
    receiptLogoUrl() {
      return this.companyLogoUrl || defaultLogoUrl;
    },
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
    formatMoney,

    formatTime(datetime) {
      if (!datetime) return '';
      return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },

    async doCheckout() {
      if (this.loading) return;
      this.loading = true;
      try {
        await this.$emit('checkout', this.paymentMethod);
        ['#payment-cash', '#payment-card', '#scan-payment', '#payment-generic'].forEach(sel => {
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
      if (!area) {
        return;
      }

      const printWindow = window.open('', '_blank', 'width=420,height=720');
      if (!printWindow) {
        useToast().warning('Allow pop-ups to print the receipt');

        return;
      }

      printWindow.document.open();
      printWindow.document.write(`<!DOCTYPE html>
        <html>
          <head>
            <meta charset="utf-8" />
            <title>Receipt ${this.lastSale?.invoice_no ?? ''}</title>
            <style>${RECEIPT_PRINT_STYLES}</style>
          </head>
          <body>${area.outerHTML}</body>
        </html>`);
      printWindow.document.close();

      const runPrint = () => {
        const images = Array.from(printWindow.document.images ?? []);
        if (!images.length) {
          printWindow.focus();
          printWindow.print();
          printWindow.close();

          return;
        }

        let settled = 0;
        const finish = () => {
          settled += 1;
          if (settled >= images.length) {
            printWindow.focus();
            printWindow.print();
            printWindow.close();
          }
        };

        images.forEach((img) => {
          if (img.complete) {
            finish();
          } else {
            img.addEventListener('load', finish, { once: true });
            img.addEventListener('error', finish, { once: true });
          }
        });
      };

      if (printWindow.document.readyState === 'complete') {
        runPrint();
      } else {
        printWindow.addEventListener('load', runPrint, { once: true });
      }
    },

    async createCustomer() {
      if (!this.newCustomer.name) return;
      this.customerSaving = true;
      try {
        const res = await apiAdmin('party', 'post', {
          type: 'customer',
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
        this.companyLogoUrl = settings.logo_url ?? '';
      } catch {
        // silently ignore — company info is cosmetic
      }
    },
  },
};
</script>

<style scoped>
.pos-receipt-modal .modal-dialog {
  max-width: 380px;
}

.pos-receipt {
  color: var(--bs-body-color);
  font-size: 0.8125rem;
  line-height: 1.45;
  padding: 1.5rem 1.25rem;
}

.pos-receipt__header {
  margin-bottom: 0.75rem;
  text-align: center;
}

.pos-receipt__logo {
  display: block;
  height: auto;
  margin: 0 auto 0.65rem;
  max-height: 52px;
  max-width: 130px;
  object-fit: contain;
  width: auto;
}

.pos-receipt__company {
  font-size: 0.9375rem;
  font-weight: 700;
  margin: 0 0 0.25rem;
}

.pos-receipt__meta {
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
  margin: 0;
}

.pos-receipt__divider {
  border-top: 1px dashed var(--bs-border-color);
  margin: 0.75rem 0;
}

.pos-receipt__title {
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  margin: 0;
  text-align: center;
  text-transform: uppercase;
}

.pos-receipt__info {
  margin-bottom: 0.75rem;
}

.pos-receipt__info-row {
  display: flex;
  font-size: 0.75rem;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 0.3rem;
}

.pos-receipt__info-row span {
  color: var(--bs-secondary-color);
}

.pos-receipt__info-row strong {
  font-weight: 600;
  text-align: right;
}

.pos-receipt__items {
  border-collapse: collapse;
  margin-bottom: 0.75rem;
  width: 100%;
}

.pos-receipt__items th {
  border-bottom: 1px dashed var(--bs-border-color);
  border-top: 1px dashed var(--bs-border-color);
  color: var(--bs-secondary-color);
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  padding: 0.45rem 0.15rem;
  text-transform: uppercase;
}

.pos-receipt__items td {
  border-bottom: 1px dotted rgba(var(--bs-border-color-rgb, 222, 226, 230), 0.85);
  font-size: 0.75rem;
  padding: 0.45rem 0.15rem;
  vertical-align: top;
}

.pos-receipt__item-name {
  display: block;
  font-weight: 600;
}

.pos-receipt__item-wh {
  color: var(--bs-secondary-color);
  display: block;
  font-size: 0.6875rem;
  margin-top: 0.15rem;
}

.pos-receipt__totals {
  border-top: 1px dashed var(--bs-border-color);
  margin-bottom: 0.85rem;
  padding-top: 0.65rem;
}

.pos-receipt__total-row {
  color: var(--bs-secondary-color);
  display: flex;
  font-size: 0.75rem;
  gap: 0.75rem;
  justify-content: space-between;
  margin-bottom: 0.3rem;
}

.pos-receipt__total-row.is-discount {
  color: var(--bs-danger);
}

.pos-receipt__total-row.is-grand {
  border-top: 1px dashed var(--bs-border-color);
  color: var(--bs-body-color);
  font-size: 0.9375rem;
  font-weight: 700;
  margin-bottom: 0;
  margin-top: 0.45rem;
  padding-top: 0.45rem;
}

.pos-receipt__footer {
  border-top: 1px dashed var(--bs-border-color);
  padding-top: 0.75rem;
  text-align: center;
}

.pos-receipt__footer p {
  color: var(--bs-secondary-color);
  font-size: 0.75rem;
  margin: 0;
}
</style>
