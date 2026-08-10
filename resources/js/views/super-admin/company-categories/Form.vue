<template>
    <VModal
        :show-modal="show"
        modal-class="medium-modal"
        :title="isEdit ? 'Edit Category' : 'Add Category'"
        @close-click="close"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submit">
                <div class="col-md-7">
                    <VInput
                        id="category_name"
                        v-model="form.name"
                        label="Category Name"
                        placeholder="Gym / Fitness"
                        required
                        :error="errors.name"
                        @validate="validateField('name')"
                    />
                </div>
                <div class="col-md-5">
                    <VInput
                        id="category_icon"
                        v-model="form.icon"
                        label="Icon"
                        placeholder="ti ti-barbell"
                    />
                </div>
                <div class="col-12">
                    <VTextarea
                        id="category_description"
                        v-model="form.description"
                        label="Description"
                        placeholder="Membership-driven fitness centres."
                    />
                </div>
                <div class="col-md-6">
                    <VInput
                        id="category_sort_order"
                        v-model="form.sort_order"
                        input-type="number"
                        label="Sort Order"
                    />
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="d-flex gap-4 pb-2">
                        <div class="form-check form-switch">
                            <input id="category_is_active" v-model="form.is_active" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="category_is_active">Active</label>
                        </div>
                        <div class="form-check form-switch">
                            <input id="category_is_default" v-model="form.is_default" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="category_is_default">Default</label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Default Modules</label>
                    <ModulePicker
                        v-model="form.modules"
                        hint="New companies in this industry start with these modules. Changing them never affects companies that already exist."
                    />
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="close">Cancel</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { object, string } from 'yup';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import { useYup } from '@/helpers/yup';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VButton from '@/components/base/VButton.vue';
import ModulePicker from './ModulePicker.vue';
import { useModuleStore } from '@/stores/super-admin/module';

const props = defineProps({
    category: { type: Object, default: null },
});

const show = defineModel('show', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const moduleStore = useModuleStore();

const initialState = {
    name: '',
    description: '',
    icon: '',
    sort_order: 0,
    is_active: true,
    is_default: false,
    modules: [],
};

const form = reactive({ ...initialState });
const isSubmitting = ref(false);

const isEdit = computed(() => !!props.category?.id);

const validations = object({
    name: string().required('Category name is required.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

watch(
    () => [show.value, props.category],
    () => {
        if (!show.value) {
            return;
        }

        moduleStore.getCatalogue();

        Object.assign(form, {
            ...initialState,
            ...(props.category ?? {}),
            modules: [...(props.category?.modules ?? [])],
        });
    },
    { immediate: true },
);

const submit = async () => {
    if (!(await validateForm(validations, form))) {
        return;
    }

    isSubmitting.value = true;

    try {
        const payload = {
            name: form.name,
            description: form.description,
            icon: form.icon,
            sort_order: Number(form.sort_order) || 0,
            is_active: form.is_active,
            is_default: form.is_default,
            modules: form.modules,
        };

        const res = isEdit.value
            ? await moduleStore.updateCategory(props.category.id, payload)
            : await moduleStore.storeCategory(payload);

        toast(res.status, res.data.message);
        emit('saved');
        close();
    } catch (e) {
        showErrors(e);
    } finally {
        isSubmitting.value = false;
    }
};

const close = () => {
    Object.assign(form, { ...initialState, modules: [] });
    errors.value = {};
    show.value = false;
};
</script>
