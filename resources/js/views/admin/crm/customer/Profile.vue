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
                <div class="card h-100">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ card.label }}</p>
                        <h4 v-if="!summaryLoading" class="mb-0">{{ card.value }}</h4>
                        <div v-else class="placeholder-glow"><span class="placeholder col-7"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header p-0">
                <ul class="nav nav-tabs nav-tabs-bottom">
                    <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                        <a
                            href="javascript:void(0);"
                            class="nav-link"
                            :class="{ active: activeTab === tab.key }"
                            @click="selectTab(tab.key)"
                        >
                            <i :class="`ti ${tab.icon} me-1`"></i> {{ tab.label }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="card-body">
                <!-- Overview -->
                <div v-show="activeTab === 'overview'">
                    <div class="row g-4">
                        <div class="col-lg-4">
                            <h6 class="mb-3">Open follow-ups</h6>
                            <p v-if="!summary?.open_follow_ups?.length" class="text-muted">No pending follow-ups.</p>
                            <ul class="list-group list-group-flush">
                                <li v-for="f in summary?.open_follow_ups || []" :key="f.id" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ f.channel_label }}</span>
                                    <small class="text-muted">{{ formatDate(f.scheduled_at) }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <h6 class="mb-3">Open tasks</h6>
                            <p v-if="!summary?.open_tasks?.length" class="text-muted">No open tasks.</p>
                            <ul class="list-group list-group-flush">
                                <li v-for="t in summary?.open_tasks || []" :key="t.id" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ t.title }}</span>
                                    <small class="text-muted">{{ t.due_date || '—' }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-4">
                            <h6 class="mb-3">Recent receipts</h6>
                            <p v-if="!summary?.recent_receipts?.length" class="text-muted">No receipts yet.</p>
                            <ul class="list-group list-group-flush">
                                <li v-for="r in summary?.recent_receipts || []" :key="r.id" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ r.receipt_no }}</span>
                                    <small>{{ money(r.amount) }}</small>
                                </li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-3">Recent invoices</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>No.</th><th>Date</th><th class="text-end">Total</th><th class="text-end">Paid</th></tr></thead>
                                    <tbody>
                                        <tr v-for="i in summary?.recent_invoices || []" :key="i.id">
                                            <td>{{ i.invoice_no }}</td>
                                            <td>{{ i.invoice_date }}</td>
                                            <td class="text-end">{{ money(i.total_amount) }}</td>
                                            <td class="text-end">{{ money(i.paid_amount) }}</td>
                                        </tr>
                                        <tr v-if="!summary?.recent_invoices?.length"><td colspan="4" class="text-muted">No invoices.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <h6 class="mb-3">Recent sales orders</h6>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th>No.</th><th>Date</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <tr v-for="s in summary?.recent_sales || []" :key="s.id">
                                            <td>{{ s.order_no }}</td>
                                            <td>{{ s.order_date }}</td>
                                            <td>{{ s.status }}</td>
                                        </tr>
                                        <tr v-if="!summary?.recent_sales?.length"><td colspan="3" class="text-muted">No sales orders.</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div v-show="activeTab === 'timeline'">
                    <div v-if="timeline.loading && !timeline.data.length">
                        <div v-for="n in 4" :key="n" class="placeholder-glow mb-3">
                            <span class="placeholder col-3 me-2"></span><span class="placeholder col-6"></span>
                        </div>
                    </div>
                    <p v-else-if="!timeline.data.length" class="text-muted">No activity yet.</p>
                    <ul v-else class="timeline-feed list-unstyled">
                        <li v-for="item in timeline.data" :key="item.id" class="d-flex gap-3 mb-3">
                            <span class="badge rounded-pill" :class="item.source === 'finance' ? 'bg-success' : 'bg-primary'">
                                <i :class="`ti ${item.source === 'finance' ? 'ti-cash' : 'ti-activity'}`"></i>
                            </span>
                            <div>
                                <div>{{ item.description }}</div>
                                <small class="text-muted">
                                    {{ formatDate(item.occurred_at) }}
                                    <span v-if="item.causer_name"> · {{ item.causer_name }}</span>
                                </small>
                            </div>
                        </li>
                    </ul>
                    <div v-if="hasMoreTimeline" class="text-center py-2">
                        <button class="btn btn-sm btn-outline-secondary" :disabled="timeline.loading" @click="loadMoreTimeline">
                            {{ timeline.loading ? 'Loading…' : 'Load more' }}
                        </button>
                    </div>
                </div>

                <!-- Follow-ups -->
                <div v-show="activeTab === 'follow-ups'">
                    <SimpleList :rows="followUpsTab" :loading="tabLoading['follow-ups']" empty="No follow-ups for this contact.">
                        <template #row="{ row }">
                            <span>{{ row.channel_label }} · {{ row.status_label }}</span>
                            <small class="text-muted">{{ formatDate(row.scheduled_at) }}</small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Tasks -->
                <div v-show="activeTab === 'tasks'">
                    <SimpleList :rows="tasksTab" :loading="tabLoading['tasks']" empty="No tasks for this contact.">
                        <template #row="{ row }">
                            <span>{{ row.title }} <small class="text-muted">({{ row.status_label }})</small></span>
                            <small class="text-muted">{{ row.due_date || '—' }}</small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Notes -->
                <div v-show="activeTab === 'notes'">
                    <form class="d-flex gap-2 mb-3" @submit.prevent="addNote">
                        <input v-model="newNote" type="text" class="form-control" placeholder="Add a note…" />
                        <button class="btn btn-primary" :disabled="!newNote.trim() || savingNote" type="submit">Add</button>
                    </form>
                    <SimpleList :rows="notes.data" :loading="notes.loading" empty="No notes yet." :deletable="true" @delete="removeNote">
                        <template #row="{ row }">
                            <span>{{ row.body }}</span>
                            <small class="text-muted">{{ row.user_name }} · {{ formatDate(row.created_at) }}</small>
                        </template>
                    </SimpleList>
                </div>

                <!-- Contacts -->
                <div v-show="activeTab === 'contacts'">
                    <SimpleList :rows="contactsTab" :loading="tabLoading['contacts']" empty="No contact persons.">
                        <template #row="{ row }">
                            <span>{{ row.name }} <small class="text-muted">{{ row.designation }}</small></span>
                            <small class="text-muted">{{ row.phone }} {{ row.email }}</small>
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
const tabLoading = reactive({});
const followUpsTab = ref([]);
const tasksTab = ref([]);
const contactsTab = ref([]);
const newNote = ref('');
const savingNote = ref(false);

const tabs = [
    { key: 'overview', label: 'Overview', icon: 'ti-layout-dashboard' },
    { key: 'timeline', label: 'Timeline', icon: 'ti-timeline' },
    { key: 'follow-ups', label: 'Follow-ups', icon: 'ti-phone' },
    { key: 'tasks', label: 'Tasks', icon: 'ti-checklist' },
    { key: 'notes', label: 'Notes', icon: 'ti-note' },
    { key: 'contacts', label: 'Contacts', icon: 'ti-address-book' },
];

const money = (v) => new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v || 0));
const formatDate = (v) => (v ? new Date(v).toLocaleString() : '—');

const summaryCards = computed(() => [
    { label: 'Outstanding balance', value: money(summary.value?.outstanding_balance) },
    { label: 'Lifetime value', value: money(summary.value?.lifetime_value) },
    { label: 'Invoices', value: summary.value?.invoice_count ?? 0 },
    { label: 'Open follow-ups', value: summary.value?.open_follow_ups?.length ?? 0 },
]);

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
