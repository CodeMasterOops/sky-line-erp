<template>
    <PageHeader :title="isEdit ? 'Edit Employee' : 'Add Employee'" :subtitle="isEdit ? 'Update employee details' : 'Register a new employee'" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <VLoader v-if="loading" loader-type="progress" />
                <form v-else @submit.prevent="submit" class="row g-3">
                    <div class="col-12"><h6 class="text-muted fw-bold">Basic Information</h6></div>

                    <div class="col-md-3">
                        <VInput id="employee_code" v-model="form.employee_code" label="Employee Code *" @validate="validateField('employee_code')" :error="errors.employee_code" />
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
                        <label class="form-label">Gender</label>
                        <select v-model="form.gender" class="form-select">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <VInput id="dob" v-model="form.dob" label="Date of Birth" type="date" />
                    </div>
                    <div class="col-md-3">
                        <VInput id="join_date" v-model="form.join_date" label="Join Date *" type="date" @validate="validateField('join_date')" :error="errors.join_date" />
                    </div>

                    <div class="col-12 mt-2"><h6 class="text-muted fw-bold">Department & Role</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Department</label>
                        <select v-model="form.department_id" class="form-select">
                            <option value="">Select Department</option>
                            <option v-for="d in departments.data" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation</label>
                        <select v-model="form.designation_id" class="form-select">
                            <option value="">Select Designation</option>
                            <option v-for="d in designations.data" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Employment Type</label>
                        <select v-model="form.employment_type" class="form-select">
                            <option value="full_time">Full Time</option>
                            <option value="part_time">Part Time</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select v-model="form.status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                        </select>
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
                        <label class="form-label">TDS Category</label>
                        <select v-model="form.tds_category" class="form-select">
                            <option value="">None (no TDS deduction)</option>
                            <option value="service_vat_bill">Service Fee (VAT Bill) – 1.5%</option>
                            <option value="service_pan_bill">Service Fee (PAN Bill) – 15%</option>
                            <option value="service_vat_exempt_institution">Service Fee (VAT-Exempt Institution) – 1%</option>
                            <option value="contract_vat_registered">Contract Payment (VAT Registered) – 1.5%</option>
                            <option value="rent_property">Rent (House/Land/Property) – 10%</option>
                            <option value="rent_vehicle_vat">Vehicle Hire (VAT Bill) – 1.5%</option>
                            <option value="rent_vehicle_no_vat">Vehicle Hire (No VAT Bill) – 10%</option>
                            <option value="interest_bank_natural_person">Interest by Bank to Natural Person – 6%</option>
                            <option value="interest_company">Interest by Company/Debenture – 15%</option>
                            <option value="dividend">Dividend – 5%</option>
                            <option value="royalty">Royalty – 15%</option>
                            <option value="commission">Commission/Sales Bonus – 15%</option>
                            <option value="windfall">Windfall Gains – 25%</option>
                        </select>
                        <small class="text-muted">TDS will be auto-calculated on taxable salary components for this employee.</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" v-model="form.address" rows="2"></textarea>
                    </div>

                    <div class="col-12 text-end">
                        <router-link :to="{ name: 'admin.hr-employee-list' }" class="btn btn-danger me-2">Cancel</router-link>
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
import { apiAdmin } from '@/helpers/api.js';

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
    gender: '', dob: '', join_date: '', employment_type: 'full_time', status: 'active',
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
