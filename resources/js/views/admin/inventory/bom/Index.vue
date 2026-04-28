<template>
    <PageHeader title="Bill of Materials" subtitle="Define material lists for manufactured products" @refresh="fetchBoms">
        <template #actions>
            <button v-can="'create_bom'" type="button" class="btn btn-primary" @click="openCreate">
                <i class="ti ti-circle-plus me-2"></i> New BOM
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table :columns="columns" :data-source="boms" :loading="loading" row-key="id">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'product'">
                                {{ record.product_variant?.product?.name }}
                            </template>
                            <template v-if="column.key === 'is_default'">
                                <span v-if="record.is_default" class="badge bg-primary">Default</span>
                            </template>
                            <template v-if="column.key === 'is_active'">
                                <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                    {{ record.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </template>
                            <template v-if="column.key === 'items_count'">
                                {{ record.items?.length ?? 0 }} materials
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="d-flex gap-2">
                                    <a href="#" @click.prevent="viewBom(record)"><i class="ti ti-eye"></i></a>
                                    <a href="#" @click.prevent="editBom(record)"><i class="ti ti-edit"></i></a>
                                    <a href="#" @click.prevent="deleteBom(record.id)" class="text-danger"><i class="ti ti-trash"></i></a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>

    <!-- Create/Edit BOM Modal -->
    <div v-if="formModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ editId ? 'Edit BOM' : 'Create BOM' }}</h5>
                    <button type="button" class="btn-close" @click="formModal = false"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Product Variant ID <span class="text-danger">*</span></label>
                            <input v-model="form.product_variant_id" type="number" class="form-control" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">BOM Name <span class="text-danger">*</span></label>
                            <input v-model="form.name" class="form-control" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Output Qty</label>
                            <input v-model="form.output_qty" type="number" class="form-control" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Version</label>
                            <input v-model="form.version" class="form-control" />
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-3">
                            <div class="form-check">
                                <input v-model="form.is_default" type="checkbox" class="form-check-input" id="isDefault" />
                                <label class="form-check-label" for="isDefault">Default BOM</label>
                            </div>
                            <div class="form-check">
                                <input v-model="form.is_active" type="checkbox" class="form-check-input" id="isActive" />
                                <label class="form-check-label" for="isActive">Active</label>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-2 border-bottom pb-1">Raw Materials / Components</h6>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Variant ID</th>
                                <th>Quantity</th>
                                <th>Type</th>
                                <th>Wastage %</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, i) in form.items" :key="i">
                                <td><input v-model="item.product_variant_id" type="number" class="form-control form-control-sm" /></td>
                                <td><input v-model="item.quantity" type="number" class="form-control form-control-sm" step="0.0001" /></td>
                                <td>
                                    <select v-model="item.item_type" class="form-select form-select-sm">
                                        <option value="material">Material</option>
                                        <option value="labour">Labour</option>
                                        <option value="overhead">Overhead</option>
                                    </select>
                                </td>
                                <td><input v-model="item.wastage_pct" type="number" class="form-control form-control-sm" min="0" max="100" /></td>
                                <td><button type="button" class="btn btn-sm btn-outline-danger" @click="form.items.splice(i,1)"><i class="ti ti-x"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addItem">
                        <i class="ti ti-plus me-1"></i> Add Material
                    </button>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" @click="formModal = false">Cancel</button>
                    <button class="btn btn-primary" :disabled="saving" @click="saveBom">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        Save BOM
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View BOM -->
    <div v-if="viewModal" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ viewData?.name }}</h5>
                    <button type="button" class="btn-close" @click="viewModal = false"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong>Product:</strong> {{ viewData?.product_variant?.product?.name }}</p>
                    <p class="mb-3"><strong>Output:</strong> {{ viewData?.output_qty }} {{ viewData?.output_unit?.name }}</p>
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Material</th><th>Qty</th><th>Type</th><th>Wastage</th></tr></thead>
                        <tbody>
                            <tr v-for="item in viewData?.items" :key="item.id">
                                <td>{{ item.product_variant?.product?.name }}</td>
                                <td>{{ item.quantity }} {{ item.unit?.name }}</td>
                                <td>{{ item.item_type }}</td>
                                <td>{{ item.wastage_pct }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { apiAdmin } from '@/helpers/api';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

const loading = ref(false);
const saving = ref(false);
const boms = ref([]);
const formModal = ref(false);
const viewModal = ref(false);
const viewData = ref(null);
const editId = ref(null);

const defaultItem = () => ({ product_variant_id: '', quantity: 1, item_type: 'material', wastage_pct: 0 });

const form = ref({
    product_variant_id: '', name: '', version: '1.0', output_qty: 1,
    is_active: true, is_default: false, items: [defaultItem()],
});

const columns = [
    { title: 'Product', key: 'product' },
    { title: 'BOM Name', dataIndex: 'name', key: 'name' },
    { title: 'Version', dataIndex: 'version', key: 'version' },
    { title: 'Output Qty', dataIndex: 'output_qty', key: 'output_qty' },
    { title: 'Materials', key: 'items_count' },
    { title: 'Default', key: 'is_default' },
    { title: 'Status', key: 'is_active' },
    { title: 'Action', key: 'action' },
];

onMounted(fetchBoms);

async function fetchBoms() {
    loading.value = true;
    try {
        const { data } = await apiAdmin('bom');
        boms.value = data.data;
    } finally { loading.value = false; }
}

function openCreate() {
    editId.value = null;
    form.value = { product_variant_id: '', name: '', version: '1.0', output_qty: 1,
        is_active: true, is_default: false, items: [defaultItem()] };
    formModal.value = true;
}

function editBom(bom) {
    editId.value = bom.id;
    form.value = {
        product_variant_id: bom.product_variant_id,
        name: bom.name, version: bom.version, output_qty: bom.output_qty,
        is_active: bom.is_active, is_default: bom.is_default,
        items: bom.items?.map(i => ({ product_variant_id: i.product_variant_id, quantity: i.quantity, item_type: i.item_type, wastage_pct: i.wastage_pct })) ?? [],
    };
    formModal.value = true;
}

async function viewBom(bom) {
    const { data } = await apiAdmin(`bom/${bom.id}`);
    viewData.value = data.data;
    viewModal.value = true;
}

function addItem() {
    form.value.items.push(defaultItem());
}

async function saveBom() {
    saving.value = true;
    try {
        if (editId.value) {
            await apiAdmin(`bom/${editId.value}`, 'put', form.value);
        } else {
            await apiAdmin('bom', 'post', form.value);
        }
        toast('BOM saved successfully');
        formModal.value = false;
        fetchBoms();
    } catch (e) { showErrors(e); }
    finally { saving.value = false; }
}

async function deleteBom(id) {
    const result = await Swal.fire({ title: 'Delete BOM?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Delete' });
    if (!result.isConfirmed) return;
    try {
        await apiAdmin(`bom/${id}`, 'delete');
        toast('BOM deleted');
        fetchBoms();
    } catch (e) { showErrors(e); }
}
</script>
