<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add New Warehouse">
        <template #modal-body>
            <form @submit.prevent="storeWarehouse" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="code">
                        Code
                        <VRequiredMark />
                    </label>
                    <div class="input-group">
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': errors.code }"
                            autocomplete="off"
                            @blur="validateField('code')"
                        />
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="codeLoading"
                            @click="fetchNextCode">
                            Generate
                        </button>
                    </div>
                    <div v-if="errors.code" class="invalid-feedback d-block">
                        {{ errors.code }}
                    </div>
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="parent_id"
                        v-model="form.parent_id"
                        :options="parentOptionsTree"
                        label="Parent warehouse"
                        placeholder="warehouse"
                        @validate="validateField('parent_id')"
                        :error="errors.parent_id"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="phone"
                        v-model="form.phone"
                        label="Phone"
                        @validate="validateField('phone')"
                        :error="errors.phone"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="address"
                        v-model="form.address"
                        label="Address"
                        @validate="validateField('address')"
                        :error="errors.address"
                    />
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeCreateModal" class="btn btn-cancel" type="button">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string, mixed} from 'yup';
import {useYup} from '@/helpers/yup';
import {useNextCode} from '@/helpers/useNextCode.js';
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {buildWarehouseOptionsTree} from '@/helpers/warehouseTree.js';

const warehouseStore = useWarehouseStore();
const {warehouses} = storeToRefs(warehouseStore);

const createModalOpened = defineModel('createModalOpened');

const emit = defineEmits(['saved']);

watch(createModalOpened, async (open) => {
    if (open) {
        warehouseStore.getWarehouses();
        await fetchNextCode();
    }
});

const parentOptionsTree = computed(() => buildWarehouseOptionsTree(warehouses.value.data));

const initialState = {
    parent_id: '',
    name: '',
    code: '',
    phone: '',
    address: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    parent_id: mixed().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    phone: string().nullable(),
    address: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);
const {loading: codeLoading, fetchNextCode} = useNextCode(form, 'code', 'warehouse/next-code', validateField);

const storeWarehouse = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await warehouseStore.storeWarehouse(form);
            toast(res.status, res.data.message);
            emit('saved');
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
