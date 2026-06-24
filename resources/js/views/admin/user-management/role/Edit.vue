<template>
    <PageHeader title="Edit Role" subtitle="Update role details and assigned permissions">
        <template #actions>
            <router-link :to="{name:'admin.role-list'}" class="btn btn-outline-primary d-flex align-items-center">
                <i class="ti ti-arrow-left me-2"></i> Back To Roles
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <RoleForm
            v-model="form"
            :permission-groups="groups.data"
            :loading="role.loading || groups.loading"
            :submitting="isSubmitting"
            submit-label="Update Role"
            @submit="updateRole"
        />
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { useRoleStore } from "@/stores/admin/user-management/role";
import { storeToRefs } from "pinia";
import { useRoute, useRouter } from "vue-router";
import RoleForm from "./components/RoleForm.vue";

const roleStore = useRoleStore();
const router = useRouter();
const route = useRoute();

const { permissions: groups, role } = storeToRefs(roleStore);

const form = reactive({
    name: "",
    permissions: [],
});

const isSubmitting = ref(false);

onMounted(async () => {
    await Promise.all([
        roleStore.getPermissions(),
        roleStore.getRole(route.params.id),
    ]);

    form.name = role.value.data.name ?? "";
    form.permissions = role.value.data.permissions ?? [];
});

const updateRole = async () => {
    isSubmitting.value = true;

    try {
        const res = await roleStore.updateRole(route.params.id, form);
        toast(res.status, res.data.message);
        await router.push({ name: "admin.role-list" });
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
