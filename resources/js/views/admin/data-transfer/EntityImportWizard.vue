<template>
    <VModal :show-modal="open" size="lg" :title="title" @close-click="close">
        <template #modal-body>
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item" v-for="(label, idx) in stepLabels" :key="idx">
                            <span class="nav-link" :class="{ active: step === idx, disabled: idx > maxReachableStep }">
                                {{ label }}
                            </span>
                        </li>
                    </ul>

                    <div v-if="step === 0">
                        <p class="text-muted small">
                            Upload CSV or XLSX. <a href="#" @click.prevent="downloadTemplate('csv')">CSV template</a>
                            · <a href="#" @click.prevent="downloadTemplate('xlsx')">XLSX template</a>
                        </p>
                        <input type="file" class="form-control" accept=".csv,.xlsx,.txt" @change="onFileSelect" />
                    </div>

                    <div v-else-if="step === 1 && job">
                        <p class="text-muted small">Map file columns to {{ entityLabel }} fields.</p>
                        <div class="mb-3">
                            <label class="form-label">Duplicate handling</label>
                            <select v-model="duplicateMode" class="form-select form-select-sm">
                                <option value="update">Update existing (match by code)</option>
                                <option value="skip">Skip existing</option>
                                <option value="create_only">Create only (fail if exists)</option>
                            </select>
                        </div>
                        <div class="table-responsive" style="max-height: 320px">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>File column</th>
                                        <th>Maps to</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="header in detectedHeaders" :key="header">
                                        <td>{{ header }}</td>
                                        <td>
                                            <select v-model="mapping[header]" class="form-select form-select-sm">
                                                <option value="">— Skip —</option>
                                                <option v-for="f in fields" :key="f" :value="f">{{ f }}</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="step === 2 && job">
                        <div v-if="isActive(job.status)" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status" />
                            <p class="mt-2 text-muted">{{ job.status }}…</p>
                        </div>
                        <template v-else>
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <div class="border rounded p-2 text-center">
                                        <div class="fw-bold">{{ job.stats?.total_rows ?? 0 }}</div>
                                        <div class="small text-muted">Rows</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 text-center text-success">
                                        <div class="fw-bold">{{ job.stats?.valid ?? 0 }}</div>
                                        <div class="small text-muted">Valid</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2 text-center text-danger">
                                        <div class="fw-bold">{{ job.stats?.invalid ?? 0 }}</div>
                                        <div class="small text-muted">Invalid</div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="previewRows.length" class="table-responsive" style="max-height: 240px">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Errors</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in previewRows" :key="row.row_number">
                                            <td>{{ row.row_number }}</td>
                                            <td class="text-danger small">{{ (row.errors || []).join('; ') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>

                    <div v-else-if="step === 3 && job">
                        <div v-if="isActive(job.status)" class="mb-3">
                            <label class="form-label">Import progress</label>
                            <div class="progress">
                                <div
                                    class="progress-bar"
                                    :style="{ width: progressPercent + '%' }"
                                />
                            </div>
                            <p class="small text-muted mt-1">
                                {{ job.stats?.processed ?? 0 }} / {{ job.stats?.valid ?? job.stats?.total_rows ?? 0 }} rows
                            </p>
                        </div>
                        <div v-else class="alert" :class="job.status === 'completed' ? 'alert-success' : 'alert-warning'">
                            Import {{ job.status }}.
                            <span v-if="job.stats"> Created: {{ job.stats.created }}, Updated: {{ job.stats.updated }},
                                Failed: {{ job.stats.failed }}, Skipped: {{ job.stats.skipped }}.</span>
                        </div>
                        <button
                            v-if="job.has_errors_download"
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            @click="downloadErrors"
                        >
                            Download error report
                        </button>
                    </div>

                    <div class="modal-footer border-0 px-0 pb-0">
                    <button type="button" class="btn btn-secondary" @click="close">Close</button>
                    <button v-if="step > 0 && step < 3" type="button" class="btn btn-outline-primary" @click="step--">Back</button>
                    <button
                        v-if="step === 0"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!selectedFile || uploading"
                        @click="upload"
                    >
                        Upload
                    </button>
                    <button
                        v-if="step === 1"
                        type="button"
                        class="btn btn-primary"
                        :disabled="saving"
                        @click="saveMappingAndValidate"
                    >
                        Validate
                    </button>
                    <button
                        v-if="step === 2 && job?.status === 'validated'"
                        type="button"
                        class="btn btn-primary"
                        @click="startImport"
                    >
                        Start import
                    </button>
                    </div>
        </template>
    </VModal>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';
import { useDataTransferJob } from '@/composables/useDataTransferJob.js';
import { useToast } from 'vue-toastification';

const props = defineProps({
    entityType: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    entityLabel: {
        type: String,
        default: '',
    },
    fields: {
        type: Array,
        required: true,
    },
    uploadOptions: {
        type: Object,
        default: () => ({}),
    },
    templateDownloadFn: {
        type: Function,
        required: true,
    },
    modalId: {
        type: String,
        default: 'entity-import-wizard',
    },
});

const emit = defineEmits(['imported']);

const store = useDataTransferStore();
const toast = useToast();
const open = ref(false);

const step = ref(0);
const maxReachableStep = ref(0);
const stepLabels = ['Upload', 'Mapping', 'Preview', 'Import'];
const selectedFile = ref(null);
const uploading = ref(false);
const saving = ref(false);
const job = ref(null);
const mapping = ref({});
const duplicateMode = ref('update');

const detectedHeaders = computed(() => job.value?.stats?.detected_headers ?? Object.keys(mapping.value));

const previewRows = computed(() => store.previewRows.data);

const { startPolling, stopPolling, isActive, fetchJob } = useDataTransferJob(() => job.value?.uuid);

const progressPercent = computed(() => {
    const total = job.value?.stats?.valid || 1;
    const done = job.value?.stats?.processed || 0;
    return Math.min(100, Math.round((done / total) * 100));
});

const show = () => {
    step.value = 0;
    maxReachableStep.value = 0;
    job.value = null;
    mapping.value = {};
    selectedFile.value = null;
    open.value = true;
};

const close = () => {
    open.value = false;
};

defineExpose({ show });

const onFileSelect = (e) => {
    selectedFile.value = e.target.files?.[0] ?? null;
};

const downloadTemplate = (format) => props.templateDownloadFn(format);

const upload = async () => {
    if (!selectedFile.value) return;
    uploading.value = true;
    try {
        const data = await store.uploadImport(selectedFile.value, props.entityType, props.uploadOptions);
        job.value = data;
        mapping.value = { ...(data.mapping || {}) };
        duplicateMode.value = data.options?.duplicate_mode ?? 'update';
        step.value = 1;
        maxReachableStep.value = 1;
        startPolling();
    } finally {
        uploading.value = false;
    }
};

const saveMappingAndValidate = async () => {
    saving.value = true;
    try {
        await store.saveMapping(job.value.uuid, mapping.value, duplicateMode.value);
        await store.validate(job.value.uuid);
        step.value = 2;
        maxReachableStep.value = 2;
        startPolling();
        const pollValidate = setInterval(async () => {
            const j = await fetchJob();
            job.value = j;
            if (j?.status === 'validated' || j?.status === 'failed') {
                clearInterval(pollValidate);
                stopPolling();
                if (j?.status === 'validated') {
                    await store.getPreviewRows(j.uuid, 'invalid');
                }
            }
        }, 2000);
    } finally {
        saving.value = false;
    }
};

const startImport = async () => {
    await store.commit(job.value.uuid);
    step.value = 3;
    maxReachableStep.value = 3;
    startPolling();
    const pollImport = setInterval(async () => {
        const j = await fetchJob();
        job.value = j;
        if (j && !isActive(j.status)) {
            clearInterval(pollImport);
            stopPolling();
            toast.success('Import finished.');
            emit('imported');
        }
    }, 2000);
};

const downloadErrors = () => {
    if (job.value?.uuid) {
        store.downloadErrors(job.value.uuid);
    }
};
</script>
