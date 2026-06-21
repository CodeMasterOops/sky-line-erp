<template>
    <div class="bpi" ref="rootEl">
        <label v-if="label" class="form-label">
            {{ label }}
            <VRequiredMark v-if="required" />
        </label>

        <div
            class="bpi__control form-select form-select-sm"
            :class="{ 'is-invalid': error, 'bpi__control--disabled': isDisabled, 'bpi__control--open': isOpen }"
            role="button"
            tabindex="0"
            @click="toggle"
            @keydown.down.prevent="open ? moveDown() : toggle()"
            @keydown.up.prevent="moveUp"
            @keydown.enter.prevent="onEnter"
            @keydown.escape="close"
        >
            <template v-if="selected">
                <span class="bpi__selected-no">{{ selected.batch_no }}</span>
                <span v-if="selected.expiry_date" class="bpi__chip" :class="expiryInfo(selected.expiry_date).cls">
                    <i class="ti ti-clock-hour-4"></i> {{ selected.expiry_date }}
                </span>
                <span class="bpi__chip bpi__chip--qty">Qty: {{ formatQty(selected.remaining_qty) }}</span>
            </template>
            <span v-else class="bpi__placeholder">{{ placeholderText }}</span>
        </div>

        <Transition name="bpi-fade">
            <div v-if="isOpen" class="bpi__dropdown">
                <div class="bpi__header">
                    <i class="ti ti-stack-2"></i>
                    <span>FEFO Batches</span>
                    <span v-if="batches.length" class="bpi__count">{{ batches.length }}</span>
                    <span class="bpi__hint ms-auto">First-Expiry-First-Out</span>
                </div>

                <template v-if="loading">
                    <div v-for="n in 3" :key="n" class="bpi__skeleton-row">
                        <div class="bpi__skeleton-line bpi__skeleton-line--title"></div>
                        <div class="bpi__skeleton-line bpi__skeleton-line--meta"></div>
                    </div>
                </template>

                <ul v-else-if="batches.length" class="bpi__list" ref="listEl">
                    <li
                        class="bpi__item"
                        :class="{ 'bpi__item--active': activeIndex === -1, 'bpi__item--clear': true }"
                        @mouseenter="activeIndex = -1"
                        @mousedown.prevent="selectBatch(null)"
                    >
                        <i class="ti ti-circle-off bpi__clear-icon"></i>
                        <span class="bpi__clear-text">{{ placeholderText }}</span>
                    </li>
                    <li
                        v-for="(batch, i) in batches"
                        :key="batch.id"
                        :ref="el => setItemRef(el, i)"
                        class="bpi__item"
                        :class="{ 'bpi__item--active': activeIndex === i, 'bpi__item--selected': batch.id === modelValue }"
                        @mouseenter="activeIndex = i"
                        @mousedown.prevent="selectBatch(batch.id)"
                    >
                        <div class="bpi__info">
                            <div class="bpi__name">
                                {{ batch.batch_no }}
                                <span v-if="i === 0" class="bpi__fefo-badge">FEFO</span>
                                <span v-if="batch.lot_no && batch.lot_no !== batch.batch_no" class="bpi__lot">Lot {{ batch.lot_no }}</span>
                            </div>
                            <div class="bpi__meta">
                                <span v-if="batch.expiry_date" class="bpi__chip" :class="expiryInfo(batch.expiry_date).cls">
                                    <i class="ti ti-clock-hour-4"></i> {{ expiryInfo(batch.expiry_date).label }}
                                </span>
                                <span v-else class="bpi__chip bpi__chip--muted">No expiry</span>
                            </div>
                        </div>
                        <div class="bpi__qty">
                            <span class="bpi__qty-value">{{ formatQty(batch.remaining_qty) }}</span>
                            <span class="bpi__qty-label">in stock</span>
                        </div>
                        <i v-if="batch.id === modelValue" class="ti ti-check bpi__check"></i>
                    </li>
                </ul>

                <div v-else class="bpi__empty">
                    <i class="ti ti-stack-pop bpi__empty-icon"></i>
                    <p class="bpi__empty-title">No available batches</p>
                    <p class="bpi__empty-sub">Receive stock with a batch to pick here</p>
                </div>
            </div>
        </Transition>

        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-if="expiryWarning" class="form-text text-warning">
            <i class="ti ti-alert-triangle me-1"></i>{{ expiryWarning }}
        </div>
    </div>
