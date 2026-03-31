<template>
    <PageHeader title="Employees" subtitle="Manage your workforce" @refresh="loadEmployees">
        <template #actions>
            <router-link :to="{ name: 'admin.hr-employee-create' }" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Employee
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input v-model="filters.search" @input="loadEmployees" type="text" class="form-control" placeholder="Search by name, code, email..." />
                    </div>
                    <div class="col-md-3">
                        <select v-model="filters.status" @change="loadEmployees" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="employees.loading" :colspan="8" />
                            <template v-else-if="employees.data.length">
                                <tr v-for="(emp, i) in employees.data" :key="emp.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ emp.employee_code }}</td>
                                    <td>{{ emp.full_name }}</td>
                                    <td>{{ emp.department?.name || '—' }}</td>
                                    <td>{{ emp.designation?.name || '—' }}</td>
                                    <td>{{ emp.employment_type_label }}</td>
                                    <td>
                                        <span :class="statusBadge(emp.status)">{{ emp.status_label }}</span>
                                    </td>
                                    <td style="width:100px;">
                                        <router-link :to="{ name: 'admin.hr-employee-edit', params: { id: emp.id } }" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-edit"></i>
                                        </router-link>
                                        <button type="button" @click="deleteEmp(emp.id)" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="8" class="text-center">No employees found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useEmployeeStore } from '@/stores/admin/hr/employee.js';

const store = useEmployeeStore();
const { employees } = storeToRefs(store);
const filters = reactive({ search: '', status: '' });

const loadEmployees = () => store.getEmployees({ ...filters });
onMounted(loadEmployees);

const statusBadge = (status) => ({
    active: 'badge bg-success',
    inactive: 'badge bg-secondary',
    terminated: 'badge bg-danger',
}[status?.value ?? status] ?? 'badge bg-secondary');

const deleteEmp = (id) => {
    Swal.fire({ title: 'Delete employee?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => {
            if (r.value) {
                try { const res = await store.deleteEmployee(id); toast(res.status, res.data.message); }
                catch (e) { showErrors(e); }
            }
        });
};
</script>
