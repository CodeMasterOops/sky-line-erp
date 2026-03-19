<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add Account">
        <template #modal-body>
            <form @submit.prevent="storeAccount" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="account_group_id"
                        v-model="form.account_group_id"
                        :options="accountGroups.data"
                        label="Account Group"
                        @validate="validateField('account_group_id')"
                        :error="errors.account_group_id"
                    />
                </div>
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
                        id="category"
                        v-model="form.category"
                        label="Category"
                        @validate="validateField('category')"
                        :error="errors.category"
                    />
                </div>
                <div class="col-md-12">
                    <VTextarea
                        id="description"
                        v-model="form.description"
                        label="Description"
                        @validate="validateField('description')"
                        :error="errors.description"
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
import {storeToRefs} from "pinia";
import {useAccountStore} from "@/stores/admin/accounting/account.js";
import {useAccountGroupStore} from "@/stores/admin/accounting/account-group.js";

const accountStore = useAccountStore();
const accountGroupStore = useAccountGroupStore();

const {accountGroups} = storeToRefs(accountGroupStore);

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    account_group_id: '',
    name: '',
    code: '',
    category: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    account_group_id: string().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    category: string().nullable(),
    description: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeAccount = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await accountStore.storeAccount(form);
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
