<template>
    <VModal
        :show-modal="!!editRecord"
        modal-class="extra-medium-modal"
        :title="`Edit ${formTitle()}`"
        @close-click="closeEditModal"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submitEdit">
                <FormFields
                    :active-tab="activeTab"
                    :form="form"
                    :pal-form="palForm"
                    :ward-form="wardForm"
                    :provinces="provinces"
                    :palika-districts="palikaDistricts"
                    :ward-districts="wardDistricts"
                    :ward-palikas="wardPalikas"
                />
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="closeEditModal">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { toRef, watch } from 'vue';
import FormFields from './FormFields.vue';
import { useLocationForm } from './useLocationForm';

const editRecord = defineModel('editRecord');
const props = defineProps({
    activeTab: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['saved']);

const activeTab = toRef(props, 'activeTab');

const {
    provinces,
    form,
    palForm,
    wardForm,
    palikaDistricts,
    wardDistricts,
    wardPalikas,
    isSubmitting,
    resetForm,
    formTitle,
    populateForEdit,
    saveRecord,
    locationStore,
} = useLocationForm(activeTab, () => emit('saved'));

watch(editRecord, async (record) => {
    if (!record) {
        return;
    }
    resetForm();
    await locationStore.loadProvinces();
    await populateForEdit(record);
});

async function submitEdit() {
    if (!editRecord.value?.id) {
        return;
    }
    const saved = await saveRecord(editRecord.value.id);
    if (saved) {
        closeEditModal();
    }
}

function closeEditModal() {
    resetForm();
    editRecord.value = null;
}
</script>
