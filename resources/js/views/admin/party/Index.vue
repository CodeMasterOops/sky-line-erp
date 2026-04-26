<template>
    <PageHeader :title="pageTitle" :subtitle="pageSubtitle" @refresh="fetchParties">
        <template #actions>
            <button
                v-can="'create_party'"
                type="button"
                @click.prevent="createModalOpened=true"
                class="btn btn-primary d-flex align-items-center">
                <i class="ti ti-circle-plus me-2"></i> Add New
            </button>
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
                            type="search"
                            v-model="filter.search"
                            class="form-control form-control-sm"
                            placeholder="Search party"
                            @input="debouncedFetch"
                        >
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="parties.data"
                        :pagination="pagination"
                        :loading="parties.loading"
                        @change="handleTableChange"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (parties.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <div class="action-icon d-inline-flex">
                                    <a class="me-2" href="javascript:void(0);"
                                       @click="edit_party_id=record.id">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a href="javascript:void(0);"
                                       @click="deleteParty(record.id)">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>
    </section>
    <CreateParty v-model:create-modal-opened="createModalOpened" :type="filter.type || undefined"/>
    <EditParty v-model:party_id="edit_party_id"/>
</template>

<script setup>
import {computed, reactive, ref, watch} from 'vue';
import {useRoute} from 'vue-router';
import debounce from 'lodash/debounce';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import CreateParty from './Create.vue';
import EditParty from './Edit.vue';
import {usePartyStore} from "@/stores/admin/party.js";

const VALID_PARTY_TYPES = ['customer', 'supplier', 'lead'];

function normalizePartyTypeFromQuery(queryType) {
    const t = Array.isArray(queryType) ? queryType[0] : queryType;
    if (t && VALID_PARTY_TYPES.includes(t)) {
        return t;
    }
    return '';
}

const partyStore = usePartyStore();
const route = useRoute();

const edit_party_id = ref('');
const createModalOpened = ref(false);

const {parties} = storeToRefs(partyStore);

const filter = reactive({
    type: '',
    search: '',
    page: 1,
    limit: 10
});

const fetchParties = () => {
    partyStore.getParties({filter});
};

const debouncedFetch = debounce(() => {
    filter.page = 1;
    fetchParties();
}, 300);

const pageTitle = computed(() => {
    switch (filter.type) {
        case 'customer':
            return 'Customers';
        case 'supplier':
            return 'Suppliers';
        case 'lead':
            return 'Leads';
        default:
            return 'Party list';
    }
});

const pageSubtitle = computed(() => {
    switch (filter.type) {
        case 'customer':
            return 'People and businesses you sell to';
        case 'supplier':
            return 'Vendors you purchase from';
        case 'lead':
            return 'Prospects before they become customers';
        default:
            return 'Manage your parties';
    }
});

watch(
    () => route.query.type,
    () => {
        filter.type = normalizePartyTypeFromQuery(route.query.type);
        filter.page = 1;
        fetchParties();
    },
    { immediate: true }
);

const pagination = computed(() => ({
    total: parties.value.meta.total || 0,
    current: parties.value.meta.current_page || 1,
    pageSize: parties.value.meta.per_page || filter.limit,
    showSizeChanger: true,
    showQuickJumper: true,
}));

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Name',
        dataIndex: 'name',
        sorter: true,
    },
    {
        title: 'Code',
        dataIndex: 'code',
        sorter: true,
    },
    {
        title: 'Type',
        dataIndex: 'type_label',
        sorter: true,
    },
    {
        title: 'Phone',
        dataIndex: 'phone',
    },
    {
        title: 'Email',
        dataIndex: 'email',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const handleTableChange = (pagination) => {
    filter.page = pagination.current;
    filter.limit = pagination.pageSize;
    fetchParties();
};

const deleteParty = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: 'If you delete this, it will be gone forever.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes'
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await partyStore.deleteParty(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
