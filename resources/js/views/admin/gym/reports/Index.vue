<template>
    <PageHeader title="Gym Reports" subtitle="Membership, renewals, revenue and floor traffic" @refresh="load">
        <template #actions>
            <div class="d-flex gap-2 align-items-end">
                <div style="min-width: 10rem">
                    <VDatepicker id="report_from" v-model="range.from" label="From" @update:model-value="load" />
                </div>
                <div style="min-width: 10rem">
                    <VDatepicker id="report_to" v-model="range.to" label="To" @update:model-value="load" />
                </div>
            </div>
        </template>
    </PageHeader>

    <section class="section">
        <ul class="nav nav-tabs mb-3">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item">
                <a
                    href="javascript:void(0);"
                    :class="['nav-link', { active: active === tab.key }]"
                    @click="select(tab.key)"
                >{{ tab.label }}</a>
            </li>
        </ul>

        <div v-if="report.loading" class="card">
            <div class="card-body text-center py-5 text-muted">Loading report…</div>
        </div>

        <template v-else-if="data">
            <!-- Membership summary -->
            <div v-if="active === 'membership-summary'" class="row g-3">
                <div class="col-lg-5">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">By status</h6></div>
                        <div class="card-body">
                            <table class="table table-center mb-0">
                                <tbody>
                                    <tr v-for="row in data.by_status" :key="row.status">
                                        <td>{{ row.label }}</td>
                                        <td class="text-end fw-medium">{{ row.count }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">By plan</h6></div>
                        <div class="card-body">
                            <table class="table table-center mb-0">
                                <thead>
                                    <tr><th>Plan</th><th class="text-end">Sold</th><th class="text-end">Active</th></tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in data.by_plan" :key="row.plan">
                                        <td>{{ row.plan }}</td>
                                        <td class="text-end">{{ row.count }}</td>
                                        <td class="text-end">{{ row.active }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Renewals -->
            <div v-else-if="active === 'renewals'">
                <div class="row g-3 mb-3">
                    <div v-for="tile in renewalTiles" :key="tile.label" class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <p class="fs-12 fw-medium mb-1">{{ tile.label }}</p>
                                <h4>{{ tile.value }}</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Renewals in the period</h6></div>
                    <div class="card-body table-responsive">
                        <table class="table table-hover table-center mb-0">
                            <thead>
                                <tr><th>Membership</th><th>Member</th><th>Plan</th><th>Period</th><th class="text-end">Payable</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in data.rows" :key="row.membership_no">
                                    <td>{{ row.membership_no }}</td>
                                    <td>{{ row.member }}</td>
                                    <td>{{ row.plan }}</td>
                                    <td>{{ row.start_date }} → {{ row.end_date }}</td>
                                    <td class="text-end">{{ row.payable_amount }}</td>
                                </tr>
                                <tr v-if="!data.rows.length">
                                    <td colspan="5" class="text-center text-muted">No renewals in this period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Revenue by plan -->
            <div v-else-if="active === 'revenue-by-plan'" class="card">
                <div class="card-header d-flex justify-content-between">
                    <h6 class="mb-0 text-uppercase fs-12">Revenue by plan</h6>
                    <span class="fw-medium">Total: {{ data.total }}</span>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover table-center mb-0">
                        <thead>
                            <tr>
                                <th>Plan</th>
                                <th class="text-end">Terms</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Joining fees</th>
                                <th class="text-end">Net</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data.rows" :key="row.plan">
                                <td>{{ row.plan }}</td>
                                <td class="text-end">{{ row.terms }}</td>
                                <td class="text-end">{{ row.gross }}</td>
                                <td class="text-end">{{ row.discount }}</td>
                                <td class="text-end">{{ row.joining_fees }}</td>
                                <td class="text-end fw-medium">{{ row.net }}</td>
                            </tr>
                            <tr v-if="!data.rows.length">
                                <td colspan="6" class="text-center text-muted">Nothing sold in this period.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Attendance -->
            <div v-else-if="active === 'attendance'" class="row g-3">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between">
                            <h6 class="mb-0 text-uppercase fs-12">Visits per day</h6>
                            <span class="fs-12 text-muted">
                                {{ data.total_visits }} visits · {{ data.unique_members }} members
                                <span v-if="data.busiest_hour !== null"> · busiest at {{ data.busiest_hour }}:00</span>
                            </span>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-center mb-0">
                                <thead><tr><th>Date</th><th class="text-end">Visits</th><th class="text-end">Members</th></tr></thead>
                                <tbody>
                                    <tr v-for="row in data.per_day" :key="row.date">
                                        <td>{{ row.date }}</td>
                                        <td class="text-end">{{ row.visits }}</td>
                                        <td class="text-end">{{ row.members }}</td>
                                    </tr>
                                    <tr v-if="!data.per_day.length">
                                        <td colspan="3" class="text-center text-muted">No visits in this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Most frequent</h6></div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li
                                    v-for="row in data.most_frequent"
                                    :key="row.member_code"
                                    class="d-flex justify-content-between py-1"
                                >
                                    <span>{{ row.member }}</span>
                                    <span class="fw-medium">{{ row.visits }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { storeToRefs } from 'pinia';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { useGymReportStore } from '@/stores/admin/gym/report';

const reportStore = useGymReportStore();
const { report } = storeToRefs(reportStore);

const tabs = [
    { key: 'membership-summary', label: 'Membership summary' },
    { key: 'renewals', label: 'Renewals' },
    { key: 'revenue-by-plan', label: 'Revenue by plan' },
    { key: 'attendance', label: 'Attendance' },
];

const active = ref('membership-summary');
const range = reactive({ from: '', to: '' });

const data = computed(() => report.value.data);

const renewalTiles = computed(() => [
    { label: 'Terms sold', value: data.value?.terms_sold ?? 0 },
    { label: 'Renewals', value: data.value?.renewals ?? 0 },
    { label: 'New members', value: data.value?.new_memberships ?? 0 },
    { label: 'Renewal share', value: `${data.value?.renewal_share ?? 0}%` },
]);

const load = () => reportStore.load(active.value, range);

const select = (key) => {
    active.value = key;
    load();
};

onMounted(load);
</script>
