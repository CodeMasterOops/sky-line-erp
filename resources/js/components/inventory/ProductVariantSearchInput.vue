<template>
    <div class="pvs" ref="rootEl">
        <label v-if="label" class="form-label">
            {{ label }}
            <VRequiredMark v-if="required" />
        </label>

        <div class="pvs__input-wrap">
            <span class="pvs__search-icon">
                <i :class="searching ? 'ti ti-loader-2 pvs__spin' : 'ti ti-search'"></i>
            </span>
            <input
                ref="inputRef"
                v-model="query"
                type="text"
                class="form-control pvs__input"
                :placeholder="placeholder"
                autocomplete="off"
                :disabled="disabled"
                @input="onQueryInput"
                @keydown.down.prevent="moveDown"
                @keydown.up.prevent="moveUp"
                @keydown.enter.prevent="onEnter"
                @keydown.escape="close"
                @paste="onPaste"
                @focus="onFocus"
                @click="onInputClick"
            />
            <span class="pvs__scan-icon">
                <img src="@/assets/images/icons/qrcode-scan.svg" alt="scan" />
            </span>
        </div>

        <Transition name="pvs-fade">
            <div v-if="isOpen" class="pvs__dropdown">
                <div class="pvs__header">
                    <template v-if="isTyping">
                        <i class="ti ti-search"></i>
                        <span>Search Results</span>
                        <span v-if="!searching && searchResults.length" class="pvs__count">{{ searchResults.length }}</span>
                    </template>
                    <template v-else>
                        <i class="ti ti-layout-grid"></i>
                        <span>All Products</span>
                    </template>
                    <span class="pvs__hint ms-auto">↑↓ navigate · Enter select</span>
                </div>

                <template v-if="isLoading">
                    <div v-for="n in 4" :key="n" class="pvs__skeleton-row">
                        <div class="pvs__skeleton-dot"></div>
                        <div class="pvs__skeleton-body">
                            <div class="pvs__skeleton-line pvs__skeleton-line--title"></div>
                            <div class="pvs__skeleton-line pvs__skeleton-line--meta"></div>
                        </div>
                        <div class="pvs__skeleton-line pvs__skeleton-line--price"></div>
                    </div>
                </template>

                <ul v-else-if="displayResults.length" class="pvs__list" ref="listEl">
                    <li
                        v-for="(v, i) in displayResults"
                        :key="v.id"
                        :ref="el => setItemRef(el, i)"
                        :class="['pvs__item', { 'pvs__item--active': activeIndex === i, 'pvs__item--out': showStock && !v.is_service && Number(v.stock) <= 0 }]"
                        @mouseenter="activeIndex = i"
                        @mouseleave="activeIndex = -1"
                        @mousedown.prevent="selectVariant(v)"
                    >
                        <div :class="['pvs__icon', v.is_service ? 'pvs__icon--service' : 'pvs__icon--product']">
                            <i :class="v.is_service ? 'ti ti-tool' : 'ti ti-package'"></i>
                        </div>
                        <div class="pvs__info">
                            <div class="pvs__name">{{ v.name }}</div>
                            <div class="pvs__meta">
                                <span v-if="v.sku" class="pvs__chip">SKU: {{ v.sku }}</span>
                                <span v-if="v.barcode" class="pvs__chip">{{ v.barcode }}</span>
                                <span v-if="v.product_code" class="pvs__chip">{{ v.product_code }}</span>
                                <span v-if="v.is_service" class="pvs__tag-service">Service</span>
                                <span v-if="v.item_role_label" class="pvs__chip pvs__chip--role">{{ v.item_role_label }}</span>
                                <span
                                    v-if="showStock && !v.is_service"
                                    :class="['pvs__stock', stockClass(v.stock)]"
                                >
                                    {{ stockLabel(v.stock) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="v.sales_price" class="pvs__price">
                            {{ formatPrice(v.sales_price) }}
                        </div>
                        <i v-if="activeIndex === i" class="ti ti-corner-down-left pvs__enter-hint"></i>
                    </li>
                </ul>

                <div v-else-if="isTyping" class="pvs__empty">
                    <i class="ti ti-search-off pvs__empty-icon"></i>
                    <p class="pvs__empty-title">No products found</p>
                    <p class="pvs__empty-sub">Try a different name, SKU, or barcode</p>
                </div>

                <div v-else class="pvs__empty">
                    <i class="ti ti-package-off pvs__empty-icon"></i>
                    <p class="pvs__empty-title">No products available</p>
                    <p class="pvs__empty-sub">{{ categoryId ? 'No products in this category yet — type to search all.' : 'Start typing to search by name, SKU, code or barcode.' }}</p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import debounce from 'lodash/debounce';
import { useProductStore } from '@/stores/admin/inventory/product.js';
import VRequiredMark from '@/components/base/VRequiredMark.vue';

const props = defineProps({
    label: {
        type: String,
        default: 'Product',
    },
    placeholder: {
        type: String,
        default: 'Search by name, SKU, code or barcode — Enter to add',
    },
    physicalOnly: {
        type: Boolean,
        default: false,
    },
    required: {
        type: Boolean,
        default: false,
    },
    categoryId: {
        type: Number,
        default: null,
    },
    batchTrackedOnly: {
        type: Boolean,
        default: false,
    },
    showStock: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    itemRoles: {
        type: Array,
        default: () => [],
    },
    saleableOnly: {
        type: Boolean,
        default: false,
    },
    purchasableOnly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['select']);
const productStore = useProductStore();

const itemRolesParam = computed(() =>
    Array.isArray(props.itemRoles) && props.itemRoles.length ? props.itemRoles.join(',') : null,
);

const query = ref('');
const searchResults = ref([]);
const defaultResults = ref([]);
const searching = ref(false);
const loadingDefaults = ref(false);
const open = ref(false);
const activeIndex = ref(-1);
const inputRef = ref(null);
const rootEl = ref(null);
const itemRefs = ref([]);
const _suppressNextOpen = ref(false);

const isTyping = computed(() => query.value.trim().length >= 2);
const displayResults = computed(() => (isTyping.value ? searchResults.value : defaultResults.value));
const isLoading = computed(() => (isTyping.value ? searching.value : loadingDefaults.value));
const isOpen = computed(() => open.value);

const setItemRef = (el, i) => {
    if (el) itemRefs.value[i] = el;
};

const formatPrice = (price) => Number(price).toLocaleString();

const stockLabel = (stock) => {
    const qty = Number(stock ?? 0);
    if (qty <= 0) {
        return 'Out of stock';
    }
    if (qty <= 5) {
        return `Low: ${qty}`;
    }
    return `${qty} in stock`;
};

const stockClass = (stock) => {
    const qty = Number(stock ?? 0);
    if (qty <= 0) {
        return 'pvs__stock--out';
    }
    if (qty <= 5) {
        return 'pvs__stock--low';
    }
    return 'pvs__stock--ok';
};

watch(displayResults, () => {
    itemRefs.value = [];
    activeIndex.value = -1;
});

const loadDefaults = async () => {
    loadingDefaults.value = true;
    try {
        const params = {
            limit: 20,
            physical_only: props.physicalOnly ? 1 : 0,
            batch_tracked_only: props.batchTrackedOnly ? 1 : 0,
            with_stock: props.showStock ? 1 : 0,
            saleable_only: props.saleableOnly ? 1 : 0,
            purchasable_only: props.purchasableOnly ? 1 : 0,
        };
        if (props.categoryId) {
            params.category_id = props.categoryId;
        }
        if (itemRolesParam.value) {
            params.item_roles = itemRolesParam.value;
        }
        const res = await productStore.searchProductVariants(params);
        defaultResults.value = res.data ?? [];
    } catch {
        defaultResults.value = [];
    } finally {
        loadingDefaults.value = false;
    }
};

const runSearch = async (q, { barcode = false } = {}) => {
    const trimmed = q.trim();
    if (!barcode && trimmed.length < 2) {
        searchResults.value = [];
        return;
    }
    searching.value = true;
    try {
        const res = await productStore.searchProductVariants(
            barcode
                ? { barcode: trimmed, limit: 20, physical_only: props.physicalOnly ? 1 : 0, batch_tracked_only: props.batchTrackedOnly ? 1 : 0, with_stock: props.showStock ? 1 : 0, saleable_only: props.saleableOnly ? 1 : 0, purchasable_only: props.purchasableOnly ? 1 : 0, item_roles: itemRolesParam.value ?? undefined }
                : { q: trimmed, limit: 20, physical_only: props.physicalOnly ? 1 : 0, batch_tracked_only: props.batchTrackedOnly ? 1 : 0, category_id: props.categoryId, with_stock: props.showStock ? 1 : 0, saleable_only: props.saleableOnly ? 1 : 0, purchasable_only: props.purchasableOnly ? 1 : 0, item_roles: itemRolesParam.value ?? undefined },
        );
        searchResults.value = res.data ?? [];
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
};

const debouncedSearch = debounce((q) => runSearch(q), 300);

const onFocus = () => {
    if (_suppressNextOpen.value) {
        _suppressNextOpen.value = false;
        return;
    }
    open.value = true;
};

const onInputClick = () => {
    open.value = true;
};

const onQueryInput = () => {
    open.value = true;
    activeIndex.value = -1;
    debouncedSearch(query.value);
};

const onPaste = () => {
    _suppressNextOpen.value = false;
    open.value = true;
    nextTick(async () => {
        debouncedSearch.cancel();
        const trimmed = query.value.trim();
        if (!trimmed.length) return;
        await runSearch(trimmed, { barcode: true });
        if (!searchResults.value.length) {
            await runSearch(trimmed);
        }
    });
};

const scrollActive = () => {
    nextTick(() => {
        itemRefs.value[activeIndex.value]?.scrollIntoView({ block: 'nearest' });
    });
};

const moveDown = () => {
    if (!displayResults.value.length) return;
    const max = displayResults.value.length - 1;
    activeIndex.value = activeIndex.value >= max ? 0 : activeIndex.value + 1;
    scrollActive();
};

const moveUp = () => {
    if (!displayResults.value.length) return;
    const max = displayResults.value.length - 1;
    activeIndex.value = activeIndex.value <= 0 ? max : activeIndex.value - 1;
    scrollActive();
};

const close = () => {
    open.value = false;
    activeIndex.value = -1;
};

const selectVariant = (variant) => {
    emit('select', variant);
    query.value = '';
    searchResults.value = [];
    open.value = false;
    activeIndex.value = -1;
    debouncedSearch.cancel();
};

const onEnter = async () => {
    if (activeIndex.value >= 0 && displayResults.value[activeIndex.value]) {
        selectVariant(displayResults.value[activeIndex.value]);
        return;
    }
    debouncedSearch.cancel();
    const trimmed = query.value.trim();
    if (!trimmed.length) return;
    await runSearch(trimmed);
    if (searchResults.value.length === 1) {
        selectVariant(searchResults.value[0]);
        return;
    }
    await runSearch(trimmed, { barcode: true });
    if (searchResults.value.length === 1) {
        selectVariant(searchResults.value[0]);
    }
};

const onDocClick = (e) => {
    if (!rootEl.value?.contains(e.target)) close();
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
    loadDefaults();
});

onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    debouncedSearch.cancel();
});

defineExpose({
    focus: () => {
        _suppressNextOpen.value = true;
        inputRef.value?.focus();
    },
    openAndFocus: () => {
        inputRef.value?.focus();
    },
});
</script>

<style scoped>
.pvs {
    position: relative;
}

/* ── Input ── */
.pvs__input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

.pvs__search-icon {
    position: absolute;
    left: 10px;
    z-index: 1;
    display: flex;
    align-items: center;
    color: #6c757d;
    pointer-events: none;
    font-size: 15px;
}

.pvs__spin {
    animation: pvs-spin 0.75s linear infinite;
}

@keyframes pvs-spin {
    to { transform: rotate(360deg); }
}

.pvs__input {
    padding-left: 34px !important;
    padding-right: 44px !important;
}

.pvs__scan-icon {
    position: absolute;
    right: 11px;
    display: flex;
    align-items: center;
    pointer-events: none;
    opacity: 0.45;
}

.pvs__scan-icon img {
    width: 20px;
    height: 20px;
}

/* ── Dropdown ── */
.pvs__dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1060;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    overflow: hidden;
}

