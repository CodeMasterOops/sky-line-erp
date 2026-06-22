<template>
    <VModal
        :show-modal="!!show"
        modal-class="large-modal"
        :title="isEdit ? 'Edit task' : 'Add task'"
        @close-click="close"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submit">
                <div class="col-md-12">
                    <VInput
                        id="task-title"
                        v-model="form.title"
                        label="Title"
                        required
                        :error="errors.title"
                        @validate="validateField('title')"
                    />
                </div>
                <div class="col-md-12">
                    <VTextarea
                        id="task-description"
                        v-model="form.description"
                        label="Description"
                        :error="errors.description"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="task-priority"
                        v-model="form.priority"
                        label="Priority"
                        :options="taskPriorities"
                        :error="errors.priority"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="task-status"
                        v-model="form.status"
                        label="Status"
                        :options="taskStatuses"
                        :error="errors.status"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="task-assignee"
                        v-model="form.assigned_to_user_id"
                        label="Assign to"
                        :options="users.data"
                        :error="errors.assigned_to_user_id"
                    />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="task-party"
                        v-model="form.party_id"
                        label="Linked contact"
                        :options="partyOptions"
                        :error="errors.party_id"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="task-due">Due date</label>
                    <VDatepicker id="task-due" v-model="form.due_date" placeholder="Due date" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="task-reminder">Reminder at</label>
                    <VDateTimePicker id="task-reminder" v-model="form.reminder_at" placeholder="Reminder" />
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
import { storeToRefs } from 'pinia';
import { object, string } from 'yup';
import { toast } from '@/helpers/toast';
import { useYup } from '@/helpers/yup';
import showErrors from '@/helpers/showErrors';
import { apiAdmin } from '@/helpers/api.js';
import VModal from '@/components/base/VModal.vue';
import VInput from '@/components/base/VInput.vue';
import VSelect from '@/components/base/VSelect.vue';
import VButton from '@/components/base/VButton.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import VDateTimePicker from '@/components/base/VDateTimePicker.vue';
import { useEnumStore } from '@/stores/admin/enum.js';
import { useUserStore } from '@/stores/admin/user-management/user.js';
import { useCrmTaskStore } from '@/stores/admin/crm/tasks.js';

const props = defineProps({
    task: { type: Object, default: null },
});

const show = defineModel('show');
const emit = defineEmits(['saved']);

const taskStore = useCrmTaskStore();
const enumStore = useEnumStore();
const userStore = useUserStore();

const { taskStatuses, taskPriorities } = storeToRefs(enumStore);
const { users } = storeToRefs(userStore);

const partyOptions = ref([]);
const isSubmitting = ref(false);

const isEdit = computed(() => !!props.task?.id);

const emptyForm = () => ({
    title: '',
    description: '',
    priority: 'medium',
    status: 'open',
    assigned_to_user_id: '',
    party_id: '',
    due_date: '',
    reminder_at: '',
});

const form = reactive(emptyForm());

const validations = object({
    title: string().required('Title is required.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const fetchParties = async () => {
    if (partyOptions.value.length) return;
    try {
        const res = await apiAdmin('party?limit=500');
        partyOptions.value = res.data.data;
    } catch (e) {
        showErrors(e);
    }
};

watch(show, (opened) => {
    if (!opened) return;
    enumStore.getTaskStatuses();
    enumStore.getTaskPriorities();
    if (!users.value.data.length) userStore.getUsers();
    fetchParties();

    if (isEdit.value) {
        Object.assign(form, {
            ...emptyForm(),
            title: props.task.title ?? '',
            description: props.task.description ?? '',
            priority: props.task.priority ?? 'medium',
            status: props.task.status ?? 'open',
            assigned_to_user_id: props.task.assigned_to_user_id ?? '',
            party_id: props.task.party_id ?? '',
            due_date: props.task.due_date ?? '',
            reminder_at: props.task.reminder_at ?? '',
        });
    } else {
        Object.assign(form, emptyForm());
    }
    errors.value = {};
});

const submit = async () => {
    if (!(await validateForm(validations, form))) return;
    isSubmitting.value = true;
    try {
        const payload = { ...form };
        if (!payload.party_id) delete payload.party_id;
        if (!payload.assigned_to_user_id) delete payload.assigned_to_user_id;

        const res = isEdit.value
            ? await taskStore.updateTask(props.task.id, payload)
            : await taskStore.storeTask(payload);
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
    show.value = false;
};
</script>
