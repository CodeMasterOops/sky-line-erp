<template>
    <PageHeader title="Create Role" subtitle="Define role details and assign permissions">
        <template #actions>
            <router-link :to="{name:'admin.role-list'}" class="btn btn-outline-primary d-flex align-items-center">
                <i class="ti ti-arrow-left me-2"></i> Back To Roles
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card border-0 shadow-sm">
            <VLoader v-if="groups.loading" loader-type="progress"/>

            <div class="card-body p-4">
                <form @submit.prevent="storeRole" class="row g-4">
                    <div class="col-12">
                        <VInput
                            id="name"
                            v-model="form.name"
                            label="Role Name"
                            @validate="validateField('name')"
                            :error="errors.name"
                        />
                    </div>

                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Permissions</h6>
                                    <p class="text-muted mb-0">
                                        {{ form.permissions.length }} of {{ allPermissionValues.length }} selected
                                    </p>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input
                                        id="select-all-permissions"
                                        v-model="allPermissionsChecked"
                                        class="form-check-input"
                                        type="checkbox"
                                    >
                                    <label class="form-check-label" for="select-all-permissions">
                                        Select all permissions
                                    </label>
                                </div>
                            </div>
                        </div>
                        <p v-if="errors.permissions" class="text-danger mt-2 mb-0">
                            {{ errors.permissions }}
                        </p>
                    </div>

                    <div class="col-12" v-if="moduleEntries.length">
                        <div
                            v-for="([module, permissionGroups], moduleIndex) in moduleEntries"
                            :key="module"
                            class="card border mb-4"
                        >
                            <div class="card-header bg-body-tertiary">
                                <h6 class="card-title mb-0 fw-bold">
                                    {{ moduleIndex + 1 }}. {{ module }}
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <div
                                        v-for="([group, permissions], groupIndex) in Object.entries(permissionGroups)"
                                        :key="`${module}-${group}`"
                                        class="col-lg-4 col-md-6"
                                    >
                                        <div class="border rounded-3 h-100">
                                            <div class="px-3 py-2 border-bottom bg-light fw-semibold">
                                                {{ moduleIndex + 1 }}.{{ groupIndex + 1 }} {{ group }}
                                            </div>
                                            <div class="p-3">
                                                <div
                                                    v-for="permission in permissions"
                                                    :key="permission.permission"
                                                    class="form-check mb-2"
                                                >
                                                    <input
                                                        :id="permission.permission"
                                                        v-model="form.permissions"
                                                        :value="permission.permission"
                                                        class="form-check-input"
                                                        type="checkbox"
                                                        @change="validateField('permissions')"
                                                    >
                                                    <label class="form-check-label" :for="permission.permission">
                                                        {{ permission.description }}
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 text-end">
                        <VButton :loading="isSubmitting"/>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted, reactive, ref} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useRoleStore} from "@/stores/admin/user-management/role";
import {storeToRefs} from "pinia";
import {useRouter} from "vue-router";

const roleStore = useRoleStore();
const router = useRouter();

onMounted(() => {
    roleStore.getPermissions();
});

const {permissions: groups} = storeToRefs(roleStore);

const initialState = {
    name: '',
    permissions: []
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Role is required.'),
    permissions: array().min(1, 'Permissions is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const moduleEntries = computed(() => Object.entries(groups.value.data ?? {}));

const allPermissionValues = computed(() => {
    const values = [];

    for (const [, permissionGroups] of moduleEntries.value) {
        for (const permissions of Object.values(permissionGroups)) {
            values.push(...permissions.map(permission => permission.permission));
        }
    }

    return [...new Set(values)];
});

const allPermissionsChecked = computed({
    get: () => allPermissionValues.value.length > 0 && form.permissions.length === allPermissionValues.value.length,
    set: (checked) => {
        form.permissions = checked ? [...allPermissionValues.value] : [];
        validateField('permissions');
    }
});

const storeRole = async () => {
    const validated = await validateForm(validations, form);

    if (!validated) {
        return;
    }

    isSubmitting.value = true;

    try {
        const res = await roleStore.storeRole(form);
        toast(res.status, res.data.message);
        resetForm();
        await router.push({name: 'admin.role-list'});
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const resetForm = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
};
</script>
