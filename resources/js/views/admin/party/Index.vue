<template>
    <PageHeader :title="pageTitle" :subtitle="pageSubtitle" @refresh="fetchParties">
        <template #actions>
            <button v-can="'create_party'" type="button" @click.prevent="openCreate"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <VTableToolbar v-model="filter.search" :placeholder="`Search ${pageTitle}`" @search="onSearchInput" />
            <div class="card-body">
                <div class="table-responsive">
                    <a-table class="table datanew table-hover table-center mb-0" :columns="partyColumns"
                        :data-source="parties.data" :pagination="false" :loading="parties.loading"
                        @change="handleTableChange">
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (parties.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <VTableActions :actions="rowActions" :record="record" />
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="parties.meta" />
                </div>
            </div>
        </div>
    </section>

    <CreateParty v-model:create-modal-opened="createModalOpened" :type="filter.type || undefined" />
    <EditParty v-model:party_id="edit_party_id" />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import CreateParty from './Create.vue';
import EditParty from './Edit.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import { usePartyStore } from '@/stores/admin/party.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { partyColumns, createRowActions } from './tableConfig.js';

const VALID_PARTY_TYPES = ['customer', 'supplier', 'lead'];

const partyStore = usePartyStore();
const route = useRoute();
const router = useRouter();

const edit_party_id = ref('');
const createModalOpened = ref(false);

const { parties } = storeToRefs(partyStore);

const fetchParties = () => partyStore.getParties({ filter });

const { filter, onSearchInput } = useUrlFilter({
    defaults: { search: '', type: '', page: 1, limit: 10 },
    onFilter: fetchParties,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => parties.value.meta),
    filter,
});

const { confirmDelete } = useConfirmAction();

// Normalize invalid type values that may arrive via direct URL manipulation
watch(() => filter.type, (val) => {
    if (val && !VALID_PARTY_TYPES.includes(val)) {
        filter.type = '';
    }
});

// Open party edit modal when ?open_party=<id> is present in the URL
watch(
    () => route.query.open_party,
    (q) => {
        const raw = Array.isArray(q) ? q[0] : q;
        if (raw == null || String(raw).trim() === '') return;
        edit_party_id.value = String(raw);
        const next = { ...route.query };
        delete next.open_party;
        router.replace({ query: next });
    },
    { immediate: true }
);

const pageTitle = computed(() => {
    switch (filter.type) {
        case 'customer': return 'Customers';
        case 'supplier': return 'Suppliers';
        case 'lead': return 'Leads';
        default: return 'Party list';
    }
});

const pageSubtitle = computed(() => {
    switch (filter.type) {
        case 'customer': return 'People and businesses you sell to';
        case 'supplier': return 'Vendors you purchase from';
        case 'lead': return 'Prospects before they become customers';
        default: return 'Manage your parties';
    }
});

const openCreate = () => { createModalOpened.value = true; };
const openEdit = (id) => { edit_party_id.value = id; };

const handleDelete = (id) => {
    confirmDelete(
        () => partyStore.deleteParty(id),
        fetchParties,
    );
};

const rowActions = createRowActions({
    onEdit:   openEdit,
    onDelete: handleDelete,
});
</script>
