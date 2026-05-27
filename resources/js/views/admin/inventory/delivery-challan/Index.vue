<template>
    <PageHeader title="Delivery Challans" subtitle="Manage goods delivery notes" @refresh="fetchChallans">
        <template #actions>
            <button
                v-can="'create_delivery_challan'"
                type="button"
                @click.prevent="createModalOpened = true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i>
                Create Challan
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset">
                        <i class="ti ti-search fs-14 feather-search"></i>
                    </a>
                    <input
                        type="search"
                        v-model="filter.search"
                        class="form-control form-control-sm"
                        placeholder="Search challan no"
                        @input="debouncedFetch"
                    >
                </div>
            </div>
            <select v-model="filter.status" class="form-select form-select-sm w-auto" @change="fetchChallans">
                <option value="">All Status</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
            </select>
        </div>
        <div class="card-body">
            <div class="custom-datatable-filter table-responsive">
                <a-table
                    class="table datanew table-hover table-center mb-0"
                    :columns="tableColumns"
                    :data-source="tableData"
                    :pagination="pagination"
                    :loading="challans.loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'challan_no'">
                            <router-link
                                :to="{ name: 'admin.delivery-challan-view', params: { id: record.id } }"
                                class="text-primary fw-semibold">
                                {{ record.challan_no }}
                            </router-link>
                        </template>
                        <template v-else-if="column.key === 'challan_date'">
                            {{ formatDate(record.challan_date) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span
                                class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <VTableActions :actions="rowActions" :record="record" />
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>

    <CreateDeliveryChallan v-model:create-modal-opened="createModalOpened" @saved="fetchChallans" />
    <EditDeliveryChallan v-model:challan-id="editChallanId" @saved="fetchChallans" />
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import {useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateDeliveryChallan from './Create.vue';
import EditDeliveryChallan from './Edit.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import {useDeliveryChallanStore} from '@/stores/admin/inventory/delivery-challan.js';
import {useConfirmAction} from '@/composables/useConfirmAction.js';
import {formatDate} from '@/helpers/helper.js';
import {deliveryChallanColumns, createRowActions} from './tableConfig.js';

const router = useRouter();
const deliveryChallanStore = useDeliveryChallanStore();
const {challans} = storeToRefs(deliveryChallanStore);
const {confirmDelete, confirmAction} = useConfirmAction();

const createModalOpened = ref(false);
const editChallanId = ref('');

const filter = reactive({
    search: '',
    status: '',
    page: 1,
    per_page: 15,
});

const tableColumns = deliveryChallanColumns.map((col) => ({
    ...col,
    key: col.key ?? col.dataIndex,
}));

const tableData = computed(() =>
    (challans.value.data || []).map((challan) => ({
        ...challan,
        party_name: challan.party?.name || '-',
        warehouse_name: challan.warehouse?.name || '-',
    })),
);

const pagination = computed(() => ({
    total: challans.value.meta?.total || 0,
    current: challans.value.meta?.current_page || filter.page,
    pageSize: challans.value.meta?.per_page || filter.per_page,
    showSizeChanger: true,
    showQuickJumper: true,
}));

onMounted(() => {
    fetchChallans();
});

const fetchChallans = () => {
    deliveryChallanStore.getChallans({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchChallans();
}, 300);

const handleTableChange = (pag) => {
    filter.page = pag.current;
    filter.per_page = pag.pageSize;
    fetchChallans();
};

const viewChallan = (id) => {
    router.push({ name: 'admin.delivery-challan-view', params: { id } });
};

const editChallan = (id) => {
    editChallanId.value = String(id);
};

const approveChallan = (id) => {
    confirmAction({
        title: 'Approve Delivery Challan?',
        text: 'This will issue stock from the warehouse.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => deliveryChallanStore.approveChallan(id),
        onSuccess: fetchChallans,
    });
};

const deleteChallan = (id) => {
    confirmDelete(
        () => deliveryChallanStore.deleteChallan(id),
        fetchChallans,
    );
};

const rowActions = createRowActions({
    onView: viewChallan,
    onEdit: editChallan,
    onApprove: approveChallan,
    onDelete: deleteChallan,
});
</script>
