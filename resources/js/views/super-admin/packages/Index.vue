<template>
  <PageHeader title="Packages" subtitle="Manage subscription plans" @refresh="fetchPlans(true)">
    <template #actions>
      <button
        type="button"
        class="btn btn-primary"
        @click.prevent="createModalOpened = true"
      >
        <i class="ti ti-circle-plus me-1"></i>Add Plan
      </button>
    </template>
  </PageHeader>

  <div class="row">
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <p class="fs-12 fw-medium mb-1">Total Plans</p>
            <h4>{{ stats.total }}</h4>
          </div>
          <span class="avatar avatar-lg bg-primary flex-shrink-0">
            <i class="ti ti-box fs-16"></i>
          </span>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <p class="fs-12 fw-medium mb-1">Active Plans</p>
            <h4>{{ stats.active }}</h4>
          </div>
          <span class="avatar avatar-lg bg-success flex-shrink-0">
            <i class="ti ti-activity-heartbeat fs-16"></i>
          </span>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <p class="fs-12 fw-medium mb-1">Inactive Plans</p>
            <h4>{{ stats.inactive }}</h4>
          </div>
          <span class="avatar avatar-lg bg-danger flex-shrink-0">
            <i class="ti ti-player-pause fs-16"></i>
          </span>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div>
            <p class="fs-12 fw-medium mb-1">Paid Plans</p>
            <h4>{{ stats.billingTypes }}</h4>
          </div>
          <span class="avatar avatar-lg bg-skyblue flex-shrink-0">
            <i class="ti ti-mask fs-16"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <section class="section">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
        <div class="search-set">
          <div class="search-input">
            <a href="javascript:void(0);" class="btn-searchset">
              <i class="ti ti-search fs-14 feather-search"></i>
            </a>
            <input
              v-model="filter.search"
              type="search"
              class="form-control form-control-sm"
              placeholder="Search plans"
              @keyup.enter="fetchPlans(true)"
            />
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <a-table
            class="table datanew table-hover table-center mb-0"
            :columns="columns"
            :data-source="plans.data"
            :loading="plans.loading"
            :pagination="false"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.key === 'sn'">
                {{ (plans.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
              </template>
              <template v-if="column.key === 'price'">
                {{ formatPrice(record.price_monthly) }} / {{ formatPrice(record.price_yearly) }}
              </template>
              <template v-if="column.key === 'status'">
                <span :class="['badge badge-sm', record.is_active ? 'badge-success' : 'badge-danger']">
                  {{ record.is_active ? 'Active' : 'Inactive' }}
                </span>
                <span v-if="record.is_default" class="badge badge-info badge-sm ms-1">Default</span>
                <span v-if="record.is_recommended" class="badge badge-warning badge-sm ms-1">Recommended</span>
              </template>
              <template v-if="column.key === 'branch_limit'">
                {{ record.branch_limit ?? 'Unlimited' }}
              </template>
              <template v-if="column.key === 'subscribers'">
                {{ record.active_subscriptions_count ?? 0 }}
              </template>
              <template v-if="column.key === 'action'">
                <div class="action-icon d-inline-flex">
                  <a href="javascript:void(0);" class="me-2" @click="editPlanId = record.id">
                    <i class="ti ti-edit"></i>
                  </a>
                  <a
                    v-if="!record.is_default"
                    href="javascript:void(0);"
                    @click="deletePlan(record.id)"
                  >
                    <i class="ti ti-trash"></i>
                  </a>
                </div>
              </template>
            </template>
          </a-table>
          <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="plans.meta" />
        </div>
      </div>
    </div>
  </section>

  <CreatePlan v-model:create-modal-opened="createModalOpened" />
  <EditPlan v-model:plan-id="editPlanId" />
</template>

<script setup>
import {onMounted, reactive, ref, watch} from "vue";
import VPagination from '@/components/base/VPagination.vue';
import Swal from "sweetalert2";
import {storeToRefs} from "pinia";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import CreatePlan from "./Create.vue";
import EditPlan from "./Edit.vue";
import {usePlanStore} from "@/stores/super-admin/plan";
import {formatSuperAdminMoney} from '@/helpers/formatSuperAdminMoney.js';

const planStore = usePlanStore();
const {plans, stats} = storeToRefs(planStore);

const createModalOpened = ref(false);
const editPlanId = ref('');
const filter = reactive({
    search: '',
    page: 1,
    limit: 25,
});

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Plan Name', dataIndex: 'name', key: 'name'},
    {title: 'Monthly / Yearly', key: 'price'},
    {title: 'Branch Limit', key: 'branch_limit', align: 'center'},
    {title: 'Subscribers', key: 'subscribers'},
    {title: 'Status', key: 'status'},
    {title: 'Action', key: 'action', align: 'center'},
];

onMounted(() => {
    fetchPlans();
});

const fetchPlans = (refetch = false) => {
    if (refetch) {
        filter.page = 1;
    }
    planStore.getPlans({filter});
};

watch(() => [filter.page, filter.limit], () => {
    fetchPlans();
});

const formatPrice = formatSuperAdminMoney;

const deletePlan = async (id) => {
    Swal.fire({
        title: 'Delete this plan?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'red',
        confirmButtonText: 'Yes, delete',
    }).then(async (result) => {
        if (result.value) {
            try {
                const res = await planStore.deletePlan(id);
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};
</script>
