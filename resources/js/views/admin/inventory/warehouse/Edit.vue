<template>
    <VModal
        :show-modal="!!edit_warehouse_id"
        @close-click="closeEditModal"
        title="Update Warehouse">
        <template #modal-body>
            <VLoader v-if="warehouse.loading" loader-type="progress"/>
            <form @submit.prevent="updateWarehouse(warehouse.data.id)" class="row g-3">
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
                    <VInput
                        id="code"
                        v-model="form.code"
                        label="Code"
                        @validate="validateField('code')"
                        :error="errors.code"
                    />
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
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-1" type="button">
                        Close
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
import {storeToRefs} from 'pinia';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';
import {buildWarehouseOptionsTree, collectDescendantIds} from './warehouseTree.js';

const warehouseStore = useWarehouseStore();

const edit_warehouse_id = defineModel('warehouse_id');

const {warehouse, warehouses} = storeToRefs(warehouseStore);

const parentOptionsTree = computed(() => {
    const id = edit_warehouse_id.value;
    if (!id) {
        return [];
    }
    const n = Number(id);
    const ex = new Set([n, ...collectDescendantIds(warehouses.value.data, n)]);
    return buildWarehouseOptionsTree(warehouses.value.data, ex);
});

const initialState = {
    parent_id: '',
    name: '',
    code: '',
    phone: '',
    address: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_warehouse_id.value, async (id) => {
    if (id) {
        await warehouseStore.getWarehouses(true);
        await warehouseStore.getWarehouse(id);
        Object.keys(form).forEach((key) => {
            const v = warehouse.value.data[key];
            form[key] = key === 'parent_id' ? (v ?? '') : (v || '');
        });
    }
});

const validations = object({
    parent_id: mixed().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    phone: string().nullable(),
    address: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateWarehouse = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await warehouseStore.updateWarehouse(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeEditModal = () => {
    resetForm();
    edit_warehouse_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
