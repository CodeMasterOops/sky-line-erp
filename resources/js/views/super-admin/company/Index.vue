<template>
    <PageHeader title="Company List" subtitle="Manage companies" @refresh="fetchCompanies">
        <template #actions>
            <router-link :to="{name:'super-admin.company-create'}">
                <button class="btn btn-primary d-flex align-items-center">
                    <i class="ti ti-circle-plus me-2"></i> Add New
                </button>
            </router-link>
        </template>
    </PageHeader>

    <div class="row">
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Total Companies</p>
                        <h4>{{ stats.total }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-primary flex-shrink-0">
                        <i class="ti ti-building fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Active Companies</p>
                        <h4>{{ stats.active }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-success flex-shrink-0">
                        <i class="ti ti-circle-check fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Inactive Companies</p>
                        <h4>{{ stats.inactive }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-danger flex-shrink-0">
                        <i class="ti ti-player-pause fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">New Today</p>
                        <h4>{{ stats.newToday }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-skyblue flex-shrink-0">
                        <i class="ti ti-calendar-plus fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="companies.data"
                        :pagination="false"
                        :loading="companies.loading"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (companies.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'plan'">
                                {{ record.current_subscription?.plan?.name || '—' }}
                            </template>
                            <template v-if="column.key === 'expires_at'">
                                <span v-if="record.expires_at" :class="isExpired(record.expires_at) ? 'text-danger fw-semibold' : 'text-success'">
                                    {{ formatDate(record.expires_at) }}
                                </span>
                                <span v-else class="text-muted">—</span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           @click.prevent="updateCompanyStatus(record.id)"
                                           type="checkbox"
                                           :id="'switch'+record.id" :checked="record.is_active">
                                    <label class="form-check-label" :for="'switch'+record.id"></label>
                                </div>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <button type="button" @click="loginToCompany(record.id)"
                                            class="btn btn-xs btn-outline-primary me-2"
                                            title="Company login">
                                        <i class="fa fa-sign-in"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-xs btn-outline-success me-2"
                                        title="Upgrade plan"
                                        @click="upgrade_company = record"
                                    >
                                        <i class="ti ti-arrow-up-circle"></i>
                                    </button>
                                    <button @click="reset_company_password=record.id" type="button"
                                            class="btn btn-xs btn-outline-warning me-2" title="Password Reset">
                                        <i class="fa fa-refresh"></i>
                                    </button>
                                    <router-link
                                        :to="{name:'super-admin.company-edit',params:{id:record.id}}"
                                        class="btn btn-xs btn-outline-primary me-2">
                                        <i class="fa fa-edit"></i>
                                    </router-link>
                                    <router-link
                                        :to="{name:'super-admin.company-provision-log',params:{id:record.id}}"
                                        class="btn btn-xs btn-outline-info"
                                        title="Provision Log">
                                        <i class="ti ti-database"></i>
                                    </router-link>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="companies.meta" />
                </div>
            </div>
        </div>
    </section>
    <RestPassword v-model:reset_password="reset_company_password"/>
    <UpgradePlan v-model:company="upgrade_company" @upgraded="fetchCompanies"/>

</template>

<script setup>
import {onMounted, reactive, ref, computed, watch} from "vue";
import VPagination from '@/components/base/VPagination.vue';
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useCompanyStore} from "@/stores/super-admin/company";
import {useSuperAdminDashboardStore} from "@/stores/super-admin/dashboard";
import {storeToRefs} from "pinia";
import RestPassword from './ResetPassword.vue';
import UpgradePlan from './UpgradePlan.vue';
import {useRouter} from "vue-router";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth.js";
import Swal from "sweetalert2";

const companyStore = useCompanyStore();
const dashboardStore = useSuperAdminDashboardStore();
const authStore = useSuperAdminAuthStore();
const router = useRouter();

const filter = reactive({
    limit: 10,
    page: 1
});

const reset_company_password = ref('');
const upgrade_company = ref(null);

const {companies} = storeToRefs(companyStore);

const stats = computed(() => {
    const dash = dashboardStore.dashboard.data;

    return {
        total: dash.total_companies ?? companies.value.meta?.total ?? 0,
        active: dash.active_companies ?? 0,
        inactive: dash.inactive_companies ?? 0,
        newToday: dash.companies_today ?? 0,
    };
});

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Name',
        dataIndex: 'company_name',
        sorter: true,
    },
    {
        title: 'Code',
        dataIndex: 'code',
        sorter: true,
    },
    {
        title: 'Email',
        dataIndex: 'email',
        sorter: true,
    },
    {
        title: 'Plan',
        key: 'plan',
    },
    {
        title: 'Expires',
        key: 'expires_at',
    },
    {
        title: 'Status',
        key: 'status',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

onMounted(() => {
    fetchCompanies();
    dashboardStore.getDashboardData();
});

const fetchCompanies = () => {
    companyStore.getCompanies({filter});
    dashboardStore.getDashboardData();
};

watch(() => [filter.page, filter.limit], () => {
    fetchCompanies();
});

const updateCompanyStatus = async (id) => {
    Swal.fire({
        title: 'Are You Sure to change status ? ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await companyStore.updateStatus(id);
                toast(res.status, res.data.message);
                fetchCompanies();
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
};

const isExpired = (dateStr) => {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
};

const loginToCompany = async (id) => {
    Swal.fire({
        title: 'Are You Sure to login ? ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await authStore.companyLogin(id);
                toast(res.status, res.data.message);
                const {href: companyLoginUrl} = router.resolve({name: 'admin.dashboard'})
                window.open(companyLoginUrl, '_blank');
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>

