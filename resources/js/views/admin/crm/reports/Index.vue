<template>
    <PageHeader
        title="CRM Reports"
        subtitle="Lead pipeline, conversion, follow-ups and tasks at a glance"
        @refresh="reload"
    />

    <section class="section">
        <div class="row g-3">
            <!-- Lead pipeline -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Lead Pipeline</h6>
                        <span class="badge bg-primary">{{ pipeline?.total ?? 0 }} leads</span>
                    </div>
                    <div class="card-body">
                        <div v-if="loading" class="placeholder-glow">
                            <span v-for="n in 5" :key="n" class="placeholder col-12 mb-2 d-block"></span>
                        </div>
                        <template v-else>
                            <div v-for="s in pipeline?.by_status || []" :key="s.status" class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>{{ s.label }}</span>
                                    <strong>{{ s.count }}</strong>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar" :style="{ width: pct(s.count, pipeline?.total) + '%' }"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Conversion -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0">Conversion</h6>
                        <div class="d-flex gap-2">
                            <input v-model="range.date_from" type="date" class="form-control form-control-sm" @change="reload" />
                            <input v-model="range.date_to" type="date" class="form-control form-control-sm" @change="reload" />
                        </div>
                    </div>
                    <div class="card-body">
                        <div v-if="loading" class="placeholder-glow">
                            <span class="placeholder col-8 d-block mb-2" style="height: 2rem;"></span>
                            <span class="placeholder col-6 d-block"></span>
                        </div>
                        <template v-else>
                            <h2 class="mb-1">{{ conversion?.conversion_rate ?? 0 }}%</h2>
                            <p class="text-muted mb-3">
                                {{ conversion?.converted ?? 0 }} of {{ conversion?.total_leads ?? 0 }} leads converted
                            </p>
                            <p class="mb-0">
                                <i class="ti ti-clock me-1"></i>
                                Avg. time to convert:
                                <strong>{{ conversion?.avg_days_to_convert != null ? conversion.avg_days_to_convert + ' days' : '—' }}</strong>
                            </p>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Follow-ups -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0">Follow-ups</h6></div>
                    <div class="card-body">
                        <div v-if="loading" class="placeholder-glow"><span class="placeholder col-12"></span></div>
                        <template v-else>
                            <div class="row text-center mb-3">
                                <div class="col">
                                    <h4 class="mb-0 text-warning">{{ followUps?.due ?? 0 }}</h4>
                                    <small class="text-muted">Due</small>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0 text-danger">{{ followUps?.overdue ?? 0 }}</h4>
                                    <small class="text-muted">Overdue</small>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li v-for="s in followUps?.by_status || []" :key="s.status" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ s.label }}</span><strong>{{ s.count }}</strong>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tasks -->
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Tasks</h6>
                        <span class="badge bg-danger">{{ tasks?.overdue ?? 0 }} overdue</span>
                    </div>
                    <div class="card-body">
                        <div v-if="loading" class="placeholder-glow"><span class="placeholder col-12"></span></div>
                        <template v-else>
                            <ul class="list-group list-group-flush mb-3">
                                <li v-for="s in tasks?.by_status || []" :key="s.status" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ s.label }}</span><strong>{{ s.count }}</strong>
                                </li>
                            </ul>
                            <h6 class="mb-2">Open tasks by assignee</h6>
                            <p v-if="!tasks?.by_assignee?.length" class="text-muted mb-0">No open tasks.</p>
                            <ul class="list-group list-group-flush">
                                <li v-for="a in tasks?.by_assignee || []" :key="a.user_id" class="list-group-item px-0 d-flex justify-content-between">
                                    <span>{{ a.name || 'Unassigned' }}</span><strong>{{ a.count }}</strong>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import { storeToRefs } from 'pinia';
import { useCrmReportStore } from '@/stores/admin/crm/reports.js';

const reportStore = useCrmReportStore();
const { pipeline, conversion, followUps, tasks, loading } = storeToRefs(reportStore);

const range = reactive({ date_from: '', date_to: '' });

const pct = (count, total) => (total ? Math.round((count / total) * 100) : 0);

const reload = () => reportStore.load({ ...range });

onMounted(reload);
</script>