/* ── Header ── */
.pvs__header {
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

.pvs__count {
    background: #e9ecef;
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 10px;
    font-weight: 700;
    color: #495057;
}

.pvs__hint {
    font-size: 10px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: 0;
    color: #adb5bd;
}

/* ── Skeleton ── */
.pvs__skeleton-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
}

.pvs__skeleton-dot {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    flex-shrink: 0;
    background: linear-gradient(90deg, #f0f0f0 25%, #e4e4e4 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: pvs-shimmer 1.3s infinite;
}

.pvs__skeleton-body {
    flex: 1;
}

.pvs__skeleton-line {
    border-radius: 4px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e4e4e4 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: pvs-shimmer 1.3s infinite;
    margin-bottom: 6px;
}

.pvs__skeleton-line--title { width: 52%; height: 11px; }
.pvs__skeleton-line--meta  { width: 34%; height: 9px; margin-bottom: 0; }
.pvs__skeleton-line--price { width: 48px; height: 11px; flex-shrink: 0; margin-bottom: 0; }

@keyframes pvs-shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}

/* ── List ── */
.pvs__list {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    max-height: 288px;
    overflow-y: auto;
}

.pvs__item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    transition: background 0.1s;
}

.pvs__item:hover,
.pvs__item--active {
    background: #f0f4ff;
}

