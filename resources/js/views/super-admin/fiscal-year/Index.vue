<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'super-admin.dashboard'}">
                    <i class="fa fa-dashboard"> </i> Home
                </router-link>
            </li>
            <li class="breadcrumb-item active">
                Fiscal Year
            </li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">
                    Fiscal Year
                </h5>
                <button
                    type="button"
                    @click.prevent="createModalOpened=true"
                    class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-plus-circle"> Add New</i>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-responsive table-bordered">
                        <thead>
                        <tr>
                            <th>SN</th>
                            <th>Year</th>
                            <th>Code</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-center">Action</th>
                        </tr>
                        </thead>
                        <tbody class="align-middle">
                        <VLoader v-if="fiscalYears.loading" :colspan="6"/>
                        <template v-else-if="fiscalYears.data.length">
                            <tr v-for="(fiscalYear,index) in fiscalYears.data" :key="index">
                                <th>{{ index + 1 }}</th>
                                <td> {{ fiscalYear.year_name }}</td>
                                <td> {{ fiscalYear.year_code }}</td>
                                <td> {{ adToBs(fiscalYear.start_date) }}</td>
                                <td> {{ adToBs(fiscalYear.end_date) }}</td>
                                <td style="width:90px;">
                                    <button
                                        type="button"
                                        @click.prevent="edit_fiscal_year_id=fiscalYear.id"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-edit"> </i>
                                    </button>
                                    <button  @click="deleteFiscalYear(fiscalYear.id)"
                                            type="button"
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
                </div>
            </div>
        </div>
    </section>
    <CreateFiscalYear v-model:create-modal-opened="createModalOpened"/>
    <EditFiscalYear v-model:fiscal_year_id="edit_fiscal_year_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateFiscalYear from './Create.vue';
import EditFiscalYear from './Edit.vue';
import {useDateHelper} from "@/composables/dateHelper.js";
import { useFiscalYearStore } from '@/stores/super-admin/fiscal-year.js';

const {adToBs} = useDateHelper();
const fiscalYearStore = useFiscalYearStore();

onMounted(() => {
    fiscalYearStore.getFiscalYears();
})

const edit_fiscal_year_id = ref('');
const createModalOpened = ref(false);

const {fiscalYears} = storeToRefs(fiscalYearStore);

const deleteFiscalYear = async (id) => {
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
                let res = await fiscalYearStore.deleteFiscalYear(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
