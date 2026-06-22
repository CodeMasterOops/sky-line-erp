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
                                <div class="col-3">
                                    <div class="border rounded p-2 text-center">
                                        <div class="fw-bold">{{ job.stats?.total_rows ?? 0 }}</div>
                                        <div class="small text-muted">Rows</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 text-center text-success">
                                        <div class="fw-bold">{{ job.stats?.valid ?? 0 }}</div>
                                        <div class="small text-muted">Valid</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 text-center text-danger">
                                        <div class="fw-bold">{{ job.stats?.invalid ?? 0 }}</div>
                                        <div class="small text-muted">Invalid</div>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="border rounded p-2 text-center text-secondary">
                                        <div class="fw-bold">{{ job.stats?.skipped ?? 0 }}</div>
                                        <div class="small text-muted">Skipped</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="unresolvedCount" class="alert alert-warning py-2 small">
                                {{ unresolvedCount }} value(s) couldn't be matched (category, unit, brand, tax or
                                product type). Use <strong>Resolve values</strong> to map them once and re-validate.
                            </div>

                            <div v-if="previewRows.length" class="table-responsive" style="max-height: 240px">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Issues</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in previewRows" :key="row.row_number">
                                            <td>{{ row.row_number }}</td>
                                            <td class="small">
                                                <div
                                                    v-for="(fe, i) in (row.field_errors || [])"
                                                    :key="`fe-${i}`"
                                                    class="text-danger"
                                                >
                                                    <span class="badge bg-danger-subtle text-danger me-1">{{ fe.field }}</span>
                                                    “{{ fe.value }}” — {{ fe.message }}
                                                </div>
                                                <div
                                                    v-if="!(row.field_errors || []).length"
                                                    class="text-danger"
                                                >
                                                    {{ (row.errors || []).join('; ') }}
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>

                    <div v-else-if="step === 3 && job">
                        <div v-if="resolving" class="py-4">
                            <div class="placeholder-glow">
                                <span class="placeholder col-7 mb-2"></span>
                                <span class="placeholder col-12 mb-2"></span>
                                <span class="placeholder col-10"></span>
                            </div>
                            <p class="mt-2 text-muted small">Re-validating…</p>
                        </div>
                        <template v-else>
                            <p class="text-muted small">
                                Tell us what each unrecognized value should map to. Your choices are remembered for
                                future imports.
                            </p>
                            <div class="table-responsive" style="max-height: 320px">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>Field</th>
                                            <th>Value in file</th>
                                            <th>Resolve to</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="item in unresolvedItems" :key="keyOf(item)">
                                            <td><span class="badge bg-secondary">{{ item.field }}</span></td>
                                            <td>“{{ item.value }}” <span class="text-muted">×{{ item.count }}</span></td>
                                            <td>
                                                <select
                                                    v-model="resolutionChoice[keyOf(item)]"
                                                    class="form-select form-select-sm"
                                                >
                                                    <option
                                                        v-for="(opt, i) in optionsFor(item)"
                                                        :key="i"
                                                        :value="opt"
                                                    >
                                                        {{ opt.label }}
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>

                    <div v-else-if="step === 4 && job">
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
                    <button v-if="step > 0 && step < 4 && step !== 3" type="button" class="btn btn-outline-primary" @click="step--">Back</button>
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
                        v-if="step === 2 && job?.status === 'validated' && unresolvedCount"
                        type="button"
                        class="btn btn-warning"
                        @click="openResolveStep"
                    >
                        Resolve values
                    </button>
                    <button
                        v-if="step === 2 && job?.status === 'validated' && (job?.stats?.valid ?? 0) > 0"
                        type="button"
                        class="btn btn-primary"
                        @click="startImport"
                    >
                        Start import
                    </button>
                    <button
                        v-if="step === 3 && !resolving"
                        type="button"
                        class="btn btn-outline-primary"
                        @click="step = 2"
                    >
                        Back
                    </button>
                    <button
                        v-if="step === 3 && !resolving"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!unresolvedItems.length"
                        @click="applyResolutions"
                    >
                        Apply &amp; re-validate
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
const stepLabels = ['Upload', 'Mapping', 'Preview', 'Resolve', 'Import'];
const selectedFile = ref(null);
const uploading = ref(false);
const saving = ref(false);
const resolving = ref(false);
const job = ref(null);
const mapping = ref({});
const duplicateMode = ref('update');
const resolutionChoice = ref({});

const detectedHeaders = computed(() => job.value?.stats?.detected_headers ?? Object.keys(mapping.value));

const previewRows = computed(() => store.previewRows.data);

const unresolvedItems = computed(() => job.value?.stats?.unresolved ?? []);
const unresolvedCount = computed(() => unresolvedItems.value.length);

const { startPolling, stopPolling, isActive, fetchJob } = useDataTransferJob(() => job.value?.uuid);

const progressPercent = computed(() => {
    const total = job.value?.stats?.valid || 1;
    const done = job.value?.stats?.processed || 0;
    return Math.min(100, Math.round((done / total) * 100));
});

const keyOf = (item) => `${item.field}|${item.value}`;

const optionsFor = (item) => {
    const opts = item.suggestions.map((s) =>
        item.field === 'product_type'
            ? { label: `Use "${s.label}"`, action: 'map', target_value: s.label }
            : { label: `Map to "${s.label}"`, action: 'map', target_id: s.id },
    );
    if (['category', 'brand', 'unit'].includes(item.field)) {
        opts.push({ label: `Create new "${item.value}"`, action: 'create' });
    }
    opts.push({ label: 'Skip these rows', action: 'skip' });
    return opts;
};

const show = () => {
    step.value = 0;
    maxReachableStep.value = 0;
    job.value = null;
    mapping.value = {};
    selectedFile.value = null;
    resolutionChoice.value = {};
    resolving.value = false;
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

const pollUntilValidated = () =>
    new Promise((resolve) => {
        startPolling();
        const poll = setInterval(async () => {
            const j = await fetchJob();
            job.value = j;
            if (j?.status === 'validated' || j?.status === 'failed') {
                clearInterval(poll);
                stopPolling();
                if (j?.status === 'validated') {
                    await store.getPreviewRows(j.uuid, 'invalid');
                }
                resolve(j);
            }
        }, 2000);
    });

const saveMappingAndValidate = async () => {
    saving.value = true;
    try {
        await store.saveMapping(job.value.uuid, mapping.value, duplicateMode.value);
        await store.validate(job.value.uuid);
        step.value = 2;
        maxReachableStep.value = 2;
        await pollUntilValidated();
    } finally {
        saving.value = false;
    }
};

const openResolveStep = () => {
    resolutionChoice.value = {};
    unresolvedItems.value.forEach((item) => {
        resolutionChoice.value[keyOf(item)] = optionsFor(item)[0];
    });
    step.value = 3;
    maxReachableStep.value = Math.max(maxReachableStep.value, 3);
};

const applyResolutions = async () => {
    const resolutions = unresolvedItems.value.map((item) => {
        const choice = resolutionChoice.value[keyOf(item)] ?? {};
        return {
            field: item.field,
            source_value: item.value,
            action: choice.action,
            target_id: choice.target_id ?? null,
            target_value: choice.target_value ?? null,
        };
    });

    resolving.value = true;
    try {
        await store.submitResolutions(job.value.uuid, resolutions);
        step.value = 2;
        await pollUntilValidated();
    } finally {
        resolving.value = false;
    }
};

const startImport = async () => {
    await store.commit(job.value.uuid);
    step.value = 4;
    maxReachableStep.value = 4;
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
