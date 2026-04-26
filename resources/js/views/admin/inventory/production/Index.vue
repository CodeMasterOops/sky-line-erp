<template>
    <PageHeader title="Production Orders" subtitle="Manufacturing & assembly orders" @refresh="fetchOrders">
        <template #actions>
            <button v-can="'create_production_order'" type="button" class="btn btn-primary" @click="showCreate = true">
                <i class="ti ti-circle-plus me-2"></i> New Order
            </button>
        </template>
    </PageHeader>

    <!-- Status filter tabs -->
    <section class="section">
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="btn-group btn-group-sm">
                    <button v-for="s in statuses" :key="s.value"
                        :class="['btn', filter.status === s.value ? 'btn-primary' : 'btn-outline-secondary']"
                        @click="setStatus(s.value)">{{ s.label }}</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="orders" :loading="loading" row-key="id">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'product'">
                                {{ record.bom?.product_variant?.product?.name }}
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="statusBadge(record.status)" class="badge">{{ record.status }}</span>
                            </template>
                            <template v-if="column.key === 'qty'">
                                {{ record.produced_qty }}/{{ record.planned_qty }}
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="d-flex gap-2">
                                    <a href="#" @click.prevent="openDetail(record)"><i class="ti ti-eye"></i></a>
                                    <a v-if="record.status === 'draft'" href="#" @click.prevent="startOrder(record.id)"
                                       class="text-success" title="Start"><i class="ti ti-player-play"></i></a>
                                    <a v-if="record.status === 'in_progress'" href="#" @click.prevent="openComplete(record)"
                                       class="text-primary" title="Complete"><i class="ti ti-check"></i></a>
                                    <a v-if="!['completed','cancelled'].includes(record.status)" href="#"
                                       @click.prevent="cancelOrder(record.id)" class="text-danger" title="Cancel">
                                       <i class="ti ti-x"></i></a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create Order Modal -->
    <div v-if="showCreate" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">New Production Order</h5>
                    <button type="button" class="btn-close" @click="showCreate = false"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">BOM ID <span class="text-danger">*</span></label>
                            <input v-model="createForm.bom_id" type="number" class="form-control" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select v-model="createForm.warehouse_id" class="form-select">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Planned Qty <span class="text-danger">*</span></label>
                            <input v-model="createForm.planned_qty" type="number" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Planned Start</label>
                            <input v-model="createForm.planned_start" type="date" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Planned End</label>
                            <input v-model="createForm.planned_end" type="date" class="form-control" />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea v-model="createForm.remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="showCreate = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="createOrder">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Create Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Complete Order Modal -->
    <div v-if="completeModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white"><h5 class="modal-title">Complete Production: {{ selectedOrder?.order_no }}</h5>
                    <button type="button" class="btn-close btn-close-white" @click="completeModal = false"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Actual Produced Qty</label>
                        <input v-model="completeForm.produced_qty" type="number" class="form-control" />
                    </div>
                    <h6>Material Consumptions</h6>
                    <table class="table table-sm">
                        <thead><tr><th>Material</th><th>Required</th><th>Consumed</th><th>Batch</th></tr></thead>
                        <tbody>
                            <tr v-for="c in completeForm.consumptions" :key="c.id">
                                <td>{{ c.product_variant?.product?.name }}</td>
                                <td>{{ c.required_qty }}</td>
                                <td><input v-model="c.consumed_qty" type="number" class="form-control form-control-sm" /></td>
                                <td><input v-model="c.batch_id" type="number" class="form-control form-control-sm" placeholder="Batch ID" /></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="completeModal = false">Cancel</button>
                    <button class="btn btn-success" :disabled="saving" @click="completeOrder">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Mark Complete
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const orders = ref([]);
const warehouses = ref([]);
const showCreate = ref(false);
const completeModal = ref(false);
const selectedOrder = ref(null);

const filter = ref({ status: '' });
const statuses = [
    { value: '', label: 'All' }, { value: 'draft', label: 'Draft' },
    { value: 'in_progress', label: 'In Progress' }, { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

const createForm = ref({ bom_id: '', warehouse_id: '', planned_qty: '', planned_start: '', planned_end: '', remarks: '' });
const completeForm = ref({ produced_qty: '', consumptions: [] });

const columns = [
    { title: 'Order No', dataIndex: 'order_no', key: 'order_no' },
    { title: 'Product (BOM)', key: 'product' },
    { title: 'Warehouse', key: 'warehouse', dataIndex: ['warehouse', 'name'] },
    { title: 'Qty (Done/Plan)', key: 'qty' },
    { title: 'Status', key: 'status' },
    { title: 'Planned Start', dataIndex: 'planned_start', key: 'planned_start' },
    { title: 'Created By', key: 'created_by', dataIndex: ['create_user', 'name'] },
    { title: 'Action', key: 'action' },
];

onMounted(() => { fetchOrders(); fetchWarehouses(); });

async function fetchOrders() {
    loading.value = true;
    try {
        const params = filter.value.status ? `?status=${filter.value.status}` : '';
        const { data } = await apiAdmin(`production-order${params}`);
        orders.value = data.data;
    } finally { loading.value = false; }
}

async function fetchWarehouses() {
    const { data } = await apiAdmin('warehouse');
    warehouses.value = data.data;
}

function setStatus(s) { filter.value.status = s; fetchOrders(); }

async function createOrder() {
    saving.value = true;
    try {
        await apiAdmin('production-order', 'post', createForm.value);
        toast('Production order created');
        showCreate.value = false;
        fetchOrders();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function startOrder(id) {
    await apiAdmin(`production-order/${id}/start`, 'post');
    toast('Production started');
    fetchOrders();
}

async function openComplete(order) {
    const { data } = await apiAdmin(`production-order/${order.id}`);
    selectedOrder.value = data.data;
    completeForm.value = {
        produced_qty: data.data.planned_qty,
        consumptions: data.data.consumptions.map(c => ({ ...c, consumed_qty: c.required_qty, batch_id: '' })),
    };
    completeModal.value = true;
}

async function completeOrder() {
    saving.value = true;
    try {
        await apiAdmin(`production-order/${selectedOrder.value.id}/complete`, 'post', completeForm.value);
        toast('Production order completed');
        completeModal.value = false;
        fetchOrders();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function openDetail(order) {
    console.log(order); // TODO: Detail view
}

async function cancelOrder(id) {
    await apiAdmin(`production-order/${id}/cancel`, 'post');
    toast('Production order cancelled');
    fetchOrders();
}

function statusBadge(s) {
    return { draft: 'bg-secondary', in_progress: 'bg-warning text-dark', completed: 'bg-success', cancelled: 'bg-danger' }[s] ?? 'bg-info';
}
</script>
