<template>
    <VModal
        :show-modal="!!edit_tax_id"
        @close-click="closeEditModal"
        title="Update Tax">
        <template #modal-body>
            <VLoader v-if="tax.loading" loader-type="progress" />
            <form @submit.prevent="updateTax(tax.data.id)" class="row g-3">
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
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { storeToRefs } from 'pinia';
import { useTaxStore } from '@/stores/admin/setting/tax.js';

const taxStore = useTaxStore();

const edit_tax_id = defineModel('tax_id');

const { tax } = storeToRefs(taxStore);

const initialState = {
    name: '',
    rate: ''
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

watch(() => edit_tax_id.value, async (id) => {
    if (id) {
        await taxStore.getTax(id);
        Object.keys(form).forEach(key => {
            form[key] = tax.value.data[key] || '';
        });
    }
});

const validations = object({
    name: string().required('Name is required.'),
    rate: string().required('Rate is required.')
});

const { errors, validateField, validateForm } = useYup(form, validations);

const updateTax = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await taxStore.updateTax(id, form);
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
    edit_tax_id.value = '';
};

function resetForm() {
    Object.assign(form, { ...initialState });
    errors.value = {};
}

</script>
