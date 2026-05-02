<template>
    <div>
        <PageHeader title="Payment Modes" subtitle="Manage your payment modes" @refresh="fetchPaymentModes(true)">
            <template #actions>
                <button
                    v-can="'create_payment_mode'"
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-primary d-flex align-items-center">
                    <i class="ti ti-circle-plus me-2"></i> Add New
                </button>
            </template>
        </PageHeader>
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <settings-sidebar></settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-body">
                        <div class="table-responsive">
                            <a-table
                                class="table datanew table-hover table-center mb-0"
                                :columns="columns"
                                :data-source="paymentModes.data"
                                :loading="paymentModes.loading"
                            >
                                <template #bodyCell="{ column, record, index }">
                                    <template v-if="column.key === 'sn'">
                                        {{ index + 1 }}
                                    </template>
                                    <template v-if="column.key === 'status'">
                                        <span :class="record.is_active ? 'badge bg-success' : 'badge bg-secondary'">
                                            {{ record.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </template>
                                    <template v-if="column.key === 'action'">
                                        <div class="action-icon d-inline-flex">
                                            <a v-can="'edit_payment_mode'" class="me-2" href="javascript:void(0);"
                                               @click="edit_payment_mode_id=record.id">
                                                <i class="ti ti-edit"></i>
                                            </a>
                                            <a v-can="'delete_payment_mode'" href="javascript:void(0);"
                                               @click="deletePaymentMode(record.id)">
                                                <i class="ti ti-trash"></i>
                                            </a>
                                        </div>
                                    </template>
                                </template>
                            </a-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <CreatePaymentMode v-model:create-modal-opened="createModalOpened"/>
    <EditPaymentMode v-model:payment_mode_id="edit_payment_mode_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreatePaymentMode from './Create.vue';
import EditPaymentMode from './Edit.vue';
import {usePaymentModeStore} from '@/stores/admin/settings/payment-mode.js';

const paymentModeStore = usePaymentModeStore();

onMounted(() => {
    fetchPaymentModes();
})

const edit_payment_mode_id = ref('');
const createModalOpened = ref(false);

const {paymentModes} = storeToRefs(paymentModeStore);

const fetchPaymentModes = (refetch = false) => {
    paymentModeStore.getPaymentModes(refetch);
}

const columns = [
    {
        title: 'SN',
        key: 'sn',
        width: 60,
    },
    {
        title: 'Name',
        dataIndex: 'name',
        sorter: {
            compare: (a, b) => {
                a = a.name.toLowerCase();
                b = b.name.toLowerCase();
                return a > b ? -1 : b > a ? 1 : 0;
            },
        },
    },
    {
        title: 'Status',
        key: 'status',
        align: 'center',
    },
    {
        title: 'Action',
        key: 'action',
        align: 'center',
    },
];

const deletePaymentMode = async (id) => {
    Swal.fire({
        title: 'Are You Sure to Delete ? ',
        text: "If you delete this, it will be gone forever.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await paymentModeStore.deletePaymentMode(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
