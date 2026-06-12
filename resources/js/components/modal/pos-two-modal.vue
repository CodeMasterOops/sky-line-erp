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
                @click="openPrintReceiptForLastSale"
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
          <!-- Customer PAN input (outside print area) -->
          <div class="pos-receipt-pan-bar px-3 pt-3 pb-2">
            <label class="form-label small mb-1 text-muted">
              <i class="ti ti-id-badge me-1"></i>Customer PAN No. (optional — printed on bill)
            </label>
            <input
              type="text"
              class="form-control form-control-sm"
              v-model="customerPan"
              placeholder="Enter customer PAN number"
              maxlength="15"
            />
          </div>

          <!-- Receipt preview (this is what gets printed) -->
          <div id="receipt-print-area" class="pos-receipt">
            <!-- Header: Company identity -->
            <header class="pos-receipt__header">
              <img
                v-if="receiptLogoUrl"
                :src="receiptLogoUrl"
                alt="Company logo"
                class="pos-receipt__logo"
              />
              <h5 class="pos-receipt__company">{{ companyName }}</h5>
              <p v-if="companyLegalName && companyLegalName !== companyName" class="pos-receipt__legal">
                {{ companyLegalName }}
              </p>
              <p v-if="companyAddress" class="pos-receipt__meta">{{ companyAddress }}</p>
              <p v-if="companyLocation" class="pos-receipt__meta">{{ companyLocation }}</p>
              <p v-if="companyPhone" class="pos-receipt__meta">Tel: {{ companyPhone }}</p>
              <p v-if="companyPan" class="pos-receipt__pan-badge">PAN: {{ companyPan }}</p>
            </header>

            <div class="pos-receipt__divider"></div>
            <p class="pos-receipt__title">{{ receiptTitle }}</p>
            <div class="pos-receipt__divider"></div>

            <!-- Invoice metadata -->
            <section class="pos-receipt__info">
              <div class="pos-receipt__info-row">
                <span>Invoice No</span>
                <strong>{{ activeReceipt?.invoice_no ?? '—' }}</strong>
              </div>
              <div class="pos-receipt__info-row">
                <span>Date (AD)</span>
                <strong>{{ activeReceipt?.invoice_date ?? '—' }}</strong>
              </div>
              <div v-if="activeReceipt?.invoice_date_bs" class="pos-receipt__info-row">
                <span>Date (BS)</span>
                <strong>{{ activeReceipt.invoice_date_bs }}</strong>
              </div>
              <div class="pos-receipt__info-row">
                <span>Payment</span>
                <strong class="text-capitalize">{{ activeReceipt?.payment_method ?? '—' }}</strong>
              </div>
            </section>

            <div class="pos-receipt__divider"></div>

            <!-- Customer section -->
            <section class="pos-receipt__customer">
              <div class="pos-receipt__customer-row">
                <span>Customer</span>
                <span>{{ activeReceipt?.party_name ?? 'Walk-in Customer' }}</span>
              </div>
              <div class="pos-receipt__customer-row">
                <span>PAN No.</span>
                <span>{{ customerPan || '—' }}</span>
              </div>
            </section>

            <div class="pos-receipt__divider"></div>

            <!-- Items table -->
            <table class="pos-receipt__items">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Item</th>
                  <th class="text-end">Qty</th>
                  <th class="text-end">Rate</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in (activeReceipt?.items ?? [])" :key="idx">
                  <td class="pos-receipt__item-sn">{{ idx + 1 }}</td>
                  <td>
                    <span class="pos-receipt__item-name">{{ item.name }}</span>
                    <span v-if="item.sku" class="pos-receipt__item-sku">{{ item.sku }}</span>
                    <span v-if="item.warehouse_name" class="pos-receipt__item-wh">{{ item.warehouse_name }}</span>
                  </td>
                  <td class="text-end">{{ item.quantity }}</td>
                  <td class="text-end">{{ formatMoneyPlain(item.rate) }}</td>
                  <td class="text-end">{{ formatMoneyPlain(item.total) }}</td>
                </tr>
              </tbody>
            </table>

            <!-- Totals -->
            <section class="pos-receipt__totals">
              <div v-if="activeReceipt?.subtotal != null" class="pos-receipt__total-row">
                <span>Subtotal</span>
                <span>{{ formatMoney(activeReceipt.subtotal) }}</span>
              </div>
              <div v-if="Number(activeReceipt?.line_discount_total) > 0" class="pos-receipt__total-row is-discount">
                <span>Item Discount</span>
                <span>- {{ formatMoney(activeReceipt.line_discount_total) }}</span>
              </div>
              <div v-if="Number(activeReceipt?.order_discount_amount) > 0" class="pos-receipt__total-row is-discount">
                <span>Order Discount</span>
                <span>- {{ formatMoney(activeReceipt.order_discount_amount) }}</span>
              </div>

              <!-- VAT breakdown — only when tax is present -->
              <template v-if="Number(activeReceipt?.tax_total) > 0">
                <div class="pos-receipt__divider pos-receipt__divider--sm"></div>
                <div class="pos-receipt__total-row">
                  <span>Taxable Amount</span>
                  <span>{{ formatMoney(receiptTaxableAmount) }}</span>
                </div>
                <div class="pos-receipt__total-row is-tax">
                  <span>VAT (13%)</span>
                  <span>{{ formatMoney(activeReceipt.tax_total) }}</span>
                </div>
              </template>

              <div class="pos-receipt__total-row is-grand">
                <span>TOTAL</span>
                <span>{{ formatMoney(activeReceipt?.grand_total) }}</span>
              </div>
            </section>

            <!-- Amount in words -->
            <div class="pos-receipt__words">
              {{ amountToWords(activeReceipt?.grand_total ?? 0) }}
            </div>

            <footer class="pos-receipt__footer">
              <p v-if="companyInvoiceNote" class="pos-receipt__footer-note">{{ companyInvoiceNote }}</p>
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
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Invoice No</th>
                  <th>Customer</th>
                  <th>Date</th>
                  <th class="text-end">Total</th>
                  <th>Status</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="txn in recentTransactions" :key="txn.id">
                  <td class="fw-semibold">{{ txn.invoice_no }}</td>
                  <td>{{ txn.party_name || 'Walk-in Customer' }}</td>
                  <td>{{ txn.invoice_date }}</td>
                  <td class="text-end fw-semibold">{{ formatMoney(txn.grand_total) }}</td>
                  <td><span class="badge bg-success">{{ txn.status }}</span></td>
                  <td class="text-center">
                    <button
                      class="btn btn-xs btn-outline-primary"
                      :disabled="reprintLoading === txn.id"
                      @click="reprintTransaction(txn)"
                    >
                      <span v-if="reprintLoading === txn.id" class="spinner-border spinner-border-sm"></span>
                      <i v-else class="ti ti-printer"></i>
                    </button>
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
       CASH REGISTER (Till Summary)
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
          <!-- No active session -->
          <div v-if="!tillSession" class="text-center py-3">
            <i class="ti ti-lock fs-32 text-muted d-block mb-2"></i>
            <p class="text-muted mb-3">Till is not open</p>
            <button
              type="button"
              class="btn btn-success btn-sm"
              data-bs-dismiss="modal"
              data-bs-toggle="modal"
              data-bs-target="#till-open"
            >
              <i class="ti ti-lock-open me-1"></i>Open Till
            </button>
          </div>

          <!-- Active session summary -->
          <div v-else>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="badge bg-success">Till Open</span>
              <small class="text-muted">Since {{ formatTime(tillSession.opened_at) }}</small>
            </div>

            <!-- Live summary (loaded when modal opens) -->
            <div v-if="tillSummaryLoading" class="text-center py-3">
              <div class="spinner-border spinner-border-sm text-primary"></div>
            </div>
            <div v-else-if="tillSummaryData">
              <table class="table table-sm table-borderless mb-0">
                <tbody>
                  <tr>
                    <td class="text-muted">Opening Cash</td>
                    <td class="text-end fw-semibold">{{ formatMoney(tillSession.opening_cash) }}</td>
                  </tr>
                  <tr v-for="(amount, method) in tillSummaryData.sales_by_method" :key="method">
                    <td class="text-muted text-capitalize">{{ method }} Sales</td>
                    <td class="text-end fw-semibold">{{ formatMoney(amount) }}</td>
                  </tr>
                  <template v-if="tillSummaryData.cash_ins > 0 || tillSummaryData.cash_outs > 0">
                    <tr class="border-top">
                      <td class="text-muted">Cash In</td>
                      <td class="text-end text-success fw-semibold">+ {{ formatMoney(tillSummaryData.cash_ins) }}</td>
                    </tr>
                    <tr>
                      <td class="text-muted">Cash Out</td>
                      <td class="text-end text-danger fw-semibold">- {{ formatMoney(tillSummaryData.cash_outs) }}</td>
                    </tr>
                  </template>
                  <tr class="border-top">
                    <td class="fw-bold">Expected Cash</td>
                    <td class="text-end fw-bold text-primary">{{ formatMoney(tillSummaryData.expected_cash) }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Cash movements log -->
              <div v-if="tillSummaryData.movements.length" class="mt-3">
                <p class="small text-muted mb-1 fw-semibold">Cash Movements</p>
                <div
                  v-for="m in tillSummaryData.movements"
                  :key="m.id"
                  class="d-flex justify-content-between align-items-center py-1 border-bottom small"
                >
                  <span>
                    <span :class="m.type === 'cash_in' ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger'">
                      {{ m.type === 'cash_in' ? '+' : '−' }}
                    </span>
                    {{ m.reason || (m.type === 'cash_in' ? 'Cash In' : 'Cash Out') }}
                  </span>
                  <span :class="m.type === 'cash_in' ? 'text-success' : 'text-danger'" class="fw-semibold">
                    {{ formatMoney(m.amount) }}
                  </span>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3 flex-wrap">
              <button
                type="button"
                class="btn btn-sm btn-outline-primary flex-grow-1"
                data-bs-dismiss="modal"
                data-bs-toggle="modal"
                data-bs-target="#cash-movement"
              >
                <i class="ti ti-arrows-exchange me-1"></i>Cash In/Out
              </button>
              <button
                type="button"
                class="btn btn-sm btn-outline-danger flex-grow-1"
                data-bs-dismiss="modal"
                data-bs-toggle="modal"
                data-bs-target="#till-close"
                @click="loadTillSummaryForClose"
              >
                <i class="ti ti-lock me-1"></i>Close Till
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       TILL OPEN
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="till-open" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-lock-open me-2 text-success"></i>Open Till</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Count the cash in the drawer and enter the opening balance to start your shift.</p>
          <div class="mb-3">
            <label class="form-label fw-semibold">Opening Cash <span class="text-danger">*</span></label>
            <div class="input-icon-start position-relative">
              <span class="input-icon-addon text-gray-9"><i class="ti ti-currency-rupee"></i></span>
              <input
                type="number"
                class="form-control"
                v-model.number="tillOpeningCash"
                min="0"
                step="0.01"
                placeholder="0.00"
              />
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md btn-success"
            :disabled="tillOpening || tillOpeningCash < 0"
            @click="doOpenTill"
          >
            <span v-if="tillOpening" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ti ti-lock-open me-1"></i>Open Till
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       CASH MOVEMENT (Cash In / Cash Out)
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade modal-default" id="cash-movement" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-arrows-exchange me-2"></i>Cash In / Cash Out</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
            <div class="d-flex gap-2">
              <button
                type="button"
                class="btn flex-grow-1"
                :class="movementType === 'cash_in' ? 'btn-success' : 'btn-outline-success'"
                @click="movementType = 'cash_in'"
              >
                <i class="ti ti-arrow-down-circle me-1"></i>Cash In
              </button>
              <button
                type="button"
                class="btn flex-grow-1"
                :class="movementType === 'cash_out' ? 'btn-danger' : 'btn-outline-danger'"
                @click="movementType = 'cash_out'"
              >
                <i class="ti ti-arrow-up-circle me-1"></i>Cash Out
              </button>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
            <div class="input-icon-start position-relative">
              <span class="input-icon-addon text-gray-9"><i class="ti ti-currency-rupee"></i></span>
              <input
                type="number"
                class="form-control"
                v-model.number="movementAmount"
                min="0.01"
                step="0.01"
                placeholder="0.00"
              />
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason / Note</label>
            <input
              type="text"
              class="form-control"
              v-model="movementReason"
              placeholder="e.g. Petty cash, Expense, Received from bank…"
              maxlength="255"
            />
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md"
            :class="movementType === 'cash_in' ? 'btn-success' : 'btn-danger'"
            :disabled="movementSaving || !movementAmount || movementAmount <= 0"
            @click="doMovement"
          >
            <span v-if="movementSaving" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else :class="movementType === 'cash_in' ? 'ti ti-arrow-down-circle' : 'ti ti-arrow-up-circle'" class="me-1"></i>
            Confirm {{ movementType === 'cash_in' ? 'Cash In' : 'Cash Out' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════
       TILL CLOSE (Z-Report)
  ════════════════════════════════════════════════════════════════ -->
  <div class="modal fade pos-modal" id="till-close" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-lock me-2 text-danger"></i>Close Till — Z-Report</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <div v-if="tillCloseLoading" class="text-center py-4">
            <div class="spinner-border text-primary"></div>
          </div>
          <div v-else-if="tillCloseData">
            <div class="row g-3 mb-3">
              <!-- Sales breakdown -->
              <div class="col-md-6">
                <div class="bg-light br-10 p-3">
                  <h6 class="text-muted mb-2 small text-uppercase fw-bold">Sales by Payment Method</h6>
                  <div
                    v-for="(amount, method) in tillCloseData.sales_by_method"
                    :key="method"
                    class="d-flex justify-content-between py-1 border-bottom small"
                  >
                    <span class="text-capitalize">{{ method }}</span>
                    <span class="fw-semibold">{{ formatMoney(amount) }}</span>
                  </div>
                  <div v-if="!Object.keys(tillCloseData.sales_by_method || {}).length" class="text-muted small">
                    No sales this session
                  </div>
                </div>
              </div>

              <!-- Cash summary -->
              <div class="col-md-6">
                <div class="bg-light br-10 p-3">
                  <h6 class="text-muted mb-2 small text-uppercase fw-bold">Cash Summary</h6>
                  <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span>Opening Cash</span>
                    <span class="fw-semibold">{{ formatMoney(tillCloseData.opening_cash) }}</span>
                  </div>
                  <div class="d-flex justify-content-between py-1 border-bottom small">
                    <span>Cash Sales</span>
                    <span class="fw-semibold text-success">+ {{ formatMoney(tillCloseData.cash_sales) }}</span>
                  </div>
                  <div v-if="tillCloseData.cash_ins > 0" class="d-flex justify-content-between py-1 border-bottom small">
                    <span>Cash In</span>
                    <span class="fw-semibold text-success">+ {{ formatMoney(tillCloseData.cash_ins) }}</span>
                  </div>
                  <div v-if="tillCloseData.cash_outs > 0" class="d-flex justify-content-between py-1 border-bottom small">
                    <span>Cash Out</span>
                    <span class="fw-semibold text-danger">− {{ formatMoney(tillCloseData.cash_outs) }}</span>
                  </div>
                  <div class="d-flex justify-content-between py-1 fw-bold">
                    <span>Expected in Drawer</span>
                    <span class="text-primary">{{ formatMoney(tillCloseData.expected_cash) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Closing count input -->
            <div class="mb-3">
              <label class="form-label fw-semibold">
                Actual Cash Counted <span class="text-danger">*</span>
              </label>
              <div class="input-icon-start position-relative">
                <span class="input-icon-addon text-gray-9"><i class="ti ti-currency-rupee"></i></span>
                <input
                  type="number"
                  class="form-control"
                  v-model.number="tillClosingCash"
                  min="0"
                  step="0.01"
                  placeholder="Count the cash in your drawer"
                />
              </div>
            </div>

            <!-- Difference indicator -->
            <div
              v-if="tillClosingCash !== null && tillClosingCash !== ''"
              class="alert mb-3"
              :class="tillCashDifference === 0 ? 'alert-success' : (tillCashDifference > 0 ? 'alert-info' : 'alert-danger')"
            >
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold">
                  <i :class="tillCashDifference === 0 ? 'ti ti-check' : (tillCashDifference > 0 ? 'ti ti-trending-up' : 'ti ti-trending-down')" class="me-1"></i>
                  {{ tillCashDifference === 0 ? 'Balanced' : (tillCashDifference > 0 ? 'Overage' : 'Shortage') }}
                </span>
                <span class="fw-bold">{{ formatMoney(Math.abs(tillCashDifference)) }}</span>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Notes (optional)</label>
              <textarea
                class="form-control"
                rows="2"
                v-model="tillCloseNotes"
                placeholder="Any notes about this closing…"
              ></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-md btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-md btn-danger"
            :disabled="tillClosing || tillClosingCash === null || tillClosingCash === ''"
            @click="doCloseTill"
          >
            <span v-if="tillClosing" class="spinner-border spinner-border-sm me-1"></span>
            <i v-else class="ti ti-lock me-1"></i>Close Till
          </button>
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

// ─── Amount-in-words helper (Nepali Rupees, lakh/crore system) ───────────────
function numberToWords(n) {
    const ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if (n === 0) {
        return 'Zero';
    }

    function below100(num) {
        if (num < 20) {
            return ones[num];
        }

        return tens[Math.floor(num / 10)] + (num % 10 ? ' ' + ones[num % 10] : '');
    }

    function below1000(num) {
        if (num < 100) {
            return below100(num);
        }

        return ones[Math.floor(num / 100)] + ' Hundred' + (num % 100 ? ' ' + below100(num % 100) : '');
    }

    const parts = [];
    const crore = Math.floor(n / 10000000);
    const lakh = Math.floor((n % 10000000) / 100000);
    const thousand = Math.floor((n % 100000) / 1000);
    const rest = n % 1000;

    if (crore) { parts.push(below1000(crore) + ' Crore'); }
    if (lakh)  { parts.push(below1000(lakh)  + ' Lakh'); }
    if (thousand) { parts.push(below100(thousand) + ' Thousand'); }
    if (rest)  { parts.push(below1000(rest)); }

    return parts.join(' ');
}

function amountToWords(amount) {
    if (!amount || Number.isNaN(Number(amount))) {
        return '—';
    }

    const n = Math.abs(Number(amount));
    const whole = Math.floor(n);
    const paisa = Math.round((n - whole) * 100);
    let words = 'Rupees ' + numberToWords(whole);

    if (paisa > 0) {
        words += ' and ' + numberToWords(paisa) + ' Paisa';
    }

    return words + ' Only';
}
// ─────────────────────────────────────────────────────────────────────────────

const RECEIPT_PRINT_STYLES = `
  * { box-sizing: border-box; }
  html, body {
    margin: 0; padding: 0;
    background: #fff; color: #111;
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px; line-height: 1.45;
  }
  .pos-receipt {
    width: 100%;
    max-width: 302px;
    margin: 0 auto;
    padding: 8px 6px 14px;
  }
  /* Header */
  .pos-receipt__header { text-align: center; margin-bottom: 6px; }
  .pos-receipt__logo {
    display: block; max-width: 90px; max-height: 40px;
    width: auto; height: auto; margin: 0 auto 5px; object-fit: contain;
  }
  .pos-receipt__company { margin: 0 0 1px; font-size: 13px; font-weight: 700; }
  .pos-receipt__legal { margin: 0 0 1px; font-size: 11px; font-weight: 600; }
  .pos-receipt__meta { margin: 0; color: #555; font-size: 10px; }
  .pos-receipt__pan-badge {
    display: inline-block; margin: 3px 0 0;
    font-size: 10px; font-weight: 700;
    border: 1px solid #333; padding: 1px 5px; letter-spacing: 0.03em;
  }
  /* Dividers */
  .pos-receipt__divider { border-top: 1px dashed #777; margin: 6px 0; }
  .pos-receipt__divider--sm { border-top: 1px dotted #aaa; margin: 3px 0; }
  /* Title */
  .pos-receipt__title {
    margin: 0; text-align: center;
    font-size: 12px; font-weight: 700;
    letter-spacing: 0.1em; text-transform: uppercase;
  }
  /* Info rows */
  .pos-receipt__info { margin-bottom: 5px; }
  .pos-receipt__info-row {
    display: flex; justify-content: space-between; gap: 8px;
    margin-bottom: 1px; font-size: 10px;
  }
  .pos-receipt__info-row span { color: #555; }
  .pos-receipt__info-row strong { font-weight: 700; text-align: right; color: #111; }
  /* Customer */
  .pos-receipt__customer { margin-bottom: 5px; font-size: 10px; }
  .pos-receipt__customer-row {
    display: flex; justify-content: space-between; gap: 8px; margin-bottom: 1px;
  }
  .pos-receipt__customer-row span:first-child { color: #555; }
  .pos-receipt__customer-row span:last-child { font-weight: 700; text-align: right; color: #111; }
  /* Items table */
  .pos-receipt__items { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
  .pos-receipt__items th {
    border-top: 1px dashed #777; border-bottom: 1px dashed #777;
    padding: 3px 2px; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.04em; color: #333;
  }
  .pos-receipt__items td { padding: 3px 2px; vertical-align: top; border-bottom: 1px dotted #ddd; font-size: 10px; }
  .pos-receipt__item-sn { color: #888; font-size: 9px; white-space: nowrap; }
  .pos-receipt__item-name { display: block; font-weight: 700; }
  .pos-receipt__item-sku { display: block; margin-top: 1px; color: #888; font-size: 9px; }
  .pos-receipt__item-wh { display: block; margin-top: 1px; color: #777; font-size: 9px; }
  .text-end { text-align: right; }
  /* Totals */
  .pos-receipt__totals { border-top: 1px dashed #777; padding-top: 5px; margin-bottom: 6px; }
  .pos-receipt__total-row {
    display: flex; justify-content: space-between; gap: 8px;
    margin-bottom: 1px; font-size: 10px; color: #444;
  }
  .pos-receipt__total-row.is-discount { color: #c0392b; }
  .pos-receipt__total-row.is-tax { color: #1a3a5c; font-style: italic; }
  .pos-receipt__total-row.is-grand {
    margin-top: 4px; padding-top: 4px; border-top: 1px dashed #777;
    font-size: 13px; font-weight: 700; color: #111;
  }
  /* Amount in words */
  .pos-receipt__words {
    border-top: 1px dashed #777; padding: 5px 0;
    font-size: 9.5px; font-style: italic; color: #444; word-break: break-word;
  }
  /* Footer */
  .pos-receipt__footer { border-top: 1px dashed #777; padding-top: 5px; text-align: center; }
  .pos-receipt__footer p { margin: 0 0 2px; font-size: 10px; color: #555; }
  .pos-receipt__footer-note { font-weight: 600; color: #333 !important; }
  /* PAN input area is outside print area — never shown in print */
  .pos-receipt-pan-bar { display: none; }
  @page { margin: 4mm; size: 80mm auto; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .pos-receipt { max-width: 100%; padding: 0; }
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
    tillSession:     { type: Object, default: null },
  },

  emits: ['checkout', 'clear-cart', 'hold', 'restore-held-order', 'delete-held-order', 'customer-created', 'till-opened', 'till-closed'],

  data() {
    return {
      cashTendered: 0,
      holdLabel: '',
      loading: false,
      customerSaving: false,
      recentLoading: false,
      recentTransactions: [],
      reprintSale: null,
      reprintLoading: null,
      newCustomer: { name: '', phone: '', email: '', address: '' },
      // company info for receipt
      companyName: '',
      companyLegalName: '',
      companyPhone: '',
      companyLogoUrl: '',
      companyPan: '',
      companyAddress: '',
      companyLocation: '',
      companyInvoiceNote: '',
      // customer PAN (editable in receipt modal before printing)
      customerPan: '',
      // Till management
      tillOpeningCash: 0,
      tillOpening: false,
      movementType: 'cash_in',
      movementAmount: null,
      movementReason: '',
      movementSaving: false,
      tillCloseData: null,
      tillCloseLoading: false,
      tillClosingCash: null,
      tillCloseNotes: '',
      tillClosing: false,
      // Cash register summary data
      tillSummaryData: null,
      tillSummaryLoading: false,
    };
  },

  computed: {
    receiptLogoUrl() {
      return this.companyLogoUrl;
    },

    /** The receipt being previewed — either a reprint or the last completed sale. */
    activeReceipt() {
      return this.reprintSale ?? this.lastSale;
    },

    /** "TAX INVOICE" when VAT is present, otherwise "CASH MEMO". */
    receiptTitle() {
      return Number(this.activeReceipt?.tax_total) > 0 ? 'TAX INVOICE' : 'CASH MEMO';
    },

    /** Taxable amount — uses server value when available, otherwise computes client-side. */
    receiptTaxableAmount() {
      const r = this.activeReceipt;
      if (!r) { return 0; }
      if (r.taxable_amount != null) { return r.taxable_amount; }

      return (r.subtotal ?? 0) - (r.line_discount_total ?? 0) - (r.order_discount_amount ?? 0);
    },

    tillCashDifference() {
      if (this.tillClosingCash === null || this.tillClosingCash === '') { return 0; }
      const expected = this.tillCloseData?.expected_cash ?? 0;

      return Math.round((Number(this.tillClosingCash) - expected) * 100) / 100;
    },
  },

  watch: {
    activeReceipt(val) {
      this.customerPan = val?.party_pan ?? '';
    },
  },

  mounted() {
    const recentsEl = document.getElementById('recents');
    if (recentsEl) {
      recentsEl.addEventListener('show.bs.modal', () => this.loadRecentTransactions());
    }
    const cashRegisterEl = document.getElementById('cash-register');
    if (cashRegisterEl) {
      cashRegisterEl.addEventListener('show.bs.modal', () => this.loadTillSummary());
    }
    this.loadCompanyInfo();
  },

  methods: {
    formatMoney,
    formatMoneyPlain,
    amountToWords,

    formatTime(datetime) {
      if (!datetime) { return ''; }

      return new Date(datetime).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },

    openPrintReceiptForLastSale() {
      this.reprintSale = null;
    },

    async doCheckout() {
      if (this.loading) { return; }
      this.loading = true;
      try {
        await this.$emit('checkout', this.paymentMethod);
        ['#payment-cash', '#payment-card', '#scan-payment', '#payment-generic'].forEach(sel => {
          const el = document.querySelector(sel);
          if (el) { Modal.getInstance(el)?.hide(); }
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
      if (!area) { return; }

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
            <title>Receipt ${this.activeReceipt?.invoice_no ?? ''}</title>
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

    async reprintTransaction(txn) {
      this.reprintLoading = txn.id;
      try {
        const res = await apiAdmin(`pos/receipt/${txn.id}`);
        this.reprintSale = res.data.data;
        this.customerPan = this.reprintSale?.party_pan ?? '';
        const recentsEl = document.getElementById('recents');
        if (recentsEl) { Modal.getInstance(recentsEl)?.hide(); }
        await this.$nextTick();
        const printEl = document.getElementById('print-receipt');
        if (printEl) {
          const modal = Modal.getInstance(printEl) ?? new Modal(printEl);
          modal.show();
        }
      } catch (err) {
        showErrors(err);
      } finally {
        this.reprintLoading = null;
      }
    },

    async createCustomer() {
      if (!this.newCustomer.name) { return; }
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
        const el = document.getElementById('create');
        Modal.getInstance(el)?.hide();
        this.newCustomer = { name: '', phone: '', email: '', address: '' };
      } catch (err) {
        showErrors(err);
      } finally {
        this.customerSaving = false;
      }
    },

    async doOpenTill() {
      if (this.tillOpening) { return; }
      this.tillOpening = true;
      try {
        const res = await apiAdmin('pos/till/open', 'post', { opening_cash: this.tillOpeningCash ?? 0 });
        this.$emit('till-opened', res.data.data);
        useToast().success(res.data.message ?? 'Till opened');
        const el = document.getElementById('till-open');
        Modal.getInstance(el)?.hide();
        this.tillOpeningCash = 0;
      } catch (err) {
        showErrors(err);
      } finally {
        this.tillOpening = false;
      }
    },

    async doMovement() {
      if (this.movementSaving) { return; }
      this.movementSaving = true;
      try {
        const res = await apiAdmin('pos/till/cash-movement', 'post', {
          type: this.movementType,
          amount: this.movementAmount,
          reason: this.movementReason || null,
        });
        useToast().success(res.data.message ?? 'Movement recorded');
        const el = document.getElementById('cash-movement');
        Modal.getInstance(el)?.hide();
        this.movementAmount = null;
        this.movementReason = '';
      } catch (err) {
        showErrors(err);
      } finally {
        this.movementSaving = false;
      }
    },

    async loadTillSummary() {
      if (!this.tillSession) { return; }
      this.tillSummaryLoading = true;
      try {
        const res = await apiAdmin('pos/till/summary');
        this.tillSummaryData = res.data.data;
      } catch (err) {
        showErrors(err);
      } finally {
        this.tillSummaryLoading = false;
      }
    },

    async loadTillSummaryForClose() {
      this.tillCloseLoading = true;
      this.tillClosingCash = null;
      this.tillCloseNotes = '';
      try {
        const res = await apiAdmin('pos/till/summary');
        this.tillCloseData = res.data.data;
      } catch (err) {
        showErrors(err);
      } finally {
        this.tillCloseLoading = false;
      }
    },

    async doCloseTill() {
      if (this.tillClosing) { return; }
      this.tillClosing = true;
      try {
        const res = await apiAdmin('pos/till/close', 'post', {
          closing_cash: this.tillClosingCash,
          notes: this.tillCloseNotes || null,
        });
        this.$emit('till-closed', res.data.data);
        useToast().success('Till closed successfully');
        const el = document.getElementById('till-close');
        Modal.getInstance(el)?.hide();
        this.tillCloseData = null;
        this.tillClosingCash = null;
        this.tillCloseNotes = '';
      } catch (err) {
        showErrors(err);
      } finally {
        this.tillClosing = false;
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
        const s = res.data.data ?? res.data ?? {};
        this.companyName        = s.company_name ?? s.name ?? '';
        this.companyLegalName   = s.legal_name ?? '';
        this.companyPhone       = s.phone ?? s.company_phone ?? '';
        this.companyLogoUrl     = s.logo_url ?? '';
        this.companyPan         = s.pan ?? '';
        this.companyAddress     = s.address ?? '';
        this.companyLocation    = s.location_label ?? '';
        this.companyInvoiceNote = s.invoice_note ?? '';
      } catch {
        // silently ignore — company info is cosmetic
      }
    },
  },
};
</script>

<style scoped>
.pos-receipt-modal .modal-dialog {
  max-width: 400px;
}

/* PAN input bar above receipt preview */
.pos-receipt-pan-bar {
  background: var(--bs-light);
  border-bottom: 1px solid var(--bs-border-color);
}

/* Receipt preview container */
.pos-receipt {
  color: var(--bs-body-color);
  font-family: 'Courier New', Courier, monospace;
  font-size: 0.78125rem;
  line-height: 1.45;
  padding: 1.25rem 1.1rem 1.5rem;
}

/* Header */
.pos-receipt__header {
  margin-bottom: 0.6rem;
  text-align: center;
}

.pos-receipt__logo {
  display: block;
  height: auto;
  margin: 0 auto 0.5rem;
  max-height: 48px;
  max-width: 120px;
  object-fit: contain;
  width: auto;
}

.pos-receipt__company {
  font-size: 0.9375rem;
  font-weight: 700;
  margin: 0 0 0.125rem;
}

.pos-receipt__legal {
  font-size: 0.8125rem;
  font-weight: 600;
  margin: 0 0 0.125rem;
}

.pos-receipt__meta {
  color: var(--bs-secondary-color);
  font-size: 0.71875rem;
  margin: 0;
}

.pos-receipt__pan-badge {
  display: inline-block;
  border: 1.5px solid var(--bs-body-color);
  font-size: 0.71875rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  margin-top: 0.25rem;
  padding: 0.1rem 0.4rem;
}

/* Dividers */
.pos-receipt__divider {
  border-top: 1px dashed var(--bs-border-color);
  margin: 0.55rem 0;
}

.pos-receipt__divider--sm {
  border-top: 1px dotted rgba(var(--bs-border-color-rgb, 200, 200, 200), 0.7);
  margin: 0.3rem 0;
}

/* Title */
.pos-receipt__title {
  font-size: 0.8125rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  margin: 0;
  text-align: center;
  text-transform: uppercase;
}

/* Info rows */
.pos-receipt__info {
  margin-bottom: 0.5rem;
}

.pos-receipt__info-row {
  display: flex;
  font-size: 0.71875rem;
  gap: 0.6rem;
  justify-content: space-between;
  margin-bottom: 0.2rem;
}

.pos-receipt__info-row span {
  color: var(--bs-secondary-color);
}

.pos-receipt__info-row strong {
  font-weight: 600;
  text-align: right;
}

/* Customer section */
.pos-receipt__customer {
  font-size: 0.71875rem;
  margin-bottom: 0.5rem;
}

.pos-receipt__customer-row {
  display: flex;
  gap: 0.6rem;
  justify-content: space-between;
  margin-bottom: 0.2rem;
}

.pos-receipt__customer-row span:first-child {
  color: var(--bs-secondary-color);
}

.pos-receipt__customer-row span:last-child {
  font-weight: 600;
  text-align: right;
}

/* Items table */
.pos-receipt__items {
  border-collapse: collapse;
  margin-bottom: 0.5rem;
  width: 100%;
}

.pos-receipt__items th {
  border-bottom: 1px dashed var(--bs-border-color);
  border-top: 1px dashed var(--bs-border-color);
  color: var(--bs-secondary-color);
  font-size: 0.625rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  padding: 0.35rem 0.15rem;
  text-transform: uppercase;
}

.pos-receipt__items td {
  border-bottom: 1px dotted rgba(var(--bs-border-color-rgb, 222, 226, 230), 0.85);
  font-size: 0.71875rem;
  padding: 0.35rem 0.15rem;
  vertical-align: top;
}

.pos-receipt__item-sn {
  color: var(--bs-secondary-color);
  font-size: 0.625rem;
  white-space: nowrap;
}

.pos-receipt__item-name {
  display: block;
  font-weight: 600;
}

.pos-receipt__item-sku,
.pos-receipt__item-wh {
  color: var(--bs-secondary-color);
  display: block;
  font-size: 0.625rem;
  margin-top: 0.1rem;
}

/* Totals */
.pos-receipt__totals {
  border-top: 1px dashed var(--bs-border-color);
  margin-bottom: 0.65rem;
  padding-top: 0.5rem;
}

.pos-receipt__total-row {
  color: var(--bs-secondary-color);
  display: flex;
  font-size: 0.71875rem;
  gap: 0.6rem;
  justify-content: space-between;
  margin-bottom: 0.2rem;
}

.pos-receipt__total-row.is-discount {
  color: var(--bs-danger);
}

.pos-receipt__total-row.is-tax {
  color: #1a4a7a;
  font-style: italic;
}

.pos-receipt__total-row.is-grand {
  border-top: 1px dashed var(--bs-border-color);
  color: var(--bs-body-color);
  font-size: 0.9375rem;
  font-weight: 700;
  margin-bottom: 0;
  margin-top: 0.4rem;
  padding-top: 0.4rem;
}

/* Amount in words */
.pos-receipt__words {
  border-top: 1px dashed var(--bs-border-color);
  color: var(--bs-secondary-color);
  font-size: 0.6875rem;
  font-style: italic;
  padding: 0.5rem 0;
  word-break: break-word;
}

/* Footer */
.pos-receipt__footer {
  border-top: 1px dashed var(--bs-border-color);
  padding-top: 0.6rem;
  text-align: center;
}

.pos-receipt__footer p {
  color: var(--bs-secondary-color);
  font-size: 0.71875rem;
  margin: 0 0 0.15rem;
}

.pos-receipt__footer-note {
  color: var(--bs-body-color) !important;
  font-weight: 600;
}
</style>
