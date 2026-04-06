<template>
    <PageHeader title="Designations" subtitle="Manage job designations" @refresh="loadDesignations">
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
                                <th>Description</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="designations.loading" :colspan="5" />
                            <template v-else-if="designations.data.length">
                                <tr v-for="(d, i) in designations.data" :key="d.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ d.name }}</td>
                                    <td>{{ d.description || '—' }}</td>
                                    <td>
                                        <span :class="d.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ d.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td style="width:100px;">
                                        <button type="button" @click="editId = d.id" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" @click="deleteDesig(d.id)" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else>
                                <td colspan="5" class="text-center">No designations found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <CreateDesignation v-model:create-modal-opened="createModalOpened" />
    <EditDesignation v-model:edit-id="editId" />
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useDesignationStore } from '@/stores/admin/hr/designation.js';
import CreateDesignation from './Create.vue';
import EditDesignation from './Edit.vue';

const store = useDesignationStore();
const { designations } = storeToRefs(store);
const createModalOpened = ref(false);
const editId = ref('');

const loadDesignations = () => store.getDesignations();
onMounted(loadDesignations);

const deleteDesig = (id) => {
    Swal.fire({ title: 'Delete designation?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => {
            if (r.value) {
                try { const res = await store.deleteDesignation(id); toast(res.status, res.data.message); }
                catch (e) { showErrors(e); }
            }
        });
};
</script>
