<template>
    <PageHeader title="Check-ins" subtitle="Front desk — who is in today" @refresh="fetch" />

    <section class="section">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Find a member</h6></div>
                    <div class="card-body">
                        <form @submit.prevent="lookupMember">
                            <label class="form-label">Member ID or phone</label>
                            <div class="input-group">
                                <input
                                    ref="identifierInput"
                                    v-model="identifier"
                                    type="text"
                                    class="form-control"
                                    placeholder="MEM-00001"
                                    autofocus
                                />
                                <button class="btn btn-primary" type="submit" :disabled="lookup.loading">
                                    <i class="ti ti-search"></i>
                                </button>
                            </div>
                        </form>

                        <div v-if="lookup.notFound" class="alert alert-warning mt-3 mb-0 py-2 fs-12">
                            No member found with that ID or phone number.
                        </div>

                        <div v-if="found" class="mt-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <img
                                    v-if="found.member.photo_url"
                                    :src="found.member.photo_url"
                                    alt=""
                                    class="rounded-circle"
                                    width="42"
                                    height="42"
                                />
                                <span v-else class="avatar avatar-md bg-light text-dark rounded-circle">
                                    <i class="ti ti-user"></i>
                                </span>
                                <div>
                                    <div class="fw-medium">{{ found.member.name }}</div>
                                    <div class="fs-11 text-muted">{{ found.member.member_code }}</div>
                                </div>
                            </div>

                            <div v-if="found.membership" class="alert alert-light border py-2 fs-12 mb-2">
                                {{ found.membership.plan_name }} · valid to {{ found.membership.end_date }}
                                <span
                                    v-if="found.membership.days_remaining <= 7"
                                    class="d-block text-danger"
                                >{{ remainingLabel(found.membership.days_remaining) }}</span>
                            </div>
                            <div v-else class="alert alert-warning py-2 fs-12 mb-2">
                                No running membership — worth asking about a renewal.
                            </div>

                            <button
                                v-if="!found.open_check_in"
                                type="button"
                                class="btn btn-primary w-100"
                                :disabled="saving"
                                @click="checkIn"
                            >
                                <i class="ti ti-login me-1"></i>Check in
                            </button>
                            <button
                                v-else
                                type="button"
                                class="btn btn-outline-primary w-100"
                                :disabled="saving"
                                @click="checkOut(found.open_check_in.id)"
                            >
                                <i class="ti ti-logout me-1"></i>Check out
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <h6 class="mb-0 text-uppercase fs-12">Visits</h6>
                        <div style="min-width: 10rem">
                            <VDatepicker id="checkin_date" v-model="filter.date" label="Date" @update:model-value="fetch" />
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="checkIns.data"
                                :loading="checkIns.loading"
                                :pagination="false"
                            >
                                <template #bodyCell="{ column, record }">
                                    <template v-if="column.key === 'member'">
                                        <router-link
                                            :to="{ name: 'admin.gym-member-profile', params: { id: record.member_id } }"
                                            class="fw-medium"
                                        >{{ record.member_name }}</router-link>
                                        <div class="fs-11 text-muted">{{ record.member_code }}</div>
                                    </template>
                                    <template v-if="column.key === 'in'">
                                        {{ formatTime(record.checked_in_at) }}
                                        <span
                                            v-if="record.without_membership"
                                            class="badge badge-sm badge-warning ms-1"
                                        >No membership</span>
                                    </template>
                                    <template v-if="column.key === 'out'">
                                        <span v-if="record.checked_out_at">
                                            {{ formatTime(record.checked_out_at) }}
                                            <span class="fs-11 text-muted">({{ record.duration_minutes }} min)</span>
                                        </span>
                                        <span v-else class="badge badge-sm badge-success">In the gym</span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <a
                                            v-if="!record.checked_out_at"
                                            href="javascript:void(0);"
                                            @click="checkOut(record.id)"
                                        ><i class="ti ti-logout"></i></a>
                                    </template>
                                </template>
                            </a-table>

                            <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="checkIns.meta" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VPagination from '@/components/base/VPagination.vue';
import VDatepicker from '@/components/base/VDatepicker.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { useCheckInStore } from '@/stores/admin/gym/checkIn';

const checkInStore = useCheckInStore();
const { checkIns, lookup } = storeToRefs(checkInStore);

const identifier = ref('');
const identifierInput = ref(null);
const saving = ref(false);

const found = computed(() => lookup.value.data);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => checkInStore.getCheckIns({ filter }),
    defaults: { date: new Date().toISOString().slice(0, 10), page: 1, limit: 15 },
});

const columns = [
    { title: 'Member', key: 'member' },
    { title: 'In', key: 'in' },
    { title: 'Out', key: 'out' },
    { title: '', key: 'action', align: 'center' },
];

const formatTime = (value) =>
    value ? new Date(value).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';

const remainingLabel = (days) => {
    if (days < 0) return 'Membership has ended';
    if (days === 0) return 'Membership ends today';
    if (days === 1) return 'Membership ends tomorrow';
    return `${days} days left`;
};

const lookupMember = async () => {
    if (!identifier.value.trim()) {
        return;
    }

    await checkInStore.findMember(identifier.value.trim());
};

/** Clear down and put the cursor back in the box, ready for the next person. */
const resetDesk = () => {
    identifier.value = '';
    checkInStore.clearLookup();
    identifierInput.value?.focus();
};

const checkIn = async () => {
    saving.value = true;

    try {
        const res = await checkInStore.checkIn(found.value.member.id);
        toast(res.status, res.data.message);
        resetDesk();
        fetch();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};

const checkOut = async (id) => {
    saving.value = true;

    try {
        const res = await checkInStore.checkOut(id);
        toast(res.status, res.data.message);
        resetDesk();
        fetch();
    } catch (e) {
        showErrors(e);
    } finally {
        saving.value = false;
    }
};
</script>
