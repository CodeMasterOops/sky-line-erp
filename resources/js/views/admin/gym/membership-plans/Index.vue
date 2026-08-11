<template>
    <PageHeader title="Membership Plans" subtitle="The terms this gym sells" @refresh="fetch">
        <template #actions>
            <button type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-1"></i>Add Plan
            </button>
        </template>
    </PageHeader>

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
                            placeholder="Search plans"
                            @input="debouncedFetch"
                        />
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="plans.data"
                        :loading="plans.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (plans.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'plan'">
                                <div class="fw-medium">{{ record.name }}</div>
                                <div class="fs-11 text-muted">{{ record.code }}</div>
                            </template>
                            <template v-if="column.key === 'term'">
                                {{ record.duration_label }}
                            </template>
                            <template v-if="column.key === 'price'">
                                <span class="d-block text-end">{{ record.price }}</span>
                            </template>
                            <template v-if="column.key === 'joining_fee'">
                                <span class="d-block text-end">{{ record.joining_fee }}</span>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', record.is_active ? 'badge-success' : 'bg-light text-dark']">
                                    {{ record.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a href="javascript:void(0);" class="me-2" @click="openEdit(record)">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);" class="me-2" :title="record.is_active ? 'Deactivate' : 'Activate'" @click="toggle(record)">
                                        <i :class="record.is_active ? 'ti ti-toggle-right' : 'ti ti-toggle-left'"></i>
                                    </a>
                                    <a href="javascript:void(0);" @click="destroy(record)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>

                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="plans.meta" />
                </div>
            </div>
        </div>
    </section>

    <PlanForm v-model:show="formOpened" :plan="editing" @saved="fetch" />
</template>

<script setup>
import { ref } from 'vue';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import PlanForm from './Form.vue';
import { useMembershipPlanStore } from '@/stores/admin/gym/membershipPlan';

const planStore = useMembershipPlanStore();
const { plans } = storeToRefs(planStore);

const formOpened = ref(false);
const editing = ref(null);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => planStore.getPlans({ filter }),
    defaults: { search: '', page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Plan', key: 'plan' },
    { title: 'Term', key: 'term' },
    { title: 'Price', key: 'price', align: 'right' },
    { title: 'Joining Fee', key: 'joining_fee', align: 'right' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetch();
    }
}, 300);

const openCreate = () => {
    editing.value = null;
    formOpened.value = true;
};

const openEdit = (record) => {
    editing.value = record;
    formOpened.value = true;
};

const toggle = async (record) => {
    try {
        const res = await planStore.toggleActive(record.id);
        toast(res.status, res.data.message);
        fetch();
    } catch (e) {
        showErrors(e);
    }
};

const destroy = (record) => {
    Swal.fire({
        title: `Delete "${record.name}"?`,
        text: 'A plan that has already been sold cannot be deleted — deactivate it instead.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (!result.value) {
            return;
        }

        try {
            const res = await planStore.deletePlan(record.id);
            toast(res.status, res.data.message);
            fetch();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>
