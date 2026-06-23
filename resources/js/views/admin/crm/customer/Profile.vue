<template>
    <PageHeader
        :title="party?.name || 'Customer'"
        :subtitle="party?.code ? `${party.type_label} · ${party.code}` : 'Customer 360'"
        @refresh="reload"
    >
        <template #actions>
            <router-link :to="{ name: 'admin.crm-contacts' }" class="btn btn-outline-secondary d-flex align-items-center">
                <i class="ti ti-arrow-left me-2"></i> Back to contacts
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <!-- Summary cards -->
        <div class="row g-3 mb-3">
            <div v-for="card in summaryCards" :key="card.label" class="col-sm-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-card-icon" :class="card.iconBg">
                            <i :class="`ti ${card.icon}`"></i>
                        </div>
                        <div>
                            <p class="text-muted small mb-1">{{ card.label }}</p>
                            <h5 v-if="!summaryLoading" class="mb-0 fw-semibold">{{ card.value }}</h5>
                            <div v-else class="placeholder-glow"><span class="placeholder col-7"></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header p-0 border-bottom">
                <ul class="nav nav-tabs border-0 px-3">
                    <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                        <a
                            href="javascript:void(0);"
                            class="nav-link px-3"
                            :class="{ active: activeTab === tab.key }"
                            @click="selectTab(tab.key)"
                        >
                            <i :class="`ti ${tab.icon} me-1`"></i> {{ tab.label }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <!-- Overview -->
                <div v-if="renderedTabs['overview']" v-show="activeTab === 'overview'">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-semibold">Open follow-ups</h6>
                                <span v-if="summary?.open_follow_ups?.length" class="badge bg-warning-subtle text-warning">
                                    {{ summary.open_follow_ups.length }}
                                </span>
                            </div>
                            <p v-if="!summary?.open_follow_ups?.length" class="text-muted small">No pending follow-ups.</p>
                            <ul v-else class="list-group list-group-flush">
                                <li v-for="f in summary.open_follow_ups" :key="f.id" class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <span class="small">
                                        <i class="ti ti-phone text-muted me-1"></i>{{ f.channel_label }}
                                    </span>
                                    <small class="text-muted">{{ formatDateShort(f.scheduled_at) }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-semibold">Open tasks</h6>
                                <span v-if="summary?.open_tasks?.length" class="badge bg-primary-subtle text-primary">
                                    {{ summary.open_tasks.length }}
                                </span>
                            </div>
                            <p v-if="!summary?.open_tasks?.length" class="text-muted small">No open tasks.</p>
                            <ul v-else class="list-group list-group-flush">
                                <li v-for="t in summary.open_tasks" :key="t.id" class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <span class="small">
                                        <i class="ti ti-checkbox text-muted me-1"></i>{{ t.title }}
                                    </span>
                                    <small class="text-muted">{{ t.due_date || '—' }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-semibold">Recent receipts</h6>
                            </div>
                            <p v-if="!summary?.recent_receipts?.length" class="text-muted small">No receipts yet.</p>
                            <ul v-else class="list-group list-group-flush">
                                <li v-for="r in summary.recent_receipts" :key="r.id" class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                                    <span class="small">{{ r.receipt_no }}</span>
                                    <small class="fw-medium text-success">{{ money(r.amount) }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2 fw-semibold">Recent invoices</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="text-muted small">
                                        <tr>
                                            <th class="fw-medium">No.</th>
                                            <th class="fw-medium">Date</th>
                                            <th class="text-end fw-medium">Total</th>
                                            <th class="text-end fw-medium">Paid</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="i in summary?.recent_invoices || []" :key="i.id">
                                            <td class="small">{{ i.invoice_no }}</td>
                                            <td class="small text-muted">{{ i.invoice_date }}</td>
                                            <td class="text-end small">{{ money(i.total_amount) }}</td>
                                            <td class="text-end small text-success">{{ money(i.paid_amount) }}</td>
                                        </tr>
                                        <tr v-if="!summary?.recent_invoices?.length">
                                            <td colspan="4" class="text-muted small">No invoices.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-2 fw-semibold">Recent sales orders</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-borderless mb-0">
                                    <thead class="text-muted small">
                                        <tr>
                                            <th class="fw-medium">No.</th>
                                            <th class="fw-medium">Date</th>
                                            <th class="fw-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="s in summary?.recent_sales || []" :key="s.id">
                                            <td class="small">{{ s.order_no }}</td>
                                            <td class="small text-muted">{{ s.order_date }}</td>
                                            <td class="small">{{ s.status }}</td>
                                        </tr>
                                        <tr v-if="!summary?.recent_sales?.length">
                                            <td colspan="3" class="text-muted small">No sales orders.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div v-if="renderedTabs['timeline']" v-show="activeTab === 'timeline'">
                    <div v-if="timeline.loading && !timeline.data.length">
                        <div v-for="n in 5" :key="n" class="d-flex gap-3 mb-4 placeholder-glow">
                            <span class="placeholder rounded-circle flex-shrink-0" style="width:1.9rem;height:1.9rem;"></span>
                            <div class="flex-grow-1 pt-1">
                                <span class="placeholder col-5 d-block mb-2"></span>
                                <span class="placeholder col-3"></span>
                            </div>
                        </div>
                    </div>
                    <p v-else-if="!timeline.data.length" class="text-muted">No activity yet.</p>
                    <div v-else class="crm-timeline">
                        <div v-for="item in timeline.data" :key="item.id" class="crm-timeline__item">
                            <div class="crm-timeline__dot" :class="timelineDotClass(item)">
                                <i :class="timelineIcon(item)"></i>
                            </div>
                            <div class="crm-timeline__body" :class="timelineBodyClass(item)">
                                <p class="mb-1 small">{{ item.description }}</p>
                                <small class="text-muted">
                                    {{ formatDate(item.occurred_at) }}
                                    <span v-if="item.causer_name"> · <span class="fw-medium">{{ item.causer_name }}</span></span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div v-if="hasMoreTimeline" class="text-center pt-2">
                        <button class="btn btn-sm btn-outline-secondary" :disabled="timeline.loading" @click="loadMoreTimeline">
                            {{ timeline.loading ? 'Loading…' : 'Load more' }}
                        </button>
                    </div>
                </div>

                <!-- Follow-ups -->
                <div v-if="renderedTabs['follow-ups']" v-show="activeTab === 'follow-ups'">
                    <SimpleList :rows="followUpsTab" :loading="tabLoading['follow-ups']" empty="No follow-ups for this contact.">
                        <template #row="{ row }">
                            <span class="small">{{ row.channel_label }}</span>
                            <small class="text-muted">
                                <span class="badge rounded-pill me-2" :class="followUpBadge[row.status]">{{ row.status_label }}</span>
                                {{ formatDateShort(row.scheduled_at) }}
                            </small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Tasks -->
                <div v-if="renderedTabs['tasks']" v-show="activeTab === 'tasks'">
                    <SimpleList :rows="tasksTab" :loading="tabLoading['tasks']" empty="No tasks for this contact.">
                        <template #row="{ row }">
                            <span class="small">{{ row.title }}</span>
                            <small class="text-muted">
                                <span class="badge rounded-pill me-2" :class="taskBadge[row.status]">{{ row.status_label }}</span>
                                {{ row.due_date || '—' }}
                            </small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Notes -->
                <div v-if="renderedTabs['notes']" v-show="activeTab === 'notes'">
                    <form class="d-flex gap-2 mb-3" @submit.prevent="addNote">
                        <input v-model="newNote" type="text" class="form-control" placeholder="Add a note…" />
                        <button class="btn btn-primary flex-shrink-0" :disabled="!newNote.trim() || savingNote" type="submit">Add</button>
                    </form>
                    <SimpleList :rows="notes.data" :loading="notes.loading" empty="No notes yet." :deletable="true" @delete="removeNote">
                        <template #row="{ row }">
                            <span class="small">{{ row.body }}</span>
                            <small class="text-muted">{{ row.user_name }} · {{ formatDateShort(row.created_at) }}</small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Contacts -->
                <div v-if="renderedTabs['contacts']" v-show="activeTab === 'contacts'">
                    <SimpleList :rows="contactsTab" :loading="tabLoading['contacts']" empty="No contact persons.">
                        <template #row="{ row }">
                            <span class="small fw-medium">{{ row.name }} <span class="fw-normal text-muted">{{ row.designation }}</span></span>
                            <small class="text-muted">{{ [row.phone, row.email].filter(Boolean).join(' · ') }}</small>
                        </template>
                    </SimpleList>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';
import { toast } from '@/helpers/toast';
import { useConfirmAction } from '@/composables/useConfirmAction.js';
import { useCrmCustomerProfileStore } from '@/stores/admin/crm/customerProfile.js';
import { useCrmNoteStore } from '@/stores/admin/crm/notes.js';
import SimpleList from './SimpleList.vue';

const route = useRoute();
const partyId = route.params.id;

const profileStore = useCrmCustomerProfileStore();
const noteStore = useCrmNoteStore();
const { confirmAction } = useConfirmAction();

const { summary: summaryState, timeline } = storeToRefs(profileStore);
const { notes } = storeToRefs(noteStore);

const summary = computed(() => summaryState.value.data);
const summaryLoading = computed(() => summaryState.value.loading);
const party = computed(() => summary.value?.party);

const activeTab = ref('overview');
const loadedTabs = reactive({ overview: true });
const renderedTabs = reactive({ overview: true });
const tabLoading = reactive({});
const followUpsTab = ref([]);
const tasksTab = ref([]);
const contactsTab = ref([]);
const newNote = ref('');
const savingNote = ref(false);

const tabs = [
    { key: 'overview',   label: 'Overview',   icon: 'ti-layout-dashboard' },
    { key: 'timeline',   label: 'Timeline',   icon: 'ti-timeline' },
    { key: 'follow-ups', label: 'Follow-ups', icon: 'ti-phone' },
    { key: 'tasks',      label: 'Tasks',      icon: 'ti-checklist' },
    { key: 'notes',      label: 'Notes',      icon: 'ti-note' },
    { key: 'contacts',   label: 'Contacts',   icon: 'ti-address-book' },
];

const followUpBadge = {
    pending: 'bg-warning-subtle text-warning',
    done:    'bg-success-subtle text-success',
    missed:  'bg-danger-subtle text-danger',
};

const taskBadge = {
    open:        'bg-primary-subtle text-primary',
    in_progress: 'bg-warning-subtle text-warning',
    done:        'bg-success-subtle text-success',
    cancelled:   'bg-secondary-subtle text-secondary',
};

const moneyFmt     = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFmt      = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
const dateShortFmt = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

const money           = (v) => moneyFmt.format(Number(v || 0));
const formatDate      = (v) => (v ? dateFmt.format(new Date(v)) : '—');
const formatDateShort = (v) => (v ? dateShortFmt.format(new Date(v)) : '—');

const summaryCards = computed(() => [
    { label: 'Outstanding balance', value: money(summary.value?.outstanding_balance), icon: 'ti-coins',       iconBg: 'summary-icon-warning' },
    { label: 'Lifetime value',      value: money(summary.value?.lifetime_value),      icon: 'ti-trending-up', iconBg: 'summary-icon-success' },
    { label: 'Invoices',            value: summary.value?.invoice_count ?? 0,          icon: 'ti-file-invoice', iconBg: 'summary-icon-info' },
    { label: 'Open follow-ups',     value: summary.value?.open_follow_ups?.length ?? 0, icon: 'ti-phone',     iconBg: 'summary-icon-primary' },
]);

const timelineDotClass = (item) => {
    const map = { finance: 'is-finance', note: 'is-note', task: 'is-task', follow_up: 'is-followup' };
    return map[item.source] ?? 'is-default';
};
const timelineBodyClass = (item) => {
    const map = { finance: 'is-finance', note: 'is-note', task: 'is-task', follow_up: 'is-followup' };
    return map[item.source] ?? 'is-default';
};
const timelineIcon = (item) => {
    const map = { finance: 'ti ti-cash', note: 'ti ti-note', task: 'ti ti-checklist', follow_up: 'ti ti-phone' };
    return map[item.source] ?? 'ti ti-activity';
};

const hasMoreTimeline = computed(() => {
    const meta = timeline.value.meta;
    return meta.current_page && meta.last_page && meta.current_page < meta.last_page;
});

const reload = () => {
    profileStore.getSummary(partyId);
    if (loadedTabs.timeline) {
        profileStore.resetTimeline();
        profileStore.getTimeline(partyId, { page: 1 });
    }
};

const selectTab = (key) => {
    activeTab.value = key;
    renderedTabs[key] = true;
    if (loadedTabs[key]) return;
    loadedTabs[key] = true;

    if (key === 'timeline') {
        profileStore.getTimeline(partyId, { page: 1 });
    } else if (key === 'follow-ups') {
        fetchTab('follow-ups', `crm/follow-up?party_id=${partyId}&limit=100`, followUpsTab);
    } else if (key === 'tasks') {
        fetchTab('tasks', `crm/task?party_id=${partyId}&limit=100`, tasksTab);
    } else if (key === 'notes') {
        noteStore.getNotes(partyId);
    } else if (key === 'contacts') {
        fetchTab('contacts', `crm/contact-person?party_id=${partyId}&limit=100`, contactsTab);
    }
};

const fetchTab = (key, url, target) => {
    tabLoading[key] = true;
    apiAdmin(url)
        .then((res) => { target.value = res.data.data; })
        .catch(showErrors)
        .finally(() => { tabLoading[key] = false; });
};

const loadMoreTimeline = () => {
    if (!hasMoreTimeline.value || timeline.value.loading) return;
    profileStore.getTimeline(partyId, { page: timeline.value.meta.current_page + 1, append: true });
};

const addNote = async () => {
    if (!newNote.value.trim()) return;
    savingNote.value = true;
    try {
        const res = await noteStore.storeNote(partyId, newNote.value.trim());
        toast(res.status, res.data.message);
        newNote.value = '';
        noteStore.getNotes(partyId);
        profileStore.getSummary(partyId);
    } catch (e) {
        showErrors(e);
    } finally {
        savingNote.value = false;
    }
};

const removeNote = (id) => {
    confirmAction({
        title: 'Delete note?',
        confirmButtonText: 'Delete',
        action: () => noteStore.deleteNote(id),
        onSuccess: () => noteStore.getNotes(partyId),
    });
};

onMounted(() => {
    profileStore.getSummary(partyId);
});
</script>

<style scoped>
.summary-card-icon {
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}
.summary-icon-warning { background: #fff3cd; color: #856404; }
.summary-icon-success { background: #d1e7dd; color: #0f5132; }
.summary-icon-info    { background: #cff4fc; color: #055160; }
.summary-icon-primary { background: #cfe2ff; color: #084298; }

/* Timeline */
.crm-timeline {
    position: relative;
    padding-left: 2.75rem;
}
.crm-timeline::before {
    content: '';
    position: absolute;
    left: 0.9rem;
    top: 1rem;
    bottom: 0.5rem;
    width: 2px;
    background: #e9ecef;
}
.crm-timeline__item {
    position: relative;
    margin-bottom: 1.25rem;
}
.crm-timeline__dot {
    position: absolute;
    left: -2.75rem;
    top: 0.35rem;
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    z-index: 1;
}
.crm-timeline__body {
    background: #f8f9fa;
    border-radius: 0.5rem;
    padding: 0.6rem 0.9rem;
    border-left: 3px solid #dee2e6;
}
.crm-timeline__dot.is-finance  { background: #d1e7dd; color: #0f5132; }
.crm-timeline__body.is-finance { border-left-color: #198754; }
.crm-timeline__dot.is-note  { background: #fff3cd; color: #856404; }
.crm-timeline__body.is-note { border-left-color: #ffc107; }
.crm-timeline__dot.is-task  { background: #e8d5f5; color: #5a1e8a; }
.crm-timeline__body.is-task { border-left-color: #9c36c4; }
.crm-timeline__dot.is-followup  { background: #e0cffc; color: #3d0a91; }
.crm-timeline__body.is-followup { border-left-color: #6610f2; }
.crm-timeline__dot.is-default  { background: #cfe2ff; color: #084298; }
.crm-timeline__body.is-default { border-left-color: #0d6efd; }
</style>
