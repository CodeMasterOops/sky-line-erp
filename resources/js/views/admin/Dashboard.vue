<template>
  <div class="dashboard-page">
    <header class="dashboard-header">
      <div>
        <h1 class="dashboard-header__title mb-1">Welcome, Admin</h1>
        <p class="dashboard-header__subtitle mb-0">
          Overview of your sales, purchases, inventory, and financial performance.
        </p>
      </div>
      <div class="input-icon-start position-relative dashboard-header__filter">
        <span class="input-icon-addon fs-16 text-gray-9">
          <i class="ti ti-calendar"></i>
        </span>
        <input
          type="text"
          class="form-control date-range bookingrange"
          ref="dateRangeInput"
          placeholder="Select Date Range"
          readonly
        />
      </div>
    </header>

    <div v-if="dashboardStore.isLoading" class="dashboard-loading">
      <span class="spinner-border text-primary"></span>
    </div>

    <template v-else>
      <!-- Business snapshot -->
      <section v-if="businessMetrics.length" class="dashboard-section">
        <div class="row g-2">
          <div
            v-for="metric in businessMetrics"
            :key="metric.label"
            class="col-xl-3 col-sm-6 col-12 d-flex"
          >
            <div :class="`card metric-card metric-card--${metric.cardColor} flex-fill`">
              <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                  <span class="metric-card__icon">
                    <i :class="`ti ${metric.icon} fs-24`"></i>
                  </span>
                  <div>
                    <p class="metric-card__label mb-1">{{ metric.label }}</p>
                    <h3 class="metric-card__count mb-0">{{ metric.displayValue }}</h3>
                  </div>
                </div>
                <router-link
                  v-if="isModuleEnabled(metric.routeModule)"
                  :to="metric.route"
                  class="metric-card__footer"
                >
                  View all <i class="ti ti-arrow-right fs-12"></i>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Quick links -->
      <section class="dashboard-section">
        <div class="card dashboard-panel quick-links">
          <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon fs-16 me-2"><i class="ti ti-pin"></i></span>
              <h5 class="card-title mb-0">Quick Links</h5>
            </div>
            <button type="button" class="quick-links__edit" @click="openLinksEditor">
              <i class="ti ti-pencil me-1"></i>Edit Links
            </button>
          </div>
          <div class="card-body">
            <div v-if="!pinnedLinks.length" class="dashboard-empty">
              No quick links pinned yet. Click “Edit Links” to add shortcuts to the pages you use most.
            </div>
            <div v-else class="row g-2">
              <div
                v-for="link in pinnedLinks"
                :key="link.routeName"
                class="col-xl-4 col-md-6 col-12 d-flex"
              >
                <router-link :to="{ name: link.routeName }" class="quick-link flex-fill">
                  <span class="quick-link__icon"><i :class="link.icon"></i></span>
                  <span class="quick-link__text min-w-0">
                    <span class="quick-link__title">{{ link.label }}</span>
                    <span class="quick-link__category">{{ link.category }}</span>
                  </span>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Analytics -->
      <section
        v-if="has('sales_purchase_chart') || has('sales_expense_chart')"
        class="dashboard-section"
      >
        <div class="row g-2">
          <div v-if="has('sales_purchase_chart')" class="col-xxl-7 col-xl-12 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-report-analytics"></i></span>
                  <h5 class="card-title mb-0">{{ salesPurchaseTitle }}</h5>
                </div>
              </div>
              <div class="card-body pb-0">
                <div class="d-flex align-items-stretch gap-2 mb-3 flex-wrap">
                  <div v-if="has('sales_totals')" class="chart-stat">
                    <p class="chart-stat__label mb-1">
                      <span class="chart-stat__dot chart-stat__dot--sales"></span>Total Sales
                    </p>
                    <h5 class="chart-stat__value mb-0">{{ formatMoney(dash.total_sales) }}</h5>
                  </div>
                  <div v-if="has('purchase_totals')" class="chart-stat">
                    <p class="chart-stat__label mb-1">
                      <span class="chart-stat__dot chart-stat__dot--purchase"></span>Total Purchase
                    </p>
                    <h5 class="chart-stat__value mb-0">{{ formatMoney(dash.total_purchase) }}</h5>
                  </div>
                </div>
                <apexchart
                  v-if="chartsReady && hasSalesChartData"
                  type="bar"
                  height="280"
                  :options="salesChartOptions"
                  :series="salesChartSeries"
                />
              </div>
            </div>
          </div>

          <div
            v-if="has('sales_expense_chart')"
            :class="has('sales_purchase_chart') ? 'col-xxl-5 col-xl-12 d-flex' : 'col-12 d-flex'"
          >
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-chart-line"></i></span>
                  <h5 class="card-title mb-0">{{ has('sales_totals') ? 'Sales vs Expenses' : 'Expenses' }}</h5>
                </div>
              </div>
              <div class="card-body pb-0">
                <div class="d-flex align-items-stretch gap-2 mb-3 flex-wrap">
                  <div v-if="has('sales_totals')" class="chart-stat">
                    <p class="chart-stat__label mb-1">
                      <span class="chart-stat__dot chart-stat__dot--revenue"></span>Total Revenue
                    </p>
                    <h5 class="chart-stat__value mb-0">{{ formatMoney(dash.total_sales) }}</h5>
                  </div>
                  <div class="chart-stat">
                    <p class="chart-stat__label mb-1">
                      <span class="chart-stat__dot chart-stat__dot--expense"></span>Total Expenses
                    </p>
                    <h5 class="chart-stat__value mb-0">{{ formatMoney(totalExpenses) }}</h5>
                  </div>
                </div>
                <apexchart
                  v-if="chartsReady && hasExpensesChartData"
                  type="bar"
                  height="280"
                  :options="expensesChartOptions"
                  :series="expensesChartSeries"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Financial overview -->
      <section v-if="showProfit || summaryCards.length" class="dashboard-section">
        <div class="row g-2">
          <div v-if="showProfit" class="col-xxl col-lg-6 col-12 d-flex">
            <div class="card stat-card flex-fill">
              <div class="card-body d-flex align-items-center gap-2">
                <span class="stat-card__icon bg-soft-success text-success">
                  <i class="ti ti-report-money"></i>
                </span>
                <div class="min-w-0">
                  <p class="stat-card__label mb-0">Estimated Profit</p>
                  <h4 class="stat-card__value mb-0">{{ formatMoney(profit) }}</h4>
                </div>
              </div>
            </div>
          </div>

          <div
            v-for="card in summaryCards"
            :key="card.label"
            class="col-xxl col-lg-6 col-12 d-flex"
          >
            <div class="card stat-card flex-fill">
              <div class="card-body d-flex align-items-center gap-2">
                <span :class="`stat-card__icon bg-soft-${card.color} text-${card.color}`">
                  <i :class="`ti ${card.icon}`"></i>
                </span>
                <div class="min-w-0">
                  <p class="stat-card__label mb-0">{{ card.label }}</p>
                  <h4 class="stat-card__value mb-0">{{ formatMoney(card.value) }}</h4>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Recent activity -->
      <section v-if="transactionTabs.length" class="dashboard-section">
        <div class="card dashboard-panel">
          <div class="card-body p-0">
            <ul class="nav nav-tabs nav-justified transaction-tab">
              <li v-for="(tab, idx) in transactionTabs" :key="tab.key" class="nav-item">
                <a
                  class="nav-link"
                  :class="{ active: idx === 0 }"
                  :href="`#${tab.id}`"
                  data-bs-toggle="tab"
                >{{ tab.label }}</a>
              </li>
            </ul>
            <div class="tab-content">
              <div
                v-for="(tab, idx) in transactionTabs"
                :key="tab.key"
                class="tab-pane"
                :class="idx === 0 ? 'show active' : 'fade'"
                :id="tab.id"
              >
                <div class="table-responsive">
                  <table class="table table-borderless custom-table mb-0">
                    <thead class="thead-light">
                      <tr>
                        <th>Date</th>
                        <th>{{ tab.partyLabel }}</th>
                        <th>Status</th>
                        <th class="text-end">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-if="!transactionRows(tab).length">
                        <td colspan="4" class="text-center text-muted py-4">{{ tab.emptyText }}</td>
                      </tr>
                      <tr v-for="row in transactionRows(tab)" :key="row.id">
                        <td>{{ row[tab.dateField] }}</td>
                        <td>
                          <span class="fw-semibold">{{ row[tab.titleField] || '–' }}</span>
                          <br><small class="text-muted">{{ row[tab.subtitleField] || '–' }}</small>
                        </td>
                        <td>
                          <span :class="`badge ${statusBadge(row.status)} badge-xs d-inline-flex align-items-center`">
                            <i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(row.status) }}
                          </span>
                        </td>
                        <td class="fw-bold text-gray-9 text-end">{{ formatMoney(row.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Insights -->
      <section v-if="hasInsights" class="dashboard-section dashboard-section--last">
        <div class="row g-2">
          <div v-if="has('top_selling_products')" class="col-xxl-4 col-md-6 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-trending-up"></i></span>
                  <h5 class="card-title mb-0">Top Selling Products</h5>
                </div>
                <router-link :to="{ name: 'admin.product-wise-sales-report' }" class="stat-card__link">View all</router-link>
              </div>
              <div class="card-body sell-product">
                <div v-if="!dash.top_selling_products.length" class="dashboard-empty">No sales data yet</div>
                <div
                  v-for="(product, idx) in dash.top_selling_products"
                  :key="product.id"
                  :class="idx < dash.top_selling_products.length - 1 ? 'dashboard-list-item' : 'dashboard-list-item dashboard-list-item--last'"
                >
                  <div class="d-flex align-items-center min-w-0">
                    <span class="dashboard-list-item__avatar text-primary">
                      <i class="ti ti-box"></i>
                    </span>
                    <div class="ms-2 min-w-0">
                      <h6 class="dashboard-list-item__title mb-1">{{ product.name }}</h6>
                      <div class="d-flex align-items-center item-list">
                        <p>{{ product.category_name || '–' }}</p>
                        <p>{{ product.sold_qty }} sold</p>
                      </div>
                    </div>
                  </div>
                  <span class="badge bg-outline-success badge-xs flex-shrink-0">{{ formatMoney(product.sold_amount) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="has('low_stock_products')" class="col-xxl-4 col-md-6 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-package-off"></i></span>
                  <h5 class="card-title mb-0">Low Stock Products</h5>
                </div>
                <router-link :to="{ name: 'admin.reorder-alerts' }" class="stat-card__link">View all</router-link>
              </div>
              <div class="card-body">
                <div v-if="!dash.low_stock_products.length" class="dashboard-empty">All products above minimum level</div>
                <div
                  v-for="(product, idx) in dash.low_stock_products"
                  :key="`${product.sku}-${product.warehouse_name}`"
                  :class="idx < dash.low_stock_products.length - 1 ? 'dashboard-list-item' : 'dashboard-list-item dashboard-list-item--last'"
                >
                  <div class="d-flex align-items-center min-w-0">
                    <span class="dashboard-list-item__avatar text-danger">
                      <i class="ti ti-package"></i>
                    </span>
                    <div class="ms-2 min-w-0">
                      <h6 class="dashboard-list-item__title mb-1">{{ product.product_name }}</h6>
                      <p class="dashboard-list-item__meta mb-0">{{ product.warehouse_name }}</p>
                    </div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <p class="dashboard-list-item__meta mb-1">In stock</p>
                    <h6 class="text-orange fw-semibold mb-0">{{ product.quantity }}</h6>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-if="has('top_customers')" class="col-xxl-4 col-md-12 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-users"></i></span>
                  <h5 class="card-title mb-0">Top Customers</h5>
                </div>
                <router-link v-if="isModuleEnabled('crm')" :to="{ name: 'admin.crm-contacts' }" class="stat-card__link">View all</router-link>
              </div>
              <div class="card-body">
                <div v-if="!dash.top_customers.length" class="dashboard-empty">No customer data yet</div>
                <div
                  v-for="(customer, idx) in dash.top_customers"
                  :key="customer.id"
                  :class="idx < dash.top_customers.length - 1 ? 'dashboard-list-item' : 'dashboard-list-item dashboard-list-item--last'"
                >
                  <div class="d-flex align-items-center min-w-0">
                    <span class="dashboard-list-item__avatar text-primary">
                      <i class="ti ti-user-circle"></i>
                    </span>
                    <div class="ms-2 min-w-0">
                      <h6 class="dashboard-list-item__title mb-1">{{ customer.name }}</h6>
                      <div class="d-flex align-items-center item-list">
                        <p class="text-truncate">{{ customer.address || '–' }}</p>
                        <p>{{ customer.order_count }} {{ customer.order_count === 1 ? 'invoice' : 'invoices' }}</p>
                      </div>
                    </div>
                  </div>
                  <h6 class="mb-0 flex-shrink-0">{{ formatMoney(customer.total_amount) }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </template>

    <VModal
      :show-modal="showLinksEditor"
      size="lg"
      title="Edit Quick Links"
      @close-click="closeLinksEditor"
    >
      <template #modal-body>
        <p class="text-muted fs-13 mb-3">
          Select the pages you want quick access to from your dashboard.
          <span class="fw-semibold">{{ draftLinks.length }}</span> selected.
        </p>
        <div class="quick-links-editor">
          <div
            v-for="group in groupedLeaves"
            :key="group.category"
            class="quick-links-editor__group"
          >
            <h6 class="quick-links-editor__heading">{{ group.category }}</h6>
            <div class="row g-2">
              <div v-for="leaf in group.items" :key="leaf.routeName" class="col-md-6">
                <label
                  class="quick-links-editor__item"
                  :class="{ 'quick-links-editor__item--active': isDrafted(leaf.routeName) }"
                >
                  <input
                    type="checkbox"
                    class="form-check-input m-0 flex-shrink-0"
                    :checked="isDrafted(leaf.routeName)"
                    @change="toggleDraft(leaf.routeName)"
                  />
                  <i :class="leaf.icon" class="mx-2 fs-16"></i>
                  <span class="text-truncate">{{ leaf.label }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
          <button type="button" class="btn btn-light" @click="closeLinksEditor">Cancel</button>
          <button type="button" class="btn btn-primary" :disabled="savingLinks" @click="saveLinks">
            <span v-if="savingLinks" class="spinner-border spinner-border-sm me-1"></span>
            Save Links
          </button>
        </div>
      </template>
    </VModal>
  </div>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {CHART_COLORS, baseChartOptions} from '@/constants/chartTheme.js';
import {ref, computed, onMounted, watch, nextTick} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {useAdminDashboardStore} from '@/stores/admin/dashboard';
import {usePinnedLinksStore} from '@/stores/admin/pinnedLinks';
import {useDisplayDate} from '@/composables/useDisplayDate.js';
import VModal from '@/components/base/VModal.vue';
import rawSidebar from '@/assets/json/sidebar.json';
import {filterSidebarSections, flattenMenuLeaves} from '@/helpers/sidebarMenu';
import {hasPermission} from '@/helpers/checkPermission';
import {isModuleEnabled} from '@/helpers/checkModule';
import {toast} from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors';

const dashboardStore = useAdminDashboardStore();
const pinnedLinksStore = usePinnedLinksStore();
const {mode: dateMode, formatDate} = useDisplayDate();
const route  = useRoute();
const router = useRouter();
const dateRangeInput = ref(null);
const chartsReady    = ref(false);
let   pickerInstance = null;

const dash         = computed(() => dashboardStore.dashboard.data);
const summaryCards = computed(() => dashboardStore.summaryCards);
const fiscalYear   = computed(() => dashboardStore.fiscalYear);

/**
 * Whether the server computed a widget for this company. A widget it did not
 * compute belongs to a module the company does not run — its panel is not
 * rendered at all, rather than rendered with zeros.
 *
 * @see app/Http/Controllers/Api/Admin/DashboardController.php — WIDGETS
 */
const has = (widget) => dashboardStore.hasWidget(widget);

/** Profit is sales minus purchase, so it needs both sides to mean anything. */
const showProfit = computed(() => has('sales_totals') && has('purchase_totals'));

const hasInsights = computed(() =>
    has('top_selling_products') || has('low_stock_products') || has('top_customers'),
);

const salesPurchaseTitle = computed(() => {
    if (has('sales_totals') && has('purchase_totals')) return 'Sales & Purchase';
    return has('sales_totals') ? 'Sales' : 'Purchase';
});

const profit = computed(() => {
    const d = dashboardStore.dashboard.data;
    return Math.max(0, (d.total_sales - d.total_sales_return) - (d.total_purchase - d.total_purchase_return));
});

const todayStr = moment().format('YYYY-MM-DD');

/**
 * Party counts are core — every company has customers and suppliers — but the
 * only screen that lists them lives in CRM, so the count stays and the "View
 * all" link goes when CRM is off.
 */
const businessMetrics = computed(() => {
    const d = dashboardStore.dashboard.data;

    return [
        {
            label: 'Customers',
            displayValue: d.customers_count,
            icon: 'ti-users',
            cardColor: 'green',
            widget: 'party_counts',
            route: {name: 'admin.crm-contacts'},
            routeModule: 'crm',
        },
        {
            label: 'Products',
            displayValue: d.products_count,
            icon: 'ti-shopping-bag',
            cardColor: 'teal',
            widget: 'product_count',
            route: {name: 'admin.product-list'},
            routeModule: 'inventory',
        },
        {
            label: 'Suppliers',
            displayValue: d.suppliers_count,
            icon: 'ti-truck',
            cardColor: 'purple',
            widget: 'party_counts',
            route: {name: 'admin.crm-contacts'},
            routeModule: 'crm',
        },
        {
            label: "Today's Sales",
            displayValue: d.orders_today,
            icon: 'ti-cash',
            cardColor: 'primary',
            widget: 'sales_totals',
            route: {name: 'admin.invoice-list', query: {date_from: todayStr, date_to: todayStr}},
            routeModule: 'sales',
        },
    ].filter((metric) => has(metric.widget));
});

/**
 * The recent-activity tabs, one per document type the company still runs. Built
 * as data so the first available tab is the active one — hardcoding "Sales" as
 * active left a company without the Sales module staring at a blank pane.
 */
const transactionTabs = computed(() => [
    {
        widget: 'recent_invoices',
        key: 'invoices',
        id: 'sale',
        label: 'Sales',
        partyLabel: 'Customer',
        dateField: 'invoice_date',
        titleField: 'party_name',
        subtitleField: 'invoice_no',
        emptyText: 'No recent invoices',
    },
    {
        widget: 'recent_bills',
        key: 'bills',
        id: 'purchase-transaction',
        label: 'Purchases',
        partyLabel: 'Supplier',
        dateField: 'bill_date',
        titleField: 'party_name',
        subtitleField: 'bill_no',
        emptyText: 'No recent bills',
    },
    {
        widget: 'recent_quotations',
        key: 'quotations',
        id: 'quotation',
        label: 'Quotations',
        partyLabel: 'Customer',
        dateField: 'quotation_date',
        titleField: 'party_name',
        subtitleField: 'quotation_no',
        emptyText: 'No recent quotations',
    },
    {
        widget: 'recent_expenses',
        key: 'expenses',
        id: 'expenses',
        label: 'Expenses',
        partyLabel: 'Expense',
        dateField: 'date',
        titleField: 'expense_no',
        subtitleField: 'party_name',
        emptyText: 'No recent expenses',
    },
].filter((tab) => has(tab.widget)));

const transactionRows = (tab) => dash.value.recent_transactions?.[tab.key] ?? [];

/* ---- Quick links (pinned dashboard shortcuts) ---- */
const availableLeaves = computed(() =>
    flattenMenuLeaves(
        filterSidebarSections(rawSidebar, (p) => hasPermission(p), (m) => isModuleEnabled(m)),
    )
        .filter((leaf) => leaf.routeName !== 'admin.dashboard'),
);

const leafIndex = computed(() => {
    const index = {};
    availableLeaves.value.forEach((leaf) => {
        index[leaf.routeName] = leaf;
    });
    return index;
});

const pinnedLinks = computed(() =>
    pinnedLinksStore.links.map((name) => leafIndex.value[name]).filter(Boolean),
);

const groupedLeaves = computed(() => {
    const groups = [];
    const byCategory = {};
    availableLeaves.value.forEach((leaf) => {
        if (!byCategory[leaf.category]) {
            byCategory[leaf.category] = {category: leaf.category, items: []};
            groups.push(byCategory[leaf.category]);
        }
        byCategory[leaf.category].items.push(leaf);
    });
    return groups;
});

const showLinksEditor = ref(false);
const savingLinks     = ref(false);
const draftLinks      = ref([]);

const isDrafted = (routeName) => draftLinks.value.includes(routeName);

const toggleDraft = (routeName) => {
    draftLinks.value = isDrafted(routeName)
        ? draftLinks.value.filter((name) => name !== routeName)
        : [...draftLinks.value, routeName];
};

const openLinksEditor = () => {
    draftLinks.value = [...pinnedLinksStore.links];
    showLinksEditor.value = true;
};

const closeLinksEditor = () => {
    showLinksEditor.value = false;
};

const saveLinks = async () => {
    savingLinks.value = true;
    const ordered = availableLeaves.value
        .filter((leaf) => draftLinks.value.includes(leaf.routeName))
        .map((leaf) => leaf.routeName);

    try {
        await pinnedLinksStore.setLinks(ordered);
        toast(200, 'Quick links updated');
        showLinksEditor.value = false;
    } catch (err) {
        showErrors(err);
    } finally {
        savingLinks.value = false;
    }
};

const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '–';

const statusBadge = (status) => {
    const map = {
        approved:   'badge-success',
        draft:      'badge-pink',
        pending:    'badge-cyan',
        cancelled:  'badge-danger',
        sent:       'badge-success',
        ordered:    'badge-warning',
    };
    return map[status?.toLowerCase()] ?? 'bg-secondary';
};

const chartData = computed(() => dashboardStore.dashboard.data.chart_data ?? {});

const chartLabels = computed(() =>
    (dateMode.value === 'ne' ? chartData.value.labels_bs : chartData.value.labels) || [],
);

const salesChartOptions = computed(() => ({
    ...baseChartOptions(),
    chart: { ...baseChartOptions().chart, type: 'bar' },
    colors: [CHART_COLORS.primary, CHART_COLORS.success],
    fill: {
        type: 'gradient',
        gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.9, opacityTo: 0.55 },
    },
    yaxis: { labels: { formatter: (val) => formatMoney(val) } },
    xaxis: { categories: chartLabels.value },
}));

/**
 * Only the series the server actually sent. A module that is off has no series
 * at all, so the chart draws one bar group instead of pairing real data with a
 * flat line of zeros that reads as "we sold nothing".
 */
const salesChartSeries = computed(() => {
    const series = [];
    if (chartData.value.sales)     series.push({name: 'Sales',    data: chartData.value.sales});
    if (chartData.value.purchases) series.push({name: 'Purchase', data: chartData.value.purchases});
    return series;
});

const expensesChartOptions = computed(() => ({
    ...baseChartOptions(),
    chart: { ...baseChartOptions().chart, type: 'bar' },
    colors: [CHART_COLORS.primary, CHART_COLORS.warning],
    fill: {
        type: 'gradient',
        gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.9, opacityTo: 0.55 },
    },
    yaxis: { labels: { formatter: (val) => formatMoney(val) } },
    xaxis: { categories: chartLabels.value },
}));

const expensesChartSeries = computed(() => {
    const series = [];
    if (chartData.value.sales)    series.push({name: 'Revenue',  data: chartData.value.sales});
    if (chartData.value.expenses) series.push({name: 'Expenses', data: chartData.value.expenses});
    return series;
});

const hasSalesChartData    = computed(() => salesChartSeries.value.some((s) => s.data.length > 0));
const hasExpensesChartData = computed(() => expensesChartSeries.value.some((s) => s.data.length > 0));
const totalExpenses        = computed(() => (chartData.value.expenses || []).reduce((sum, v) => sum + (v || 0), 0));

watch(
    () => dashboardStore.isLoading,
    async (loading) => {
        chartsReady.value = false;

        if (!loading) {
            await nextTick();
            chartsReady.value = true;
        }
    },
    {immediate: true},
);

const rangeDisplay = (m) => formatDate(m.format('YYYY-MM-DD'), {adFormat: 'MM/DD/YYYY'});

const setRangeInputText = (from, to) => {
    if (dateRangeInput.value) {
        dateRangeInput.value.value = `${rangeDisplay(from)} - ${rangeDisplay(to)}`;
    }
};

function initPicker(fyStart, fyEnd) {
    if (!dateRangeInput.value) { return; }

    const minDate = moment(fyStart);
    const maxDate = moment.min(moment(), moment(fyEnd));

    const currentFrom = route.query.date_from ? moment(route.query.date_from) : minDate.clone();
    const currentTo   = route.query.date_to   ? moment(route.query.date_to)   : moment();

    if (pickerInstance) {
        pickerInstance.remove();
        pickerInstance = null;
    }

    pickerInstance = new DateRangePicker(
        dateRangeInput.value,
        {
            startDate: currentFrom,
            endDate:   currentTo,
            minDate,
            maxDate,
            ranges: {
                Today:             [moment(), moment()],
                Yesterday:         [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days':     [moment().subtract(6, 'days'), moment()],
                'Last 30 Days':    [moment().subtract(29, 'days'), moment()],
                'This Month':      [moment().startOf('month'), moment().endOf('month')],
                'Last Month':      [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                'This Fiscal Year': [minDate.clone(), moment()],
            },
        },
        (start, end) => {
            const from = start.format('YYYY-MM-DD');
            const to   = end.format('YYYY-MM-DD');
            router.push({query: {date_from: from, date_to: to}});
            dashboardStore.getDashboardData({date_from: from, date_to: to});
        },
    );

    setRangeInputText(currentFrom, currentTo);
}

watch(fiscalYear, (fy) => {
    if (fy?.start_date && fy?.end_date) {
        initPicker(fy.start_date, fy.end_date);
    }
});

watch(dateMode, () => {
    if (pickerInstance) {
        setRangeInputText(pickerInstance.startDate, pickerInstance.endDate);
    }
});

watch(
    () => route.query,
    (query) => {
        if (query.date_from && query.date_to && pickerInstance) {
            pickerInstance.setStartDate(moment(query.date_from));
            pickerInstance.setEndDate(moment(query.date_to));
            setRangeInputText(moment(query.date_from), moment(query.date_to));
        }
    },
);

onMounted(async () => {
    const {date_from, date_to} = route.query;

    await dashboardStore.getDashboardData(
        date_from && date_to ? {date_from, date_to} : {},
    );

    await nextTick();

    const fy = dashboardStore.fiscalYear;

    if (fy?.start_date && fy?.end_date) {
        if (!date_from || !date_to) {
            const from = fy.start_date;
            const to   = todayStr;
            router.replace({query: {date_from: from, date_to: to}});
        }

        initPicker(fy.start_date, fy.end_date);
    }
});
</script>
