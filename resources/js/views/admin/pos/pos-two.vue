<template>
  <pos-header></pos-header>

  <div class="page-wrapper pos-pg-wrapper ms-0">
    <div class="content pos-design p-0">
      <div class="row g-0 pos-wrapper" style="min-height: calc(100vh - 60px);">

        <!-- ── Left: Product Search + Line Items ───────────────── -->
        <div class="col-lg-8 col-xl-9 d-flex flex-column" style="overflow-y:auto; max-height:calc(100vh - 60px);">
          <div class="p-3 flex-grow-1 d-flex flex-column">

            <!-- Top action bar -->
            <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
              <a class="btn btn-teal btn-sm"
                 href="javascript:void(0);"
                 @click="posStore.fetchHeldOrders(); posStore.openModal('orders')">
                <i class="ti ti-shopping-cart me-1"></i>Held Orders
                <span v-if="posStore.heldOrders.length" class="badge bg-white text-teal ms-1">{{ posStore.heldOrders.length }}</span>
              </a>
              <a class="btn btn-info btn-sm" href="javascript:void(0);" @click="posStore.openModal('recents')">
                <i class="ti ti-refresh-dot me-1"></i>Transactions
              </a>
              <a class="btn btn-secondary btn-sm"
                 href="javascript:void(0);"
                 :class="{ disabled: !posStore.cart.length }"
                 @click="posStore.cart.length && posStore.openModal('reset')">
                <i class="ti ti-reload me-1"></i>Reset
              </a>
              <div v-if="posStore.cartWarehouseSummary.length" class="ms-auto d-flex flex-wrap gap-1 justify-content-end">
                <span
                  v-for="wh in posStore.cartWarehouseSummary"
                  :key="wh.warehouseId"
                  class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 py-2 px-3"
                >
                  <i class="ti ti-building-warehouse"></i>
                  {{ wh.warehouseName }}
                  <span class="text-muted">({{ wh.lineCount }})</span>
                </span>
              </div>
            </div>

            <!-- Category Quick-Filter Tabs -->
            <div v-if="posStore.categories.length" class="pos-category-tabs mb-2">
              <button
                type="button"
                class="pos-category-tab"
                :class="{ active: selectedCategoryId === null }"
                @click="selectedCategoryId = null"
              >All</button>
              <button
                v-for="cat in posStore.categories"
                :key="cat.id"
                type="button"
                class="pos-category-tab"
                :class="{ active: selectedCategoryId === cat.id }"
                @click="selectedCategoryId = cat.id"
              >{{ cat.name }}</button>
            </div>

            <!-- Product Search + grid toggle -->
            <div class="mb-2 d-flex gap-2 align-items-center">
              <div class="flex-grow-1 position-relative">
                <ProductVariantSearchInput saleable-only
                  ref="productSearch"
                  physical-only
                  show-stock
                  placeholder="Search product by name, SKU or scan barcode — Enter to add  [F2]"
                  :category-id="selectedCategoryId"
                  @select="onVariantSelected"
                />
              </div>
              <button
                type="button"
                class="pos-grid-toggle flex-shrink-0 mt-4"
                :class="{ 'is-on': showGrid }"
                title="Toggle product grid"
                @click="showGrid = !showGrid"
              >
                <i class="ti ti-layout-grid"></i>
              </button>
            </div>

            <!-- Product Quick-Add Grid -->
            <div v-if="showGrid" class="pos-grid mb-3">
              <div v-if="posStore.gridLoading" class="pos-grid__loading">
                <div v-for="n in 8" :key="n" class="pos-grid-card pos-grid-card--skeleton">
                  <div class="pos-grid-card__img-placeholder"></div>
                  <div class="pos-grid-card__body">
                    <div class="pos-grid-card__skeleton-line"></div>
                    <div class="pos-grid-card__skeleton-line short"></div>
                  </div>
                </div>
              </div>
              <template v-else-if="posStore.gridProducts.length">
                <button
                  v-for="product in posStore.gridProducts"
                  :key="product.id"
                  type="button"
                  class="pos-grid-card"
                  :class="{
                    'is-out': product.stock <= 0,
                    'is-low': product.stock > 0 && product.stock <= 5,
                  }"
                  @click="onVariantSelected(product)"
                >
                  <div class="pos-grid-card__img">
                    <img v-if="product.image" :src="product.image" :alt="product.name" />
                    <i v-else class="ti ti-package"></i>
                  </div>
                  <div class="pos-grid-card__body">
                    <div class="pos-grid-card__name">{{ product.name }}</div>
                    <div class="pos-grid-card__price">{{ formatMoney(product.sales_price) }}</div>
                    <span
                      class="pos-grid-card__stock"
                      :class="{
                        'out': product.stock <= 0,
                        'low': product.stock > 0 && product.stock <= 5,
                      }"
                    >
                      {{ product.stock <= 0 ? 'Out' : product.stock <= 5 ? `Low: ${product.stock}` : `${product.stock}` }}
                    </span>
                  </div>
                </button>
              </template>
              <div v-else class="pos-grid__empty">
                <i class="ti ti-box-off"></i>
                <span>No products found</span>
              </div>
            </div>

            <!-- Line Items Table -->
            <div class="pos-cart-panel flex-grow-1 d-flex flex-column">
              <div class="table-responsive pos-cart-scroll flex-grow-1">
                <table class="table pos-cart-table mb-0 align-middle">
                  <thead>
                    <tr>
                      <th class="pos-col-index">#</th>
                      <th class="pos-col-product">Product</th>
                      <th class="pos-col-qty text-center">Qty</th>
                      <th class="pos-col-rate text-end">Rate</th>
                      <th class="pos-col-discount">Discount</th>
                      <th class="pos-col-tax">Tax</th>
                      <th class="pos-col-total text-end">Total</th>
                      <th class="pos-col-action"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-if="!posStore.cart.length">
                      <td colspan="8">
                        <div class="pos-cart-empty">
                          <i class="ti ti-shopping-cart"></i>
                          <p class="mb-0 fw-medium">No products added yet</p>
                          <span class="text-muted small">Search or scan a barcode above to start the order</span>
                        </div>
                      </td>
                    </tr>
                    <tr v-for="(item, index) in posStore.cart" :key="item.lineKey" class="pos-cart-row">
                      <td class="text-muted pos-col-index">{{ index + 1 }}</td>
                      <td class="pos-col-product">
                        <div class="pos-cart-product__name">{{ item.name }}</div>
                        <div class="pos-cart-product__meta">
                          <span v-if="item.sku" class="pos-cart-product__sku">{{ item.sku }}</span>
                          <span class="pos-cart-product__wh">
                            <i class="ti ti-building-warehouse"></i>
                            {{ item.warehouseName || '—' }}
                          </span>
                          <span
                            class="pos-cart-product__stock"
                            :class="{ 'is-over': item.stock !== null && item.quantity > item.stock }"
                          >
                            Stock {{ item.stock ?? '—' }}
                          </span>
                        </div>
                      </td>
                      <td class="pos-col-qty">
                        <div class="pos-qty-group">
                          <button type="button" class="pos-qty-btn" @click="posStore.setCartItemQty(item.lineKey, Math.max(1, item.quantity - 1))">−</button>
                          <input
                            type="number"
                            class="form-control form-control-sm pos-cart-input pos-cart-input--qty text-center"
                            :class="{ 'is-invalid': item.stock !== null && item.quantity > item.stock }"
                            :value="item.quantity"
                            min="1"
                            :max="item.stock ?? undefined"
                            @change="e => posStore.setCartItemQty(item.lineKey, parseInt(e.target.value) || 1)"
                          />
                          <button type="button" class="pos-qty-btn" @click="posStore.setCartItemQty(item.lineKey, item.quantity + 1)">+</button>
                        </div>
                      </td>
                      <td class="pos-col-rate">
                        <input
                          type="number"
                          class="form-control form-control-sm pos-cart-input pos-cart-input--rate text-end"
                          :value="item.rate"
                          min="0"
                          step="0.01"
                          @change="e => posStore.updateCartItem(item.lineKey, { rate: parseFloat(e.target.value) || 0 })"
                        />
                      </td>
                      <td class="pos-col-discount">
                        <VDiscountAmountTypeGroup
                          :input-id="`pos_line_disc_${item.lineKey}`"
                          :model-value="item.line_discount_value"
                          :discount-type="item.line_discount_type || 'fixed'"
                          selector-mode="toggle"
                          compact-toggle
                          extra-group-class="pos-line-discount-group"
                          @update:model-value="v => onLineDiscountValue(item.lineKey, v)"
                          @update:discount-type="t => onLineDiscountType(item.lineKey, t)"
                        />
                      </td>
                      <td class="pos-col-tax">
                        <VMultiselect
                          size="sm"
                          :model-value="item.taxId ?? ''"
                          :options="lineTaxOptions"
                          placeholder="None"
                          :append-to-body="true"
                          @update:model-value="v => posStore.onLineTaxChange(item.lineKey, v)"
                        />
                        <span v-if="item.taxAmount > 0" class="pos-cart-tax-amt">{{ formatMoney(item.taxAmount) }}</span>
                      </td>
                      <td class="pos-col-total text-end">
                        <span class="pos-cart-line-total">{{ formatMoney(calcLineTotal(item, index)) }}</span>
                      </td>
                      <td class="pos-col-action text-center">
                        <button
                          type="button"
                          class="btn btn-sm pos-cart-remove"
                          title="Remove line"
                          @click="posStore.removeFromCart(item.lineKey)"
                        >
                          <i class="ti ti-trash"></i>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div v-if="posStore.cart.length" class="pos-cart-summary">
                <div class="pos-cart-summary__row">
                  <span>Subtotal</span>
                  <span>{{ formatMoney(posStore.subtotal) }}</span>
                </div>
                <div v-if="posStore.lineDiscountTotal > 0" class="pos-cart-summary__row is-discount">
                  <span>Line discount</span>
                  <span>-{{ formatMoney(posStore.lineDiscountTotal) }}</span>
                </div>
                <div v-if="posStore.orderDiscountTotal > 0" class="pos-cart-summary__row is-discount">
                  <span>Order discount</span>
                  <span>-{{ formatMoney(posStore.orderDiscountTotal) }}</span>
                </div>
                <div v-if="posStore.taxTotal > 0" class="pos-cart-summary__row">
                  <span>Tax</span>
                  <span>{{ formatMoney(posStore.taxTotal) }}</span>
                </div>
                <div class="pos-cart-summary__row is-total">
                  <span>Grand total</span>
                  <span>{{ formatMoney(posStore.grandTotal) }}</span>
                </div>
              </div>
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
                  <Multiselect
                    :options="customerOptions"
                    v-model="selectedCustomerOption"
                    placeholder="Walk-in Customer"
                    :searchable="true"
                    :filter-results="false"
                    @search-change="onCustomerSearch"
                    @update:modelValue="onCustomerSelect"
                  />
                </div>
                <a class="btn btn-primary btn-icon flex-shrink-0"
                   href="javascript:void(0);"
                   title="Add Customer"
                   @click="posStore.openModal('createCustomer')">
                  <vue-feather type="user-plus" class="feather-16"></vue-feather>
                </a>
              </div>
            </div>

            <!-- Order Discount -->
            <div>
              <label class="form-label fw-semibold mb-1 small text-uppercase text-muted ls-1">Order Discount</label>
              <VDiscountAmountTypeGroup
                input-id="pos_order_discount"
                :model-value="posStore.order_discount_value"
                :discount-type="posStore.order_discount_type"
                selector-mode="toggle"
                @update:model-value="onOrderDiscountValue"
                @update:discount-type="onOrderDiscountType"
              />
            </div>

            <!-- Order Summary -->
            <div class="rounded border p-3 bg-light">
              <p class="fw-semibold mb-2 small text-uppercase text-muted ls-1">Order Summary</p>
              <table class="table table-borderless table-sm mb-0">
                <tbody>
                  <tr>
                    <td class="ps-0 text-muted py-1">Subtotal</td>
                    <td class="pe-0 text-end py-1">{{ formatMoney(posStore.subtotal) }}</td>
                  </tr>
                  <tr v-if="posStore.lineDiscountTotal > 0">
                    <td class="ps-0 text-danger py-1">Line Discount</td>
                    <td class="pe-0 text-danger text-end py-1">-{{ formatMoney(posStore.lineDiscountTotal) }}</td>
                  </tr>
                  <tr v-if="posStore.orderDiscountTotal > 0">
                    <td class="ps-0 text-danger py-1">Order Discount</td>
                    <td class="pe-0 text-danger text-end py-1">-{{ formatMoney(posStore.orderDiscountTotal) }}</td>
                  </tr>
                  <tr v-if="posStore.discountTotal > 0">
                    <td class="ps-0 text-danger py-1 fw-medium">Total Discount</td>
                    <td class="pe-0 text-danger text-end py-1 fw-medium">-{{ formatMoney(posStore.discountTotal) }}</td>
                  </tr>
                  <tr v-if="posStore.taxTotal > 0">
                    <td class="ps-0 text-muted py-1">Tax</td>
                    <td class="pe-0 text-end py-1">{{ formatMoney(posStore.taxTotal) }}</td>
                  </tr>
                  <tr class="border-top">
                    <td class="ps-0 fw-bold pt-2">Total</td>
                    <td class="pe-0 fw-bold text-end text-primary fs-16 pt-2">{{ formatMoney(posStore.grandTotal) }}</td>
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
                  <!-- Non-bank modes: render individual tiles as before -->
                  <div v-for="mode in nonBankModes" :key="mode.id" class="col-6">
                    <a
                      href="javascript:void(0);"
                      class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none"
                      :class="selectedPaymentModeId === mode.id
                        ? 'bg-primary border-primary text-white'
                        : 'bg-white text-body'"
                      style="cursor:pointer; transition:all .15s; min-height:58px;"
                      @click="selectPaymentMode(mode)"
                    >
                      <i :class="paymentModeIcon(mode.name)" class="fs-20 mb-1"></i>
                      <span class="small lh-sm text-center">{{ mode.name }}</span>
                    </a>
                  </div>

                  <!-- Bank-linked modes: single grouped "Bank Transfer" tile -->
                  <div v-if="bankLinkedModes.length" class="col-6">
                    <a
                      href="javascript:void(0);"
                      class="d-flex flex-column align-items-center justify-content-center border rounded py-2 px-1 text-decoration-none position-relative"
                      :class="isBankLinkedModeSelected
                        ? 'bg-primary border-primary text-white'
                        : 'bg-white text-body'"
                      style="cursor:pointer; transition:all .15s; min-height:58px;"
                      @click="openBankPicker()"
                    >
                      <i class="ti ti-building-bank fs-20 mb-1"></i>
                      <span class="small lh-sm text-center fw-semibold">
                        {{ isBankLinkedModeSelected && selectedBankAccount ? selectedBankAccount.bank_name : 'Bank Transfer' }}
                      </span>
                      <span
                        v-if="isBankLinkedModeSelected && selectedBankAccount"
                        class="text-center lh-1 opacity-75"
                        style="font-size:10px;"
                      >{{ selectedBankAccount.account_number }}</span>
                      <span
                        v-if="isBankLinkedModeSelected"
                        class="position-absolute top-0 end-0 p-1 opacity-50"
                        style="font-size:9px;"
                      ><i class="ti ti-edit"></i></span>
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
                class="btn btn-success w-100 btn-lg fw-bold d-flex align-items-center justify-content-center gap-2"
                :disabled="!posStore.canCheckout || posStore.checkoutLoading"
                @click="openPaymentModal"
              >
                <span v-if="posStore.checkoutLoading" class="spinner-border spinner-border-sm"></span>
                <i v-else class="ti ti-cash-banknote"></i>
                <span>{{ posStore.checkoutLoading ? 'Processing...' : `Pay  ${formatMoney(posStore.grandTotal)}` }}</span>
                <kbd class="pos-kbd ms-auto opacity-75">F4</kbd>
              </button>
              <!-- Credit Sale + Split Payment shortcuts -->
              <div class="d-flex gap-2">
                <button
                  type="button"
                  class="btn btn-warning flex-fill"
                  :disabled="!posStore.canCheckout || posStore.checkoutLoading"
                  title="Sell on credit (Udhaaro)"
                  @click="posStore.openModal('creditSale')"
                >
                  <i class="ti ti-clock-dollar me-1"></i>Credit
                </button>
                <button
                  type="button"
                  class="btn btn-info flex-fill"
                  :disabled="!posStore.canCheckout || posStore.checkoutLoading"
                  title="Split across multiple payment methods"
                  @click="posStore.openModal('splitPayment')"
                >
                  <i class="ti ti-arrows-split me-1"></i>Split
                </button>
                <button
                  type="button"
                  class="btn flex-fill"
                  :class="posStore.hasVat ? 'btn-primary' : 'btn-outline-secondary'"
                  :disabled="!posStore.cart.length"
                  @click="toggleVat"
                  title="Toggle VAT (13%) on all items"
                >
                  <i class="ti ti-receipt-tax me-1"></i>VAT
                </button>
              </div>
              <div class="d-flex gap-2">
                <button
                  type="button"
                  class="btn btn-purple flex-fill"
                  :disabled="!posStore.canCheckout"
                  @click="posStore.openModal('holdOrder')"
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
    :payment-mode-id="selectedPaymentModeId"
    :last-sale="posStore.lastSale"
    :held-orders="posStore.heldOrders"
    :today-summary="posStore.todaySummary"
    :payment-modes="posStore.paymentModes"
    :till-session="posStore.tillSession"
    :checkout-loading="posStore.checkoutLoading"
    @checkout="processCheckout"
    @clear-cart="onNextOrder"
    @hold="onHold"
    @restore-held-order="onRestoreHeldOrder"
    @delete-held-order="onDeleteHeldOrder"
    @customer-created="onCustomerCreated"
    @till-opened="onTillOpened"
    @till-closed="onTillClosed"
    @return-processed="onReturnProcessed"
  ></pos-two-modal>

  <WarehousePickerModal
    ref="warehousePicker"
    modal-id="pos-warehouse-picker"
    confirm-label="Add to Order"
    @confirm="onWarehousePicked"
  />

  <!-- Bank Account Picker -->
  <VModal
    :show-modal="showBankPicker"
    size="md"
    title="Select Bank Account"
    @close-click="showBankPicker = false"
  >
    <template #modal-body>
      <div class="row g-3 p-2">
        <div
          v-for="mode in bankLinkedModes"
          :key="mode.id"
          class="col-6"
        >
          <a
            href="javascript:void(0);"
            class="bank-picker-card d-flex flex-column align-items-center text-center text-decoration-none rounded-3 p-3 position-relative"
            :class="selectedPaymentModeId === mode.id ? 'bank-picker-card--selected' : ''"
            @click="onBankAccountSelected(mode)"
          >
            <!-- Selected checkmark badge -->
            <span
              v-if="selectedPaymentModeId === mode.id"
              class="position-absolute top-0 end-0 m-2 d-flex align-items-center justify-content-center bg-primary rounded-circle text-white"
              style="width:20px; height:20px; font-size:11px;"
            ><i class="ti ti-check"></i></span>

            <!-- Bank icon -->
            <div
              class="bank-picker-card__icon d-flex align-items-center justify-content-center rounded-circle mb-2"
              :class="selectedPaymentModeId === mode.id ? 'bg-primary' : 'bg-primary bg-opacity-10'"
              style="width:52px; height:52px;"
            >
              <i
                class="ti ti-building-bank fs-22"
                :class="selectedPaymentModeId === mode.id ? 'text-white' : 'text-primary'"
              ></i>
            </div>

            <!-- Bank name -->
            <div class="fw-semibold lh-sm mb-1" style="font-size:13px;">{{ mode.bank_account.bank_name }}</div>

            <!-- Account number -->
            <div class="text-muted" style="font-size:11px; letter-spacing:.5px;">{{ mode.bank_account.account_number }}</div>

            <!-- Mode label -->
            <span class="badge bg-light text-secondary border mt-2" style="font-size:10px;">{{ mode.name }}</span>
          </a>
        </div>
      </div>
    </template>
  </VModal>
