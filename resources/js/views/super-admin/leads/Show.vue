<template>
    <PageHeader title="Lead Detail" subtitle="View and manage lead follow-up">
        <template #actions>
            <router-link :to="{name: 'super-admin.leads'}" class="btn btn-secondary">
                <i class="ti ti-arrow-left me-1"></i>Back to Leads
            </router-link>
        </template>
    </PageHeader>

    <div v-if="lead.loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <div v-else-if="lead.data?.id" class="row">
        <!-- Left: Submitted Details -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">Inquiry Details</h5>
                    <span :class="['badge badge-sm', statusBadge(lead.data.status)]">
                        {{ lead.data.status_label }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Company Name</label>
                            <p class="mb-0">{{ lead.data.company_name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Business Type</label>
                            <p class="mb-0">{{ lead.data.business_type_label }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">PAN</label>
                            <p class="mb-0">{{ lead.data.pan || '—' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Registration No.</label>
                            <p class="mb-0">{{ lead.data.registration_number || '—' }}</p>
                        </div>
                        <div class="col-12"><hr class="my-1" /></div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Full Name</label>
                            <p class="mb-0">{{ lead.data.full_name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Email</label>
                            <p class="mb-0">
                                <a :href="`mailto:${lead.data.email}`">{{ lead.data.email }}</a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Phone</label>
                            <p class="mb-0">
                                <a :href="`tel:${lead.data.phone}`">{{ lead.data.phone }}</a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Plan Interest</label>
                            <p class="mb-0">
                                <span v-if="lead.data.plan_interest" class="badge badge-info badge-sm">{{ lead.data.plan_interest }}</span>
                                <span v-else>—</span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">No. of Branches</label>
                            <p class="mb-0">{{ lead.data.branch_count }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-medium text-muted fs-12">Submitted</label>
                            <p class="mb-0">{{ formatDate(lead.data.created_at) }}</p>
                        </div>
                        <div v-if="lead.data.note" class="col-12">
                            <label class="form-label fw-medium text-muted fs-12">Note from Prospect</label>
                            <p class="mb-0 text-break">{{ lead.data.note }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Follow-up -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Follow-Up</h5>
                </div>
                <div class="card-body">
                    <form @submit.prevent="saveFollowUp">
                        <div class="mb-3">
                            <VMultiselect
                                id="status"
                                v-model="form.status"
                                label="Status"
                                :options="statuses"
                                value-prop="value"
                                name-prop="label"
                                required
                            />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow-up Note</label>
                            <textarea
                                v-model="form.follow_up_note"
                                class="form-control"
                                rows="5"
                                placeholder="Add notes about this lead, demos given, next steps..."
                            ></textarea>
                        </div>
                        <div v-if="lead.data.followed_up_at" class="mb-3">
                            <label class="form-label fw-medium text-muted fs-12">Last Updated</label>
                            <p class="mb-0">{{ formatDate(lead.data.followed_up_at) }}</p>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" :disabled="saving">
                            <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                            Save Follow-Up
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Meta</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-medium text-muted fs-12">Source</label>
                            <p class="mb-0">{{ lead.data.source || '—' }}</p>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium text-muted fs-12">IP Address</label>
                            <p class="mb-0">{{ lead.data.ip_address || '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import {storeToRefs} from 'pinia';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {useLeadStore} from '@/stores/super-admin/lead';

const route = useRoute();
const leadStore = useLeadStore();
const {lead} = storeToRefs(leadStore);

const saving = ref(false);

const statuses = [
    {value: 'new', label: 'New'},
    {value: 'contacted', label: 'Contacted'},
    {value: 'demo_given', label: 'Demo Given'},
    {value: 'converted', label: 'Converted'},
    {value: 'lost', label: 'Lost'},
];

const form = reactive({
    status: 'new',
    follow_up_note: '',
});

const statusBadge = (status) => ({
    'badge-primary': status === 'new',
    'badge-warning': status === 'contacted',
    'badge-info': status === 'demo_given',
    'badge-success': status === 'converted',
    'badge-danger': status === 'lost',
});

const formatDate = (dateStr) => {
    if (!dateStr) {
        return '—';
    }
    return new Date(dateStr).toLocaleString();
};

onMounted(async () => {
    await leadStore.getLead(route.params.id);
    syncForm();
});

watch(() => lead.value.data, syncForm);

function syncForm() {
    if (lead.value.data?.id) {
        form.status = lead.value.data.status ?? 'new';
        form.follow_up_note = lead.value.data.follow_up_note ?? '';
    }
}

const saveFollowUp = async () => {
    saving.value = true;
    try {
        const res = await leadStore.updateLead(route.params.id, form);
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};
</script>
