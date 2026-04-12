<template>
    <VModal
        :show-modal="!!edit_unit_id"
        @close-click="closeEditModal"
        title="Update Unit">
        <template #modal-body>
            <VLoader v-if="unit.loading" loader-type="progress"/>
            <form @submit.prevent="updateUnit(unit.data.id)" class="row g-3">
                <div class="col-md-6">
                    <VInput
                        id="name"
                        v-model="form.name"
                        label="Name"
                        @validate="validateField('name')"
                        :error="errors.name"
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="code"
                        v-model="form.code"
                        label="Code"
                        @validate="validateField('code')"
                        :error="errors.code"
                    />
                </div>
                <div class="col-12 text-end">
                    <button @click="closeEditModal" class="btn btn-danger me-2" type="button">
                        Close
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from 'vue';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {object, string} from 'yup';
import {useYup} from '@/helpers/yup';
import {storeToRefs} from 'pinia';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';

const unitStore = useUnitStore();

const edit_unit_id = defineModel('unit_id');

const {unit} = storeToRefs(unitStore);

const initialState = {
    name: '',
    code: ''
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

watch(() => edit_unit_id.value, async (id) => {
    if (id) {
        await unitStore.getUnit(id);
        Object.keys(form).forEach(key => {
            form[key] = unit.value.data[key] || '';
        });
    }
});

const validations = object({
    name: string().required('Name is required.'),
    code: string().required('Code is required.')
});

const {errors, validateField, validateForm} = useYup(form, validations);

const updateUnit = async (id) => {
    let validated = await validateForm(validations, form);
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await unitStore.updateUnit(id, form);
            toast(res.status, res.data.message);
            closeEditModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
};

const closeEditModal = () => {
    resetForm();
    edit_unit_id.value = '';
};

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
