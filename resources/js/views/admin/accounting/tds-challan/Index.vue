<template>
    <PageHeader title="TDS Deposit Challan & Certificate" subtitle="Generate IRD TDS deposit challan and withholding certificates" />

    <!-- Filters -->
    <div class="card border-0 mb-3">
        <div class="card-body pb-1">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" v-model="filters.start_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" v-model="filters.end_date" />
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button class="btn btn-primary w-100" @click="loadSummary" :disabled="loading || !filters.start_date || !filters.end_date">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="ti ti-report me-1"></i>
                            Generate Summary
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <button
                            class="btn btn-outline-danger w-100"
                            @click="downloadChallan"
                            :disabled="downloadingChallan || !rows.length"
                        >
                            <span v-if="downloadingChallan" class="spinner-border spinner-border-sm me-1"></span>
                            <i v-else class="ti ti-file-download me-1"></i>
                            Download Challan PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div v-if="summary" class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 bg-primary-subtle text-center p-3">
                <h6 class="text-muted mb-1">Total Base Amount</h6>
                <h4 class="fw-bold mb-0">NPR {{ fmt(summary.total_base) }}</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-danger-subtle text-center p-3">
                <h6 class="text-muted mb-1">Total TDS Payable</h6>
                <h4 class="fw-bold text-danger mb-0">NPR {{ fmt(summary.total_tds) }}</h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 bg-success-subtle text-center p-3">
                <h6 class="text-muted mb-1">Net Amount After TDS</h6>
                <h4 class="fw-bold text-success mb-0">NPR {{ fmt((summary.total_base || 0) - (summary.total_tds || 0)) }}</h4>
            </div>
        </div>
    </div>

    <!-- TDS Deduction Table -->
    <div class="card border-0 mb-3">
        <div class="card-header">
            <h6 class="mb-0">TDS Deduction Details</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Party Name</th>
                            <th>PAN</th>
                            <th>TDS Category</th>
                            <th class="text-end">Rate %</th>
                            <th class="text-end">Base Amount</th>
                            <th class="text-end">TDS Amount</th>
                            <th>Period</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length && !loading">
                            <td colspan="9" class="text-center text-muted py-4">
                                Select a date range and click Generate Summary.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in rows" :key="idx">
                            <td>{{ idx + 1 }}</td>
                            <td class="fw-medium">{{ row.party_name || '—' }}</td>
                            <td><code>{{ row.party_pan || '—' }}</code></td>
                            <td>{{ row.tds_category }}</td>
                            <td class="text-end">{{ row.tds_rate }}%</td>
                            <td class="text-end">NPR {{ fmt(row.base_amount) }}</td>
                            <td class="text-end text-danger fw-semibold">NPR {{ fmt(row.tds_amount) }}</td>
                            <td>{{ row.period_month || '—' }}</td>
                            <td>
                                <button
                                    v-if="row.party_id"
                                    class="btn btn-xs btn-outline-primary"
                                    @click="downloadCertificate(row.party_id)"
                                    :disabled="downloadingCert === row.party_id"
                                    title="Download withholding certificate"
                                >
                                    <span v-if="downloadingCert === row.party_id" class="spinner-border spinner-border-sm"></span>
                                    <i v-else class="ti ti-certificate"></i>
                                </button>
                                <span v-else class="text-muted small">—</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length">
                        <tr class="table-light fw-bold">
                            <td colspan="5" class="text-end">TOTAL</td>
                            <td class="text-end">NPR {{ fmt(summary?.total_base) }}</td>
                            <td class="text-end text-danger">NPR {{ fmt(summary?.total_tds) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Certificate Generation -->
    <div v-if="rows.length" class="card border-0">
        <div class="card-header">
            <h6 class="mb-0">Generate Individual Withholding Certificate</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Generate an Income Tax Act 2058 §87/88 withholding certificate for a specific vendor for the selected period.
            </p>
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Select Party</label>
                    <vue-select
                        v-model="certPartyId"
                        :options="partyOptions"
                        :reduce="opt => opt.value"
                        placeholder="Search party..."
                    />
                </div>
                <div class="col-md-3">
                    <button
                        class="btn btn-outline-success"
                        @click="downloadCertificate(certPartyId)"
                        :disabled="!certPartyId || downloadingCert === certPartyId"
                    >
                        <span v-if="downloadingCert === certPartyId" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ti ti-certificate me-1"></i>
                        Download Certificate PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { apiAdmin, downloadAdminFile } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

const filters = ref({ start_date: null, end_date: null });
const loading = ref(false);
const downloadingChallan = ref(false);
const downloadingCert = ref(null);
const rows = ref([]);
const summary = ref(null);
const certPartyId = ref(null);

const partyOptions = computed(() => {
    const seen = new Set();
    return rows.value
        .filter(r => r.party_id && !seen.has(r.party_id) && seen.add(r.party_id))
        .map(r => ({ label: `${r.party_name} (${r.party_pan || 'No PAN'})`, value: r.party_id }));
});

const fmt = (val) => Number(val || 0).toLocaleString('en-NP', { minimumFractionDigits: 2 });

const loadSummary = async () => {
    loading.value = true;
    try {
        const res = await apiAdmin('nepal/tds/summary', 'get', filters.value);
        const d = res.data.data;
        rows.value = d.rows || [];
        summary.value = d.summary;
    } catch (e) {
        showErrors(e);
    } finally {
        loading.value = false;
    }
};

const downloadChallan = async () => {
    downloadingChallan.value = true;
    try {
        const filename = `TDS-Challan-${filters.value.start_date}-to-${filters.value.end_date}.pdf`;
        await downloadAdminFile('nepal/tds/challan-pdf', filename, {
            ...filters.value,
            deposit_date: new Date().toISOString().split('T')[0],
        });
    } catch (e) {
        showErrors(e);
    } finally {
        downloadingChallan.value = false;
    }
};

const downloadCertificate = async (partyId) => {
    if (!partyId) return;
    downloadingCert.value = partyId;
    try {
        const filename = `TDS-Certificate-${partyId}-${filters.value.start_date}.pdf`;
        await downloadAdminFile('nepal/tds/certificate-pdf', filename, {
            party_id: partyId,
            ...filters.value,
        });
    } catch (e) {
        showErrors(e);
    } finally {
        downloadingCert.value = null;
    }
};
</script>
