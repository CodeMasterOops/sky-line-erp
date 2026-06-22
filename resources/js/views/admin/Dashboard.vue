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
      <section class="dashboard-section">
        <div class="row g-2">
          <div
            v-for="metric in businessMetrics"
            :key="metric.label"
            class="col-xl-3 col-sm-6 col-12 d-flex"
          >
            <div :class="`card metric-card metric-card--${metric.cardColor} flex-fill`">
              <div class="metric-card__body">
                <div class="metric-card__info">
                  <h3 class="metric-card__count">{{ metric.displayValue }}</h3>
                  <p class="metric-card__label mb-0">{{ metric.label }}</p>
                </div>
                <i :class="`ti ${metric.icon} metric-card__bg-icon`"></i>
              </div>
              <router-link :to="metric.route" class="metric-card__footer">
                {{ metric.label }}
              </router-link>
            </div>
          </div>
        </div>
      </section>

      <!-- Analytics -->
      <section class="dashboard-section">
        <div class="row g-2">
          <div class="col-xxl-7 col-xl-12 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-report-analytics"></i></span>
                  <h5 class="card-title mb-0">Sales &amp; Purchase</h5>
                </div>
              </div>
              <div class="card-body pb-0">
                <div class="d-flex align-items-stretch gap-2 mb-3 flex-wrap">
                  <div class="chart-stat">
                    <p class="chart-stat__label mb-1">
                      <span class="chart-stat__dot chart-stat__dot--sales"></span>Total Sales
                    </p>
                    <h5 class="chart-stat__value mb-0">{{ formatMoney(dash.total_sales) }}</h5>
                  </div>
                  <div class="chart-stat">
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

          <div class="col-xxl-5 col-xl-12 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-chart-line"></i></span>
                  <h5 class="card-title mb-0">Sales vs Expenses</h5>
                </div>
              </div>
              <div class="card-body pb-0">
                <apexchart
                  v-if="chartsReady && hasExpensesChartData"
                  type="bar"
                  height="340"
                  :options="expensesChartOptions"
                  :series="expensesChartSeries"
                />
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Financial overview -->
      <section class="dashboard-section">
        <div class="row g-2">
          <div class="col-xxl-4 col-lg-6 col-12 d-flex">
            <div class="card stat-card stat-card--featured flex-fill">
              <div class="card-body d-flex align-items-center gap-2">
                <span class="stat-card__icon">
                  <i class="ti ti-report-money"></i>
                </span>
                <div class="min-w-0 flex-fill">
                  <p class="stat-card__label mb-0">Estimated Profit</p>
                  <h4 class="stat-card__value mb-0">{{ formatMoney(profit) }}</h4>
                </div>
                <router-link :to="{ name: 'admin.profit-and-loss' }" class="stat-card__link flex-shrink-0">P&amp;L</router-link>
              </div>
            </div>
          </div>

          <div
            v-for="card in summaryCards"
            :key="card.label"
            class="col-xxl-2 col-lg-6 col-12 d-flex"
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
      <section class="dashboard-section">
        <div class="card dashboard-panel">
          <div class="card-body p-0">
            <ul class="nav nav-tabs nav-justified transaction-tab">
              <li class="nav-item"><a class="nav-link active" href="#sale" data-bs-toggle="tab">Sales</a></li>
              <li class="nav-item"><a class="nav-link" href="#purchase-transaction" data-bs-toggle="tab">Purchases</a></li>
              <li class="nav-item"><a class="nav-link" href="#quotation" data-bs-toggle="tab">Quotations</a></li>
              <li class="nav-item"><a class="nav-link" href="#expenses" data-bs-toggle="tab">Expenses</a></li>
            </ul>
            <div class="tab-content">
              <div class="tab-pane show active" id="sale">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table mb-0">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.invoices.length">
                        <td colspan="4" class="text-center text-muted py-4">No recent invoices</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.invoices" :key="row.id">
                        <td>{{ row.invoice_date }}</td>
                        <td>
                          <span class="fw-semibold">{{ row.party_name || '–' }}</span>
                          <br><small class="text-muted">{{ row.invoice_no }}</small>
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

              <div class="tab-pane fade" id="purchase-transaction">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table mb-0">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Supplier</th><th>Status</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.bills.length">
                        <td colspan="4" class="text-center text-muted py-4">No recent bills</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.bills" :key="row.id">
                        <td>{{ row.bill_date }}</td>
                        <td>
                          <span class="fw-semibold">{{ row.party_name || '–' }}</span>
                          <br><small class="text-muted">{{ row.bill_no }}</small>
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

              <div class="tab-pane" id="quotation">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table mb-0">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.quotations.length">
                        <td colspan="4" class="text-center text-muted py-4">No recent quotations</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.quotations" :key="row.id">
                        <td>{{ row.quotation_date }}</td>
                        <td>
                          <span class="fw-semibold">{{ row.party_name || '–' }}</span>
                          <br><small class="text-muted">{{ row.quotation_no }}</small>
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

              <div class="tab-pane fade" id="expenses">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table mb-0">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Expense</th><th>Status</th><th class="text-end">Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.expenses.length">
                        <td colspan="4" class="text-center text-muted py-4">No recent expenses</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.expenses" :key="row.id">
                        <td>{{ row.date }}</td>
                        <td>
                          <span class="fw-semibold">{{ row.expense_no }}</span>
                          <br><small class="text-muted">{{ row.party_name || '–' }}</small>
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
      <section class="dashboard-section dashboard-section--last">
        <div class="row g-2">
          <div class="col-xxl-4 col-md-6 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-trending-up"></i></span>
                  <h5 class="card-title mb-0">Top Selling Products</h5>
                </div>
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

          <div class="col-xxl-4 col-md-6 d-flex">
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

          <div class="col-xxl-4 col-md-12 d-flex">
            <div class="card dashboard-panel flex-fill">
              <div class="card-header dashboard-panel__header d-flex justify-content-between align-items-center">
                <div class="d-inline-flex align-items-center">
                  <span class="title-icon fs-16 me-2"><i class="ti ti-users"></i></span>
                  <h5 class="card-title mb-0">Top Customers</h5>
                </div>
                <router-link :to="{ name: 'admin.party-list', query: { type: 'customer' } }" class="stat-card__link">View all</router-link>
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
  </div>
