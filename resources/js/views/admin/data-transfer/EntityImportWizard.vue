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
                        <div v-if="job && !fileChanged" class="alert alert-info py-2 small d-flex justify-content-between align-items-center">
                            <span>Using uploaded file: <strong>{{ uploadedFileName }}</strong></span>
                            <span class="text-muted">Choose a file below to replace it.</span>
                        </div>
                        <input type="file" class="form-control" accept=".csv,.xlsx,.txt" @change="onFileSelect" />
                    </div>

                    <div v-else-if="step === 1 && job">
                        <div v-if="parsing" class="py-4">
                            <div class="placeholder-glow">
                                <span class="placeholder col-6 mb-2"></span>
                                <span class="placeholder col-12 mb-2"></span>
                                <span class="placeholder col-12 mb-2"></span>
                                <span class="placeholder col-9"></span>
                            </div>
                            <p class="mt-2 text-muted small">Reading your file and detecting columns…</p>
                        </div>
                        <template v-else>
                            <p class="text-muted small">
                                Map file columns to {{ entityLabel }} fields.
                                Matching columns are auto-selected — review and adjust as needed.
                            </p>
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
                                            <td>
                                                {{ header }}
                                                <span v-if="mapping[header]" class="badge bg-success-subtle text-success ms-1">matched</span>
                                            </td>
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
                        </template>
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
                        <div v-else-if="optionsLoading" class="py-4">
                            <div class="placeholder-glow">
                                <span class="placeholder col-5 mb-2"></span>
                                <span class="placeholder col-12 mb-2"></span>
                                <span class="placeholder col-12"></span>
                            </div>
                            <p class="mt-2 text-muted small">Loading available values…</p>
                        </div>
                        <template v-else>
                            <p class="text-muted small">
                                For each value we couldn't recognise, search and pick the correct one,
                                create it, or skip those rows. Your choices are remembered for future imports.
                            </p>
                            <div style="max-height: 360px; overflow-y: auto">
                                <div
                                    v-for="item in unresolvedItems"
                                    :key="keyOf(item)"
                                    class="border rounded p-3 mb-2"
                                >
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div>
                                            <span class="badge bg-secondary text-uppercase me-2">{{ item.field }}</span>
                                            <strong>“{{ item.value }}”</strong>
                                            <span class="text-muted small ms-1">in {{ item.count }} row(s)</span>
                                        </div>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button
                                                type="button"
                                                class="btn"
                                                :class="resolutionState[keyOf(item)]?.action === 'map' ? 'btn-primary' : 'btn-outline-primary'"
                                                @click="setAction(item, 'map')"
                                            >
                                                Use existing
                                            </button>
                                            <button
                                                v-if="canCreate(item.field)"
                                                type="button"
                                                class="btn"
                                                :class="resolutionState[keyOf(item)]?.action === 'create' ? 'btn-success' : 'btn-outline-success'"
                                                @click="setAction(item, 'create')"
                                            >
                                                Create new
                                            </button>
                                            <button
                                                type="button"
                                                class="btn"
                                                :class="resolutionState[keyOf(item)]?.action === 'skip' ? 'btn-secondary' : 'btn-outline-secondary'"
                                                @click="setAction(item, 'skip')"
                                            >
                                                Skip rows
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="resolutionState[keyOf(item)]?.action === 'map' && item.field === 'product_type'">
                                        <select
                                            v-model="resolutionState[keyOf(item)].targetValue"
                                            class="form-select form-select-sm"
                                        >
                                            <option value="product">Product</option>
                                            <option value="service">Service</option>
                                        </select>
                                    </div>
                                    <div v-else-if="resolutionState[keyOf(item)]?.action === 'map'">
                                        <VMultiselect
                                            v-model="resolutionState[keyOf(item)].targetId"
                                            :options="optionLists[item.field] || []"
                                            value-prop="id"
                                            name-prop="label"
                                            :placeholder="`Search ${item.field}…`"
                                        />
                                    </div>
                                    <p
                                        v-else-if="resolutionState[keyOf(item)]?.action === 'create'"
                                        class="text-success small mb-0"
                                    >
                                        A new {{ item.field }} “{{ item.value }}” will be created.
                                    </p>
                                    <p v-else class="text-muted small mb-0">
                                        Rows containing “{{ item.value }}” will be skipped.
                                    </p>
                                </div>
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
                        <div
                            v-else
                            class="alert"
                            :class="job.status === 'completed' ? 'alert-success' : (job.status === 'failed' ? 'alert-danger' : 'alert-warning')"
                        >
                            Import {{ job.status }}.
                            <span v-if="job.status !== 'failed' && job.stats"> Created: {{ job.stats.created }}, Updated: {{ job.stats.updated }},
                                Failed: {{ job.stats.failed }}, Skipped: {{ job.stats.skipped }}.</span>
                            <div v-if="job.status === 'failed' && job.error_summary" class="small mt-1">
                                {{ job.error_summary }}
                            </div>
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
                        v-if="step === 0 && job && !fileChanged"
                        type="button"
                        class="btn btn-primary"
                        @click="step = 1"
                    >
                        Continue
                    </button>
                    <button
                        v-else-if="step === 0"
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
                        :disabled="saving || parsing || !detectedHeaders.length"
                        @click="saveMappingAndValidate"
                    >
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" />
                        {{ saving ? 'Validating…' : 'Validate' }}
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
                        :disabled="!unresolvedItems.length || !allResolved"
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
import VMultiselect from '@/components/base/VMultiselect.vue';

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
const uploadedFileName = ref('');
const fileChanged = ref(false);
const uploading = ref(false);
const parsing = ref(false);
const saving = ref(false);
const resolving = ref(false);
const job = ref(null);
const mapping = ref({});
const duplicateMode = ref('update');
const resolutionState = ref({});
const optionLists = ref({ category: [], unit: [], brand: [], tax: [] });
const optionsLoading = ref(false);

