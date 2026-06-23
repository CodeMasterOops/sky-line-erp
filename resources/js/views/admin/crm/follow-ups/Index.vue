<template>
    <PageHeader
        title="Follow-ups"
        subtitle="Scheduled touchpoints with customers and leads"
        @refresh="fetchFollowUps"
    >
        <template #actions>
            <button
                v-can="'create_crm_follow_up'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click.prevent="openCreate"
            >
                <i class="ti ti-circle-plus me-2"></i> Schedule Follow-up
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <VTableToolbar
                v-model="filter.search"
                placeholder="Search follow-ups"
                :is-filtered="isFiltered"
                @search="onSearchInput"
                @reset="resetFilters"
            >
                <template #filters>
                    <div class="form-check form-switch d-flex align-items-center me-2">
                        <input
                            id="due-only"
                            v-model="dueOnly"
                            class="form-check-input me-2"
                            type="checkbox"
                            @change="fetchFollowUps"
                        />
                        <label class="form-check-label" for="due-only">Due now</label>
                    </div>
                    <select v-model="filter.status" class="form-select form-select-sm" :disabled="dueOnly" @change="fetchFollowUps">
                        <option value="">All statuses</option>
                        <option v-for="s in followUpStatuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <select v-model="filter.channel" class="form-select form-select-sm" @change="fetchFollowUps">
                        <option value="">All channels</option>
                        <option v-for="c in followUpChannels" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </template>
            </VTableToolbar>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="followUpColumns"
                        :data-source="followUps.data"
                        :pagination="false"
                        :loading="followUps.loading"
                        @change="handleTableChange"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (followUps.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'scheduled'">
                                {{ formatDateTime(record.scheduled_at) }}
                            </template>
                            <template v-else-if="column.key === 'status'">
                                <span :class="STATUS_BADGE[record.status]">{{ record.status_label }}</span>
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <VTableActions :actions="rowActions" :record="record" />
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="followUps.meta" />
                </div>
            </div>
        </div>
    </section>

    <FormModal v-model:show="modalOpened" :follow-up="editing" @saved="fetchFollowUps" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import { useCrmFollowUpStore } from '@/stores/admin/crm/followUps.js';
import { useEnumStore } from '@/stores/admin/enum.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import FormModal from './FormModal.vue';
import { followUpColumns, createRowActions, STATUS_BADGE } from './tableConfig.js';

const followUpStore = useCrmFollowUpStore();
const enumStore = useEnumStore();

const { followUps } = storeToRefs(followUpStore);
const { followUpStatuses, followUpChannels } = storeToRefs(enumStore);

const dueOnly = ref(false);
const modalOpened = ref(false);
const editing = ref(null);

const fetchFollowUps = () => followUpStore.getFollowUps({ filter, due: dueOnly.value });

const { filter, onSearchInput } = useUrlFilter({
    defaults: { search: '', status: '', channel: '', page: 1, limit: 10 },
    onFilter: fetchFollowUps,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => followUps.value.meta),
    filter,
});

const { confirmAction } = useConfirmAction();

const isFiltered = computed(() => !!filter.status || !!filter.channel || dueOnly.value);

const resetFilters = () => {
    filter.status = '';
    filter.channel = '';
    dueOnly.value = false;
    fetchFollowUps();
};

const formatDateTime = (value) => (value ? new Date(value).toLocaleString() : '');

onMounted(() => {
    enumStore.getFollowUpStatuses();
    enumStore.getFollowUpChannels();
});

const openCreate = () => {
    editing.value = null;
    modalOpened.value = true;
};

const openEdit = (record) => {
    editing.value = record;
    modalOpened.value = true;
};

const handleComplete = (record) => {
    confirmAction({
        title: 'Complete follow-up?',
        text: `Mark the ${record.channel_label} with ${record.party_name} as done.`,
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Complete',
        action: () => followUpStore.completeFollowUp(record.id),
        onSuccess: fetchFollowUps,
    });
};

const handleDelete = (id) => {
    confirmAction({
        title: 'Delete follow-up?',
        text: 'This will remove the follow-up.',
        confirmButtonText: 'Delete',
        action: () => followUpStore.deleteFollowUp(id),
        onSuccess: fetchFollowUps,
    });
};

const rowActions = createRowActions({
    onEdit: openEdit,
    onComplete: handleComplete,
    onDelete: handleDelete,
});
</script>
