<template>
    <PageHeader :title="member.data?.name ? `Edit ${member.data.name}` : 'Edit Member'" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <MemberForm :member="member.data" :is-submitting="isSubmitting" @submit="save" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import MemberForm from './Form.vue';
import { useGymMemberStore } from '@/stores/admin/gym/member';

const route = useRoute();
const router = useRouter();
const memberStore = useGymMemberStore();
const { member } = storeToRefs(memberStore);
const isSubmitting = ref(false);

onMounted(() => memberStore.getMember(route.params.id));

const save = async (form) => {
    isSubmitting.value = true;

    try {
        const res = await memberStore.updateMember(route.params.id, form);
        toast(res.status, res.data.message);
        router.push({ name: 'admin.gym-member-profile', params: { id: route.params.id } });
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};
</script>
