<template>
    <VModal
        :show-modal="!!edit_account_group_id"
        @close-click="closeEditModal"
        title="Update Account Group">
        <template #modal-body>
            <VLoader v-if="accountGroup.loading" loader-type="progress"/>
            <form @submit.prevent="updateAccountGroup(accountGroup.data.id)" class="row g-3">
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
                    <VMultiselect
                        id="parent_id"
                        v-model="form.parent_id"
                        :options="accountGroups.data"
                        label="Parent"
                        @validate="validateField('parent_id')"
                        :error="errors.parent_id"
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
import {useAccountGroupStore} from "@/stores/admin/accounting/account-group.js";

const accountGroupStore = useAccountGroupStore();

const edit_account_group_id = defineModel('account_group_id');

const {accountGroup, accountGroups} = storeToRefs(accountGroupStore);

const initialState = {
    parent_id: '',
    name: '',
    code: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_account_group_id.value, async (id) => {
    if (id) {
        await accountGroupStore.getAccountGroup(id);
        Object.keys(form).forEach(key => {
            form[key] = accountGroup.value.data[key] || '';
        });
    }
});

const validations = object({
    parent_id: string().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    description: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateAccountGroup = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await accountGroupStore.updateAccountGroup(id, form);
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
    edit_account_group_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
