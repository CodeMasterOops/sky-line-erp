<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Units</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Unit List</h5>
        <button
            v-can="'create_unit'"
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
              <th>Name</th>
              <th>Code</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="units.loading" :colspan="4"/>
            <template v-else-if="units.data.length">
              <tr v-for="(unit,index) in units.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>
                  {{ unit.name }}
                </td>
                  <td>
                  {{ unit.code }}
                </td>
                <td style="width:90px;">
                  <button
                      v-can="'edit_unit'"
                      type="button"
                      @click.prevent="edit_unit_id=unit.id"
                      class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-edit"> </i>
                  </button>
                  <button v-can="'delete_unit'" @click="deleteUnit(unit.id)" type="button"
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
  <CreateUnit v-model:create-modal-opened="createModalOpened"/>
  <EditUnit v-model:unit_id="edit_unit_id"/>
</template>

<script setup>
import {onMounted, ref} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import CreateUnit from './Create.vue';
import EditUnit from './Edit.vue';
import { useUnitStore } from '@/stores/admin/inventory/unit.js';

const unitStore = useUnitStore();

onMounted(() => {
  unitStore.getUnits();
})

const edit_unit_id = ref('');
const createModalOpened = ref(false);

const {units} = storeToRefs(unitStore);

const deleteUnit = async (id) => {
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
        let res = await unitStore.deleteUnit(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
