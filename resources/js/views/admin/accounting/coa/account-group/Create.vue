<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add Account Group">
        <template #modal-body>
            <form @submit.prevent="storeAccountGroup" class="row g-3">
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
import {useAccountGroupStore} from "@/stores/admin/accounting/account-group.js";
import {storeToRefs} from "pinia";

const accountGroupStore = useAccountGroupStore();

const {accountGroups} = storeToRefs(accountGroupStore);

const createModalOpened = defineModel('createModalOpened');

const initialState = {
    parent_id: '',
    name: '',
    code: '',
    description: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    parent_id: string().nullable(),
    name: string().required('Name is required.'),
    code: string().required('Code is required.'),
    description: string().nullable()
});

const {errors, validateField, validateForm} = useYup(form, validations);

const storeAccountGroup = async () => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await accountGroupStore.storeAccountGroup(form);
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
