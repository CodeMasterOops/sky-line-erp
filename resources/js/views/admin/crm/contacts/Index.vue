<template>
    <PageHeader
        title="Contacts"
        subtitle="Customers, suppliers and leads in one place"
        export-entity="party"
        :get-export-filters="getExportFilters"
        @refresh="fetchContacts"
    >
        <template #actions>
            <button
                v-can="'create_party'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click.prevent="openCreate"
            >
                <i class="ti ti-circle-plus me-2"></i> Add Contact
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <VTableToolbar
                v-model="filter.search"
                placeholder="Search contacts"
                :is-filtered="isFiltered"
                @search="onSearchInput"
                @reset="resetFilters"
            >
                <template #filters>
                    <select v-model="filter.type" class="form-select form-select-sm" @change="fetchContacts">
                        <option value="">All types</option>
                        <option v-for="t in partyTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </template>
            </VTableToolbar>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="contactColumns"
                        :data-source="parties.data"
                        :pagination="false"
                        :loading="parties.loading"
                        @change="handleTableChange"
                    >
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

    <CreateParty v-model:create-modal-opened="createModalOpened" />
    <EditParty v-model:party_id="edit_party_id" />
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import CreateParty from '@/views/admin/party/Create.vue';
import EditParty from '@/views/admin/party/Edit.vue';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import { usePartyStore } from '@/stores/admin/party.js';
import { useCrmLeadStore } from '@/stores/admin/crm/leads.js';
import { useEnumStore } from '@/stores/admin/enum.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { contactColumns, createRowActions } from './tableConfig.js';

const partyStore = usePartyStore();
const crmLeadStore = useCrmLeadStore();
const enumStore = useEnumStore();
const route = useRoute();
const router = useRouter();

const { parties } = storeToRefs(partyStore);
const { partyTypes } = storeToRefs(enumStore);

const edit_party_id = ref('');
const createModalOpened = ref(false);

const fetchContacts = () => partyStore.getParties({ filter });

const { filter, onSearchInput } = useUrlFilter({
    defaults: { search: '', type: '', page: 1, limit: 10 },
    onFilter: fetchContacts,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => parties.value.meta),
    filter,
});

const { confirmAction } = useConfirmAction();

const isFiltered = computed(() => !!filter.type || !!filter.search);

const resetFilters = () => {
    filter.search = '';
    filter.type = '';
    fetchContacts();
};

onMounted(() => {
    enumStore.getPartyTypes();
});

// Open a contact's edit modal when ?open_party=<id> is present (deep links
// from sales/purchase documents via PartyMetaPanel).
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
    { immediate: true },
);

// Refresh once the create/edit modals close so newly added or edited contacts
// (incl. freshly provisioned lead status) are reflected in the list.
watch(createModalOpened, (opened, was) => {
    if (was && !opened) {
        fetchContacts();
    }
});
watch(edit_party_id, (id, prev) => {
    if (prev && !id) {
        fetchContacts();
    }
});

const openCreate = () => { createModalOpened.value = true; };
const openEdit = (id) => { edit_party_id.value = id; };

const handleDelete = (id) => {
    confirmAction({
        title: 'Delete contact?',
        text: 'This will remove the contact.',
        confirmButtonText: 'Delete',
        action: () => partyStore.deleteParty(id),
        onSuccess: fetchContacts,
    });
};

const handleConvert = (record) => {
    confirmAction({
        title: 'Convert lead to customer?',
        text: `${record.name} will become a customer. All history is preserved.`,
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Convert',
        action: () => crmLeadStore.convert(record.id),
        onSuccess: fetchContacts,
    });
};

const rowActions = createRowActions({
    onEdit: openEdit,
    onConvert: handleConvert,
    onDelete: handleDelete,
});

const getExportFilters = () => ({
    search: filter.search,
    type: filter.type,
});
</script>
