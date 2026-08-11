<template>
    <div class="reports-hub">
        <PageHeader
            title="Reports"
            subtitle="Open a report by category. Links respect your role permissions and the modules your company runs."
            :hide-action-buttons="true"
        />

        <div class="reports-hub__search">
            <div class="search-input">
                <a class="btn-searchset" href="javascript:void(0);" tabindex="-1">
                    <i class="ti ti-search fs-14 feather-search"></i>
                </a>
                <input
                    v-model="searchQuery"
                    type="search"
                    class="form-control"
                    placeholder="Search reports…"
                />
                <button
                    v-if="searchQuery"
                    class="btn-clearsearch"
                    type="button"
                    @click="searchQuery = ''"
                >
                    <i class="ti ti-x"></i>
                </button>
            </div>
            <p v-if="searchQuery" class="reports-hub__search-meta">
                {{ totalVisible }} report{{ totalVisible !== 1 ? 's' : '' }} found
            </p>
        </div>

        <div v-if="loading" class="reports-hub__loading">
            <span class="spinner-border text-primary"></span>
        </div>

        <template v-else>
        <div v-if="pinnedReports.length && !searchQuery" class="reports-hub__pinned">
            <div class="reports-hub__pinned-head">
                <span class="reports-hub__pinned-title">
                    <i class="ti ti-pinned-filled"></i> Pinned Reports
                </span>
                <span class="reports-hub__pinned-count">{{ pinnedReports.length }}</span>
            </div>
            <div class="reports-hub__pinned-grid">
                <div
                    v-for="rep in pinnedReports"
                    :key="rep.name"
                    class="reports-hub__pinned-card"
                >
                    <router-link :to="{ name: rep.name }" class="reports-hub__pinned-link">
                        <span class="reports-hub__pinned-icon" :class="rep.accentClass">
                            <i :class="rep.icon"></i>
                        </span>
                        <span class="reports-hub__pinned-text">
                            <span class="reports-hub__pinned-label">{{ rep.label }}</span>
                            <span class="reports-hub__pinned-cat">{{ rep.category }}</span>
                        </span>
                    </router-link>
                    <button
                        type="button"
                        class="reports-hub__pinned-remove"
                        title="Unpin report"
                        aria-label="Unpin report"
                        @click="togglePin(rep.name)"
                    >
                        <i class="ti ti-x"></i>
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="filteredCategories.length > 0"
            id="reportsHubAccordion"
            class="reports-hub__grid"
        >
            <div
                v-for="(cat, idx) in filteredCategories"
                :key="cat.slug"
                class="accordion-item reports-hub__category"
            >
                <h2 class="accordion-header" :id="`heading-${cat.slug}`">
                    <button
                        class="accordion-button reports-hub__toggle bg-white"
                        :class="{ collapsed: !isFirstAndNoSearch(idx) }"
                        type="button"
                        :data-bs-toggle="searchQuery ? undefined : 'collapse'"
                        :data-bs-target="searchQuery ? undefined : `#collapse-${cat.slug}`"
                        :aria-expanded="isFirstAndNoSearch(idx) || !!searchQuery"
                        :aria-controls="`collapse-${cat.slug}`"
                    >
                        <span
                            class="reports-hub__icon"
                            :class="cat.accentClass"
                            aria-hidden="true"
                        >
                            <i :class="cat.icon"></i>
                        </span>
                        <span class="reports-hub__head-text">
                            <span class="reports-hub__category-title">{{ cat.title }}</span>
                            <span class="reports-hub__category-desc">{{ cat.description }}</span>
                        </span>
                        <span class="reports-hub__badge">{{ cat.items.length }}</span>
                    </button>
                </h2>
                <div
                    :id="`collapse-${cat.slug}`"
                    class="accordion-collapse collapse"
                    :class="{ show: isFirstAndNoSearch(idx) || !!searchQuery }"
                    :data-bs-parent="searchQuery ? undefined : '#reportsHubAccordion'"
                    :aria-labelledby="`heading-${cat.slug}`"
                >
                    <div class="accordion-body reports-hub__body">
                        <ul class="reports-hub__list list-unstyled mb-0">
                            <li
                                v-for="item in cat.items"
                                :key="item.name"
                                class="reports-hub__item"
                            >
                                <router-link
                                    class="reports-hub__link"
                                    :to="{ name: item.name }"
                                >
                                    <span
                                        v-if="searchQuery"
                                        v-html="highlight(item.label)"
                                    ></span>
                                    <template v-else>{{ item.label }}</template>
                                </router-link>
                                <button
                                    type="button"
                                    class="reports-hub__pin"
                                    :class="{ 'is-pinned': isPinned(item.name) }"
                                    :title="isPinned(item.name) ? 'Unpin report' : 'Pin report'"
                                    :aria-label="isPinned(item.name) ? 'Unpin report' : 'Pin report'"
                                    @click="togglePin(item.name)"
                                >
                                    <i :class="isPinned(item.name) ? 'ti ti-pinned-filled' : 'ti ti-pin'"></i>
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="reports-hub__empty">
            <i class="ti ti-search-off"></i>
            <p v-if="searchQuery">No reports match "<strong>{{ searchQuery }}</strong>"</p>
            <p v-else>
                No reports are available. They appear here as your company's
                modules are enabled and your role is given access.
            </p>
        </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { apiAdmin } from '@/helpers/api';
