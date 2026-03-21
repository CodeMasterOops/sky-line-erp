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
                    <VInput
                        id="code"
                        v-model="form.code"
                        label="Code"
                        @validate="validateField('code')"
                        :error="errors.code"
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
                    <button @click="closeCreateModal" class="btn btn-danger me-1" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {useWarehouseStore} from '@/stores/admin/inventory/warehouse.js';

const warehouseStore = useWarehouseStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    code: '',
    phone: '',
    address: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    phone: string().nullable(),
    address: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeWarehouse = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await warehouseStore.storeWarehouse(form);
            toast(res.status, res.data.message);
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