const CREATABLE_FIELDS = ['category', 'brand', 'unit'];

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

const canCreate = (field) => CREATABLE_FIELDS.includes(field);

const setAction = (item, action) => {
    const state = resolutionState.value[keyOf(item)];
    if (state) {
        state.action = action;
    }
};

const allResolved = computed(() =>
    unresolvedItems.value.every((item) => {
        const state = resolutionState.value[keyOf(item)];
        if (!state) {
            return false;
        }
        if (state.action !== 'map') {
            return true;
        }
        return item.field === 'product_type' ? !!state.targetValue : !!state.targetId;
    }),
);

const show = () => {
    step.value = 0;
    maxReachableStep.value = 0;
    job.value = null;
    mapping.value = {};
    selectedFile.value = null;
    uploadedFileName.value = '';
    fileChanged.value = false;
    resolutionState.value = {};
    optionsLoading.value = false;
    resolving.value = false;
    parsing.value = false;
    open.value = true;
};

const close = () => {
    open.value = false;
};

defineExpose({ show });

const onFileSelect = (e) => {
    selectedFile.value = e.target.files?.[0] ?? null;
    fileChanged.value = !!selectedFile.value;
};

const downloadTemplate = (format) => props.templateDownloadFn(format);

const normalizeKey = (value) => String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');

const applySuggestedMapping = (j) => {
    const headers = j?.stats?.detected_headers ?? [];
    const suggested = j?.mapping ?? {};
    const fieldByNorm = {};
    props.fields.forEach((f) => {
        fieldByNorm[normalizeKey(f)] = f;
    });

    const next = {};
    headers.forEach((header) => {
        if (suggested[header]) {
            next[header] = suggested[header];
            return;
        }
        next[header] = fieldByNorm[normalizeKey(header)] ?? '';
    });
    mapping.value = next;
};

const pollUntilParsed = () =>
    new Promise((resolve) => {
        parsing.value = true;
        startPolling();
        const poll = setInterval(async () => {
            const j = await fetchJob();
            if (!j) {
                return;
            }
            job.value = j;
            if (['parsed', 'mapped', 'validated', 'failed'].includes(j.status)) {
                clearInterval(poll);
                stopPolling();
                parsing.value = false;
                if (j.status !== 'failed') {
                    applySuggestedMapping(j);
                }
                resolve(j);
            }
        }, 1500);
    });

const upload = async () => {
    if (!selectedFile.value) return;
    uploading.value = true;
    try {
        const data = await store.uploadImport(selectedFile.value, props.entityType, props.uploadOptions);
        job.value = data;
        uploadedFileName.value = selectedFile.value.name;
        fileChanged.value = false;
        mapping.value = { ...(data.mapping || {}) };
        duplicateMode.value = data.options?.duplicate_mode ?? 'update';
        step.value = 1;
        maxReachableStep.value = 1;
        await pollUntilParsed();
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

const initResolutionState = () => {
    const state = {};
    unresolvedItems.value.forEach((item) => {
        const best = item.suggestions?.[0] ?? null;
        if (item.field === 'product_type') {
            state[keyOf(item)] = {
                action: 'map',
                targetId: null,
                targetValue: best?.label ?? 'product',
            };
            return;
        }
        state[keyOf(item)] = {
            action: 'map',
            targetId: best?.id ?? null,
            targetValue: null,
        };
    });
    resolutionState.value = state;
};

const openResolveStep = async () => {
    step.value = 3;
    maxReachableStep.value = Math.max(maxReachableStep.value, 3);
    initResolutionState();
    optionsLoading.value = true;
    try {
        const lists = await store.getLookupOptions(job.value.uuid);
        if (lists) {
            optionLists.value = { category: [], unit: [], brand: [], tax: [], ...lists };
        }
    } finally {
        optionsLoading.value = false;
    }
};

const applyResolutions = async () => {
    const resolutions = unresolvedItems.value.map((item) => {
        const state = resolutionState.value[keyOf(item)] ?? {};
        return {
            field: item.field,
            source_value: item.value,
            action: state.action,
            target_id: state.action === 'map' ? (state.targetId ?? null) : null,
            target_value: state.action === 'map' ? (state.targetValue ?? null) : null,
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
            if (j.status === 'failed') {
                toast.error(j.error_summary || 'Import failed.');
            } else if (j.status === 'completed_with_errors') {
                toast.warning('Import finished with some errors.');
                emit('imported');
            } else {
                toast.success('Import finished.');
                emit('imported');
            }
        }
    }, 2000);
};

const downloadErrors = () => {
    if (job.value?.uuid) {
        store.downloadErrors(job.value.uuid);
    }
};
</script>
