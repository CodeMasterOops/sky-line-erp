<template>
    <VModal :show-modal="!!createModalOpened" @close-click="closeModal" title="New Leave Application">
        <template #modal-body>
            <form @submit.prevent="submit" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employee *</label>
                    <select v-model="form.employee_id" class="form-select" @change="validateField('employee_id')">
                        <option value="">Select Employee</option>
                        <option v-for="e in employees.data" :key="e.id" :value="e.id">{{ e.full_name }}</option>
                    </select>
                    <small class="text-danger">{{ errors.employee_id }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Leave Type *</label>
                    <select v-model="form.leave_type_id" class="form-select">
                        <option value="">Select Type</option>
                        <option v-for="t in leaveTypes.data" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <VInput id="from_date" v-model="form.from_date" label="From Date *" type="date" @validate="validateField('from_date')" :error="errors.from_date" />
                </div>
                <div class="col-md-4">
                    <VInput id="to_date" v-model="form.to_date" label="To Date *" type="date" />
                </div>
                <div class="col-md-4">
                    <VInput id="days" v-model="form.days" label="Days *" type="number" step="0.5" />
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" v-model="form.reason" rows="2"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="button" @click="closeModal" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string, number } from 'yup';
import { useYup } from '@/helpers/yup';
import { storeToRefs } from 'pinia';
import { useLeaveStore } from '@/stores/admin/hr/leave.js';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';

const leaveStore = useLeaveStore();
const empStore = useEmployeeStore();
const { leaveTypes } = storeToRefs(leaveStore);
const { employees } = storeToRefs(empStore);

const createModalOpened = defineModel('createModalOpened');
const emit = defineEmits(['created']);

const initial = { employee_id: '', leave_type_id: '', from_date: '', to_date: '', days: 1, reason: '' };
const form = reactive({ ...initial });
const isSubmitting = ref(false);

const validations = object({
    employee_id: string().required('Employee is required.'),
    from_date: string().required('From date is required.'),
});
const { errors, validateField, validateForm } = useYup(form, validations);

onMounted(() => {
    leaveStore.getLeaveTypes();
    empStore.getEmployees({ limit: 200 });
});

const submit = async () => {
    if (await validateForm(validations, form)) {
        isSubmitting.value = true;
        try {
            const res = await leaveStore.storeApplication(form);
            toast(res.status, res.data.message);
            emit('created');
            closeModal();
        } catch (e) { showErrors(e); }
        finally { isSubmitting.value = false; }
    }
};
const closeModal = () => { Object.assign(form, { ...initial }); errors.value = {}; createModalOpened.value = false; };
</script>
