<template>
    <PageHeader title="Register Member" subtitle="A member is a customer with a gym profile" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <MemberForm :is-submitting="isSubmitting" @submit="save" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import MemberForm from './Form.vue';
import { useGymMemberStore } from '@/stores/admin/gym/member';

const router = useRouter();
const memberStore = useGymMemberStore();
const isSubmitting = ref(false);

onMounted(() => memberStore.getNextCode());

const save = async (form) => {
    isSubmitting.value = true;

    try {
        const res = await memberStore.storeMember({ ...form, member_code: form.member_code || undefined });
        toast(res.status, res.data.message);
        router.push({ name: 'admin.gym-member-profile', params: { id: res.data.data.id } });
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