import { useReportPinnedLinksStore } from '@/stores/admin/reportPinnedLinks';
import { toast } from '@/helpers/toast.js';
import showErrors from '@/helpers/showErrors';

/**
 * The catalogue is served by GET admin/report-catalogue, already filtered by
 * BOTH the user's permissions and the company's enabled modules. It used to be
 * a hardcoded array here filtered on permissions alone, which offered every
 * report of every module — including ones the router guard immediately bounced.
 *
 * @see config/reports.php — the catalogue itself
 * @see app/Http/Controllers/Api/Admin/ReportCatalogueController.php
 */
const categories = ref([]);
const loading = ref(true);

async function loadCatalogue() {
    loading.value = true;

    try {
        const res = await apiAdmin('report-catalogue', 'get');
        categories.value = (res.data?.data ?? []).map((cat) => ({
            ...cat,
            accentClass: cat.accent_class,
        }));
    } catch (err) {
        showErrors(err);
    } finally {
        loading.value = false;
    }
}

onMounted(loadCatalogue);

const searchQuery = ref('');

const visibleCategories = computed(() => categories.value);

const filteredCategories = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return visibleCategories.value;
    return visibleCategories.value
        .map((cat) => ({
            ...cat,
            items: cat.items.filter((item) => item.label.toLowerCase().includes(q)),
        }))
        .filter((cat) => cat.items.length > 0);
});

const totalVisible = computed(() =>
    filteredCategories.value.reduce((n, cat) => n + cat.items.length, 0),
);

/* ── Pinned reports ─────────────────────────────────────── */
const reportPins = useReportPinnedLinksStore();

/**
 * Map every visible report's route name to its display metadata. Built from the
 * server-filtered categories, so a report the user can no longer reach — a
 * revoked permission, a module switched off — drops out of the pinned strip
 * automatically while the stored pin itself survives untouched, ready for the
 * day the module comes back. First occurrence of a duplicated route name wins
 * (some reports are surfaced under multiple categories).
 */
const reportIndex = computed(() => {
    const index = {};
    visibleCategories.value.forEach((cat) => {
        cat.items.forEach((item) => {
            if (!index[item.name]) {
                index[item.name] = {
                    name: item.name,
                    label: item.label,
                    category: cat.title,
                    icon: cat.icon,
                    accentClass: cat.accentClass,
                };
            }
        });
    });
    return index;
});

const pinnedReports = computed(() =>
    reportPins.links.map((name) => reportIndex.value[name]).filter(Boolean),
);

function isPinned(name) {
    return reportPins.links.includes(name);
}

function togglePin(name) {
    const wasPinned = isPinned(name);
    reportPins
        .toggle(name)
        .then(() => toast(200, wasPinned ? 'Report unpinned' : 'Report pinned'))
        .catch(showErrors);
}

function isFirstAndNoSearch(idx) {
    return idx === 0 && !searchQuery.value;
}

function highlight(label) {
    const q = searchQuery.value.trim();
    if (!q) return label;
    const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return label.replace(
        new RegExp(escaped, 'gi'),
        (match) => `<mark class="reports-hub__mark">${match}</mark>`,
    );
}
</script>

