<template>
    <div class="bli">
        <div class="bli__modes">
            <button
                type="button"
                class="bli__mode"
                :class="{ 'bli__mode--active': !line.create_batch }"
                @click="setMode(false)"
            >
                <i class="ti ti-stack-2"></i> Existing
            </button>
            <button
                type="button"
                class="bli__mode"
                :class="{ 'bli__mode--active': line.create_batch }"
                @click="setMode(true)"
            >
                <i class="ti ti-plus"></i> New
            </button>
        </div>

        <div v-if="line.create_batch" class="bli__new">
            <div class="bli__field">
                <i class="ti ti-hash bli__field-icon"></i>
                <input
                    type="text"
                    class="form-control form-control-sm bli__no"
                    :class="{ 'is-invalid': !line.batch_no }"
                    v-model="line.batch_no"
                    placeholder="Batch no *"
                />
            </div>

            <div class="bli__dates">
                <VDatepicker
                    v-if="showMfg"
                    v-model="line.mfg_date"
                    input-class="form-control form-control-sm"
                    placeholder="Mfg date"
                    :show-switcher="false"
                    :disable-after="line.expiry_date || undefined"
                />
                <VDatepicker
                    v-model="line.expiry_date"
                    input-class="form-control form-control-sm"
                    placeholder="Expiry date"
                    :show-switcher="false"
                    :disable-before="line.mfg_date || undefined"
                />
            </div>

            <p v-if="dateWarning" class="bli__warn">
                <i class="ti ti-alert-triangle"></i> {{ dateWarning }}
            </p>
            <p class="bli__hint">
                <i class="ti ti-info-circle"></i> Qty &amp; cost taken from this line.
            </p>
        </div>

        <BatchPickerInput
            v-else
            v-model="line.batch_id"
            label=""
            :product-variant-id="productVariantId"
            :warehouse-id="warehouseId"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import BatchPickerInput from '@/components/inventory/BatchPickerInput.vue';

const props = defineProps({
    line: { type: Object, required: true },
    productVariantId: { type: [Number, String], default: null },
    warehouseId: { type: [Number, String], default: null },
    showMfg: { type: Boolean, default: true },
});

const setMode = (createBatch) => {
    if (props.line.create_batch === createBatch) {
        return;
    }
    props.line.create_batch = createBatch;
    if (createBatch) {
        props.line.batch_id = null;
    } else {
        props.line.batch_no = '';
        props.line.mfg_date = null;
        props.line.expiry_date = null;
    }
};

const dateWarning = computed(() => {
    const { mfg_date: mfg, expiry_date: expiry } = props.line;
    if (mfg && expiry && expiry <= mfg) {
        return 'Expiry is on or before the manufacture date.';
    }
    return null;
});
</script>

<style scoped>
.bli {
    min-width: 11rem;
}

.bli__modes {
    display: inline-flex;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 6px;
}

.bli__mode {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    border: 0;
    background: #fff;
    color: #6c757d;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 9px;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}

.bli__mode + .bli__mode {
    border-left: 1px solid #dee2e6;
}

.bli__mode:hover {
    background: #f1f3f5;
}

.bli__mode--active {
    background: #3b5bdb;
    color: #fff;
}

.bli__new {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.bli__field {
    position: relative;
}

.bli__field-icon {
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    color: #adb5bd;
    pointer-events: none;
}

.bli__no {
    padding-left: 24px;
}

.bli__dates {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.bli__warn {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    font-size: 11px;
    font-weight: 500;
    color: #9a6700;
}

.bli__hint {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
    font-size: 10.5px;
    color: #adb5bd;
}
</style>