</template>

<script>
import {formatMoney} from '@/helpers/formatMoney.js';
import ProductVariantSearchInput from '@/components/inventory/ProductVariantSearchInput.vue';
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';
import VModal from '@/components/base/VModal.vue';
import PosHeader from '@/layouts/pos-header.vue';
import PosTwoModal from '@/components/modal/pos-two-modal.vue';
import WarehousePickerModal from '@/components/modal/WarehousePickerModal.vue';
import { usePosStore } from '@/stores/admin/pos/pos.js';
import { useToast } from 'vue-toastification';
import showErrors from '@/helpers/showErrors.js';
import {
    buildOrderAllocations,
    lineNetFromItem,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';

export default {
  components: {
    PosHeader,
    PosTwoModal,
    WarehousePickerModal,
    ProductVariantSearchInput,
    VDiscountAmountTypeGroup,
    VModal,
  },

  setup() {
    const posStore = usePosStore();

    return { posStore };
  },

  data() {
    return {
      selectedCustomerOption: null,
      selectedPaymentMethod: 'cash',
      selectedPaymentModeId: null,
      selectedBankAccount: null,
      showBankPicker: false,
      pendingVariant: null,
      selectedCategoryId: null,
      showGrid: true,
    };
  },

  watch: {
    selectedCategoryId(categoryId) {
      this.posStore.fetchGridProducts(categoryId);
    },
  },

  computed: {
    customerOptions() {
      return this.posStore.customers.map((c) => ({
        label: c.name + (c.phone ? ` (${c.phone})` : ''),
        value: c.id,
        customer: c,
      }));
    },

    bankLinkedModes() {
      return this.posStore.paymentModes.filter((m) => m.bank_account !== null);
    },

    nonBankModes() {
      return this.posStore.paymentModes.filter((m) => m.bank_account === null);
    },

    isBankLinkedModeSelected() {
      return this.bankLinkedModes.some((m) => m.id === this.selectedPaymentModeId);
    },

    lineTaxOptions() {
      return [
        { id: '', name: 'None' },
        ...this.posStore.taxes.map((t) => ({
          id: t.id,
          name: `${t.name} (${t.rate}%)`,
        })),
      ];
    },
  },

  async mounted() {
    await this.posStore.init();
    this.posStore.fetchGridProducts(null);
    this.$refs.productSearch?.focus();
    // Prompt cashier to open till if no active session
    if (!this.posStore.tillSession) {
      await this.$nextTick();
      this.posStore.openModal('tillOpen');
    }
    document.addEventListener('keydown', this.onGlobalKeydown);
  },

  beforeUnmount() {
    document.removeEventListener('keydown', this.onGlobalKeydown);
  },

  methods: {
    formatMoney,

    onGlobalKeydown(e) {
      // Ignore when typing inside an input/textarea/select (except F-keys)
      const inField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

      if (e.key === 'F2') {
        e.preventDefault();
        this.$refs.productSearch?.focus();
        return;
      }

      if (e.key === 'F4') {
        e.preventDefault();
        if (this.posStore.canCheckout && !this.posStore.checkoutLoading) {
          this.openPaymentModal();
        }
        return;
      }

      if (e.key === 'F8') {
        e.preventDefault();
        if (this.posStore.canCheckout) {
          this.posStore.openModal('holdOrder');
        }
        return;
      }

      if (inField) {
        return;
      }

      // +/= → increment last cart item qty
      if ((e.key === '+' || e.key === '=' || e.key === 'NumpadAdd') && this.posStore.cart.length) {
        const last = this.posStore.cart[this.posStore.cart.length - 1];
        this.posStore.setCartItemQty(last.lineKey, last.quantity + 1);
        return;
      }

      // - → decrement last cart item qty
      if ((e.key === '-' || e.key === 'NumpadSubtract') && this.posStore.cart.length) {
        const last = this.posStore.cart[this.posStore.cart.length - 1];
        this.posStore.setCartItemQty(last.lineKey, Math.max(1, last.quantity - 1));
      }
    },

    calcLineTotal(item, index) {
      const calcItem = {
        quantity: item.quantity,
        rate: item.rate,
        line_discount_type: item.line_discount_type || 'fixed',
        line_discount_value: item.line_discount_value ?? '0',
      };
      const nets = this.posStore.cart.map((i) => lineNetFromItem({
        quantity: i.quantity,
        rate: i.rate,
        line_discount_type: i.line_discount_type || 'fixed',
        line_discount_value: i.line_discount_value ?? '0',
      }));
      const sumLineNet = nets.reduce((a, b) => a + b, 0);
      const orderDisc = orderDiscountMoney(
        sumLineNet,
        this.posStore.order_discount_type,
        this.posStore.order_discount_value,
      );
      const allocs = buildOrderAllocations(nets, orderDisc);
      const lineNet = lineNetFromItem(calcItem);
      const afterOrder = Math.max(0, lineNet - (allocs[index] || 0));

      return afterOrder + (item.taxAmount ?? 0);
    },

    async onVariantSelected(variant) {
      const result = await this.posStore.addVariantToCart(variant);

      if (result.needsWarehousePick) {
        this.pendingVariant = result.variant;
        this.$refs.warehousePicker?.open({
          options: result.options,
          variantName: result.variant.name,
        });

        return;
      }

      if (!result.success) {
        const messages = {
          out_of_stock: 'Product is out of stock in all warehouses.',
          insufficient_stock: `Only ${result.stock} unit(s) available in this warehouse.`,
          fetch_failed: 'Could not load warehouse stock.',
          service_not_allowed: 'Services cannot be sold through POS. Use a sales invoice instead.',
        };
        useToast().warning(messages[result.error] ?? 'Could not add product to cart.');

        return;
      }

      this.$refs.productSearch?.focus();
    },

    onWarehousePicked(opt) {
      if (!this.pendingVariant) {
        return;
      }
      const result = this.posStore.completeAddWithWarehouse(
        this.pendingVariant,
        opt.warehouse_id,
        opt.warehouse_name,
        opt.quantity,
      );
      this.pendingVariant = null;

      if (!result.success) {
        useToast().warning('Could not add product to cart.');
      } else {
        this.$refs.productSearch?.focus();
      }
    },

    onLineDiscountValue(lineKey, value) {
      const item = this.posStore.findCartLine(lineKey);
      if (item) {
        this.posStore.updateLineDiscount(lineKey, item.line_discount_type, value);
      }
    },

    onLineDiscountType(lineKey, type) {
      const item = this.posStore.findCartLine(lineKey);
      if (item) {
        this.posStore.updateLineDiscount(lineKey, type, item.line_discount_value);
      }
    },

    onOrderDiscountValue(value) {
      this.posStore.setOrderDiscount(this.posStore.order_discount_type, value);
    },

    onOrderDiscountType(type) {
      this.posStore.setOrderDiscount(type, this.posStore.order_discount_value);
    },

    onCustomerSearch(query) {
      this.posStore.fetchCustomers(query);
    },

    resolveCustomerFromSelect(selected) {
      if (selected == null || selected === '') {
        return null;
      }

      if (typeof selected === 'object' && selected.customer) {
        return selected.customer;
      }

      const id = typeof selected === 'object' ? selected.value : selected;

      return this.posStore.customers.find((c) => c.id === id) ?? null;
    },

    onCustomerSelect(selected) {
      this.posStore.setCustomer(this.resolveCustomerFromSelect(selected));
    },

    selectPaymentMode(mode) {
      this.selectedPaymentMethod = mode.name;
      this.selectedPaymentModeId = mode.id;
      this.selectedBankAccount = null;
    },

    openBankPicker() {
      if (this.bankLinkedModes.length === 1) {
        this.onBankAccountSelected(this.bankLinkedModes[0]);
        return;
      }
      this.showBankPicker = true;
    },

    onBankAccountSelected(mode) {
      this.selectedPaymentMethod = mode.name;
      this.selectedPaymentModeId = mode.id;
      this.selectedBankAccount = mode.bank_account;
      this.showBankPicker = false;
      this.openPaymentModal();
    },

    paymentModeIcon(name) {
      const n = (name || '').toLowerCase();
      if (n.includes('cash')) {
        return 'ti ti-cash-banknote';
      }
      if (n.includes('card') || n.includes('debit') || n.includes('credit')) {
        return 'ti ti-credit-card';
      }
      if (n.includes('scan') || n.includes('qr')) {
        return 'ti ti-scan';
      }
      if (n.includes('bank') || n.includes('transfer')) {
        return 'ti ti-building-bank';
      }

      return 'ti ti-wallet';
    },

    openPaymentModal() {
      if (!this.posStore.cart.length) {
        useToast().warning('Cart is empty');
        return;
      }
      if (!this.posStore.canCheckout) {
        useToast().warning('Each line must have a warehouse assigned');
        return;
      }

      const overStock = this.posStore.cart.some(
        (item) => item.stock !== null && item.quantity > item.stock,
      );
      if (overStock) {
        useToast().warning('One or more items exceed available stock');
        return;
      }

      const name = (this.selectedPaymentMethod || 'cash').toLowerCase();
      let modalName = 'paymentCash';
      if (name.includes('card') || name.includes('debit') || name.includes('credit')) {
        modalName = 'paymentCard';
      } else if (name.includes('scan') || name.includes('qr')) {
        modalName = 'scanPayment';
      } else if (!name.includes('cash')) {
        modalName = 'paymentGeneric';
      }
      this.posStore.openModal(modalName);
    },

    async processCheckout(paymentMethod, splitPayments = null, paymentModeId = null) {
      if (!this.posStore.cart.length) {
        return;
      }
      try {
        await this.posStore.checkout(
          paymentMethod ?? this.selectedPaymentMethod,
          splitPayments,
          paymentModeId ?? this.selectedPaymentModeId,
        );
        useToast().success('Sale completed successfully!');
        this.posStore.openModal('paymentCompleted');
        this.posStore.clearCart();
        this.selectedCustomerOption = null;
        this.$refs.productSearch?.focus();
        this.posStore.fetchGridProducts(this.selectedCategoryId);
      } catch (err) {
        showErrors(err);
      }
    },

    onNextOrder() {
      this.selectedCustomerOption = null;
      this.$refs.productSearch?.focus();
    },

    toggleVat() {
      if (!this.posStore.taxes.length) {
        useToast().warning('No tax rates configured. Add a 13% VAT tax in Settings first.');
        return;
      }
      this.posStore.toggleVat();
      const msg = this.posStore.hasVat ? 'VAT (13%) added to all items' : 'VAT removed from all items';
      useToast().info(msg);
    },

    voidOrder() {
      if (!this.posStore.cart.length) {
        return;
      }
      this.posStore.clearCart();
      this.selectedCustomerOption = null;
      useToast().info('Order voided');
    },

    async onHold(label) {
      if (!this.posStore.canCheckout) {
        useToast().warning('Add products before holding the order');
        return;
      }
      try {
        await this.posStore.holdCurrentOrder(label);
        this.selectedCustomerOption = null;
        useToast().success('Order held successfully');
      } catch (err) {
        showErrors(err);
      }
    },

    onRestoreHeldOrder(order) {
      this.posStore.restoreHeldOrder(order);
      this.posStore.deleteHeldOrder(order.id);
      if (this.posStore.selectedCustomer) {
        this.selectedCustomerOption = this.posStore.selectedCustomer.id;
      }
      useToast().info('Order restored');
    },

    onDeleteHeldOrder(id) {
      this.posStore.deleteHeldOrder(id);
    },

    onCustomerCreated(customer) {
      this.posStore.customers.unshift(customer);
      this.posStore.setCustomer(customer);
      this.selectedCustomerOption = customer.id;
    },

    onTillOpened(session) {
      this.posStore.tillSession = session;
    },

    onTillClosed() {
      this.posStore.tillSession = null;
    },

    onReturnProcessed() {
      this.posStore.fetchGridProducts(this.selectedCategoryId);
    },
  },
};
</script>

<style scoped>
.pos-cart-panel {
  border: 1px solid var(--bs-border-color);
  border-radius: 0.75rem;
  background: var(--bs-body-bg);
  overflow: hidden;
  min-height: 280px;
}

.pos-cart-scroll {
  max-height: calc(100vh - 280px);
}

.pos-cart-table {
  --bs-table-bg: transparent;
  margin-bottom: 0;
}

.pos-cart-table thead {
  position: sticky;
  top: 0;
  z-index: 2;
}

.pos-cart-table thead th {
  background: var(--bs-light, #f8f9fa);
  border-bottom: 1px solid var(--bs-border-color);
  color: var(--bs-secondary-color);
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  padding: 0.65rem 0.75rem;
  text-transform: uppercase;
  white-space: nowrap;
}

.pos-cart-table tbody td {
  border-bottom: 1px solid rgba(var(--bs-border-color-rgb, 222, 226, 230), 0.65);
  padding: 0.75rem;
  vertical-align: middle;
}

.pos-cart-row:hover {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.03);
}

.pos-col-index {
  width: 2.25rem;
  padding-left: 1rem !important;
}

.pos-col-product {
  min-width: 180px;
}

.pos-col-qty {
  width: 7.5rem;
}

.pos-col-rate {
  width: 6.5rem;
}

.pos-col-discount {
  min-width: 8.25rem;
}

.pos-col-tax {
  min-width: 9rem;
}

.pos-col-total {
  width: 6.5rem;
  white-space: nowrap;
}

.pos-col-action {
  width: 2.5rem;
  padding-right: 1rem !important;
}

.pos-cart-product__name {
  color: var(--bs-body-color);
  font-size: 0.875rem;
  font-weight: 600;
  line-height: 1.35;
}

.pos-cart-product__meta {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.5rem;
  margin-top: 0.35rem;
}

.pos-cart-product__sku,
.pos-cart-product__wh,
.pos-cart-product__stock {
  align-items: center;
  background: var(--bs-light, #f8f9fa);
  border-radius: 999px;
  color: var(--bs-secondary-color);
  display: inline-flex;
  font-size: 0.6875rem;
  gap: 0.2rem;
  line-height: 1;
  padding: 0.2rem 0.5rem;
}

.pos-cart-product__wh {
  color: var(--bs-body-color);
}

.pos-cart-product__stock.is-over {
  background: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.1);
  color: var(--bs-danger);
  font-weight: 600;
}

.pos-cart-input,
.pos-cart-select,
.pos-col-tax .multiselect {
  background-color: var(--bs-body-bg);
  border-color: rgba(var(--bs-border-color-rgb, 222, 226, 230), 0.9);
  border-radius: 0.45rem;
  font-size: 0.8125rem;
}

.pos-cart-input:focus,
.pos-cart-select:focus,
.pos-col-tax .multiselect.is-active {
  border-color: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.45);
  box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb, 13, 110, 253), 0.12);
}

