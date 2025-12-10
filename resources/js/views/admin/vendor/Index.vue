<template>
  <div class="page-title">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <router-link :to="{name:'admin.dashboard'}">
          <i class="fa fa-home"> Home</i>
        </router-link>
      </li>
      <li class="breadcrumb-item active">Vendor</li>
    </ol>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">Vendor List</h5>
        <router-link v-can="'create_vendor'" :to="{name:'admin.vendor-create'}">
          <button class="btn btn-sm btn-outline-primary">
            <i class="fa fa-plus-circle"> Add New</i>
          </button>
        </router-link>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <VDataTable :meta="vendors.meta" v-model:filter="filter">
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
              <tbody>
              <VLoader v-if="vendors.loading" :colspan="6"/>
              <template v-else-if="vendors.data.length">
                <tr v-for="(vendor,index) in vendors.data" :key="index">
                  <th>{{ vendors.meta.from + index }}</th>
                  <td>
                    <i v-if="vendor.vendor_status==='verified'" class="fa fa-check-circle text-success"
                       title="Verified"></i>
                    <i v-else-if="vendor.vendor_status==='rejected'" class="fa fa-times-circle text-danger"
                       title="Rejected"></i>
                    <i v-else class="fa fa-hourglass-half text-warning" title="Pending"></i>
                    {{ vendor.vendor_name }}
                  </td>
                  <td>{{ vendor.phone }}</td>
                  <td>{{ vendor.email }}</td>
                  <td>
                    <div class="form-check form-switch">
                      <input class="form-check-input"
                             @click.prevent="updateVendorStatus(vendor.id)"
                             type="checkbox"
                             :id="'switch'+index" :checked="vendor.is_active">
                      <label class="form-check-label" :for="'switch'+index"></label>
                    </div>
                  </td>
                  <td style="width: 160px;text-align: center;">
                    <button v-if="vendor.vendor_status==='pending'" v-can="'verify_vendor_account'" type="button"
                            @click="verifyVendorAccount(vendor.id)"
                            class="btn btn-xs btn-outline-success"
                            title="Mark as verified">
                      <i class="fa fa-check-square"></i>
                    </button>
                    <button v-if="vendor.vendor_status==='pending'" v-can="'verify_vendor_account'" type="button"
                            @click="rejectVendorAccount(vendor.id)"
                            class="btn btn-xs btn-outline-warning"
                            title="Mark as rejected">
                      <i class="fa fa-ban"></i>
                    </button>
                    <button v-can="'login_vendor'" type="button" @click="loginToVendor(vendor.id)"
                            class="btn btn-xs btn-outline-primary"
                            title="Vendor login">
                      <i class="fa fa-sign-in"></i>
                    </button>
                    <button v-can="'reset_vendor_password'" @click="reset_vendor_password=vendor.id" type="button"
                            class="btn btn-xs btn-outline-warning" title="Password Reset">
                      <i class="fa fa-refresh"></i>
                    </button>
                    <router-link
                        v-can="'edit_vendor'"
                        :to="{name:'admin.vendor-edit',params:{id:vendor.id}}"
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
  <RestPassword v-model:reset_password="reset_vendor_password"/>
</template>

<script setup>
import {onMounted, reactive, ref, watch} from "vue";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import {useVendorStore} from "@/stores/admin/vendor";
import {storeToRefs} from "pinia";
import RestPassword from './ResetPassword.vue'
import {useRouter} from "vue-router";
import Swal from "sweetalert2";

const vendorStore = useVendorStore();
const router = useRouter();

const filter = reactive({
  limit: 25
});

const reset_vendor_password = ref('');

onMounted(() => {
  fetchVendors();
})

watch(() => filter, () => {
  fetchVendors();
}, {deep: true})

const fetchVendors = () => {
  vendorStore.getVendors({filter});
}

const {vendors} = storeToRefs(vendorStore);

const updateVendorStatus = async (id) => {
  Swal.fire({
    text: "Are you sure you want to change the status?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: "red",
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await vendorStore.updateStatus(id);
        toast(res.status, res.data.message);
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const loginToVendor = async (id) => {
  Swal.fire({
    title: 'Are You Sure to login ? ',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await vendorStore.vendorLogin(id);
        toast(res.status, res.data.message);
        const {href: vendorLoginUrl} = router.resolve({name: 'vendor.dashboard'})
        window.open(vendorLoginUrl, '_blank');
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const verifyVendorAccount = async (id) => {
  Swal.fire({
    title: 'Please confirm: Verify this vendor?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: "Yes",
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await vendorStore.verify(id);
        toast(res.status, res.data.message);
        fetchVendors();
      } catch (e) {
        showErrors(e)
      }
    }
  });
}

const rejectVendorAccount = async (id) => {
  Swal.fire({
    title: 'Are you sure you want to reject this vendor?',
    input: 'text',
    inputLabel: 'Reason for rejection',
    inputPlaceholder: 'Add reason for rejection',
    showCancelButton: true,
    confirmButtonColor: 'red',
    confirmButtonText: "Yes",
    inputValidator: (value) => {
      if (!value) {
        return 'Please add rejection reason'
      }
    }
  }).then(async (result) => {
    if (result.value) {
      try {
        let res = await vendorStore.reject(id, result.value);
        toast(res.status, res.data.message);
        fetchVendors();
      } catch (e) {
        showErrors(e)
      }
    }
  });
}
</script>
