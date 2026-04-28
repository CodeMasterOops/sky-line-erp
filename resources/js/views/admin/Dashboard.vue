<template>
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-2">
    <div class="mb-3">
      <h1 class="mb-1">Welcome, Admin</h1>
      <p class="fw-medium">
        You have <span class="text-primary fw-bold">{{ dash.orders_today }}</span> Order(s) Today
      </p>
    </div>
    <div class="input-icon-start position-relative mb-3">
      <span class="input-icon-addon fs-16 text-gray-9">
        <i class="ti ti-calendar"></i>
      </span>
      <input type="text" class="form-control date-range bookingrange" ref="dateRangeInput"
        placeholder="Select Date Range" />
    </div>
  </div>

  <!-- Loading skeleton -->
  <div v-if="dashboardStore.isLoading" class="text-center py-5">
    <span class="spinner-border text-primary"></span>
  </div>

  <template v-else>

    <!-- Summary cards – Sales / Returns / Purchase / Purchase Return -->
    <div class="row">
      <div v-for="card in summaryCards" :key="card.label" class="col-xl-3 col-sm-6 col-12 d-flex">
        <div :class="`card bg-${card.color} sale-widget flex-fill`">
          <div class="card-body d-flex align-items-center">
            <span :class="`sale-icon bg-white text-${card.color}`">
              <i :class="`ti ${card.icon} fs-24`"></i>
            </span>
            <div class="ms-2">
              <p class="text-white mb-1">{{ card.label }}</p>
              <h4 class="text-white">{{ fmt(card.value) }}</h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Revenue widgets row -->
    <div class="row">
      <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card revenue-widget flex-fill">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
              <div>
                <h4 class="mb-1">{{ fmt(profit) }}</h4>
                <p>Profit</p>
              </div>
              <span class="revenue-icon bg-cyan-transparent text-cyan">
                <i class="fa-solid fa-layer-group fs-16"></i>
              </span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <p class="mb-0 fs-13 text-muted">Sales – Purchase</p>
              <router-link :to="{ name: 'admin.profit-and-loss' }" class="text-decoration-underline fs-13 fw-medium">View All</router-link>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card revenue-widget flex-fill">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
              <div>
                <h4 class="mb-1">{{ dash.suppliers_count }}</h4>
                <p>Suppliers</p>
              </div>
              <span class="revenue-icon bg-teal-transparent text-teal">
                <i class="ti ti-chart-pie fs-16"></i>
              </span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <p class="mb-0 fs-13 text-muted">Active supplier parties</p>
              <router-link :to="{ name: 'admin.party-list', query: { type: 'supplier' } }" class="text-decoration-underline fs-13 fw-medium">View All</router-link>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card revenue-widget flex-fill">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
              <div>
                <h4 class="mb-1">{{ dash.customers_count }}</h4>
                <p>Customers</p>
              </div>
              <span class="revenue-icon bg-orange-transparent text-orange">
                <i class="ti ti-lifebuoy fs-16"></i>
              </span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <p class="mb-0 fs-13 text-muted">Active customer parties</p>
              <router-link :to="{ name: 'admin.party-list', query: { type: 'customer' } }" class="text-decoration-underline fs-13 fw-medium">View All</router-link>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xl-3 col-sm-6 col-12 d-flex">
        <div class="card revenue-widget flex-fill">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
              <div>
                <h4 class="mb-1">{{ dash.products_count }}</h4>
                <p>Products</p>
              </div>
              <span class="revenue-icon bg-indigo-transparent text-indigo">
                <i class="ti ti-hash fs-16"></i>
              </span>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <p class="mb-0 fs-13 text-muted">Catalogue items</p>
              <router-link :to="{ name: 'admin.product-list' }" class="text-decoration-underline fs-13 fw-medium">View All</router-link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sales & Purchase chart + Overall info -->
    <div class="row">
      <div class="col-xxl-8 col-xl-7 col-sm-12 col-12 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-primary fs-16 me-2"><i class="ti ti-shopping-cart"></i></span>
              <h5 class="card-title mb-0">Sales &amp; Purchase (Last 12 Months)</h5>
            </div>
          </div>
          <div class="card-body pb-0">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="border p-2 br-8">
                <p class="d-inline-flex align-items-center mb-1">
                  <i class="ti ti-circle-filled fs-8 text-primary-300 me-1"></i>Total Purchase
                </p>
                <h4>{{ fmt(dash.total_purchase) }}</h4>
              </div>
              <div class="border p-2 br-8">
                <p class="d-inline-flex align-items-center mb-1">
                  <i class="ti ti-circle-filled fs-8 text-primary me-1"></i>Total Sales
                </p>
                <h4>{{ fmt(dash.total_sales) }}</h4>
              </div>
            </div>
            <apexchart v-if="salesChartOptions.series[0].data.length"
              type="bar" height="245"
              :options="salesChartOptions.chart"
              :series="salesChartOptions.series">
            </apexchart>
          </div>
        </div>
      </div>

      <!-- Overall info -->
      <div class="col-xxl-4 col-xl-5 d-flex">
        <div class="card flex-fill">
          <div class="card-header">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-info fs-16 me-2"><i class="ti ti-info-circle"></i></span>
              <h5 class="card-title mb-0">Overall Information</h5>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="info-item border bg-light p-3 text-center">
                  <div class="mb-3 text-info fs-24"><i class="ti ti-user-check"></i></div>
                  <p class="mb-1">Suppliers</p>
                  <h5>{{ dash.suppliers_count }}</h5>
                </div>
              </div>
              <div class="col-md-4">
                <div class="info-item border bg-light p-3 text-center">
                  <div class="mb-3 text-orange fs-24"><i class="ti ti-users"></i></div>
                  <p class="mb-1">Customer</p>
                  <h5>{{ dash.customers_count }}</h5>
                </div>
              </div>
              <div class="col-md-4">
                <div class="info-item border bg-light p-3 text-center">
                  <div class="mb-3 text-teal fs-24"><i class="ti ti-shopping-cart"></i></div>
                  <p class="mb-1">Today's Orders</p>
                  <h5>{{ dash.orders_today }}</h5>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Selling / Low Stock / Recent Sales -->
    <div class="row">

      <!-- Top Selling Products -->
      <div class="col-xxl-4 col-md-6 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-pink fs-16 me-2"><i class="ti ti-box"></i></span>
              <h5 class="card-title mb-0">Top Selling Products</h5>
            </div>
          </div>
          <div class="card-body sell-product">
            <div v-if="!dash.top_selling_products.length" class="text-muted text-center py-3">No data</div>
            <div v-for="(product, idx) in dash.top_selling_products" :key="product.id"
              :class="idx < dash.top_selling_products.length - 1 ? 'd-flex align-items-center justify-content-between border-bottom' : 'd-flex align-items-center justify-content-between'">
              <div class="d-flex align-items-center py-2">
                <span class="avatar avatar-lg bg-light text-primary d-inline-flex align-items-center justify-content-center">
                  <i class="ti ti-package fs-20"></i>
                </span>
                <div class="ms-2">
                  <h6 class="fw-bold mb-1">{{ product.name }}</h6>
                  <div class="d-flex align-items-center item-list">
                    <p>{{ product.category_name || '–' }}</p>
                    <p>{{ product.sold_qty }}+ Sales</p>
                  </div>
                </div>
              </div>
              <span class="badge bg-outline-success badge-xs d-inline-flex align-items-center">{{ fmt(product.sold_amount) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Products -->
      <div class="col-xxl-4 col-md-6 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-danger fs-16 me-2"><i class="ti ti-alert-triangle"></i></span>
              <h5 class="card-title mb-0">Low Stock Products</h5>
            </div>
            <router-link :to="{ name: 'admin.reorder-alerts' }" class="fs-13 fw-medium text-decoration-underline">View All</router-link>
          </div>
          <div class="card-body">
            <div v-if="!dash.low_stock_products.length" class="text-muted text-center py-3">All products above minimum level</div>
            <div v-for="product in dash.low_stock_products" :key="`${product.sku}-${product.warehouse_name}`"
              class="d-flex align-items-center justify-content-between mb-4">
              <div class="d-flex align-items-center">
                <span class="avatar avatar-lg bg-light text-danger d-inline-flex align-items-center justify-content-center">
                  <i class="ti ti-alert-triangle fs-20"></i>
                </span>
                <div class="ms-2">
                  <h6 class="fw-bold mb-1">{{ product.product_name }}</h6>
                  <p class="fs-13">{{ product.warehouse_name }}</p>
                </div>
              </div>
              <div class="text-end">
                <p class="fs-13 mb-1">In Stock</p>
                <h6 class="text-orange fw-medium">{{ product.quantity }}</h6>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Sales (Invoices) -->
      <div class="col-xxl-4 col-md-12 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-pink fs-16 me-2"><i class="ti ti-box"></i></span>
              <h5 class="card-title mb-0">Recent Sales</h5>
            </div>
          </div>
          <div class="card-body">
            <div v-if="!dash.recent_transactions.invoices.length" class="text-muted text-center py-3">No recent invoices</div>
            <div v-for="(inv, idx) in dash.recent_transactions.invoices" :key="inv.id"
              :class="idx < dash.recent_transactions.invoices.length - 1 ? 'd-flex align-items-center justify-content-between mb-4' : 'd-flex align-items-center justify-content-between mb-0'">
              <div class="d-flex align-items-center">
                <span class="avatar avatar-lg bg-light text-primary d-inline-flex align-items-center justify-content-center">
                  <i class="ti ti-file-text fs-20"></i>
                </span>
                <div class="ms-2">
                  <h6 class="fw-bold mb-1">{{ inv.party_name || '–' }}</h6>
                  <div class="d-flex align-items-center item-list">
                    <p>{{ inv.invoice_no }}</p>
                    <p class="text-gray-9">{{ fmt(inv.total) }}</p>
                  </div>
                </div>
              </div>
              <div class="text-end">
                <p class="fs-13 mb-1">{{ inv.invoice_date }}</p>
                <span :class="`badge ${statusBadge(inv.status)} badge-xs d-inline-flex align-items-center`">
                  <i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(inv.status) }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Expenses chart + Recent Transactions -->
    <div class="row">
      <!-- Expenses chart -->
      <div class="col-xl-6 col-sm-12 col-12 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-danger fs-16 me-2"><i class="ti ti-alert-triangle"></i></span>
              <h5 class="card-title mb-0">Sales vs Expenses (Last 12 Months)</h5>
            </div>
          </div>
          <div class="card-body pb-0">
            <apexchart v-if="expensesChartOptions.series[0].data.length"
              type="bar" height="290"
              :options="expensesChartOptions.chart"
              :series="expensesChartOptions.series">
            </apexchart>
          </div>
        </div>
      </div>

      <!-- Recent Transactions tabs -->
      <div class="col-xl-6 col-sm-12 col-12 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-orange fs-16 me-2"><i class="ti ti-flag"></i></span>
              <h5 class="card-title mb-0">Recent Transactions</h5>
            </div>
          </div>
          <div class="card-body p-0">
            <ul class="nav nav-tabs nav-justified transaction-tab">
              <li class="nav-item"><a class="nav-link active" href="#sale" data-bs-toggle="tab">Sale</a></li>
              <li class="nav-item"><a class="nav-link" href="#purchase-transaction" data-bs-toggle="tab">Purchase</a></li>
              <li class="nav-item"><a class="nav-link" href="#quotation" data-bs-toggle="tab">Quotation</a></li>
              <li class="nav-item"><a class="nav-link" href="#expenses" data-bs-toggle="tab">Expenses</a></li>
            </ul>
            <div class="tab-content">
              <!-- Sale tab -->
              <div class="tab-pane show active" id="sale">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Customer</th><th>Status</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.invoices.length">
                        <td colspan="4" class="text-center text-muted">No records</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.invoices" :key="row.id">
                        <td>{{ row.invoice_date }}</td>
                        <td><span class="fw-semibold">{{ row.party_name || '–' }}</span><br><small class="text-orange">{{ row.invoice_no }}</small></td>
                        <td><span :class="`badge ${statusBadge(row.status)} badge-xs d-inline-flex align-items-center`"><i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(row.status) }}</span></td>
                        <td class="fw-bold text-gray-9">{{ fmt(row.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Purchase tab -->
              <div class="tab-pane fade" id="purchase-transaction">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Supplier</th><th>Status</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.bills.length">
                        <td colspan="4" class="text-center text-muted">No records</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.bills" :key="row.id">
                        <td>{{ row.bill_date }}</td>
                        <td><span class="fw-semibold">{{ row.party_name || '–' }}</span><br><small class="text-orange">{{ row.bill_no }}</small></td>
                        <td><span :class="`badge ${statusBadge(row.status)} badge-xs d-inline-flex align-items-center`"><i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(row.status) }}</span></td>
                        <td class="fw-bold text-gray-9">{{ fmt(row.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Quotation tab -->
              <div class="tab-pane" id="quotation">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Customer</th><th>Status</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.quotations.length">
                        <td colspan="4" class="text-center text-muted">No records</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.quotations" :key="row.id">
                        <td>{{ row.quotation_date }}</td>
                        <td><span class="fw-semibold">{{ row.party_name || '–' }}</span><br><small class="text-orange">{{ row.quotation_no }}</small></td>
                        <td><span :class="`badge ${statusBadge(row.status)} badge-xs d-inline-flex align-items-center`"><i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(row.status) }}</span></td>
                        <td class="fw-bold text-gray-9">{{ fmt(row.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Expenses tab -->
              <div class="tab-pane fade" id="expenses">
                <div class="table-responsive">
                  <table class="table table-borderless custom-table">
                    <thead class="thead-light">
                      <tr><th>Date</th><th>Expense No</th><th>Status</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                      <tr v-if="!dash.recent_transactions.expenses.length">
                        <td colspan="4" class="text-center text-muted">No records</td>
                      </tr>
                      <tr v-for="row in dash.recent_transactions.expenses" :key="row.id">
                        <td>{{ row.date }}</td>
                        <td><span class="fw-semibold">{{ row.expense_no }}</span><br><small class="text-orange">{{ row.party_name || '–' }}</small></td>
                        <td><span :class="`badge ${statusBadge(row.status)} badge-xs d-inline-flex align-items-center`"><i class="ti ti-circle-filled fs-5 me-1"></i>{{ capitalize(row.status) }}</span></td>
                        <td class="fw-bold text-gray-9">{{ fmt(row.total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Customers -->
    <div class="row">
      <div class="col-xxl-6 col-md-6 d-flex">
        <div class="card flex-fill">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-inline-flex align-items-center">
              <span class="title-icon bg-soft-orange fs-16 me-2"><i class="ti ti-users"></i></span>
              <h5 class="card-title mb-0">Top Customers</h5>
            </div>
            <router-link :to="{ name: 'admin.party-list', query: { type: 'customer' } }" class="fs-13 fw-medium text-decoration-underline">View All</router-link>
          </div>
          <div class="card-body">
            <div v-if="!dash.top_customers.length" class="text-muted text-center py-3">No data</div>
            <div v-for="(customer, idx) in dash.top_customers" :key="customer.id"
              :class="idx < dash.top_customers.length - 1 ? 'd-flex align-items-center justify-content-between border-bottom mb-3 pb-3' : 'd-flex align-items-center justify-content-between'">
              <div class="d-flex align-items-center">
                <span class="avatar avatar-lg bg-light text-primary d-inline-flex align-items-center justify-content-center">
                  <i class="ti ti-user fs-20"></i>
                </span>
                <div class="ms-2">
                  <h6 class="fs-14 fw-medium mb-1">{{ customer.name }}</h6>
                  <div class="d-flex align-items-center item-list">
                    <p class="d-inline-flex align-items-center">
                      <i class="ti ti-map-pin me-1"></i>{{ customer.address || '–' }}
                    </p>
                    <p>{{ customer.order_count }} Orders</p>
                  </div>
                </div>
              </div>
              <div class="text-end">
                <h5>{{ fmt(customer.total_amount) }}</h5>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </template>
</template>

<script setup>
import {ref, computed, onMounted} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {useAdminDashboardStore} from '@/stores/admin/dashboard';

const dashboardStore = useAdminDashboardStore();
const dateRangeInput = ref(null);

const dash = computed(() => dashboardStore.dashboard.data);
const summaryCards = computed(() => dashboardStore.summaryCards);

const profit = computed(() => {
    const d = dashboardStore.dashboard.data;
    return Math.max(0, (d.total_sales - d.total_sales_return) - (d.total_purchase - d.total_purchase_return));
});

/** Format numbers as currency */
const fmt = (val) => {
    const n = Number(val || 0);
    return n.toLocaleString('en', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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

/** Sales & Purchase chart built from API chart_data */
const salesChartOptions = computed(() => {
    const cd = dashboardStore.dashboard.data.chart_data;
    return {
        chart: {
            toolbar: {show: false},
            xaxis: {categories: cd.labels || []},
            colors: ['#1b84ff', '#17c653'],
            plotOptions: {bar: {borderRadius: 4, columnWidth: '55%'}},
            dataLabels: {enabled: false},
            legend: {position: 'top'},
        },
        series: [
            {name: 'Sales',    data: cd.sales     || []},
            {name: 'Purchase', data: cd.purchases  || []},
        ],
    };
});

/** Sales vs Expenses chart */
const expensesChartOptions = computed(() => {
    const cd = dashboardStore.dashboard.data.chart_data;
    return {
        chart: {
            toolbar: {show: false},
            xaxis: {categories: cd.labels || []},
            colors: ['#1b84ff', '#f6820d'],
            plotOptions: {bar: {borderRadius: 4, columnWidth: '55%'}},
            dataLabels: {enabled: false},
            legend: {position: 'top'},
        },
        series: [
            {name: 'Revenue',  data: cd.sales    || []},
            {name: 'Expenses', data: cd.expenses  || []},
        ],
    };
});

onMounted(() => {
    dashboardStore.getDashboardData();

    if (dateRangeInput.value) {
        const start = moment().subtract(6, 'days');
        const end = moment();
        new DateRangePicker(
            dateRangeInput.value,
            {
                startDate: start,
                endDate: end,
                ranges: {
                    Today:         [moment(), moment()],
                    Yesterday:     [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days':[moment().subtract(29, 'days'), moment()],
                    'This Month':  [moment().startOf('month'), moment().endOf('month')],
                    'Last Month':  [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                },
            },
            (s, e) => dateRangeInput.value && (dateRangeInput.value.value = `${s.format('M/D/YYYY')} - ${e.format('M/D/YYYY')}`)
        );
    }
});
</script>
