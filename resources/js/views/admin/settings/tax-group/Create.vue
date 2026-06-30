<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="closeModal"
        title="Add New Tax Group"
        size="lg">
        <template #modal-body>
            <form @submit.prevent="store" class="row g-3">
                <div class="col-md-8">
                    <VInput
                        id="tg-name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-4 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="tg-active" v-model="form.is_active" />
                        <label class="form-check-label" for="tg-active">Active</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" rows="2" v-model="form.description" placeholder="Optional description"></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Tax Members <span class="text-danger">*</span></label>
                    <div v-if="errors.taxes" class="text-danger small mb-2">{{ errors.taxes }}</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th>Tax</th>
                                    <th style="width:110px">Sequence</th>
                                    <th style="width:50px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(member, i) in form.taxes" :key="i">
                                    <td>
                                        <VMultiselect
                                            :id="`tax-${i}`"
                                            v-model="member.tax_id"
                                            placeholder="-- Select Tax --"
                                            :options="taxSelectOptions"
                                            size="sm"
                                        />
                                    </td>
                                    <td>
                                        <input type="number" class="form-control form-control-sm" v-model.number="member.sequence" min="1" />
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger" @click="removeMember(i)" :disabled="form.taxes.length === 1">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="addMember">
                        <i class="ti ti-plus me-1"></i> Add Tax
                    </button>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-cancel" @click="closeModal">Cancel</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch, computed} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string, array} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useTaxStore} from '@/stores/admin/settings/tax.js';

const emit = defineEmits(['created']);
const createModalOpened = defineModel('createModalOpened');

const taxStore = useTaxStore();
const {taxes} = storeToRefs(taxStore);

const taxSelectOptions = computed(() =>
    taxes.value.data.map(t => ({
        id: t.id,
        name: t.rate != null ? `${t.name} (${t.rate}%)` : t.name,
    }))
);

watch(() => createModalOpened.value, (opened) => {
    if (opened) taxStore.getTaxes();
});

const initialState = () => ({
    name: '',
    description: '',
    is_active: true,
    taxes: [{tax_id: '', sequence: 1}],
});

const form = reactive(initialState());
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Name is required.'),
    taxes: array().min(1, 'At least one tax member is required.'),
});

const {errors, validateField, validateForm} = useYup(form, validations);

const addMember = () => form.taxes.push({tax_id: '', sequence: form.taxes.length + 1});
const removeMember = (i) => form.taxes.splice(i, 1);

const store = async () => {
    const validated = await validateForm(validations, form);
    if (!validated) return;

    const unselected = form.taxes.some(m => !m.tax_id);
    if (unselected) {
        errors.value.taxes = 'All tax member rows must have a tax selected.';
        return;
    }

    isSubmitting.value = true;
    try {
        const res = await taxStore.storeTaxGroup({
            name: form.name,
            description: form.description || null,
            is_active: form.is_active,
            taxes: form.taxes.map((m, i) => ({tax_id: m.tax_id, sequence: m.sequence || i + 1})),
        });
        toast(res.status, res.data.message);
        emit('created');
        closeModal();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const closeModal = () => {
    Object.assign(form, initialState());
    errors.value = {};
    createModalOpened.value = false;
};
</script>