<style scoped lang="scss">
/* ── Search bar ─────────────────────────────────────────── */
.reports-hub__search {
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;

    .search-input {
        position: relative;
        width: 320px;
        max-width: 100%;

        .btn-searchset {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 2;
            display: flex;
            align-items: center;
            color: #9595b5;
            pointer-events: none;
        }

        .form-control {
            padding-left: 2rem;
            padding-right: 2.25rem;
            border-radius: 8px;
            height: 38px;
            font-size: 14px;
        }

        .btn-clearsearch {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0 4px;
            line-height: 1;
            color: #9595b5;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 13px;

            &:hover {
                color: #475569;
            }
        }
    }
}

.reports-hub__search-meta {
    font-size: 0.875rem;
    color: #64748b;
    margin: 0;
}

/* ── Single-column grid ─────────────────────────────────── */
.reports-hub__grid {
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Accordion card ─────────────────────────────────────── */
.reports-hub__category {
    border-radius: 0 !important;
    border-left: none !important;
    border-right: none !important;
    border-top: none !important;

    &:first-child {
        border-radius: 12px 12px 0 0 !important;
        border-top: 1px solid #e6e9ed !important;
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:last-child {
        border-radius: 0 0 12px 12px !important;
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:not(:first-child):not(:last-child) {
        border-left: 1px solid #e6e9ed !important;
        border-right: 1px solid #e6e9ed !important;
    }

    &:only-child {
        border-radius: 12px !important;
        border: 1px solid #e6e9ed !important;
    }
}

/* ── Toggle button ──────────────────────────────────────── */
.reports-hub__toggle {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
    box-shadow: none !important;
    min-height: 68px;

    &:not(.collapsed) {
        color: #1e293b;
        background-color: #fff !important;
    }

    &.collapsed {
        color: #1e293b;
    }

    &::after {
        margin-left: auto;
        flex-shrink: 0;
    }
}

/* ── Category icon ──────────────────────────────────────── */
.reports-hub__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.1rem;
    color: #fff;

    &.is-blue   { background: linear-gradient(145deg, #2563eb, #1d4ed8); }
    &.is-green  { background: linear-gradient(145deg, #059669, #047857); }
    &.is-orange { background: linear-gradient(145deg, #ea580c, #c2410c); }
    &.is-teal   { background: linear-gradient(145deg, #0d9488, #0f766e); }
    &.is-amber  { background: linear-gradient(145deg, #d97706, #b45309); }
    &.is-cyan   { background: linear-gradient(145deg, #0891b2, #0e7490); }
    &.is-mint   { background: linear-gradient(145deg, #16a34a, #15803d); }
    &.is-violet { background: linear-gradient(145deg, #7c3aed, #6d28d9); }
    &.is-slate  { background: linear-gradient(145deg, #475569, #334155); }
}

/* ── Title + description block ──────────────────────────── */
.reports-hub__head-text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
    text-align: left;
}

.reports-hub__category-title {
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: -0.01em;
    line-height: 1.3;
}

.reports-hub__category-desc {
    font-size: 0.8125rem;
    color: #94a3b8;
    font-weight: 400;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Count badge ────────────────────────────────────────── */
.reports-hub__badge {
    font-size: 0.72rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #64748b;
    border-radius: 20px;
    padding: 0.2em 0.65em;
    margin-right: 0.5rem;
    flex-shrink: 0;
}

/* ── Report links body ──────────────────────────────────── */
.reports-hub__body {
    padding: 0.75rem 1.25rem 1rem 1.25rem;
    border-top: 1px solid #f1f5f6;
    background: #fafbfe;
}

.reports-hub__list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0;
}

.reports-hub__list li {
    width: 25%;

    @media (max-width: 1199.98px) {
        width: 33.333%;
    }

    @media (max-width: 767.98px) {
        width: 50%;
    }

    @media (max-width: 479.98px) {
        width: 100%;
    }
}

.reports-hub__item {
    display: flex;
    align-items: center;

    &:hover .reports-hub__pin {
        opacity: 1;
    }
}

.reports-hub__link {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-decoration: none;
    line-height: 1.45;
    padding: 0.3rem 0.5rem;
    border-radius: 4px;
    transition: color 0.13s ease, background 0.13s ease;

    &::before {
        content: '';
        display: inline-block;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #cbd5e1;
        margin-right: 0.55rem;
        flex-shrink: 0;
        transition: background 0.13s ease;
    }

    &:hover {
        color: #2563eb;
        background: #eff6ff;

        &::before {
            background: #2563eb;
        }
    }
}

/* ── Per-item pin toggle ────────────────────────────────── */
.reports-hub__pin {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    margin-left: 0.15rem;
    padding: 0;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #94a3b8;
    font-size: 0.9rem;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.13s ease, color 0.13s ease, background 0.13s ease;

    &:hover {
        background: #eff6ff;
        color: #2563eb;
    }

    &.is-pinned {
        opacity: 1;
        color: #2563eb;
    }
}

/* ── Pinned reports panel ───────────────────────────────── */
.reports-hub__pinned {
    margin-bottom: 1.5rem;
    padding: 1rem 1.25rem 1.25rem;
    border: 1px solid #e6e9ed;
    border-radius: 12px;
    background: linear-gradient(180deg, #f8fbff, #fff);
}

.reports-hub__pinned-head {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.85rem;
}

.reports-hub__pinned-title {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.9375rem;
    font-weight: 700;
    color: #1e293b;

    i {
        color: #2563eb;
        font-size: 1rem;
    }
}

.reports-hub__pinned-count {
    font-size: 0.72rem;
    font-weight: 600;
    background: #e0ecff;
    color: #2563eb;
    border-radius: 20px;
    padding: 0.15em 0.6em;
}

.reports-hub__pinned-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.6rem;

    @media (max-width: 1199.98px) {
        grid-template-columns: repeat(3, 1fr);
    }

    @media (max-width: 767.98px) {
        grid-template-columns: repeat(2, 1fr);
    }

    @media (max-width: 479.98px) {
        grid-template-columns: 1fr;
    }
}

.reports-hub__pinned-card {
    position: relative;
    display: flex;
    align-items: center;

    &:hover .reports-hub__pinned-remove {
        opacity: 1;
    }
}

.reports-hub__pinned-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex: 1;
    min-width: 0;
    padding: 0.6rem 0.7rem;
    border: 1px solid #e6e9ed;
    border-radius: 10px;
    background: #fff;
    text-decoration: none;
    transition: border-color 0.13s ease, box-shadow 0.13s ease;

    &:hover {
        border-color: #bcd4ff;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.08);
    }
}

.reports-hub__pinned-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.95rem;
    color: #fff;

    &.is-blue   { background: linear-gradient(145deg, #2563eb, #1d4ed8); }
    &.is-green  { background: linear-gradient(145deg, #059669, #047857); }
    &.is-orange { background: linear-gradient(145deg, #ea580c, #c2410c); }
    &.is-teal   { background: linear-gradient(145deg, #0d9488, #0f766e); }
    &.is-amber  { background: linear-gradient(145deg, #d97706, #b45309); }
    &.is-cyan   { background: linear-gradient(145deg, #0891b2, #0e7490); }
    &.is-mint   { background: linear-gradient(145deg, #16a34a, #15803d); }
    &.is-violet { background: linear-gradient(145deg, #7c3aed, #6d28d9); }
    &.is-slate  { background: linear-gradient(145deg, #475569, #334155); }
}

.reports-hub__pinned-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.reports-hub__pinned-label {
    font-size: 0.8125rem;
    font-weight: 600;
    color: #1e293b;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.reports-hub__pinned-cat {
    font-size: 0.6875rem;
    font-weight: 500;
    color: #94a3b8;
    line-height: 1.3;
}

.reports-hub__pinned-remove {
    position: absolute;
    top: -6px;
    right: -6px;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid #e6e9ed;
    border-radius: 50%;
    background: #fff;
    color: #94a3b8;
    font-size: 0.7rem;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.13s ease, color 0.13s ease, border-color 0.13s ease;

    &:hover {
        color: #dc2626;
        border-color: #fca5a5;
    }
}

/* ── Search highlight ───────────────────────────────────── */
:deep(.reports-hub__mark) {
    background: #fef08a;
    color: inherit;
    border-radius: 2px;
    padding: 0 1px;
}

/* ── Empty state ────────────────────────────────────────── */
.reports-hub__loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
}

.reports-hub__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: #94a3b8;
    text-align: center;

    .ti-search-off {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    p {
        font-size: 0.9375rem;
        margin: 0;
        color: #64748b;

        strong {
            color: #1e293b;
        }
    }
}
</style>
