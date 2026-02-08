<template>
    <PageHeader title="Party List" subtitle="Manage your parties" @refresh="fetchParties">
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
            <div class="card-body">
                <VDataTable :meta="parties.meta" v-model:filter="filter">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="parties.loading" :colspan="6"/>
                        <template v-else-if="parties.data.length">
                            <tr v-for="(party,index) in parties.data" :key="index">
                                <th>{{ parties.meta.from + index }}</th>
                                <td>
                                    {{ party.name }}
                                </td>
                                <td>
                                    {{ party.code }}
                                </td>
                                <td>
                                    {{ party.type_label }}
                                </td>
                                <td>
                                    {{ party.phone }}
                                </td>
                                <td>
                                    {{ party.email }}
                                </td>
                                <td style="width:90px;">
                                    <button
                                        v-can="'edit_party'"
                                        type="button"
                                        @click.prevent="edit_party_id=party.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button v-can="'delete_party'" @click="deleteParty(party.id)" type="button"
                                            class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"> </i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6" class="text-center">
                                No Result Found.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </VDataTable>
            </div>
        </div>
    </section>
    <CreateParty v-model:create-modal-opened="createModalOpened"/>
    <EditParty v-model:party_id="edit_party_id"/>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from 'vue';
import Swal from 'sweetalert2';
import {toast} from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';
import {storeToRefs} from 'pinia';
import CreateParty from './Create.vue';
import EditParty from './Edit.vue';
import {usePartyStore} from "@/stores/admin/party.js";

const partyStore = usePartyStore();

onMounted(() => {
    fetchParties();
});

const edit_party_id = ref('');
const createModalOpened = ref(false);

const {parties} = storeToRefs(partyStore);

const filter = reactive({
    type: ''
});

watch(() => filter, () => {
    fetchParties();
}, {deep: true});

const fetchParties = () => {
    partyStore.getParties({filter});
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
