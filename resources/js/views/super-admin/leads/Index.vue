<template>
    <PageHeader title="Leads" subtitle="Manage incoming inquiries" @refresh="fetchLeads(true)" />

    <div class="row">
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Total Leads</p>
                        <h4>{{ stats.total }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-primary flex-shrink-0">
                        <i class="ti ti-users fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">New</p>
                        <h4>{{ stats.new }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-info flex-shrink-0">
                        <i class="ti ti-bell fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Demo Given</p>
                        <h4>{{ stats.demoGiven }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-warning flex-shrink-0">
                        <i class="ti ti-presentation fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 d-flex">
            <div class="card flex-fill">
                <div class="card-body d-flex align-items-center justify-content-between">
                    <div>
                        <p class="fs-12 fw-medium mb-1">Converted</p>
                        <h4>{{ stats.converted }}</h4>
                    </div>
                    <span class="avatar avatar-lg bg-success flex-shrink-0">
                        <i class="ti ti-circle-check fs-16"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <div class="search-set">
                    <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset">
                            <i class="ti ti-search fs-14 feather-search"></i>
                        </a>
                        <input
                            v-model="filter.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Search by name, company, email"
                            @keyup.enter="fetchLeads(true)"
                        />
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2" style="min-width:150px">
                    <VMultiselect
                        id="filter_status"
                        v-model="filter.status"
                        placeholder="All Statuses"
                        :options="statuses"
                        value-prop="value"
                        name-prop="label"
                        size="sm"
                    />
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="leads.data"
                        :loading="leads.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (leads.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'company'">
                                <div>
                                    <div class="fw-medium">{{ record.company_name }}</div>
                                    <div class="fs-11 text-muted">{{ record.business_type_label }}</div>
                                </div>
                            </template>
                            <template v-if="column.key === 'contact'">
                                <div>
                                    <div>{{ record.full_name }}</div>
                                    <div class="fs-11 text-muted">{{ record.email }}</div>
                                    <div class="fs-11 text-muted">{{ record.phone }}</div>
                                </div>
                            </template>
                            <template v-if="column.key === 'plan'">
                                <span v-if="record.plan_interest" class="badge badge-info badge-sm">{{ record.plan_interest }}</span>
                                <span v-else class="text-muted">—</span>
                            </template>
                            <template v-if="column.key === 'branches'">
                                {{ record.branch_count }}
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', statusBadge(record.status)]">
                                    {{ record.status_label }}
                                </span>
                            </template>
                            <template v-if="column.key === 'date'">
                                {{ formatDate(record.created_at) }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <router-link :to="{name: 'super-admin.leads-show', params: {id: record.id}}" class="me-2">
                                        <i class="ti ti-eye"></i>
                                    </router-link>
                                    <a href="javascript:void(0);" @click="deleteLead(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="leads.meta" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {onMounted, reactive, watch} from 'vue';
import {storeToRefs} from 'pinia';
import Swal from 'sweetalert2';
import VPagination from '@/components/base/VPagination.vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useLeadStore} from '@/stores/super-admin/lead';

const leadStore = useLeadStore();
const {leads, stats} = storeToRefs(leadStore);

const filter = reactive({
    search: '',
    status: '',
    page: 1,
    limit: 25,
});

const statuses = [
    {value: 'new', label: 'New'},
    {value: 'contacted', label: 'Contacted'},
    {value: 'demo_given', label: 'Demo Given'},
    {value: 'converted', label: 'Converted'},
    {value: 'lost', label: 'Lost'},
];

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Company', key: 'company'},
    {title: 'Contact', key: 'contact'},
    {title: 'Plan Interest', key: 'plan'},
    {title: 'Branches', key: 'branches', align: 'center'},
    {title: 'Status', key: 'status'},
    {title: 'Date', key: 'date'},
    {title: 'Action', key: 'action', align: 'center'},
];

const statusBadge = (status) => ({
    'badge-primary': status === 'new',
    'badge-warning': status === 'contacted',
    'badge-info': status === 'demo_given',
    'badge-success': status === 'converted',
    'badge-danger': status === 'lost',
});

const formatDate = (dateStr) => {
    if (!dateStr) {
        return '—';
    }
    return new Date(dateStr).toLocaleDateString();
};

onMounted(() => {
    fetchLeads();
});

const fetchLeads = (refetch = false) => {
    if (refetch) {
        filter.page = 1;
    }
    leadStore.getLeads({filter});
};

watch(() => [filter.page, filter.limit], () => {
    fetchLeads();
});

watch(() => filter.status, () => {
    fetchLeads(true);
});

const deleteLead = async (id) => {
    Swal.fire({
        title: 'Delete this lead?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await leadStore.deleteLead(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
