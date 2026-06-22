<template>
    <VModal
        :show-modal="!!edit_party_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Party">
        <template #modal-body>
            <VLoader v-if="party.loading" loader-type="progress"/>
            <form @submit.prevent="updateParty(party.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        required
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="code"
                        v-model="form.code"
                        label="Code"
                        required
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
                        id="email"
                        v-model="form.email"
                        label="Email"
                        @validate="validateField('email')"
                        :error="errors.email"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="pan"
                        v-model="form.pan"
                        label="PAN"
                        @validate="validateField('pan')"
                        :error="errors.pan"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        input-type="number"
                        id="credit_limit"
                        v-model="form.credit_limit"
                        label="Credit Limit"
                        @validate="validateField('credit_limit')"
                        :error="errors.credit_limit"
                    />
                </div>
                <div class="col-md-12">
                    <VInput
                        id="address"
                        v-model="form.address"
                        label="Address"
                        @validate="validateField('address')"
                        :error="errors.address"
                    />
                </div>
                <template v-if="form.type === 'customer'">
                    <div class="col-md-6">
                        <label class="form-label" for="party_edit_discount_value">
                            Default order discount
                        </label>
                        <VDiscountAmountTypeGroup selector-mode="toggle"
                            id="party_edit_discount_value"
                            v-model="form.discount_value"
                            v-model:discount-type="form.discount_type"
                            :error="errors.discount_value"
                            input-id="party_edit_discount_value"
                            @blur="validateField('discount_value')"
                        />
                        <div class="form-text text-muted">
                            Applied automatically when this customer is selected on sales documents. Can be changed per transaction.
                        </div>
                    </div>
                </template>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeEditModal" class="btn btn-cancel" type="button">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {usePartyStore} from "@/stores/admin/party.js";
import VDiscountAmountTypeGroup from '@/components/base/VDiscountAmountTypeGroup.vue';

const partyStore = usePartyStore();

const edit_party_id = defineModel('party_id');

const {party} = storeToRefs(partyStore);

const initialState = {
    type: 'customer',
    name: '',
    code: '',
    phone: '',
    email: '',
    pan: '',
    address: '',
    credit_limit: '',
    discount_type: 'fixed',
    discount_value: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_party_id.value, async (id) => {
    if (id) {
        await partyStore.getParty(id);
        Object.keys(form).forEach(key => {
            const value = party.value.data[key];
            form[key] = value != null && value !== '' ? String(value) : (key === 'discount_type' ? 'fixed' : '');
        });
    }
});

const validations = object({
    type: string().required('Party Type is required.'),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    phone: string().nullable(),
    email: string().nullable(),
    pan: string().nullable(),
    address: string().nullable(),
    credit_limit: string().nullable(),
    discount_type: string().nullable(),
    discount_value: string().nullable(),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateParty = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            const payload = {...form};
            if (payload.type !== 'customer') {
                delete payload.discount_type;
                delete payload.discount_value;
            }
            let res = await partyStore.updateParty(id, payload);
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
    edit_party_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
