<template>
    <VModal
        :show-modal="!!edit_party_id"
        @close-click="closeEditModal"
        modal-class="large-modal"
        title="Update Party">
        <template #modal-body>
            <VLoader v-if="party.loading" loader-type="progress"/>
            <form @submit.prevent="updateParty(party.data.id)" class="row g-3">
                <PartyTypeSelector
                    id-prefix="party-edit"
                    v-model="form.type"
                    :error="errors.type"
                    @change="validateField('type')"
                />
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
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-2" type="button">
                        Close
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
import PartyTypeSelector from '@/components/party/PartyTypeSelector.vue';

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
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_party_id.value, async (id) => {
    if (id) {
        await partyStore.getParty(id);
        Object.keys(form).forEach(key => {
            form[key] = party.value.data[key] || '';
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
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateParty = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await partyStore.updateParty(id, form);
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
