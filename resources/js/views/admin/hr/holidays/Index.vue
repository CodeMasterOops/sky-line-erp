<template>
    <PageHeader title="Holidays" subtitle="Manage company holidays" @refresh="load">
        <template #actions>
            <button type="button" @click="createModalOpened = true" class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add Holiday
            </button>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <select v-model="filters.year" @change="load" class="form-select">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
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
                                <th>Name</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle">
                            <VLoader v-if="holidays.loading" :colspan="5" />
                            <template v-else-if="holidays.data.length">
                                <tr v-for="(h, i) in holidays.data" :key="h.id">
                                    <th>{{ i + 1 }}</th>
                                    <td>{{ h.name }}</td>
                                    <td>{{ h.date }}</td>
                                    <td>{{ h.description || '—' }}</td>
                                    <td style="width:100px;">
                                        <button type="button" @click="editItem = { ...h }" class="btn btn-sm btn-outline-primary"><i class="fa fa-edit"></i></button>
                                        <button type="button" @click="deleteHoliday(h.id)" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <tr v-else><td colspan="5" class="text-center">No holidays found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <VModal :show-modal="createModalOpened" @close-click="createModalOpened = false" title="Add Holiday">
        <template #modal-body>
            <form @submit.prevent="storeHoliday" class="row g-3">
                <div class="col-md-6"><VInput v-model="cForm.name" label="Name *" /></div>
                <div class="col-md-6"><VInput v-model="cForm.date" label="Date *" type="date" /></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" v-model="cForm.description" rows="2"></textarea></div>
                <div class="col-12 text-end">
                    <button type="button" @click="createModalOpened = false" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="cSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <VModal :show-modal="!!editItem" @close-click="editItem = null" title="Edit Holiday">
        <template #modal-body>
            <form v-if="editItem" @submit.prevent="updateHoliday" class="row g-3">
                <div class="col-md-6"><VInput v-model="editItem.name" label="Name *" /></div>
                <div class="col-md-6"><VInput v-model="editItem.date" label="Date *" type="date" /></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" v-model="editItem.description" rows="2"></textarea></div>
                <div class="col-12 text-end">
                    <button type="button" @click="editItem = null" class="btn btn-danger me-2">Close</button>
                    <VButton :loading="eSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { storeToRefs } from 'pinia';
import { useLeaveStore } from '@/stores/admin/hr/leave.js';

const leaveStore = useLeaveStore();
const { holidays } = storeToRefs(leaveStore);
const now = new Date();
const years = Array.from({ length: 5 }, (_, i) => now.getFullYear() - i);
const filters = reactive({ year: now.getFullYear() });
const createModalOpened = ref(false);
const editItem = ref(null);
const cSubmitting = ref(false);
const eSubmitting = ref(false);
const cForm = reactive({ name: '', date: '', description: '' });

const load = () => leaveStore.getHolidays(filters);
onMounted(load);

const storeHoliday = async () => {
    cSubmitting.value = true;
    try { const res = await leaveStore.storeHoliday(cForm); toast(res.status, res.data.message); createModalOpened.value = false; Object.assign(cForm, { name: '', date: '', description: '' }); }
    catch (e) { showErrors(e); } finally { cSubmitting.value = false; }
};

const updateHoliday = async () => {
    eSubmitting.value = true;
    try { const res = await leaveStore.updateHoliday(editItem.value.id, editItem.value); toast(res.status, res.data.message); editItem.value = null; }
    catch (e) { showErrors(e); } finally { eSubmitting.value = false; }
};

const deleteHoliday = (id) => {
    Swal.fire({ title: 'Delete holiday?', icon: 'warning', showCancelButton: true, confirmButtonColor: 'red', confirmButtonText: 'Yes' })
        .then(async (r) => { if (r.value) { try { const res = await leaveStore.deleteHoliday(id); toast(res.status, res.data.message); } catch (e) { showErrors(e); } } });
};
</script>
