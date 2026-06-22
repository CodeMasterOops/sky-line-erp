<template>
    <VModal
        :show-modal="!!createModalOpened"
        modal-class="extra-medium-modal"
        :title="`Add ${formTitle()}`"
        @close-click="closeCreateModal"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submitCreate">
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
                    <button class="btn btn-cancel" type="button" @click="closeCreateModal">
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

const createModalOpened = defineModel('createModalOpened');
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
    saveRecord,
    locationStore,
} = useLocationForm(activeTab, () => emit('saved'));

watch(createModalOpened, async (opened) => {
    if (!opened) {
        return;
    }
    resetForm();
    await locationStore.loadProvinces();
});

async function submitCreate() {
    const saved = await saveRecord();
    if (saved) {
        closeCreateModal();
    }
}

function closeCreateModal() {
    resetForm();
    createModalOpened.value = false;
}
</script>
