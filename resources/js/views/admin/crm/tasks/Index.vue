<template>
    <PageHeader
        title="Tasks"
        subtitle="Assignable CRM work items with due dates and reminders"
        @refresh="fetchTasks"
    >
        <template #actions>
            <button
                v-can="'create_crm_task'"
                type="button"
                class="btn btn-primary d-flex align-items-center"
                @click.prevent="openCreate"
            >
                <i class="ti ti-circle-plus me-2"></i> Add Task
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <VTableToolbar
                v-model="filter.search"
                placeholder="Search tasks"
                :is-filtered="isFiltered"
                @search="onSearchInput"
                @reset="resetFilters"
            >
                <template #filters>
                    <div class="form-check form-switch d-flex align-items-center me-2">
                        <input
                            id="mine-only"
                            v-model="mineOnly"
                            class="form-check-input me-2"
                            type="checkbox"
                            @change="fetchTasks"
                        />
                        <label class="form-check-label" for="mine-only">My tasks</label>
                    </div>
                    <div style="min-width: 150px;">
                        <VMultiselect
                            id="filter_status"
                            v-model="filter.status"
                            :options="taskStatuses"
                            placeholder="All statuses"
                            size="sm"
                            :disabled="mineOnly"
                        />
                    </div>
                    <div style="min-width: 150px;">
                        <VMultiselect
                            id="filter_priority"
                            v-model="filter.priority"
                            :options="taskPriorities"
                            placeholder="All priorities"
                            size="sm"
                        />
                    </div>
                    <div class="form-check form-switch d-flex align-items-center ms-2">
                        <input
                            id="overdue-only"
                            v-model="filter.overdue"
                            class="form-check-input me-2"
                            type="checkbox"
                            true-value="1"
                            false-value=""
                            :disabled="mineOnly"
                            @change="fetchTasks"
                        />
                        <label class="form-check-label" for="overdue-only">Overdue</label>
                    </div>
                </template>
            </VTableToolbar>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="taskColumns"
                        :data-source="tasks.data"
                        :pagination="false"
                        :loading="tasks.loading"
                        @change="handleTableChange"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (tasks.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'priority'">
                                <span :class="PRIORITY_BADGE[record.priority]">{{ record.priority_label }}</span>
                            </template>
                            <template v-else-if="column.key === 'status'">
                                <span :class="STATUS_BADGE[record.status]">{{ record.status_label }}</span>
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <VTableActions :actions="rowActions" :record="record" />
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="tasks.meta" />
                </div>
            </div>
        </div>
    </section>

    <FormModal v-model:show="modalOpened" :task="editing" @saved="fetchTasks" />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VTableToolbar from '@/components/base/VTableToolbar.vue';
import VTableActions from '@/components/base/VTableActions.vue';
import { useCrmTaskStore } from '@/stores/admin/crm/tasks.js';
import { useEnumStore } from '@/stores/admin/enum.js';
import { useUrlFilter } from '@/composables/useUrlFilter.js';
import { useTablePagination } from '@/composables/useTablePagination.js';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import FormModal from './FormModal.vue';
import { taskColumns, createRowActions, PRIORITY_BADGE, STATUS_BADGE } from './tableConfig.js';

const taskStore = useCrmTaskStore();
const enumStore = useEnumStore();

const { tasks } = storeToRefs(taskStore);
const { taskStatuses, taskPriorities } = storeToRefs(enumStore);

const mineOnly = ref(false);
const modalOpened = ref(false);
const editing = ref(null);

const fetchTasks = () => taskStore.getTasks({ filter, mine: mineOnly.value });

const { filter, onSearchInput } = useUrlFilter({
    defaults: { search: '', status: '', priority: '', overdue: '', page: 1, limit: 10 },
    onFilter: fetchTasks,
});

const { handleTableChange } = useTablePagination({
    meta: computed(() => tasks.value.meta),
    filter,
});

const { confirmAction } = useConfirmAction();

const isFiltered = computed(() => !!filter.status || !!filter.priority || !!filter.overdue || mineOnly.value);

const resetFilters = () => {
    filter.status = '';
    filter.priority = '';
    filter.overdue = '';
    mineOnly.value = false;
    fetchTasks();
};

onMounted(() => {
    enumStore.getTaskStatuses();
    enumStore.getTaskPriorities();
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
        title: 'Complete task?',
        text: `"${record.title}" will be marked done.`,
        icon: 'question',
        confirmButtonColor: 'green',
        confirmButtonText: 'Complete',
        action: () => taskStore.completeTask(record.id),
        onSuccess: fetchTasks,
    });
};

const handleDelete = (id) => {
    confirmAction({
        title: 'Delete task?',
        text: 'This will remove the task.',
        confirmButtonText: 'Delete',
        action: () => taskStore.deleteTask(id),
        onSuccess: fetchTasks,
    });
};

const rowActions = createRowActions({
    onEdit: openEdit,
    onComplete: handleComplete,
    onDelete: handleDelete,
});
</script>
