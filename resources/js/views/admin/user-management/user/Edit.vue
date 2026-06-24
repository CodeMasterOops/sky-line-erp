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

                <div class="col-12">
                    <hr class="my-1" />
                    <label class="form-label fw-semibold mb-2">
                        <i class="ti ti-building me-1"></i> Branch Access
                    </label>
                    <div v-if="branches.loading" class="text-muted small py-1">Loading branches...</div>
                    <div v-else-if="!branches.data.length" class="text-muted small fst-italic py-1">No branches configured yet.</div>
                    <div v-else class="row g-2">
                        <div v-for="branch in branches.data" :key="branch.id" class="col-sm-6">
                            <label
                                class="d-flex align-items-center gap-2 p-2 rounded border"
                                :class="form.branch_ids.includes(branch.id)
                                    ? 'border-primary bg-primary bg-opacity-10'
                                    : 'border-secondary border-opacity-25'"
                                style="cursor:pointer"
                            >
                                <input
                                    type="checkbox"
                                    class="form-check-input mt-0 flex-shrink-0"
                                    :value="branch.id"
                                    v-model="form.branch_ids"
                                />
                                <span class="fw-medium small">{{ branch.name }}</span>
                                <span v-if="branch.is_head_office" class="badge bg-warning text-dark ms-auto small">HQ</span>
                            </label>
                        </div>
                    </div>
                </div>

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
import {reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {storeToRefs} from "pinia";
import {useUserStore} from "@/stores/admin/user-management/user";
import {useRoleStore} from "@/stores/admin/user-management/role";
import {useBranchStore} from "@/stores/admin/settings/branch";
import {apiAdmin} from "@/helpers/api";

const roleStore = useRoleStore();
const userStore = useUserStore();
const branchStore = useBranchStore();

const edit_user_id = defineModel('user_id');

const {roles} = storeToRefs(roleStore);
const {user} = storeToRefs(userStore);
const {branches} = storeToRefs(branchStore);

const initialState = {
    name: '',
    phone: '',
    email: '',
    roles: [],
    branch_ids: [],
};

const form = reactive({...initialState});
const isSubmitting = ref(false);
const originalBranchIds = ref([]);

watch(() => edit_user_id.value, async (id) => {
    if (id) {
        branchStore.getBranches();
        await userStore.getUser(id);
        Object.keys(form).forEach(key => {
            if (key === 'roles') {
                form[key] = user.value.data.roles?.map(r => r.id);
            } else if (key === 'branch_ids') {
                form[key] = [...(user.value.data.branch_ids ?? [])];
            } else {
                form[key] = user.value.data[key];
            }
        });
        originalBranchIds.value = [...(user.value.data.branch_ids ?? [])];
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

            const toAdd = form.branch_ids.filter(bid => !originalBranchIds.value.includes(bid));
            const toRemove = originalBranchIds.value.filter(bid => !form.branch_ids.includes(bid));

            await Promise.all([
                ...toAdd.map(branchId =>
                    apiAdmin(`branch/${branchId}/users`, 'post', {user_id: id}).catch(() => {})
                ),
                ...toRemove.map(branchId =>
                    apiAdmin(`branch/${branchId}/users/${id}`, 'delete').catch(() => {})
                ),
            ]);

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
    Object.assign(form, {...initialState, branch_ids: []});
    originalBranchIds.value = [];
    errors.value = {};
}
</script>