</template>

<script setup>
import {formatMoney} from '@/helpers/formatMoney.js';
import {ref, computed, onMounted, watch, nextTick} from 'vue';
import {useRoute, useRouter} from 'vue-router';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {useAdminDashboardStore} from '@/stores/admin/dashboard';

const dashboardStore = useAdminDashboardStore();
const route  = useRoute();
const router = useRouter();
const dateRangeInput = ref(null);
const chartsReady    = ref(false);
let   pickerInstance = null;

const dash         = computed(() => dashboardStore.dashboard.data);
const summaryCards = computed(() => dashboardStore.summaryCards);
const fiscalYear   = computed(() => dashboardStore.fiscalYear);

const profit = computed(() => {
    const d = dashboardStore.dashboard.data;
    return Math.max(0, (d.total_sales - d.total_sales_return) - (d.total_purchase - d.total_purchase_return));
});

const todayStr = moment().format('YYYY-MM-DD');

const businessMetrics = computed(() => {
    const d = dashboardStore.dashboard.data;

    return [
        {
            label: 'Customers',
            displayValue: d.customers_count,
            icon: 'ti-users',
            cardColor: 'green',
            route: {name: 'admin.party-list', query: {type: 'customer'}},
        },
        {
            label: 'Products',
            displayValue: d.products_count,
            icon: 'ti-shopping-bag',
            cardColor: 'teal',
            route: {name: 'admin.product-list'},
        },
        {
            label: 'Suppliers',
            displayValue: d.suppliers_count,
            icon: 'ti-truck',
            cardColor: 'purple',
            route: {name: 'admin.party-list', query: {type: 'supplier'}},
        },
        {
            label: "Today's Sales",
            displayValue: d.orders_today,
            icon: 'ti-cash',
            cardColor: 'olive',
            route: {name: 'admin.invoice-list', query: {date_from: todayStr, date_to: todayStr}},
        },
    ];
});

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

const salesChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: {show: false},
    },
    colors: ['#1b84ff', '#17c653'],
    plotOptions: {bar: {borderRadius: 4, columnWidth: '55%'}},
    dataLabels: {enabled: false},
    legend: {position: 'top'},
    xaxis: {categories: chartData.value.labels || []},
}));

const salesChartSeries = computed(() => [
    {name: 'Sales',    data: chartData.value.sales     || []},
    {name: 'Purchase', data: chartData.value.purchases || []},
]);

const expensesChartOptions = computed(() => ({
    chart: {
        type: 'bar',
        toolbar: {show: false},
    },
    colors: ['#1b84ff', '#f6820d'],
    plotOptions: {bar: {borderRadius: 4, columnWidth: '55%'}},
    dataLabels: {enabled: false},
    legend: {position: 'top'},
    xaxis: {categories: chartData.value.labels || []},
}));

const expensesChartSeries = computed(() => [
    {name: 'Revenue',  data: chartData.value.sales    || []},
    {name: 'Expenses', data: chartData.value.expenses || []},
]);

const hasSalesChartData    = computed(() => salesChartSeries.value.some((s) => s.data.length > 0));
const hasExpensesChartData = computed(() => expensesChartSeries.value.some((s) => s.data.length > 0));

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

    dateRangeInput.value.value = `${currentFrom.format('MM/DD/YYYY')} - ${currentTo.format('MM/DD/YYYY')}`;
}

watch(fiscalYear, (fy) => {
    if (fy?.start_date && fy?.end_date) {
        initPicker(fy.start_date, fy.end_date);
    }
});

watch(
    () => route.query,
    (query) => {
        if (query.date_from && query.date_to && pickerInstance) {
            pickerInstance.setStartDate(moment(query.date_from));
            pickerInstance.setEndDate(moment(query.date_to));
            dateRangeInput.value.value = `${moment(query.date_from).format('MM/DD/YYYY')} - ${moment(query.date_to).format('MM/DD/YYYY')}`;
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
