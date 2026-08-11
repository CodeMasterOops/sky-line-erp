<template>
    <PageHeader title="Members" subtitle="Everyone registered at this branch" @refresh="fetch">
        <template #actions>
            <router-link :to="{ name: 'admin.gym-member-create' }" class="btn btn-primary">
                <i class="ti ti-circle-plus me-1"></i>Register Member
            </router-link>
        </template>
    </PageHeader>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
                <div class="search-set">
                    <div class="search-input">
                        <a href="javascript:void(0);" class="btn-searchset">
                            <i class="ti ti-search fs-14 feather-search"></i>
                        </a>
                        <input
                            v-model="filter.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Name, phone or member ID"
                            @input="debouncedFetch"
                        />
                    </div>
                </div>

                <select v-model="filter.status" class="form-select form-select-sm w-auto" @change="fetch">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="expired">Expired</option>
                    <option value="frozen">Frozen</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="members.data"
                        :loading="members.loading"
                        :pagination="false"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (members.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-if="column.key === 'member'">
                                <div class="d-flex align-items-center gap-2">
                                    <img
                                        v-if="record.photo_url"
                                        :src="record.photo_url"
                                        alt=""
                                        class="rounded-circle"
                                        width="36"
                                        height="36"
                                    />
                                    <span v-else class="avatar avatar-sm bg-light text-dark rounded-circle">
                                        <i class="ti ti-user"></i>
                                    </span>
                                    <div>
                                        <router-link
                                            :to="{ name: 'admin.gym-member-profile', params: { id: record.id } }"
                                            class="fw-medium"
                                        >{{ record.name }}</router-link>
                                        <div class="fs-11 text-muted">{{ record.member_code }}</div>
                                    </div>
                                </div>
                            </template>
                            <template v-if="column.key === 'contact'">
                                <div>{{ record.phone || '—' }}</div>
                                <div class="fs-11 text-muted">{{ record.email }}</div>
                            </template>
                            <template v-if="column.key === 'status'">
                                <span :class="['badge badge-sm', statusClass(record.status)]">
                                    {{ record.status_label }}
                                </span>
                            </template>
                            <template v-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <router-link
                                        :to="{ name: 'admin.gym-member-profile', params: { id: record.id } }"
                                        class="me-2"
                                    ><i class="ti ti-eye"></i></router-link>
                                    <router-link
                                        :to="{ name: 'admin.gym-member-edit', params: { id: record.id } }"
                                        class="me-2"
                                    ><i class="ti ti-edit"></i></router-link>
                                    <a href="javascript:void(0);" @click="destroy(record)"><i class="ti ti-trash"></i></a>
                                </div>
                            </template>
                        </template>
                    </a-table>

                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="members.meta" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import { storeToRefs } from 'pinia';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import VPagination from '@/components/base/VPagination.vue';
import { usePaginatedList } from '@/composables/usePaginatedList.js';
import { useGymMemberStore } from '@/stores/admin/gym/member';

const memberStore = useGymMemberStore();
const { members } = storeToRefs(memberStore);

const { filter, fetch } = usePaginatedList({
    fetchFn: ({ filter }) => memberStore.getMembers({ filter }),
    defaults: { search: '', status: '', page: 1, limit: 10 },
});

const columns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Member', key: 'member' },
    { title: 'Contact', key: 'contact' },
    { title: 'Joined', dataIndex: 'joined_on', key: 'joined_on' },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action', align: 'center' },
];

const statusClass = (status) =>
    ({
        active: 'badge-success',
        inactive: 'bg-light text-dark',
        expired: 'badge-danger',
        frozen: 'badge-warning',
        cancelled: 'badge-danger',
    })[status] ?? 'bg-light text-dark';

const debouncedFetch = debounce(() => {
    const onFirstPage = filter.page === 1;
    filter.page = 1;
    if (onFirstPage) {
        fetch();
    }
}, 300);

const destroy = (record) => {
    Swal.fire({
        title: `Delete ${record.name}?`,
        text: 'Members with membership history cannot be deleted — mark them inactive instead.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (!result.value) {
            return;
        }

        try {
            const res = await memberStore.deleteMember(record.id);
            toast(res.status, res.data.message);
            fetch();
        } catch (e) {
            showErrors(e);
        }
    });
};
</script>
