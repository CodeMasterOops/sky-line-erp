<template>
    <div>
        <PageHeader
            title="Tax Templates"
            subtitle="Default tax rates seeded to new companies on registration"
            @refresh="loadTemplates"
        >
            <template #actions>
                <button
                    type="button"
                    class="btn btn-primary d-flex align-items-center"
                    @click="createModalOpened = true"
                >
                    <i class="ti ti-circle-plus me-2"></i> Add Template
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <super-admin-settings-sidebar></super-admin-settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-header">
                        <h4 class="fs-18 fw-bold">Tax templates</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                            <i class="ti ti-info-circle fs-5 mt-1"></i>
                            <div>
                                <strong>How this works:</strong> These templates are applied as system tax rates when a new company registers.
                                Company admins can view system taxes but cannot edit or delete them.
                                You can add, edit, or remove templates here — changes apply to <em>new</em> companies only.
                            </div>
                        </div>

                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="templates"
                                :loading="loading"
                                :pagination="false"
                            >
                                <template #bodyCell="{ column, record }">
                                    <template v-if="column.key === 'rate'">
                                        <span class="text-end d-block">{{ record.rate }}%</span>
                                    </template>
                                    <template v-if="column.key === 'type_label'">
                                        {{ record.type_label || '–' }}
                                    </template>
                                    <template v-if="column.key === 'tds_category_label'">
                                        {{ record.tds_category_label || '–' }}
                                    </template>
                                    <template v-if="column.key === 'is_default'">
                                        <span
                                            class="badge badge-sm"
                                            :class="record.is_default ? 'badge-success' : 'badge-secondary'"
                                        >
                                            {{ record.is_default ? 'Yes' : 'No' }}
                                        </span>
                                    </template>
                                    <template v-if="column.key === 'description'">
                                        <span class="text-muted small">{{ record.description || '–' }}</span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <a
                                                class="me-2"
                                                href="javascript:void(0);"
                                                @click="editTemplateId = record.id"
                                            >
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <a
                                                href="javascript:void(0);"
                                                @click="deleteTemplate(record.id)"
                                            >
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <CreateTaxTemplate v-model:create-modal-opened="createModalOpened" @saved="loadTemplates"/>
    <EditTaxTemplate v-model:template-id="editTemplateId" @saved="loadTemplates"/>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { apiSuperAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast.js';
import Swal from 'sweetalert2';
import CreateTaxTemplate from './Create.vue';
import EditTaxTemplate from './Edit.vue';

const templates = ref([]);
const loading = ref(false);
const createModalOpened = ref(false);
const editTemplateId = ref('');

const columns = [
    { title: 'Name', dataIndex: 'name', key: 'name' },
    { title: 'Rate (%)', key: 'rate', align: 'right' },
    { title: 'Type', key: 'type_label' },
    { title: 'TDS Category', key: 'tds_category_label' },
    { title: 'Seeded by Default', key: 'is_default' },
    { title: 'Description', key: 'description' },
    { title: 'Action', key: 'action', align: 'center' },
];

const loadTemplates = async () => {
    loading.value = true;
    try {
        const res = await apiSuperAdmin('tax-template', 'get');
        templates.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const deleteTemplate = async (id) => {
    const result = await Swal.fire({
        title: 'Delete template?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
    if (!result.isConfirmed) {
        return;
    }
    try {
        await apiSuperAdmin(`tax-template/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadTemplates();
    } catch (e) {
        showErrors(e);
    }
};

onMounted(() => {
    loadTemplates();
});
</script>
