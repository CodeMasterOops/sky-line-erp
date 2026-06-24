<template>
    <PageHeader title="Create Role" subtitle="Define role details and assign permissions">
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
            :loading="groups.loading"
            :submitting="isSubmitting"
            submit-label="Create Role"
            @submit="storeRole"
        />
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from "vue";
import { toast } from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import { useRoleStore } from "@/stores/admin/user-management/role";
import { storeToRefs } from "pinia";
import { useRouter } from "vue-router";
import RoleForm from "./components/RoleForm.vue";

const roleStore = useRoleStore();
const router = useRouter();

const { permissions: groups } = storeToRefs(roleStore);

const form = reactive({
    name: "",
    permissions: [],
});

const isSubmitting = ref(false);

onMounted(() => {
    roleStore.getPermissions();
});

const storeRole = async () => {
    isSubmitting.value = true;

    try {
        const res = await roleStore.storeRole(form);
        toast(res.status, res.data.message);
        await router.push({ name: "admin.role-list" });
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
