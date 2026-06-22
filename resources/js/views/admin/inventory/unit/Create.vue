<template>
    <VModal
        :show-modal="!!createModalOpened"
        @close-click="createModalOpened=false"
        title="Add New Unit">
        <template #modal-body>
            <form @submit.prevent="storeUnit" class="row g-3">
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
                    <label class="form-label" for="code">
                        Code
                        <VRequiredMark />
                    </label>
                    <div class="input-group">
                        <input
                            id="code"
                            v-model="form.code"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': errors.code }"
                            autocomplete="off"
                            @blur="validateField('code')"
                        />
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="codeLoading"
                            @click="fetchNextCode">
                            Generate
                        </button>
                    </div>
                    <div v-if="errors.code" class="invalid-feedback d-block">
                        {{ errors.code }}
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button @click="closeCreateModal" class="btn btn-cancel" type="button">
                        Cancel
                    </button>
                    <VButton :loading="isSubmitting"/>
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import {reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {object, string} from "yup";
import {useYup} from "@/helpers/yup";
import {useNextCode} from '@/helpers/useNextCode.js';
import {useUnitStore} from '@/stores/admin/inventory/unit.js';

const unitStore = useUnitStore();

const createModalOpened = defineModel('createModalOpened');
const emit = defineEmits(['created']);

watch(createModalOpened, async (open) => {
    if (open) {
        await fetchNextCode();
    }
});

const initialState = {
    name: '',
    code: '',
};

const form = reactive({...initialState});
const isSubmitting = ref(false);

const validations = object({
    name: string().required('Name is required.'),
    code: string().required('Code is required.')
});

const {errors, validateField, validateForm} = useYup(form, validations);
const {loading: codeLoading, fetchNextCode} = useNextCode(form, 'code', 'unit/next-code', validateField);

const storeUnit = async () => {
    let validated = await validateForm(validations, form)
    if (validated) {
        isSubmitting.value = true;
        try {
            let res = await unitStore.storeUnit(form);
            toast(res.status, res.data.message);
            emit('created', res.data.data);
            closeCreateModal();
        } catch (e) {
            showErrors(e);
        } finally {
            isSubmitting.value = false;
        }
    }
}

const closeCreateModal = () => {
    resetForm();
    createModalOpened.value = false;
}

function resetForm() {
    Object.assign(form, {...initialState});
    errors.value = {};
}

</script>
