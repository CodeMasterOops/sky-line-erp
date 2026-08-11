<template>
    <PageHeader title="Memberships" subtitle="Terms sold, renewals and what lapses next" @refresh="fetch" />

    <section class="section">
        <div class="row g-3 mb-3">
            <div v-for="tile in tiles" :key="tile.label" class="col-lg-3 col-md-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <p class="fs-12 fw-medium mb-1">{{ tile.label }}</p>
                            <h4>{{ tile.value }}</h4>
                        </div>
                        <span :class="['avatar avatar-lg flex-shrink-0', tile.class]">
                            <i :class="[tile.icon, 'fs-16']"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>

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
                            placeholder="Member or membership no."
                            @input="debouncedFetch"
                        />
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <select v-model="filter.status" class="form-select form-select-sm w-auto" @change="fetch">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="expired">Expired</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select v-model="filter.expiring_in" class="form-select form-select-sm w-auto" @change="fetch">
                        <option value="">Any end date</option>
                        <option value="7">Expiring in 7 days</option>
                        <option value="15">Expiring in 15 days</option>
                        <option value="30">Expiring in 30 days</option>
                    </select>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="memberships.data"
                        :loading="memberships.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (memberships.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'member'">
                                <router-link
                                    :to="{ name: 'admin.gym-member-profile', params: { id: record.member_id } }"
                                    class="fw-medium"
                                >{{ record.member_name }}</router-link>
                                <div class="fs-11 text-muted">{{ record.member_code }} · {{ record.membership_no }}</div>
                            </template>
                            <template v-if="column.key === 'plan'">
                                <div>{{ record.plan_name }}</div>
                                <div class="fs-11 text-muted">{{ record.duration_label }}</div>
                            </template>
                            <template v-if="column.key === 'period'">
                                <div>{{ record.start_date }} → {{ record.end_date }}</div>
                                <div
                                    v-if="record.status === 'active'"
                                    :class="['fs-11', record.days_remaining <= 7 ? 'text-danger' : 'text-muted']"
                                >
                                    {{ remainingLabel(record.days_remaining) }}
                                </div>
                            </template>
                            <template v-if="column.key === 'payable'">
                                <span class="d-block text-end">{{ record.payable_amount }}</span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', statusClass(record.status)]">
                                    {{ record.status_label }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a
                                        v-if="record.status !== 'cancelled'"
                                        href="javascript:void(0);"
                                        class="me-2"
                                        title="Renew"
                                        @click="openRenew(record)"
                                    ><i class="ti ti-refresh"></i></a>
                                    <a
                                        v-if="record.status === 'active'"
                                        href="javascript:void(0);"
                                        title="Cancel"
                                        @click="cancel(record)"
                                    ><i class="ti ti-ban"></i></a>
                                </div>
                            </template>
                        </template>
                    </a-table>

                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="memberships.meta" />
                </div>
            </div>
        </div>
    </section>

    <AssignModal v-model:show="renewOpened" :membership="renewing" @saved="refresh" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import AssignModal from './AssignModal.vue';
import { useMembershipStore } from '@/stores/admin/gym/membership';

const membershipStore = useMembershipStore();
const { memberships, dashboard } = storeToRefs(membershipStore);

const renewOpened = ref(false);
const renewing = ref(null);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => membershipStore.getMemberships({ filter }),
    defaults: { search: '', status: '', expiring_in: '', page: 1, limit: 10 },
});

onMounted(() => membershipStore.getDashboard());

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Member', key: 'member' },
    { title: 'Plan', key: 'plan' },
    { title: 'Period', key: 'period' },
    { title: 'Payable', key: 'payable', align: 'right' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const tiles = computed(() => {
    const stats = dashboard.value.data?.memberships ?? {};

    return [
        { label: 'Active', value: stats.active ?? 0, icon: 'ti ti-user-check', class: 'bg-success' },
        { label: 'Expiring in 7 days', value: stats.expiring_soon ?? 0, icon: 'ti ti-clock-exclamation', class: 'bg-warning' },
        { label: 'Expired', value: stats.expired ?? 0, icon: 'ti ti-user-off', class: 'bg-danger' },
        { label: 'Sold this month', value: stats.sold_this_month ?? 0, icon: 'ti ti-receipt', class: 'bg-primary' },
    ];
});

const statusClass = (status) =>
    ({
        active: 'badge-success',
        pending: 'bg-light text-dark',
        expired: 'badge-danger',
        cancelled: 'badge-danger',
        frozen: 'badge-warning',
    })[status] ?? 'bg-light text-dark';

const remainingLabel = (days) => {
    if (days < 0) return 'Ended';
    if (days === 0) return 'Ends today';
    if (days === 1) return 'Ends tomorrow';
    return `${days} days left`;
};

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetch();
    }
}, 300);

const refresh = () => {
    fetch();
    membershipStore.getDashboard();
};

const openRenew = (record) => {
    renewing.value = record;
    renewOpened.value = true;
};

const cancel = (record) => {
    Swal.fire({
        title: `Cancel ${record.member_name}'s membership?`,
        input: 'text',
        inputPlaceholder: 'Reason (optional)',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, cancel',
    }).then(async (result) => {
        if (!result.isConfirmed) {
            return;
        }

        try {
            const res = await membershipStore.cancel(record.id, result.value || null);
            toast(res.status, res.data.message);
            refresh();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>
