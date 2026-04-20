<template>
    <VModal
        :show-modal="!!edit_user_id"
        @close-click="closeEditModal"
        modal-class="extra-medium-modal"
        title="Update User">
        <template #modal-body>
            <VLoader v-if="user.loading" loader-type="progress"/>
            <form @submit.prevent="updateUser(user.data.id)" class="row g-3">
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
                        id="email"
                        v-model="form.email"
                        label="Email"
                        @validate="validateField('email')"
                        :error="errors.email"
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
                    <VMultiselect
                        id="roles"
                        v-model="form.roles"
                        :options="roles.data"
                        :loading="roles.loading"
                        mode="multiple"
                        label="Role"
                        @validate="validateField('roles')"
                        :error="errors.roles"
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
import {reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import {useUserStore} from "@/stores/admin/user-management/user";
import {useRoleStore} from "@/stores/admin/user-management/role";

const roleStore = useRoleStore();
const userStore = useUserStore();

const edit_user_id = defineModel('user_id');

const {roles} = storeToRefs(roleStore);
const {user} = storeToRefs(userStore);

const initialState = {
    name: '',
    phone: '',
    email: '',
    roles: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_user_id.value, async (id) => {
    if (id) {
        await userStore.getUser(id);
        Object.keys(form).forEach(key => {
            if (key === 'roles') {
                form[key] = user.value.data.roles?.map(r => r.id);
            } else {
                form[key] = user.value.data[key];
            }
        })
    }
})

const validations = object({
    name: string().required('Name is required.'),
    email: string().required('Email is required.').email(),
    phone: string().nullable(),
    roles: array().required('Roles is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateUser = async (id) => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await userStore.updateUser(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const closeEditModal = () => {
    resetForm();
    edit_user_id.value = '';
}

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
