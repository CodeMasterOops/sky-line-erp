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
                <div class="col-12 text-end">
                    <button @click="closeCreateModal" class="btn btn-danger me-2" type="button">
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
import {useTaxStore} from '@/stores/admin/setting/tax.js';

const userStore = useTaxStore();

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    rate: ''
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
