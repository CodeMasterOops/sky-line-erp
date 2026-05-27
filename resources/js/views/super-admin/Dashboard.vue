<template>
    <div class="d-lg-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="mb-1">Welcome, {{ adminName }}</h2>
            <p>You have <span class="text-primary fw-bold">{{ dash.companies_today }}</span> New {{ dash.companies_today === 1 ? 'Company' : 'Companies' }} Today</p>
        </div>
        <ul class="table-top-head">
            <li>
                <div class="input-icon-start position-relative">
                    <span class="input-icon-addon fs-16 text-gray-9">
                        <i class="ti ti-calendar"></i>
                    </span>
                    <input type="text" class="form-control date-range bookingrange" ref="dateRangeInput"
                        placeholder="Select Date Range">
                </div>
            </li>
            <li>
                <a data-bs-toggle="tooltip" data-bs-placement="top" id="collapse-header" @click="toggleHeader"
                    aria-label="Collapse" data-bs-original-title="Collapse"><i class="ti ti-chevron-up"></i></a>
            </li>
        </ul>
    </div>

    <div v-if="dashboardStore.isLoading" class="text-center py-5">
        <span class="spinner-border text-primary"></span>
    </div>

    <template v-else>
        <div class="row">
            <div class="col-xl-3 col-sm-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="avatar avatar-md bg-dark mb-3">
                                <i class="ti ti-building fs-16"></i>
                            </span>
                            <span :class="`badge ${growthBadgeClass(dash.growth.total_companies)} fw-normal mb-3`">
                                {{ formatGrowth(dash.growth.total_companies) }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="mb-1">{{ dash.total_companies }}</h2>
                                <p class="fs-13">Total Companies</p>
                            </div>
                            <div class="company-bar1">
                                <apexchart type="bar" width="50" :options="totalChartOptions" :series="totalChartSeries">
                                </apexchart>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="avatar avatar-md bg-dark mb-3">
                                <i class="ti ti-carousel-vertical fs-16"></i>
                            </span>
                            <span :class="`badge ${growthBadgeClass(dash.growth.active_companies)} fw-normal mb-3`">
                                {{ formatGrowth(dash.growth.active_companies) }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="mb-1">{{ dash.active_companies }}</h2>
                                <p class="fs-13">Active Companies</p>
                            </div>
                            <div class="company-bar2">
                                <apexchart type="bar" width="50" :options="activeChartOptions" :series="activeChartSeries">
                                </apexchart>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="avatar avatar-md bg-dark mb-3">
                                <i class="ti ti-chalkboard-off fs-16"></i>
                            </span>
                            <span :class="`badge ${growthBadgeClass(dash.growth.onboarded_companies)} fw-normal mb-3`">
                                {{ formatGrowth(dash.growth.onboarded_companies) }}
                            </span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="mb-1">{{ dash.onboarded_companies }}</h2>
                                <p class="fs-13">Onboarded Companies</p>
                            </div>
                            <div class="company-bar3">
                                <apexchart type="bar" width="50" :options="onboardedChartOptions" :series="onboardedChartSeries">
                                </apexchart>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="avatar avatar-md bg-dark mb-3">
                                <i class="ti ti-businessplan fs-16"></i>
                            </span>
                            <span class="badge bg-secondary fw-normal mb-3">0%</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h2 class="mb-1">{{ formatCurrency(dash.total_earnings) }}</h2>
                                <p class="fs-13">Total Earnings</p>
                            </div>
                            <div class="company-bar4">
                                <apexchart type="bar" width="50" :options="earningsChartOptions" :series="earningsChartSeries">
                                </apexchart>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xxl-3 col-lg-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="mb-2">Companies</h5>
                        <span class="badge bg-light text-dark mb-2">Last 7 Days</span>
                    </div>
                    <div class="card-body">
                        <div id="company-chart">
                            <apexchart type="bar" height="240" :options="weeklyChartOptions" :series="weeklyChartSeries">
                            </apexchart>
                        </div>
                        <p class="f-13 d-inline-flex align-items-center">
                            <span :class="`badge ${growthBadgeClass(dash.growth.new_companies)} me-1`">{{ formatGrowth(dash.growth.new_companies) }}</span>
                            {{ Math.abs(dash.companies_from_last_month) }} {{ dash.companies_from_last_month === 1 ? 'company' : 'companies' }}
                            {{ dash.companies_from_last_month >= 0 ? 'more' : 'fewer' }} than last month
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="mb-2">Company Growth</h5>
                        <span class="badge bg-light text-dark mb-2">Last 12 Months</span>
                    </div>
                    <div class="card-body pb-0">
                        <div class="d-flex align-items-center justify-content-between flex-wrap">
                            <div class="mb-1">
                                <h5 class="mb-1">{{ dash.total_companies }}</h5>
                                <p><span :class="`text-${dash.growth.total_companies >= 0 ? 'success' : 'danger'} fw-bold`">{{ formatGrowth(dash.growth.total_companies) }}</span> vs last month end</p>
                            </div>
                            <p class="fs-13 text-gray-9 d-flex align-items-center mb-1">
                                <i class="ti ti-circle-filled me-1 fs-6 text-primary"></i>New Companies
                            </p>
                        </div>
                        <div id="revenue-income">
                            <apexchart type="bar" height="230" :options="monthlyChartOptions" :series="monthlyChartSeries">
                            </apexchart>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xxl-3 col-xl-12 d-flex">
                <div class="card flex-fill">
                    <div class="card-header pb-2 d-flex align-items-center justify-content-between flex-wrap">
                        <h5 class="mb-2">Top Plans</h5>
                    </div>
                    <div class="card-body">
                        <div v-if="!dash.top_plans.length" class="text-center py-4">
                            <p class="text-muted mb-0">No active subscriptions yet.</p>
                        </div>
                        <template v-else>
                            <div id="plan-overview">
                                <apexchart type="donut" height="240" :options="donutChartOptions" :series="donutChartSeries">
                                </apexchart>
                            </div>
                            <div v-for="plan in dash.top_plans" :key="plan.name"
                                class="d-flex align-items-center justify-content-between mb-2">
                                <p class="f-13 mb-0">{{ plan.name }}</p>
                                <p class="f-13 fw-medium text-gray-9">{{ plan.subscribers }} · {{ formatCurrency(plan.revenue) }}</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import {computed, onMounted, ref} from 'vue';
import moment from 'moment';
import DateRangePicker from 'daterangepicker';
import 'daterangepicker/daterangepicker.css';
import {storeToRefs} from 'pinia';
import {useSuperAdminDashboardStore} from '@/stores/super-admin/dashboard';
import {useSuperAdminProfileStore} from '@/stores/super-admin/profile';
import {formatSuperAdminMoney} from '@/helpers/formatSuperAdminMoney.js';
import {
    companyChart,
    revenueCharts,
    donutChart,
    totalChart,
    activeChart,
    inactiveChart,
    locationChart,
} from '@/assets/data/super-admin.js';

const dashboardStore = useSuperAdminDashboardStore();
const profileStore = useSuperAdminProfileStore();
const {dashboard} = storeToRefs(dashboardStore);

const dateRangeInput = ref(null);

const dash = computed(() => dashboard.value.data);
const adminName = computed(() => profileStore.profile.data?.name || 'Super Admin');

const formatCurrency = formatSuperAdminMoney;

const formatGrowth = (value) => {
    const num = Number(value || 0);
    const prefix = num > 0 ? '+' : '';
    return `${prefix}${num}%`;
};

const growthBadgeClass = (value) => {
    const num = Number(value || 0);
    if (num > 0) {
        return 'bg-success';
    }
    if (num < 0) {
        return 'bg-danger';
    }
    return 'bg-secondary';
};

const sparklineSeries = (name, data) => ([{
    name,
    data: data || [],
}]);

const totalChartSeries = computed(() => sparklineSeries('Companies', dash.value.chart_data?.sparklines?.total));
const activeChartSeries = computed(() => sparklineSeries('Active', dash.value.chart_data?.sparklines?.active));
const onboardedChartSeries = computed(() => sparklineSeries('Onboarded', dash.value.chart_data?.sparklines?.onboarded));
const earningsChartSeries = computed(() => sparklineSeries('Earnings', dash.value.chart_data?.sparklines?.earnings));

const totalChartOptions = totalChart.total;
const activeChartOptions = activeChart.active;
const onboardedChartOptions = inactiveChart.inactive;
const earningsChartOptions = locationChart.location;

const weeklyChartSeries = computed(() => [{
    name: 'Company',
    data: dash.value.chart_data?.weekly?.companies || [],
}]);

const weeklyChartOptions = computed(() => ({
    ...companyChart.company,
    xaxis: {
        ...companyChart.company.xaxis,
        categories: dash.value.chart_data?.weekly?.labels || [],
    },
}));

const monthlyChartSeries = computed(() => [
    {
        name: 'New Companies',
        data: dash.value.chart_data?.monthly?.new_companies || [],
    },
    {
        name: 'Active Sign-ups',
        data: dash.value.chart_data?.monthly?.active_companies || [],
    },
]);

const monthlyChartOptions = computed(() => ({
    ...revenueCharts.income,
    xaxis: {
        ...revenueCharts.income.xaxis,
        categories: dash.value.chart_data?.monthly?.labels || [],
    },
    yaxis: {
        ...revenueCharts.income.yaxis,
        max: undefined,
        labels: {
            ...revenueCharts.income.yaxis.labels,
            formatter: (value) => Math.round(value),
        },
    },
    tooltip: {
        y: {
            formatter: (value) => `${value} companies`,
        },
    },
}));

const donutChartSeries = computed(() => dash.value.top_plans?.map((plan) => plan.subscribers) || []);
const donutChartOptions = computed(() => ({
    ...donutChart.donut,
    labels: dash.value.top_plans?.map((plan) => plan.name) || [],
}));

const toggleHeader = () => {
    document.getElementById('collapse-header')?.classList.toggle('active');
    document.body.classList.toggle('header-collapse');
};

onMounted(() => {
    profileStore.getProfile();
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
                    Today: [moment(), moment()],
                    Yesterday: [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [
                        moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month'),
                    ],
                },
            },
            (rangeStart, rangeEnd) => {
                if (dateRangeInput.value) {
                    dateRangeInput.value.value = `${rangeStart.format('M/D/YYYY')} - ${rangeEnd.format('M/D/YYYY')}`;
                }
            }
        );

        dateRangeInput.value.value = `${start.format('M/D/YYYY')} - ${end.format('M/D/YYYY')}`;
    }
});
</script>
