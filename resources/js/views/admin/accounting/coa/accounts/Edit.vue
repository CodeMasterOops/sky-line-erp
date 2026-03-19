<template>
    <VModal
        :show-modal="!!edit_account_id"
        @close-click="closeEditModal"
        title="Update Account">
        <template #modal-body>
            <VLoader v-if="account.loading" loader-type="progress"/>
            <form @submit.prevent="updateAccount(account.data.id)" class="row g-3">
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
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useAccountStore} from "@/stores/admin/accounting/account.js";
import {useAccountGroupStore} from "@/stores/admin/accounting/account-group.js";

const accountStore = useAccountStore();
const accountGroupStore = useAccountGroupStore();

const edit_account_id = defineModel('account_id');

const {accountGroups} = storeToRefs(accountGroupStore);
const {account} = storeToRefs(accountStore);

const initialState = {
    account_group_id: '',
    name: '',
    code: '',
    category: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_account_id.value, async (id) => {
    if (id) {
        await accountStore.getAccount(id);
        Object.keys(form).forEach(key => {
            form[key] = account.value.data[key] || '';
        });
    }
});

const validations = object({
    account_group_id: string().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    category: string().nullable(),
    description: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateAccount = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await accountStore.updateAccount(id, form);
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
    edit_account_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