/* ── Icon avatar ── */
.pvs__icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.pvs__icon--product {
    background: #e8efff;
    color: #3b6fd4;
}

.pvs__icon--service {
    background: #e8f8ee;
    color: #22863a;
}

/* ── Item content ── */
.pvs__info {
    flex: 1;
    min-width: 0;
}

.pvs__name {
    font-size: 13.5px;
    font-weight: 600;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pvs__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 2px;
}

.pvs__chip {
    font-size: 11px;
    color: #868e96;
    background: #f1f3f5;
    border-radius: 4px;
    padding: 0 5px;
    line-height: 18px;
}

.pvs__tag-service {
    font-size: 10px;
    font-weight: 600;
    background: #d3f9d8;
    color: #1d7a3f;
    border-radius: 4px;
    padding: 0 6px;
    line-height: 18px;
}

.pvs__chip--role {
    font-weight: 600;
    background: #e7f5ff;
    color: #1971c2;
}

/* ── Stock badge ── */
.pvs__stock {
    font-size: 10px;
    font-weight: 600;
    border-radius: 4px;
    padding: 0 6px;
    line-height: 18px;
}

.pvs__stock--ok {
    background: #d3f9d8;
    color: #1d7a3f;
}

.pvs__stock--low {
    background: #fff3bf;
    color: #b08900;
}

.pvs__stock--out {
    background: #ffe3e3;
    color: #c92a2a;
}

.pvs__item--out {
    opacity: 0.7;
}

.pvs__item--out .pvs__name {
    color: #868e96;
}

.pvs__price {
    font-size: 13px;
    font-weight: 700;
    color: #3b6fd4;
    white-space: nowrap;
    flex-shrink: 0;
}

.pvs__enter-hint {
    font-size: 13px;
    color: #adb5bd;
    flex-shrink: 0;
}

/* ── Empty state ── */
.pvs__empty {
    padding: 30px 16px;
    text-align: center;
    color: #adb5bd;
}

.pvs__empty-icon {
    font-size: 38px;
    display: block;
    margin-bottom: 8px;
}

.pvs__empty-title {
    font-size: 14px;
    font-weight: 600;
    color: #6c757d;
    margin: 0 0 4px;
}

.pvs__empty-sub {
    font-size: 12px;
    margin: 0;
    color: #adb5bd;
}

/* ── Transition ── */
.pvs-fade-enter-active,
.pvs-fade-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.pvs-fade-enter-from,
.pvs-fade-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