.pos-cart-input--qty {
  margin: 0 auto;
  max-width: 4rem;
  padding-left: 0.25rem;
  padding-right: 0.25rem;
}

.pos-cart-input--rate {
  margin-left: auto;
  max-width: 6rem;
}

.pos-cart-tax-amt {
  color: var(--bs-secondary-color);
  display: block;
  font-size: 0.6875rem;
  margin-top: 0.25rem;
  text-align: left;
}

.pos-cart-line-total {
  color: var(--bs-body-color);
  font-size: 0.875rem;
  font-weight: 700;
}

.pos-cart-remove {
  border: 0;
  color: var(--bs-secondary-color);
  line-height: 1;
  padding: 0.25rem;
}

.pos-cart-remove:hover {
  background: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.08);
  color: var(--bs-danger);
}

.pos-cart-empty {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  justify-content: center;
  min-height: 220px;
  padding: 2rem 1rem;
  text-align: center;
}

.pos-cart-empty i {
  color: rgba(var(--bs-secondary-rgb, 108, 117, 125), 0.35);
  font-size: 2.5rem;
  margin-bottom: 0.35rem;
}

.pos-cart-summary {
  background: var(--bs-light, #f8f9fa);
  border-top: 1px solid var(--bs-border-color);
  display: grid;
  gap: 0.35rem;
  padding: 0.85rem 1rem;
}

.pos-cart-summary__row {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  font-size: 0.8125rem;
  justify-content: space-between;
}

.pos-cart-summary__row.is-discount {
  color: var(--bs-danger);
}

.pos-cart-summary__row.is-total {
  border-top: 1px dashed rgba(var(--bs-border-color-rgb, 222, 226, 230), 0.9);
  color: var(--bs-body-color);
  font-size: 0.9375rem;
  font-weight: 700;
  margin-top: 0.25rem;
  padding-top: 0.55rem;
}

:deep(.pos-line-discount-group) {
  max-width: 8.25rem;
}

:deep(.pos-line-discount-group .form-control) {
  min-width: 2.75rem;
  padding-left: 0.35rem;
  padding-right: 0.35rem;
}

:deep(.pos-line-discount-group .input-group) {
  flex-wrap: nowrap;
}

:deep(.pos-line-discount-group .v-discount-type-toggle) {
  flex-shrink: 0;
}

/* Category quick-filter tabs */
.pos-category-tabs {
  display: flex;
  flex-wrap: nowrap;
  gap: 0.35rem;
  overflow-x: auto;
  padding-bottom: 0.25rem;
  scrollbar-width: none;
}

.pos-category-tabs::-webkit-scrollbar {
  display: none;
}

.pos-category-tab {
  background: var(--bs-white);
  border: 1px solid var(--bs-border-color);
  border-radius: 999px;
  color: var(--bs-body-color);
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 500;
  padding: 0.2rem 0.75rem;
  transition: all 0.15s;
  white-space: nowrap;
}

.pos-category-tab:hover {
  background: var(--bs-light);
  border-color: var(--bs-secondary-color);
}

.pos-category-tab.active {
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  color: #fff;
}

/* Quick qty ±1 buttons */
.pos-qty-group {
  align-items: center;
  display: flex;
  gap: 0.2rem;
  justify-content: center;
}

.pos-qty-btn {
  background: var(--bs-light);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.35rem;
  color: var(--bs-body-color);
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 700;
  height: 1.75rem;
  line-height: 1;
  padding: 0 0.35rem;
  transition: all 0.12s;
}

.pos-qty-btn:hover {
  background: var(--bs-primary);
  border-color: var(--bs-primary);
  color: #fff;
}

/* Grid toggle button */
.pos-grid-toggle {
  align-items: center;
  background: var(--bs-white);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.45rem;
  color: var(--bs-secondary-color);
  cursor: pointer;
  display: flex;
  font-size: 1rem;
  height: 38px;
  justify-content: center;
  transition: all 0.15s;
  width: 38px;
}

.pos-grid-toggle:hover {
  border-color: var(--bs-primary);
  color: var(--bs-primary);
}

.pos-grid-toggle.is-on {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.08);
  border-color: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.4);
  color: var(--bs-primary);
}

