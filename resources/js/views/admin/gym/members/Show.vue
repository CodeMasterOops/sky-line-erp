<template>
    <PageHeader :title="data?.name || 'Member Profile'" :subtitle="data?.member_code" @refresh="load">
        <template #actions>
            <router-link :to="{ name: 'admin.gym-member-list' }" class="btn btn-cancel me-2">
                <i class="ti ti-arrow-left me-1"></i>Members
            </router-link>
            <router-link
                v-if="data"
                :to="{ name: 'admin.gym-member-edit', params: { id: data.id } }"
                class="btn btn-primary"
            >
                <i class="ti ti-edit me-1"></i>Edit
            </router-link>
        </template>
    </PageHeader>

    <section v-if="member.loading" class="section">
        <div class="card"><div class="card-body text-center py-5 text-muted">Loading member…</div></div>
    </section>

    <section v-else-if="data" class="section">
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="position-relative d-inline-block mb-3">
                            <img
                                v-if="data.photo_url"
                                :src="data.photo_url"
                                alt=""
                                class="rounded-circle"
                                width="120"
                                height="120"
                            />
                            <span
                                v-else
                                class="avatar avatar-xxl bg-light text-dark rounded-circle d-inline-flex align-items-center justify-content-center"
                                style="width: 120px; height: 120px"
                            >
                                <i class="ti ti-user fs-24"></i>
                            </span>
                        </div>

                        <h5 class="mb-1">{{ data.name }}</h5>
                        <p class="text-muted fs-12 mb-2">{{ data.member_code }}</p>
                        <span :class="['badge badge-sm mb-3', statusClass]">{{ data.status_label }}</span>

                        <div class="text-start mt-3">
                            <label class="form-label fs-12">Update photo</label>
                            <input type="file" class="form-control form-control-sm" accept="image/*" @change="uploadPhoto" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Profile</h6></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 fs-12 text-muted">Phone</dt>
                            <dd class="col-sm-8">{{ data.phone || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Email</dt>
                            <dd class="col-sm-8">{{ data.email || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Address</dt>
                            <dd class="col-sm-8">{{ data.address || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Date of birth</dt>
                            <dd class="col-sm-8">{{ data.date_of_birth || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Gender</dt>
                            <dd class="col-sm-8">{{ data.gender_label || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Blood group</dt>
                            <dd class="col-sm-8">{{ data.blood_group || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Occupation</dt>
                            <dd class="col-sm-8">{{ data.occupation || '—' }}</dd>

                            <dt class="col-sm-4 fs-12 text-muted">Joined on</dt>
                            <dd class="col-sm-8">{{ data.joined_on || '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Emergency contact</h6></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 fs-12 text-muted">Name</dt>
                            <dd class="col-sm-7">{{ data.emergency_contact_name || '—' }}</dd>

                            <dt class="col-sm-5 fs-12 text-muted">Phone</dt>
                            <dd class="col-sm-7">{{ data.emergency_contact_phone || '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h6 class="mb-0 text-uppercase fs-12">Health</h6></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 fs-12 text-muted">Height</dt>
                            <dd class="col-sm-7">{{ data.height_cm ? `${data.height_cm} cm` : '—' }}</dd>

                            <dt class="col-sm-5 fs-12 text-muted">Weight</dt>
                            <dd class="col-sm-7">{{ data.weight_kg ? `${data.weight_kg} kg` : '—' }}</dd>

                            <dt class="col-sm-5 fs-12 text-muted">Medical notes</dt>
                            <dd class="col-sm-7">{{ data.medical_notes || '—' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 text-uppercase fs-12">Membership history</h6>
                        <button type="button" class="btn btn-sm btn-primary" @click="openAssign">
                            <i class="ti ti-circle-plus me-1"></i>{{ current ? 'Renew' : 'Assign' }} membership
                        </button>
                    </div>
                    <div class="card-body">
                        <div v-if="history.loading" class="text-muted fs-12">Loading history…</div>
                        <div v-else-if="!history.data.length" class="text-muted fs-12">
                            No membership sold yet.
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-hover table-center mb-0">
                                <thead>
                                    <tr>
                                        <th>Membership</th>
                                        <th>Plan</th>
                                        <th>Period</th>
                                        <th class="text-end">Payable</th>
                                        <th>Invoice</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="term in history.data" :key="term.id">
                                        <td>
                                            {{ term.membership_no }}
                                            <div v-if="term.renewed_from_id" class="fs-11 text-muted">Renewal</div>
                                        </td>
                                        <td>
                                            {{ term.plan_name }}
                                            <div class="fs-11 text-muted">{{ term.duration_label }}</div>
                                        </td>
                                        <td>{{ term.start_date }} → {{ term.end_date }}</td>
                                        <td class="text-end">{{ term.payable_amount }}</td>
                                        <td>{{ term.invoice_no || '—' }}</td>
                                        <td>
                                            <span :class="['badge badge-sm', termStatusClass(term.status)]">
                                                {{ term.status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <AssignModal
        v-model:show="assignOpened"
        :member="current ? null : data"
        :membership="current"
        @saved="load"
    />
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import AssignModal from '@/views/admin/gym/memberships/AssignModal.vue';
import { useGymMemberStore } from '@/stores/admin/gym/member';
import { useMembershipStore } from '@/stores/admin/gym/membership';

const route = useRoute();
const memberStore = useGymMemberStore();
const membershipStore = useMembershipStore();
const { member } = storeToRefs(memberStore);
const { history } = storeToRefs(membershipStore);

const assignOpened = ref(false);

/** The term occupying the member's slot, if any — renewing acts on it. */
const current = computed(() =>
    history.value.data.find((term) => ['active', 'pending', 'frozen'].includes(term.status)) ?? null,
);

const termStatusClass = (status) =>
    ({
        active: 'badge-success',
        pending: 'bg-light text-dark',
        expired: 'badge-danger',
        cancelled: 'badge-danger',
        frozen: 'badge-warning',
    })[status] ?? 'bg-light text-dark';

const openAssign = () => {
    assignOpened.value = true;
};

const data = computed(() => member.value.data);

const statusClass = computed(() =>
    ({
        active: 'badge-success',
        inactive: 'bg-light text-dark',
        expired: 'badge-danger',
        frozen: 'badge-warning',
        cancelled: 'badge-danger',
    })[data.value?.status] ?? 'bg-light text-dark',
);

const load = () => Promise.all([
    memberStore.getMember(route.params.id),
    membershipStore.getMemberHistory(route.params.id),
]);

onMounted(load);

const uploadPhoto = async (event) => {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    try {
        const res = await memberStore.uploadPhoto(route.params.id, file);
        toast(res.status, res.data.message);
        await load();
    } catch (e) {
        showErrors(e);
    }
};
</script>
