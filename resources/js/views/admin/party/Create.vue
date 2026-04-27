<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        modal-class="large-modal"
        :title="modalTitle">
        <template #modal-body>
            <form @submit.prevent="storeParty" class="row g-3">
                <PartyTypeSelector
                    v-if="!isTypeLocked"
                    id-prefix="party-create"
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
import {computed, reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {usePartyStore} from "@/stores/admin/party.js";
import PartyTypeSelector from '@/components/party/PartyTypeSelector.vue';

const LOCKED_PARTY_TYPES = ['customer', 'supplier', 'lead'];

const props = defineProps({
    type: {
        type: String
    }
});

const partyStore = usePartyStore();

const createModalOpened = defineModel('createModalOpened');

const isTypeLocked = computed(() => !!props.type && LOCKED_PARTY_TYPES.includes(props.type));

const modalTitle = computed(() => {
    if (props.type === 'supplier') {
        return 'Add supplier';
    }
    if (props.type === 'customer') {
        return 'Add customer';
    }
    if (props.type === 'lead') {
        return 'Add lead';
    }
    return 'Add new party';
});

function defaultTypeForForm() {
    if (isTypeLocked.value) {
        return props.type;
    }
    return 'customer';
}

const form = reactive({
    type: defaultTypeForForm(),
    name: '',
    code: '',
    phone: '',
    email: '',
    pan: '',
    address: '',
    credit_limit: '',
});
const isSubmitting = ref(false);

watch(() => createModalOpened.value, (opened) => {
    if (opened) {
        form.type = defaultTypeForForm();
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

const storeParty = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await partyStore.storeParty(form);
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
    Object.assign(form, {
        type: defaultTypeForForm(),
        name: '',
        code: '',
        phone: '',
        email: '',
        pan: '',
        address: '',
        credit_limit: '',
    });
    errors.value = {};
}

</script>
