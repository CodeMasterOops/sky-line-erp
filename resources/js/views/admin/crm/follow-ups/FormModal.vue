<template>
    <VModal
        :show-modal="!!show"
        modal-class="large-modal"
        :title="isEdit ? 'Edit follow-up' : 'Schedule follow-up'"
        @close-click="close"
    >
        <template #modal-body>
            <form class="row g-3" @submit.prevent="submit">
                <!-- Contact (party) with searchable select + create -->
                <div class="col-md-6">
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1 min-w-0">
                            <VMultiselect
                                id="fu-party"
                                v-model="form.party_id"
                                label="Contact"
                                :options="partyOptions"
                                name-prop="display_label"
                                :filter-results="false"
                                required
                                :error="errors.party_id"
                                @search-change="onPartySearch"
                                @validate="validateField('party_id')"
                            />
                        </div>
                        <button
                            type="button"
                            class="btn btn-outline-secondary flex-shrink-0"
                            style="height:38px"
                            title="Add new contact"
                            @click="createContactOpened = true"
                        >
                            <i class="ti ti-user-plus"></i>
                        </button>
                    </div>
                    <div class="form-text text-muted mt-1">
                        <i class="ti ti-info-circle me-1"></i>Search Customers, Leads or Suppliers. Type to filter.
                    </div>
                </div>

                <div class="col-md-6">
                    <VSelect
                        id="fu-channel"
                        v-model="form.channel"
                        label="Channel"
                        required
                        :options="followUpChannels"
                        :error="errors.channel"
                    />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="fu-scheduled">
                        Scheduled at <VRequiredMark />
                    </label>
                    <VDateTimePicker id="fu-scheduled" v-model="form.scheduled_at" placeholder="Scheduled at" :error="errors.scheduled_at" />
                </div>
                <div class="col-md-6">
                    <VSelect
                        id="fu-owner"
                        v-model="form.user_id"
                        label="Owner"
                        :options="users.data"
                        :error="errors.user_id"
                    />
                </div>
                <div class="col-md-12">
                    <VTextarea
                        id="fu-note"
                        v-model="form.note"
                        label="Note"
                        :error="errors.note"
                    />
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button class="btn btn-cancel" type="button" @click="close">Cancel</button>
                    <VButton :loading="isSubmitting" />
                </div>
            </form>
        </template>
    </VModal>

    <CreateParty v-model:create-modal-opened="createContactOpened" @update:create-modal-opened="onCreateContactClose" />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { object, string } from 'yup';
import debounce from 'lodash/debounce';
import { toast } from '@/helpers/toast';
import { useYup } from '@/helpers/yup';
import showErrors from '@/helpers/showErrors';
import { apiAdmin } from '@/helpers/api.js';
import VModal from '@/components/base/VModal.vue';
import VSelect from '@/components/base/VSelect.vue';
import VMultiselect from '@/components/base/VMultiselect.vue';
import VButton from '@/components/base/VButton.vue';
import VTextarea from '@/components/base/VTextarea.vue';
import VRequiredMark from '@/components/base/VRequiredMark.vue';
import VDateTimePicker from '@/components/base/VDateTimePicker.vue';
import CreateParty from '@/views/admin/party/Create.vue';
import { useEnumStore } from '@/stores/admin/enum.js';
import { useUserStore } from '@/stores/admin/user-management/user.js';
import { useCrmFollowUpStore } from '@/stores/admin/crm/followUps.js';

const props = defineProps({
    followUp: { type: Object, default: null },
});

const show = defineModel('show');
const emit = defineEmits(['saved']);

const followUpStore = useCrmFollowUpStore();
const enumStore = useEnumStore();
const userStore = useUserStore();

const { followUpChannels } = storeToRefs(enumStore);
const { users } = storeToRefs(userStore);

const partyOptions = ref([]);
const partyLoading = ref(false);
const createContactOpened = ref(false);
const isSubmitting = ref(false);

const isEdit = computed(() => !!props.followUp?.id);

const emptyForm = () => ({
    party_id: '',
    channel: 'call',
    scheduled_at: '',
    user_id: '',
    note: '',
});

const form = reactive(emptyForm());

const validations = object({
    party_id: string().required('Contact is required.'),
    channel: string().required('Channel is required.'),
    scheduled_at: string().required('Scheduled date is required.'),
});

const { errors, validateField, validateForm } = useYup(form, validations);

const mapParties = (data) =>
    data.map((p) => ({
        ...p,
        display_label: p.name + (p.type_label ? ' — ' + p.type_label : ''),
    }));

const fetchParties = (search = '') => {
    partyLoading.value = true;
    return apiAdmin(`party?limit=100&search=${encodeURIComponent(search)}`)
        .then((res) => { partyOptions.value = mapParties(res.data.data); })
        .catch(showErrors)
        .finally(() => { partyLoading.value = false; });
};

const onPartySearch = debounce((query) => { fetchParties(query ?? ''); }, 300);

const onCreateContactClose = (opened) => {
    if (!opened) {
        fetchParties('');
    }
};

watch(show, (opened) => {
    if (!opened) return;
    enumStore.getFollowUpChannels();
    if (!users.value.data.length) userStore.getUsers();
    fetchParties('');

    if (isEdit.value) {
        Object.assign(form, {
            ...emptyForm(),
            party_id: props.followUp.party_id ?? '',
            channel: props.followUp.channel ?? 'call',
            scheduled_at: props.followUp.scheduled_at ?? '',
            user_id: props.followUp.user_id ?? '',
            note: props.followUp.note ?? '',
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
        if (!payload.user_id) delete payload.user_id;

        const res = isEdit.value
            ? await followUpStore.updateFollowUp(props.followUp.id, payload)
            : await followUpStore.storeFollowUp(payload);
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
