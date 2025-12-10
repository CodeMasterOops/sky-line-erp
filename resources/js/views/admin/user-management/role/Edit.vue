<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Edit Role</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Edit Role</h5>
                <router-link :to="{name:'admin.role-list'}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-list"> Role List</i>
                </router-link>
            </div>
            <VLoader v-if="role.loading" loader-type="progress"/>
            <div class="card-body">
                <form @submit.prevent="updateRole(role.data.id)" class="row g-3">
                    <div class="col-md-12">
                        <VInput
                            id="name"
                            v-model="form.name"
                            label="Role"
                            @validate="validateField('name')"
                            :error="errors.name"
                        />
                    </div>
                    <div class="col-md-12">
                        <label for="permissions" class="form-label">
                            Permissions : &nbsp;
                        </label>
                        <input
                            type="checkbox"
                            v-model="allPermissionsChecked"
                            :value="true"
                            class="form-check-input"
                            style="height: 1.8em;width: 1.8em;">
                    </div>
                    <div class="col-md-12">
                        <div class="row">
                            <div v-for="(permissions,group,groupKey) in groups.data" :key="groupKey" class="col-sm-4">
                                <ul class="list-group mb-3 accordion">
                                    <li class="list-group-item active" aria-current="true">{{ groupKey + 1 }}
                                        {{ group }}
                                    </li>
                                    <li v-for="(permission,pKey) in permissions.slice(0,5)" :key="pKey"
                                        class="list-group-item list-group-item-light d-flex justify-content-between">
                                        <label :for="permission.permission">
                                            <input
                                                class="form-check-input me-1"
                                                type="checkbox"
                                                :id="permission.permission"
                                                :value="permission.permission"
                                                @change="validateField('permissions')"
                                                v-model="form.permissions"
                                                aria-label="...">
                                            {{ permission.description }}
                                        </label>
                                        <span v-if="permissions.length>5 && pKey===4" class="collapsed"
                                              data-bs-toggle="collapse"
                                              :data-bs-target="'#p-'+groupKey">
                                            <i class="fa fa-expand text-success"></i>
                                        </span>
                                    </li>
                                    <template v-if="permissions.length>5">
                                        <div :id="'p-'+groupKey" class="accordion-collapse collapse">
                                            <template v-for="(permission,pKey) in permissions.slice(5)" :key="pKey">
                                                <li class="list-group-item list-group-item-light">
                                                    <label :for="permission.permission">
                                                        <input
                                                            class="form-check-input me-1"
                                                            type="checkbox"
                                                            :id="permission.permission"
                                                            :value="permission.permission"
                                                            @change="validateField('permissions')"
                                                            v-model="form.permissions">
                                                        {{ permission.description }}
                                                    </label>
                                                </li>
                                            </template>
                                        </div>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <p v-if="errors.permissions" class="text-danger">
                            {{ errors.permissions }}
                        </p>
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
import {onMounted, reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {array, object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useRoleStore} from "@/stores/admin/user-management/role";
import {storeToRefs} from "pinia";
import {useRoute, useRouter} from "vue-router";

const roleStore = useRoleStore();
const router = useRouter();
const route = useRoute();

onMounted(() => {
    roleStore.getPermissions();
    setFormData();
})

const setFormData = async () => {
    await roleStore.getRole(route.params.id);
    Object.keys(form).forEach(key => {
        form[key] = role.value.data[key] ?? ''
    })
}

const {permissions:groups, role} = storeToRefs(roleStore);

const initialState = {
    name: '',
    permissions: []
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Role is required.'),
    permissions: array().min(1,'Permissions is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateRole = async (id) => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await roleStore.updateRole(id, form);
            toast(res.status, res.data.message);
            resetForm();
            await router.push({name: 'admin.role-list'})
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const resetForm = () => {
    Object.assign(form, {...initialState});
    errors.value = {};
    form.permissions = [];
}

const allPermissionsChecked = ref(false);

watch(() => allPermissionsChecked.value, () => {
    if (allPermissionsChecked.value) {
        Object.keys(groups.value.data).forEach(d => {
            const data = (groups.value.data[d]).map(p => p.permission);
            data.forEach(p => {
                form.permissions.push(p);
            })
        })
    } else {
        form.permissions = [];
    }
})

</script>

