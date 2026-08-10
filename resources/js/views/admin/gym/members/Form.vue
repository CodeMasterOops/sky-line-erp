<template>
    <form class="row g-3" @submit.prevent="submit">
        <div class="col-12">
            <h6 class="text-uppercase fs-12 text-muted mb-0">Member details</h6>
        </div>

        <div class="col-md-4">
            <VInput
                id="member_code"
                v-model="form.member_code"
                label="Member ID"
                :disabled="isEdit"
                :error="errors.member_code"
            />
        </div>
        <div class="col-md-8">
            <VInput
                id="member_name"
                v-model="form.name"
                label="Full Name"
                placeholder="Ram Bahadur"
                required
                :error="errors.name"
                @validate="validateField('name')"
            />
        </div>

        <div class="col-md-4">
            <VInput id="member_phone" v-model="form.phone" label="Phone" :error="errors.phone" />
        </div>
        <div class="col-md-4">
            <VInput id="member_email" v-model="form.email" label="Email" :error="errors.email" />
        </div>
        <div class="col-md-4">
            <VInput id="member_joined_on" v-model="form.joined_on" input-type="date" label="Joined On" />
        </div>

        <div class="col-md-4">
            <VInput id="member_dob" v-model="form.date_of_birth" input-type="date" label="Date of Birth" :error="errors.date_of_birth" />
        </div>
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select v-model="form.gender" class="form-select">
                <option :value="null">Not specified</option>
                <option v-for="option in genders" :key="option.id" :value="option.id">{{ option.name }}</option>
            </select>
        </div>
        <div class="col-md-4">
            <VInput id="member_blood_group" v-model="form.blood_group" label="Blood Group" placeholder="O+" />
        </div>

        <div class="col-md-6">
            <VInput id="member_address" v-model="form.address" label="Address" />
        </div>
        <div class="col-md-6">
            <VInput id="member_occupation" v-model="form.occupation" label="Occupation" />
        </div>

        <div class="col-12 mt-4">
            <h6 class="text-uppercase fs-12 text-muted mb-0">Emergency contact & health</h6>
        </div>

        <div class="col-md-4">
            <VInput id="member_ec_name" v-model="form.emergency_contact_name" label="Contact Name" />
        </div>
        <div class="col-md-4">
            <VInput id="member_ec_phone" v-model="form.emergency_contact_phone" label="Contact Phone" />
        </div>
        <div class="col-md-2">
            <VInput id="member_height" v-model="form.height_cm" input-type="number" label="Height (cm)" />
        </div>
        <div class="col-md-2">
            <VInput id="member_weight" v-model="form.weight_kg" input-type="number" label="Weight (kg)" />
        </div>
        <div class="col-12">
            <VTextarea id="member_medical" v-model="form.medical_notes" label="Medical Notes" />
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <router-link :to="{ name: 'admin.gym-member-list' }" class="btn btn-cancel">Cancel</router-link>
            <VButton :loading="isSubmitting" />
        </div>
    </form>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import VInput from '@/components/base/VInput.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VButton from '@/components/base/VButton.vue';

const props = defineProps({
    member: { type: Object, default: null },
    isSubmitting: { type: Boolean, default: false },
    serverErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['submit']);

const genders = [
    { id: 'male', name: 'Male' },
    { id: 'female', name: 'Female' },
    { id: 'other', name: 'Other' },
];

const initialState = {
    member_code: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    joined_on: new Date().toISOString().slice(0, 10),
    date_of_birth: null,
    gender: null,
    blood_group: '',
    occupation: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    height_cm: null,
    weight_kg: null,
    medical_notes: '',
};

const form = reactive({ ...initialState });

const isEdit = computed(() => !!props.member?.id);

const validations = object({
    name: string().required('The member name is required.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

watch(
    () => props.member,
    (member) => {
        if (!member) {
            return;
        }

        Object.assign(form, { ...initialState, ...member });
    },
    { immediate: true },
);

const submit = async () => {
    if (!(await validateForm(validations, form))) {
        return;
    }

    emit('submit', { ...form });
};

defineExpose({ form });
</script>
