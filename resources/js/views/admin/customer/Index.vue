<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Customers</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Customer List</h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered">
            <thead>
            <tr>
              <th>SN</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Status</th>
              <th class="text-center">Action</th>
            </tr>
            </thead>
            <tbody class="align-middle">
            <VLoader v-if="customers.loading" :colspan="6"/>
            <template v-else-if="customers.data.length">
              <tr v-for="(customer,index) in customers.data" :key="index">
                <th>{{ index + 1 }}</th>
                <td>
                  {{ customer.name }}
                </td>
                <td>
                  {{ customer.phone }}
                </td>
                <td>
                  {{ customer.email }}
                </td>
                <td>
                  <div class="form-check form-switch">
                    <input
                        class="form-check-input"
                        @click.prevent="updateStatus(customer.id)"
                        type="checkbox"
                        :id="'switch'+index" :checked="customer.status">
                    <label class="form-check-label" :for="'switch'+index"></label>
                  </div>
                </td>
                <td style="width:60px;">
                  <button v-can="'delete_customer'" @click="deleteCustomer(customer.id)" type="button"
                          class="btn btn-sm btn-outline-danger">
                    <i class="fa fa-trash"></i>
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
</template>

<script setup>
import {onMounted} from "vue";
import Swal from "sweetalert2";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {storeToRefs} from "pinia";
import {useCustomerStore} from "@/stores/admin/customer";

const customerStore = useCustomerStore();

onMounted(() => {
  customerStore.getCustomers();
})

const {customers} = storeToRefs(customerStore);

const deleteCustomer = async (id) => {
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
        let res = await customerStore.deleteCustomer(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const updateStatus = async (id) => {
  Swal.fire({
    text: "Are you sure you want to change the status?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: "red",
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await customerStore.updateStatus(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