/* Product quick-add grid */
.pos-grid {
  display: grid;
  gap: 0.5rem;
  grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
  max-height: 260px;
  overflow-y: auto;
  scrollbar-width: thin;
}

.pos-grid__loading {
  display: contents;
}

.pos-grid__empty {
  align-items: center;
  color: var(--bs-secondary-color);
  display: flex;
  font-size: 0.8125rem;
  gap: 0.4rem;
  grid-column: 1 / -1;
  justify-content: center;
  padding: 1.5rem 0;
}

.pos-grid-card {
  align-items: center;
  background: var(--bs-white);
  border: 1px solid var(--bs-border-color);
  border-radius: 0.5rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0;
  padding: 0.5rem 0.35rem 0.4rem;
  text-align: center;
  transition: border-color 0.12s, box-shadow 0.12s, background 0.12s;
}

.pos-grid-card:hover {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.04);
  border-color: var(--bs-primary);
  box-shadow: 0 0 0 0.15rem rgba(var(--bs-primary-rgb, 13, 110, 253), 0.12);
}

.pos-grid-card:active {
  background: rgba(var(--bs-primary-rgb, 13, 110, 253), 0.1);
  transform: scale(0.97);
}

.pos-grid-card.is-out {
  opacity: 0.5;
  pointer-events: none;
}

