<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        size="md"
        title="Add New Payment Mode">
        <template #modal-body>
            <form @submit.prevent="storePaymentMode" class="row g-3">
                <div class="col-md-12">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-12">
                    <VSelect
                        id="bank_account_id"
                        v-model="form.bank_account_id"
                        :options="bankAccountOptions"
                        label="Bank Account"
                        placeholder="Bank Account"
                        name-prop="display_name"
                    />
                    <small class="text-muted">Link this payment mode to a bank or wallet account. Leave blank for cash.</small>
                </div>
                <div class="col-md-12">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" v-model="form.is_active"/>
                        <label class="form-check-label">Active</label>
                    </div>
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
import {computed, onMounted, reactive, ref} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import VSelect from '@/components/base/VSelect.vue';
import {usePaymentModeStore} from '@/stores/admin/settings/payment-mode.js';

const paymentModeStore = usePaymentModeStore();
const { bankAccounts } = storeToRefs(paymentModeStore);

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    name: '',
    bank_account_id: '',
    is_active: true
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const bankAccountOptions = computed(() =>
    bankAccounts.value.data.map(b => ({
        ...b,
        display_name: `${b.bank_name} – ${b.account_number}`,
    }))
);

onMounted(() => paymentModeStore.getBankAccounts());

const validations = object({
    name: string().required('Name is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storePaymentMode = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await paymentModeStore.storePaymentMode({
                ...form,
                bank_account_id: form.bank_account_id || null,
            });
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
