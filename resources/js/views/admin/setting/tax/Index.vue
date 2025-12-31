<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Tax</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Tax List</h5>
        <button
            v-can="'create_tax'"
            type="button"
            @click.prevent="createModalOpened=true"
            class="btn btn-sm btn-outline-primary">
          <i class="fa fa-plus-circle"> Add New</i>
        </button>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Name</th>
              <th>Rate (%)</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="taxes.loading" :colspan="4"/>
            <template v-else-if="taxes.data.length">
              <tr v-for="(tax,index) in taxes.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>
                  {{ tax.name }}
                </td>
                  <td>
                  {{ tax.rate }}
                </td>
                <td style="width:90px;">
                  <button
                      v-can="'edit_tax'"
                      type="button"
                      @click.prevent="edit_tax_id=tax.id"
                      class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-edit"> </i>
                  </button>
                  <button v-can="'delete_tax'" @click="deleteTax(tax.id)" type="button"
                          class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-trash"> </i>
                  </button>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="4" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
  <CreateTax v-model:create-modal-opened="createModalOpened"/>
  <EditTax v-model:tax_id="edit_tax_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateTax from './Create.vue';
import EditTax from './Edit.vue';
import { useTaxStore } from '@/stores/admin/setting/tax.js';

const taxStore = useTaxStore();

onMounted(() => {
  taxStore.getTaxes();
})

const edit_tax_id = ref('');
const createModalOpened = ref(false);

const {taxes} = storeToRefs(taxStore);

const deleteTax = async (id) => {
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
        let res = await taxStore.deleteTax(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
