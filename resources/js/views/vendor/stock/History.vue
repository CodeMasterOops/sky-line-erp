<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'vendor.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Stock History</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Stock History</h5>
        <router-link
            :to="{name:'vendor.stock-list'}"
            class="btn btn-sm btn-outline-primary">
          <i class="fa fa-list"> Stock List</i>
        </router-link>
      </div>
      <div class="card-body">
        <VDataTable :meta="stockHistories.meta" v-model:filter="filter">
          <table class="table table-bordered">
            <thead>
            <tr class="align-middle">
              <th>SN</th>
              <th>Adjust Type</th>
              <th>Qty</th>
              <th>Remarks</th>
              <th>Created at</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="stockHistories.loading" :colspan="5"/>
            <template v-else-if="stockHistories.data.length">
              <tr v-for="(history,index) in stockHistories.data" :key="index">
                <th>
                  {{ stockHistories.meta.from + index }}
                </th>
                <td>{{ history.type }}</td>
                <td>{{ history.quantity }}</td>
                <td>{{ history.remarks }}</td>
                <td>{{ history.created_at }}</td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="5" class="text-center">
                No Result Found.
              </td>
            </tr>
            </tbody>
          </table>
        </VDataTable>
      </div>
    </div>
  </section>
</template>

<script setup>
import {onMounted, reactive, watch} from "vue";
import {storeToRefs} from "pinia";
import {useRoute} from "vue-router";
import {useVendorStockStore} from "@/stores/vendor/vendor-stock.js";

const route = useRoute();
const stockStore = useVendorStockStore();

onMounted(() => {
  fetchStockHistory();
})

const filter = reactive({})

watch(() => filter, () => {
  fetchStockHistory();
}, {deep: true})

const fetchStockHistory = () => {
  stockStore.getStockHistories({variant_id: route.params.id, filter})
}

const {stockHistories} = storeToRefs(stockStore);
</script>
