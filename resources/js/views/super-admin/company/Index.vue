<template>
    <div class="page-title">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <router-link :to="{name:'super-admin.dashboard'}">
                    <i class="fa fa-home"> Home</i>
                </router-link>
            </li>
            <li class="breadcrumb-item active">Company</li>
        </ol>
    </div>

    <section class="section">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5 class="card-title">Company List</h5>
                <router-link :to="{name:'super-admin.company-create'}">
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="fa fa-plus-circle"> Add New</i>
                    </button>
                </router-link>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <VDataTable :meta="companies.meta" v-model:filter="filter">
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>SN</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <VLoader v-if="companies.loading" :colspan="6"/>
                            <template v-else-if="companies.data.length">
                                <tr v-for="(company,index) in companies.data" :key="index">
                                    <th>{{ companies.meta.from + index }}</th>
                                    <td>{{ company.company_name }}</td>
                                    <td>{{ company.code }}</td>
                                    <td>{{ company.email }}</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input"
                                                   @click.prevent="updateCompanyStatus(company.id)"
                                                   type="checkbox"
                                                   :id="'switch'+index" :checked="company.is_active">
                                            <label class="form-check-label" :for="'switch'+index"></label>
                                        </div>
                                    </td>
                                    <td style="width: 140px;text-align: center;">
                                        <button type="button" @click="loginToCompany(company.id)"
                                                class="btn btn-xs btn-outline-primary"
                                                title="Company login">
                                            <i class="fa fa-sign-in"></i>
                                        </button>
                                        <button @click="reset_company_password=company.id" type="button"
                                                class="btn btn-xs btn-outline-warning" title="Password Reset">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                        <router-link
                                            :to="{name:'super-admin.company-edit',params:{id:company.id}}"
                                            class="btn btn-xs btn-outline-primary">
                                            <i class="fa fa-edit"></i>
                                        </router-link>
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
        </div>
    </section>
    <RestPassword v-model:reset_password="reset_company_password"/>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useCompanyStore} from "@/stores/super-admin/company";
import {storeToRefs} from "pinia";
import RestPassword from './ResetPassword.vue'
import {useRouter} from "vue-router";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth.js";
import Swal from "sweetalert2";

const companyStore = useCompanyStore();
const authStore = useSuperAdminAuthStore();
const router = useRouter();

const filter = reactive({
    limit: 25
});

const reset_company_password = ref('');

onMounted(() => {
    fetchCompanies();
})

watch(() => filter, () => {
    fetchCompanies();
}, {deep: true})

const fetchCompanies = () => {
    companyStore.getCompanies({filter});
}

const {companies} = storeToRefs(companyStore);

const updateCompanyStatus = async (id) => {
    Swal.fire({
        title: 'Are You Sure to change status ? ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await companyStore.updateStatus(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e)
            }
        }
    });
}

const loginToCompany = async (id) => {
    Swal.fire({
        title: 'Are You Sure to login ? ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: "Yes",
    }).then(async (result) => {
        if (result.value) {
            try {
                let res = await authStore.companyLogin(id);
                toast(res.status, res.data.message);
                const {href: companyLoginUrl} = router.resolve({name: 'admin.dashboard'})
                window.open(companyLoginUrl, '_blank');
            } catch (e) {
                showErrors(e)
            }
        }
    });
}
</script>
