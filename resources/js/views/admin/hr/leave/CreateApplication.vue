<template>
    <VModal :show-modal="!!createModalOpened" @close-click="closeModal" title="New Leave Application">
        <template #modal-body>
            <form @submit.prevent="submit" class="row g-3">
                <div class="col-md-6">
                    <VMultiselect
                        id="employee_id"
                        v-model="form.employee_id"
                        label="Employee"
                        placeholder="Select Employee"
                        :options="employees.data"
                        name-prop="full_name"
                        required
                        :error="errors.employee_id"
                        @validate="validateField('employee_id')"
                    />
                </div>
                <div class="col-md-6">
                    <VMultiselect
                        id="leave_type_id"
                        v-model="form.leave_type_id"
                        label="Leave Type"
                        placeholder="Select Type"
                        :options="leaveTypes.data"
                        required
                    />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="from_date" v-model="form.from_date" label="From Date" required @validate="validateField('from_date')" :error="errors.from_date" />
                </div>
                <div class="col-md-4">
                    <VDatepicker id="to_date" v-model="form.to_date" label="To Date" required />
                </div>
                <div class="col-md-4">
                    <VInput id="days" v-model="form.days" label="Days *" type="number" step="0.5" />
                </div>
                <div class="col-12">
                    <label class="form-label">Reason</label>
                    <textarea class="form-control" v-model="form.reason" rows="2"></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" @click="closeModal" class="btn btn-cancel">Cancel</button>
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
import VDatepicker from '@/components/base/VDatepicker.vue';

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
