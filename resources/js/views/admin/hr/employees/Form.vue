<template>
    <PageHeader :title="isEdit ? 'Edit Employee' : 'Add Employee'" :subtitle="isEdit ? 'Update employee details' : 'Register a new employee'" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <VLoader v-if="loading" loader-type="progress" />
                <form v-else @submit.prevent="submit" class="row g-3">
                    <div class="col-12"><h6 class="text-muted fw-bold">Basic Information</h6></div>

                    <div class="col-md-3">
                        <label class="form-label" for="employee_code">
                            Employee Code
                            <VRequiredMark />
                        </label>
                        <div class="input-group">
                            <input
                                id="employee_code"
                                v-model="form.employee_code"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': errors.employee_code }"
                                autocomplete="off"
                                @blur="validateField('employee_code')"
                            />
                            <button
                                v-if="!isEdit"
                                type="button"
                                class="btn btn-primary"
                                :disabled="codeLoading"
                                @click="fetchNextCode">
                                Generate
                            </button>
                        </div>
                        <div v-if="errors.employee_code" class="invalid-feedback d-block">
                            {{ errors.employee_code }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <VInput id="first_name" v-model="form.first_name" label="First Name *" @validate="validateField('first_name')" :error="errors.first_name" />
                    </div>
                    <div class="col-md-3">
                        <VInput id="last_name" v-model="form.last_name" label="Last Name *" @validate="validateField('last_name')" :error="errors.last_name" />
                    </div>
                    <div class="col-md-3">
                        <VInput id="email" v-model="form.email" label="Email" type="email" />
                    </div>
                    <div class="col-md-3">
                        <VInput id="phone" v-model="form.phone" label="Phone" />
                    </div>
                    <div class="col-md-3">
                        <VMultiselect
                            id="gender"
                            v-model="form.gender"
                            label="Gender"
                            placeholder="Select"
                            :options="genderOptions"
                        />
                    </div>
                    <div class="col-md-3">
                        <VMultiselect
                            id="marital_status"
                            v-model="form.marital_status"
                            label="Marital Status"
                            :options="maritalStatusOptions"
                        />
                        <small class="text-muted">Affects salary tax slab.</small>
                    </div>
                    <div class="col-md-3">
                        <VDatepicker id="dob" v-model="form.dob" label="Date of Birth" />
                    </div>
                    <div class="col-md-3">
                        <VDatepicker id="join_date" v-model="form.join_date" label="Join Date" required @validate="validateField('join_date')" :error="errors.join_date" />
                    </div>

                    <div class="col-12 mt-2"><h6 class="text-muted fw-bold">Department & Role</h6></div>

                    <div class="col-md-4">
                        <VMultiselect
                            id="department_id"
                            v-model="form.department_id"
                            label="Department"
                            placeholder="Select Department"
                            :options="departments.data"
                        />
                    </div>
                    <div class="col-md-4">
                        <VMultiselect
                            id="designation_id"
                            v-model="form.designation_id"
                            label="Designation"
                            placeholder="Select Designation"
                            :options="designations.data"
                        />
                    </div>
                    <div class="col-md-4">
                        <VMultiselect
                            id="employment_type"
                            v-model="form.employment_type"
                            label="Employment Type"
                            :options="employmentTypeOptions"
                        />
                    </div>
                    <div class="col-md-4">
                        <VMultiselect
                            id="status"
                            v-model="form.status"
                            label="Status"
                            :options="statusOptions"
                        />
                    </div>

                    <div class="col-12 mt-2"><h6 class="text-muted fw-bold">Bank Details</h6></div>

                    <div class="col-md-6">
                        <VInput id="bank_name" v-model="form.bank_name" label="Bank Name" />
                    </div>
                    <div class="col-md-6">
                        <VInput id="bank_account_no" v-model="form.bank_account_no" label="Account Number" />
                    </div>

                    <div class="col-12 mt-2"><h6 class="text-muted fw-bold">Tax Information</h6></div>

                    <div class="col-md-4">
                        <VInput id="pan" v-model="form.pan" label="Employee PAN" placeholder="e.g. 123456789" />
                    </div>
                    <div class="col-md-8">
                        <VMultiselect
                            id="tds_category"
                            v-model="form.tds_category"
                            label="TDS Category"
                            placeholder="None (no TDS deduction)"
                            :options="tdsCategoryOptions"
                        />
                        <small class="text-muted">TDS will be auto-calculated on taxable salary components for this employee.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" v-model="form.address" rows="2"></textarea>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <router-link :to="{ name: 'admin.hr-employee-list' }" class="btn btn-cancel">Cancel</router-link>
                        <VButton :loading="isSubmitting" />
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { object, string } from 'yup';
import { useYup } from '@/helpers/yup';
import { storeToRefs } from 'pinia';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';
import { useDepartmentStore } from '@/stores/admin/hr/department.js';
import { useDesignationStore } from '@/stores/admin/hr/designation.js';
import { useNextCode } from '@/helpers/useNextCode.js';
import { apiAdmin } from '@/helpers/api.js';
import VDatepicker from '@/components/base/VDatepicker.vue';

const genderOptions = [
    { id: 'male', name: 'Male' },
    { id: 'female', name: 'Female' },
    { id: 'other', name: 'Other' },
];

const maritalStatusOptions = [
    { id: 'single', name: 'Single' },
    { id: 'married', name: 'Married' },
];

const employmentTypeOptions = [
    { id: 'full_time', name: 'Full Time' },
    { id: 'part_time', name: 'Part Time' },
    { id: 'contract', name: 'Contract' },
];

const statusOptions = [
    { id: 'active', name: 'Active' },
    { id: 'inactive', name: 'Inactive' },
    { id: 'terminated', name: 'Terminated' },
];

const tdsCategoryOptions = [
    { id: 'service_vat_bill', name: 'Service Fee (VAT Bill) – 1.5%' },
    { id: 'service_pan_bill', name: 'Service Fee (PAN Bill) – 15%' },
    { id: 'service_vat_exempt_institution', name: 'Service Fee (VAT-Exempt Institution) – 1%' },
    { id: 'contract_vat_registered', name: 'Contract Payment (VAT Registered) – 1.5%' },
    { id: 'rent_property', name: 'Rent (House/Land/Property) – 10%' },
    { id: 'rent_vehicle_vat', name: 'Vehicle Hire (VAT Bill) – 1.5%' },
    { id: 'rent_vehicle_no_vat', name: 'Vehicle Hire (No VAT Bill) – 10%' },
    { id: 'interest_bank_natural_person', name: 'Interest by Bank to Natural Person – 6%' },
    { id: 'interest_company', name: 'Interest by Company/Debenture – 15%' },
    { id: 'dividend', name: 'Dividend – 5%' },
    { id: 'royalty', name: 'Royalty – 15%' },
    { id: 'commission', name: 'Commission/Sales Bonus – 15%' },
    { id: 'windfall', name: 'Windfall Gains – 25%' },
];

const route = useRoute();
const router = useRouter();
const empStore = useEmployeeStore();
const deptStore = useDepartmentStore();
const desigStore = useDesignationStore();
const { departments } = storeToRefs(deptStore);
const { designations } = storeToRefs(desigStore);

const isEdit = computed(() => !!route.params.id);
const loading = ref(false);
const isSubmitting = ref(false);

const initial = {
    employee_code: '', first_name: '', last_name: '', email: '', phone: '',
    gender: '', marital_status: 'single', dob: '', join_date: '', employment_type: 'full_time', status: 'active',
    department_id: '', designation_id: '', bank_name: '', bank_account_no: '',
    pan: '', tds_category: '', address: '',
};
const form = reactive({ ...initial });

const validations = object({
    employee_code: string().required('Employee code is required.'),
    first_name: string().required('First name is required.'),
    last_name: string().required('Last name is required.'),
    join_date: string().required('Join date is required.'),
});
const { errors, validateField, validateForm } = useYup(form, validations);
const { loading: codeLoading, fetchNextCode } = useNextCode(form, 'employee_code', 'hr/employee/next-code', validateField);

onMounted(async () => {
    deptStore.getDepartments({ limit: 100 });
    desigStore.getDesignations({ limit: 100 });
    if (isEdit.value) {
        loading.value = true;
        try {
            const res = await apiAdmin(`hr/employee/${route.params.id}`);
            const d = res.data.data;
            Object.keys(form).forEach(k => {
                form[k] = d[k] ?? initial[k];
            });
            if (d.dob) form.dob = d.dob;
            if (d.join_date) form.join_date = d.join_date;
            form.employment_type = d.employment_type?.value ?? d.employment_type ?? 'full_time';
            form.status = d.status?.value ?? d.status ?? 'active';
        } finally { loading.value = false; }
    } else {
        await fetchNextCode();
    }
});

const submit = async () => {
    if (await validateForm(validations, form)) {
        isSubmitting.value = true;
        try {
            let res;
            if (isEdit.value) {
                res = await empStore.updateEmployee(route.params.id, form);
            } else {
                res = await empStore.storeEmployee(form);
            }
            toast(res.status, res.data.message);
            router.push({ name: 'admin.hr-employee-list' });
        } catch (e) { showErrors(e); }
        finally { isSubmitting.value = false; }
    }
};
</script>