</template>

<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const props = defineProps({
    modelValue: { type: Number, default: null },
    productVariantId: { type: [Number, String], default: null },
    warehouseId: { type: [Number, String], default: null },
    label: { type: String, default: 'Batch' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: null },
    autoSelect: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const batches = ref([]);
const loading = ref(false);
const open = ref(false);
const activeIndex = ref(-1);
const rootEl = ref(null);
const listEl = ref(null);
const itemRefs = ref([]);

const isDisabled = computed(() => props.disabled || !props.productVariantId || !props.warehouseId);
const isOpen = computed(() => open.value && !isDisabled.value);
const placeholderText = computed(() => '— Select Batch —');

const selected = computed(() => batches.value.find((b) => b.id === props.modelValue) ?? null);

const expiryWarning = computed(() => {
    if (!selected.value?.expiry_date) return null;
    const diff = daysUntil(selected.value.expiry_date);
    if (diff < 0) return `Batch ${selected.value.batch_no} has already expired.`;
    if (diff <= 30) return `Batch ${selected.value.batch_no} expires in ${diff} day(s).`;
    return null;
});

const setItemRef = (el, i) => {
    if (el) itemRefs.value[i] = el;
};

const formatQty = (qty) => Number(qty ?? 0).toLocaleString();

function daysUntil(date) {
    return Math.ceil((new Date(date) - new Date()) / 86400000);
}

function expiryInfo(date) {
    const diff = daysUntil(date);
    if (diff < 0) return { label: `Expired ${date}`, cls: 'bpi__chip--danger' };
    if (diff <= 30) return { label: `Exp ${date} (${diff}d)`, cls: 'bpi__chip--warn' };
    return { label: `Exp ${date}`, cls: 'bpi__chip--ok' };
}

const toggle = () => {
    if (isDisabled.value) return;
    open.value = !open.value;
    if (open.value) {
        activeIndex.value = batches.value.findIndex((b) => b.id === props.modelValue);
    }
};

const close = () => {
    open.value = false;
    activeIndex.value = -1;
};

const scrollActive = () => {
    nextTick(() => {
        itemRefs.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' });
    });
};

const moveDown = () => {
    if (!batches.value.length) return;
    const max = batches.value.length - 1;
    activeIndex.value = activeIndex.value >= max ? -1 : activeIndex.value + 1;
    scrollActive();
};

const moveUp = () => {
    if (!isOpen.value) return;
    if (!batches.value.length) return;
    const max = batches.value.length - 1;
    activeIndex.value = activeIndex.value <= -1 ? max : activeIndex.value - 1;
    scrollActive();
};

const onEnter = () => {
    if (!isOpen.value) {
        toggle();
        return;
    }
    if (activeIndex.value === -1) {
        selectBatch(null);
        return;
    }
    const batch = batches.value[activeIndex.value];
    if (batch) selectBatch(batch.id);
};

const selectBatch = (id) => {
    emit('update:modelValue', id);
    close();
};

async function loadBatches() {
    if (!props.productVariantId || !props.warehouseId) {
        batches.value = [];
        return;
    }
    loading.value = true;
    try {
        const { data } = await apiAdmin(
            `batch/fefo?product_variant_id=${props.productVariantId}&warehouse_id=${props.warehouseId}`,
        );
        batches.value = data.data ?? [];

        if (props.autoSelect && !props.modelValue && batches.value.length) {
            emit('update:modelValue', batches.value[0].id);
        }
    } catch {
        batches.value = [];
    } finally {
        loading.value = false;
    }
}

const onDocClick = (e) => {
    if (!rootEl.value?.contains(e.target)) close();
};

watch(
    () => [props.productVariantId, props.warehouseId],
    ([newVariant, newWarehouse], [oldVariant, oldWarehouse] = []) => {
        const isInitial = oldVariant === undefined && oldWarehouse === undefined;
        if (newVariant !== oldVariant || newWarehouse !== oldWarehouse) {
            if (!isInitial) {
                emit('update:modelValue', null);
            }
            loadBatches();
        }
    },
    { immediate: true },
);

onMounted(() => document.addEventListener('click', onDocClick));
onUnmounted(() => document.removeEventListener('click', onDocClick));
</script>

<style scoped>
.bpi {
    position: relative;
}

/* ── Control ── */
.bpi__control {
    display: flex;
    align-items: center;
    gap: 6px;
    min-height: 31px;
    cursor: pointer;
    background-image: none;
    padding-right: 28px;
    position: relative;
    white-space: nowrap;
    overflow: hidden;
}

.bpi__control::after {
    content: '';
    position: absolute;
    right: 10px;
    top: 50%;
    width: 7px;
    height: 7px;
    border-right: 1.5px solid #adb5bd;
    border-bottom: 1.5px solid #adb5bd;
    transform: translateY(-65%) rotate(45deg);
    transition: transform 0.15s ease;
}

.bpi__control--open::after {
    transform: translateY(-35%) rotate(225deg);
}

.bpi__control--disabled {
    background: #e9ecef;
    cursor: not-allowed;
    opacity: 0.7;
}

.bpi__selected-no {
    font-weight: 600;
    color: #212529;
    flex-shrink: 0;
}

.bpi__placeholder {
    color: #6c757d;
}

/* ── Chips ── */
.bpi__chip {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 600;
    border-radius: 4px;
    padding: 0 6px;
    line-height: 18px;
    white-space: nowrap;
}

.bpi__chip--ok { background: #e6f4ea; color: #1d7a3f; }
.bpi__chip--warn { background: #fff3cd; color: #9a6700; }
.bpi__chip--danger { background: #fde8e8; color: #c92a2a; }
.bpi__chip--muted { background: #f1f3f5; color: #868e96; }
.bpi__chip--qty { background: #eef2ff; color: #3b5bdb; }

/* ── Dropdown ── */
.bpi__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1060;
    min-width: 280px;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    overflow: hidden;
}

.bpi__header {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #6c757d;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.bpi__count {
    background: #e9ecef;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 700;
    color: #495057;
}

.bpi__hint {
    font-size: 10px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: #adb5bd;
}

/* ── List ── */
.bpi__list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    max-height: 260px;
    overflow-y: auto;
}

.bpi__item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.1s;
}

.bpi__item:hover,
.bpi__item--active {
    background: #f0f4ff;
}

.bpi__item--selected {
    background: #eef2ff;
}

.bpi__item--clear {
    border-bottom: 1px solid #f1f3f5;
}

.bpi__clear-icon { color: #adb5bd; font-size: 15px; }
.bpi__clear-text { font-size: 12.5px; color: #868e96; }

.bpi__info {
    flex: 1;
    min-width: 0;
}

.bpi__name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: #212529;
}

.bpi__fefo-badge {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.4px;
    background: #d3f9d8;
    color: #1d7a3f;
    border-radius: 4px;
    padding: 0 5px;
    line-height: 15px;
}

.bpi__lot {
    font-size: 11px;
    font-weight: 500;
    color: #868e96;
}

.bpi__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 3px;
}

.bpi__qty {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
}

.bpi__qty-value {
    font-size: 13px;
    font-weight: 700;
    color: #3b5bdb;
}

.bpi__qty-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #adb5bd;
}

.bpi__check {
    color: #3b5bdb;
    font-size: 16px;
    flex-shrink: 0;
}

/* ── Skeleton ── */
.bpi__skeleton-row {
    padding: 10px 14px;
}

.bpi__skeleton-line {
    border-radius: 4px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e4e4e4 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: bpi-shimmer 1.3s infinite;
    margin-bottom: 6px;
}

.bpi__skeleton-line--title { width: 50%; height: 11px; }
.bpi__skeleton-line--meta { width: 32%; height: 9px; margin-bottom: 0; }

@keyframes bpi-shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* ── Empty ── */
.bpi__empty {
    padding: 24px 16px;
    text-align: center;
    color: #adb5bd;
}

.bpi__empty-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 6px;
}

.bpi__empty-title {
    font-size: 13px;
    font-weight: 600;
    color: #6c757d;
    margin: 0 0 2px;
}

.bpi__empty-sub {
    font-size: 11px;
    margin: 0;
    color: #adb5bd;
}

/* ── Transition ── */
.bpi-fade-enter-active,
.bpi-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.bpi-fade-enter-from,
.bpi-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
