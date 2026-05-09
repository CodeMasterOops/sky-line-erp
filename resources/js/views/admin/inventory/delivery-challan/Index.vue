<template>
    <PageHeader title="Delivery Challans" subtitle="Manage goods delivery notes">
        <template #actions>
            <button type="button" class="btn btn-primary d-flex align-items-center" @click="createModalOpened = true">
                <i class="ti ti-circle-plus me-2"></i> Create Challan
            </button>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-body">
            <div class="d-flex gap-2 mb-3">
                <input type="text" class="form-control w-auto" v-model="search" placeholder="Search Challan No..." @input="loadChallans" />
                <select class="form-select w-auto" v-model="statusFilter" @change="loadChallans">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="approved">Approved</option>
                </select>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Challan No</th>
                            <th>Customer</th>
                            <th>Warehouse</th>
                            <th>Date</th>
                            <th>Receiver</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td>
                        </tr>
                        <tr v-else-if="!challans.length">
                            <td colspan="7" class="text-center text-muted py-4">No delivery challans found.</td>
                        </tr>
                        <tr v-for="challan in challans" :key="challan.id">
                            <td>
                                <router-link :to="{ name: 'admin.delivery-challan-view', params: { id: challan.id } }" class="text-primary fw-semibold">
                                    {{ challan.challan_no }}
                                </router-link>
                            </td>
                            <td>{{ challan.party?.name || '-' }}</td>
                            <td>{{ challan.warehouse?.name || '-' }}</td>
                            <td>{{ formatDate(challan.challan_date) }}</td>
                            <td>{{ challan.receiver_name || '-' }}</td>
                            <td>
                                <span class="badge" :class="challan.status === 'approved' ? 'bg-success' : 'bg-warning text-dark'">
                                    {{ challan.status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <router-link :to="{ name: 'admin.delivery-challan-view', params: { id: challan.id } }" class="btn btn-sm btn-outline-primary">
                                        <i class="ti ti-eye"></i>
                                    </router-link>
                                    <button v-if="challan.status === 'draft'" class="btn btn-sm btn-success" @click="approve(challan.id)">Approve</button>
                                    <button v-if="challan.status === 'draft'" class="btn btn-sm btn-danger" @click="deleteChallan(challan.id)">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <CreateDeliveryChallan v-model:create-modal-opened="createModalOpened" @saved="loadChallans" />
</template>

<script setup>
import {ref, onMounted} from 'vue';
import {apiAdmin} from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import {toast} from '@/helpers/toast.js';
import {formatDate} from '@/helpers/helper.js';
import Swal from 'sweetalert2';
import CreateDeliveryChallan from './Create.vue';

const challans = ref([]);
const loading = ref(false);
const search = ref('');
const statusFilter = ref('');
const createModalOpened = ref(false);

const loadChallans = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('delivery-challan', 'get', { search: search.value, status: statusFilter.value });
        challans.value = res.data.data || [];
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const approve = async (id) => {
    try {
        const res = await apiAdmin(`delivery-challan/${id}/approve`, 'post');
        toast('success', res.data.message);
        await loadChallans();
    } catch (e) { showErrors(e); }
};

const deleteChallan = async (id) => {
    const result = await Swal.fire({ title: 'Delete Challan?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' });
    if (!result.value) return;
    try {
        await apiAdmin(`delivery-challan/${id}`, 'delete');
        toast('success', 'Deleted.');
        await loadChallans();
    } catch (e) { showErrors(e); }
};

onMounted(() => { loadChallans(); });
</script>
