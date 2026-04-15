<template>
    <div class="product-variant-search" ref="rootEl">
        <label v-if="label">{{ label }}</label>
        <div class="input-groupicon select-code">
            <input ref="inputRef" v-model="query" type="text" class="form-control" :placeholder="placeholder"
                autocomplete="off" @input="onQueryInput" @keydown.enter.prevent="onEnter" @paste="onPaste"
                @focus="open = true" />
            <div class="addonset">
                <img src="@/assets/images/icons/qrcode-scan.svg" alt="img" />
            </div>
        </div>
        <ul v-show="open && (searchResults.length || emptyMessage)"
            class="list-unstyled border rounded bg-white shadow-sm position-absolute w-100 mt-1 p-0 product-variant-search__dropdown"
            style="z-index: 1060; max-height: 240px; overflow-y: auto;">
            <li v-for="v in searchResults" :key="v.id" class="px-3 py-2 cursor-pointer product-variant-search__item"
                @mousedown.prevent="selectVariant(v)">
                <div class="fw-medium">{{ v.name }}</div>
                <div class="small text-muted">
                    <span v-if="v.sku">SKU: {{ v.sku }}</span>
                    <span v-if="v.product_code" class="ms-2">Code: {{ v.product_code }}</span>
                </div>
            </li>
            <li v-if="!searching && emptyMessage" class="px-3 py-2 small text-muted">
                {{ emptyMessage }}
            </li>
            <li v-if="searching" class="px-3 py-2 small text-muted">Searching…</li>
        </ul>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import debounce from 'lodash/debounce';
import { useProductStore } from '@/stores/admin/inventory/product.js';

const props = defineProps({
    label: {
        type: String,
        default: 'Product',
    },
    placeholder: {
        type: String,
        default: 'Search by name, code, or SKU — Enter to add',
    },
});

const emit = defineEmits(['select']);

const productStore = useProductStore();

const query = ref('');
const searchResults = ref([]);
const searching = ref(false);
const open = ref(false);
const inputRef = ref(null);
const rootEl = ref(null);

const emptyMessage = computed(() => {
    if (searching.value) {
        return '';
    }
    const q = query.value.trim();
    if (q.length >= 2 && !searchResults.value.length) {
        return 'No products found';
    }
    if (q.length > 0 && q.length < 2) {
        return 'Type at least 2 characters';
    }
    return '';
});

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
                ? { barcode: trimmed, limit: 20 }
                : { q: trimmed, limit: 20 }
        );
        searchResults.value = res.data ?? [];
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
};

const debouncedSearch = debounce((q) => {
    runSearch(q);
}, 300);

const onQueryInput = () => {
    open.value = true;
    debouncedSearch(query.value);
};

const onPaste = () => {
    nextTick(async () => {
        debouncedSearch.cancel();
        const trimmed = query.value.trim();
        if (!trimmed.length) {
            return;
        }
        await runSearch(trimmed, { barcode: true });
        if (!searchResults.value.length) {
            await runSearch(trimmed);
        }
    });
};

const selectVariant = (variant) => {
    emit('select', variant);
    query.value = '';
    searchResults.value = [];
    open.value = false;
    debouncedSearch.cancel();
};

const onEnter = async () => {
    debouncedSearch.cancel();
    const trimmed = query.value.trim();
    if (!trimmed.length) {
        return;
    }
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
    if (!rootEl.value?.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocClick);
    debouncedSearch.cancel();
});

defineExpose({
    focus: () => inputRef.value?.focus(),
});
</script>

<style scoped>
.product-variant-search {
    position: relative;
}

.product-variant-search__item:hover {
    background: var(--bs-gray-100, #f8f9fa);
}

.cursor-pointer {
    cursor: pointer;
}
</style>
