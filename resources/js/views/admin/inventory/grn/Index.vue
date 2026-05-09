<template>
    <PageHeader title="Goods Received Notes" subtitle="Manage stock receipts from suppliers">
        <template #actions>
            <button type="button" class="btn btn-primary d-flex align-items-center" @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i> Create GRN
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-body">
            <div class="d-flex gap-2 mb-3">
                <input type="text" class="form-control w-auto" v-model="search" placeholder="Search GRN No..." @input="loadGrns" />
                <select class="form-select w-auto" v-model="statusFilter" @change="loadGrns">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>GRN No</th>
                            <th>Supplier</th>
                            <th>Warehouse</th>
                            <th>Received Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="6" class="text-center py-4">
                                <span class="spinner-border spinner-border-sm"></span>
                            </td>
                        </tr>
                        <tr v-else-if="!grns.length">
                            <td colspan="6" class="text-center text-muted py-4">No GRNs found.</td>
                        </tr>
                        <tr v-for="grn in grns" :key="grn.id">
                            <td>
                                <router-link :to="{ name: 'admin.grn-view', params: { id: grn.id } }" class="text-primary fw-semibold">
                                    {{ grn.grn_no }}
                                </router-link>
                            </td>
                            <td>{{ grn.party?.name || '-' }}</td>
                            <td>{{ grn.warehouse?.name || '-' }}</td>
                            <td>{{ formatDate(grn.received_date) }}</td>
                            <td>
                                <span class="badge" :class="grn.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">
                                    {{ grn.status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <router-link :to="{ name: 'admin.grn-view', params: { id: grn.id } }" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye"></i>
                                    </router-link>
                                    <button
                                        v-if="grn.status === 'draft'"
                                        class="btn btn-sm btn-success"
                                        @click="approve(grn.id)"
                                    >Approve</button>
                                    <button
                                        v-if="grn.status === 'draft'"
                                        class="btn btn-sm btn-danger"
                                        @click="deleteGrn(grn.id)"
                                    ><i class="ti ti-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1" @click="page = pagination.current_page - 1; loadGrns()">Prev</button>
                    <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page" @click="page = pagination.current_page + 1; loadGrns()">Next</button>
                </div>
            </div>
        </div>
    </div>

    <CreateGrn v-model:create-modal-opened="createModalOpened" @saved="loadGrns" />
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import Swal from 'sweetalert2';
import CreateGrn from './Create.vue';

const grns = ref([]);
const loading = ref(false);
const search = ref('');
const statusFilter = ref('');
const page = ref(1);
const pagination = ref(null);
const createModalOpened = ref(false);

const loadGrns = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('grn', 'get', { search: search.value, status: statusFilter.value, page: page.value });
        grns.value = res.data.data || [];
        pagination.value = res.data.meta;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const approve = async (id) => {
    try {
        const res = await apiAdmin(`grn/${id}/approve`, 'post');
        toast(res.status, res.data.message);
        await loadGrns();
    } catch (e) { showErrors(e); }
};

const deleteGrn = async (id) => {
    const result = await Swal.fire({
        title: 'Delete GRN?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    });
    if (!result.value) return;
    try {
        const res = await apiAdmin(`grn/${id}`, 'delete');
        toast(res.status, 'GRN deleted.');
        await loadGrns();
    } catch (e) { showErrors(e); }
};

onMounted(() => { loadGrns(); });
</script>
