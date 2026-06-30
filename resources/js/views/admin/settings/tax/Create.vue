<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add New Tax">
        <template #modal-body>
            <form @submit.prevent="storeTax" class="row g-3">
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
                        input-type="number"
                        id="rate"
                        v-model="form.rate"
                        label="Rate (%)"
                        @validate="validateField('rate')"
                        :error="errors.rate"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="type"
                        v-model="form.type"
                        label="Type"
                        :options="taxTypeOptions"
                    />
                </div>
                <div v-if="form.type === 'tds'" class="col-md-6">
                    <VMultiselect
                        id="tds_category"
                        v-model="form.tds_category"
                        label="TDS Category"
                        placeholder="-- Select Category --"
                        :options="tdsCategoryOptions"
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
import {reactive, ref} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {useTaxStore} from '@/stores/admin/settings/tax.js';

const taxTypeOptions = [
    { id: 'vat_standard', name: 'VAT Standard (13%)' },
    { id: 'vat_exempt', name: 'VAT Exempt' },
    { id: 'vat_zero_rated', name: 'VAT Zero Rated' },
    { id: 'tds', name: 'TDS (Tax Deducted at Source)' },
];

const tdsCategoryOptions = [
    { id: 'rent', name: 'Rent (10%)' },
    { id: 'service_payment', name: 'Service Payment (15%)' },
    { id: 'commission', name: 'Commission (15%)' },
    { id: 'dividend', name: 'Dividend (5%)' },
    { id: 'interest', name: 'Interest (15%)' },
    { id: 'contract', name: 'Contract / Work (1.5%)' },
    { id: 'royalty', name: 'Royalty (15%)' },
    { id: 'others', name: 'Others' },
];

const userStore = useTaxStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    rate: '',
    type: 'vat_standard',
    tds_category: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Name is required.'),
    rate: string().required('Rate is required.')
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeTax = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await userStore.storeTax(form);
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
