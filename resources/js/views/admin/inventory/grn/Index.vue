<template>
    <PageHeader title="Goods Received Notes" subtitle="Manage stock receipts from suppliers" @refresh="fetchGrns">
        <template #actions>
            <button
                v-can="'create_grn'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click.prevent="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i>
                Create GRN
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
                        placeholder="Search GRN No..."
                        @input="debouncedFetch"
                    />
                </div>
            </div>
            <select v-model="filter.status" class="form-select form-select-sm w-auto" @change="fetchGrns">
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
                    :loading="grns.loading"
                    @change="handleTableChange"
                >
                    <template #bodyCell="{ column, record, index }">
                        <template v-if="column.key === 'sn'">
                            {{ index + 1 }}
                        </template>
                        <template v-else-if="column.key === 'grn_no'">
                            <router-link
                                :to="{ name: 'admin.grn-view', params: { id: record.id } }"
                                class="text-primary fw-semibold">
                                {{ record.grn_no }}
                            </router-link>
                        </template>
                        <template v-else-if="column.key === 'received_date'">
                            {{ formatDate(record.received_date) }}
                        </template>
                        <template v-else-if="column.key === 'status'">
                            <span
                                class="badge"
                                :class="record.status === 'approved' ? 'bg-success' : 'bg-secondary'">
                                {{ record.status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'billing_status'">
                            <span class="badge bg-info-subtle text-info text-capitalize">
                                {{ billingLabel(record.billing_status) }}
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

    <CreateGrn v-model:create-modal-opened="createModalOpened" @saved="fetchGrns" />
    <EditGrn v-model:grn-id="editGrnId" @saved="fetchGrns" />
</template>

<script setup>
import {computed, onMounted, reactive, ref} from 'vue';
import {useRouter} from 'vue-router';
import {storeToRefs} from 'pinia';
import debounce from 'lodash/debounce';
import CreateGrn from './Create.vue';
import EditGrn from './Edit.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import {useGrnStore} from '@/stores/admin/inventory/grn.js';
import {useConfirmAction} from '@/composables/useConfirmAction.js';
import {formatDate} from '@/helpers/helper.js';
import {grnColumns, createRowActions} from './tableConfig.js';

const router = useRouter();
const grnStore = useGrnStore();
const {grns} = storeToRefs(grnStore);
const {confirmDelete, confirmAction} = useConfirmAction();

const createModalOpened = ref(false);
const editGrnId = ref('');

const filter = reactive({
    search: '',
    status: '',
    page: 1,
    per_page: 15,
});

const tableColumns = grnColumns.map((col) => ({
    ...col,
    key: col.key ?? col.dataIndex,
}));

const billingLabel = (status) => (status || 'open').replace(/_/g, ' ');

const tableData = computed(() =>
    (grns.value.data || []).map((grn) => ({
        ...grn,
        party_name: grn.party?.name || '-',
        warehouse_name: grn.warehouse?.name || '-',
    })),
);

const pagination = computed(() => ({
    total: grns.value.meta?.total || 0,
    current: grns.value.meta?.current_page || filter.page,
    pageSize: grns.value.meta?.per_page || filter.per_page,
    showSizeChanger: true,
    showQuickJumper: true,
}));

onMounted(() => {
    fetchGrns();
});

const fetchGrns = () => {
    grnStore.getGrns({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchGrns();
}, 300);

const handleTableChange = (pag) => {
    filter.page = pag.current;
    filter.per_page = pag.pageSize;
    fetchGrns();
};

const viewGrn = (id) => {
    router.push({name: 'admin.grn-view', params: {id}});
};

const editGrn = (id) => {
    editGrnId.value = String(id);
};

const approveGrn = (id) => {
    confirmAction({
        title: 'Approve GRN?',
        text: 'This will receive stock into the warehouse.',
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Approve',
        action: () => grnStore.approveGrn(id),
        onSuccess: fetchGrns,
    });
};

const deleteGrn = (id) => {
    confirmDelete(
        () => grnStore.deleteGrn(id),
        fetchGrns,
    );
};

const rowActions = createRowActions({
    onView: viewGrn,
    onEdit: editGrn,
    onApprove: approveGrn,
    onDelete: deleteGrn,
});
</script>
