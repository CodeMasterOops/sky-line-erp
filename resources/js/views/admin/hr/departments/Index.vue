<template>
    <PageHeader title="Departments" subtitle="Manage company departments" @refresh="loadDepartments">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>SN</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="departments.loading" :colspan="5" />
                            <template v-else-if="departments.data.length">
                                <tr v-for="(dept, index) in departments.data" :key="dept.id">
                                    <th>{{ index + 1 }}</th>
                                    <td>{{ dept.name }}</td>
                                    <td>{{ dept.code || '—' }}</td>
                                    <td>
                                        <span :class="dept.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ dept.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td style="width:100px;">
                                        <button type="button" @click="editId = dept.id" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" @click="deleteDept(dept.id)" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="5" class="text-center">No departments found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <CreateDepartment v-model:create-modal-opened="createModalOpened" />
    <EditDepartment v-model:edit-id="editId" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useDepartmentStore } from '@/stores/admin/hr/department.js';
import CreateDepartment from './Create.vue';
import EditDepartment from './Edit.vue';

const departmentStore = useDepartmentStore();
const { departments } = storeToRefs(departmentStore);
const createModalOpened = ref(false);
const editId = ref('');

const loadDepartments = () => departmentStore.getDepartments();

onMounted(loadDepartments);

const deleteDept = (id) => {
    Swal.fire({
        title: 'Are you sure?',
        text: 'This department will be deleted permanently.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await departmentStore.deleteDepartment(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