.pos-grid-card__img {
  align-items: center;
  border-radius: 0.35rem;
  color: var(--bs-secondary-color);
  display: flex;
  font-size: 1.5rem;
  height: 44px;
  justify-content: center;
  overflow: hidden;
  width: 100%;
}

.pos-grid-card__img img {
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.pos-grid-card__body {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  margin-top: 0.3rem;
  width: 100%;
}

.pos-grid-card__name {
  color: var(--bs-body-color);
  font-size: 0.6875rem;
  font-weight: 600;
  line-height: 1.2;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.pos-grid-card__price {
  color: var(--bs-primary);
  font-size: 0.75rem;
  font-weight: 700;
}

.pos-grid-card__stock {
  border-radius: 999px;
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  padding: 0.1rem 0.35rem;
  background: rgba(var(--bs-success-rgb, 25, 135, 84), 0.1);
  color: var(--bs-success);
}

.pos-grid-card__stock.low {
  background: rgba(var(--bs-warning-rgb, 255, 193, 7), 0.15);
  color: #b45309;
}

.pos-grid-card__stock.out {
  background: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.1);
  color: var(--bs-danger);
}

/* Skeleton loader */
.pos-grid-card--skeleton {
  animation: gridSkeletonPulse 1.2s ease-in-out infinite;
  pointer-events: none;
}

.pos-grid-card__img-placeholder {
  background: var(--bs-light);
  border-radius: 0.35rem;
  height: 44px;
  width: 100%;
}

.pos-grid-card__skeleton-line {
  background: var(--bs-light);
  border-radius: 4px;
  height: 8px;
  margin: 0.15rem auto;
  width: 85%;
}

.pos-grid-card__skeleton-line.short {
  width: 55%;
}

@keyframes gridSkeletonPulse {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.45; }
}

/* Keyboard shortcut badge */
.pos-kbd {
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 0.25rem;
  color: inherit;
  font-family: monospace;
  font-size: 0.65rem;
  padding: 0.1rem 0.3rem;
}

/* Bank Account Picker cards */
.bank-picker-card {
  border: 2px solid var(--bs-border-color);
  background: var(--bs-body-bg);
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.1s;
  min-height: 140px;
}

.bank-picker-card:hover {
  border-color: var(--bs-primary);
  box-shadow: 0 4px 16px rgba(var(--bs-primary-rgb), 0.12);
  transform: translateY(-2px);
}

.bank-picker-card--selected {
  border-color: var(--bs-primary) !important;
  box-shadow: 0 4px 20px rgba(var(--bs-primary-rgb), 0.18);
  background: rgba(var(--bs-primary-rgb), 0.03);
}
</style>
