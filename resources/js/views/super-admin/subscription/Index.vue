<template>
  <PageHeader title="Subscriptions" subtitle="Manage company subscriptions" @refresh="fetchSubscriptions(true)">
    <template #actions>
      <button type="button" class="btn btn-primary" @click="assignModalOpened = true">
        <i class="ti ti-circle-plus me-1"></i>Assign Subscription
      </button>
    </template>
  </PageHeader>

  <div class="row">
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <span class="fs-14 fw-normal d-block mb-1">Estimated MRR</span>
          <h5>{{ formatPrice(summary.totalRevenue) }}</h5>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <span class="fs-14 fw-normal d-block mb-1">Total Subscribers</span>
          <h5>{{ summary.totalSubscribers }}</h5>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <span class="fs-14 fw-normal d-block mb-1">Active / Trialing</span>
          <h5>{{ summary.activeSubscribers }}</h5>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <span class="fs-14 fw-normal d-block mb-1">Cancelled / Expired</span>
          <h5>{{ summary.expiredSubscribers }}</h5>
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
              placeholder="Search company"
              @keyup.enter="fetchSubscriptions(true)"
            />
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <select v-model="filter.status" class="form-select form-select-sm" @change="fetchSubscriptions(true)">
            <option value="">All statuses</option>
            <option value="active">Active</option>
            <option value="trialing">Trialing</option>
            <option value="cancelled">Cancelled</option>
            <option value="expired">Expired</option>
          </select>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <a-table
            class="table datanew table-hover table-center mb-0"
            :columns="columns"
            :data-source="subscriptions.data"
            :loading="subscriptions.loading"
            :pagination="false"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.key === 'sn'">
                {{ (subscriptions.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
              </template>
              <template v-if="column.key === 'company'">
                {{ record.company?.company_name || '—' }}
              </template>
              <template v-if="column.key === 'plan'">
                {{ record.plan?.name || '—' }}
              </template>
              <template v-if="column.key === 'status'">
                <span :class="statusBadgeClass(record.status)">
                  {{ record.status_label }}
                </span>
              </template>
              <template v-if="column.key === 'price'">
                {{ formatPrice(record.price) }}
              </template>
              <template v-if="column.key === 'expires_at'">
                <span v-if="record.ends_at" :class="isExpired(record.ends_at) ? 'text-danger fw-semibold' : 'text-success'">
                  {{ formatDate(record.ends_at) }}
                </span>
                <span v-else class="text-muted">—</span>
              </template>
              <template v-if="column.key === 'action'">
                <div class="action-icon d-inline-flex">
                  <button
                    v-if="['active', 'trialing'].includes(record.status)"
                    type="button"
                    class="btn btn-xs btn-outline-warning me-2"
                    @click="cancelSubscription(record.id)"
                  >
                    Cancel
                  </button>
                  <button
                    v-if="['active', 'trialing'].includes(record.status)"
                    type="button"
                    class="btn btn-xs btn-outline-success"
                    @click="renewSubscription(record.id)"
                  >
                    Renew
                  </button>
                </div>
              </template>
            </template>
          </a-table>
          <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="subscriptions.meta" />
        </div>
      </div>
    </div>
  </section>

  <AssignSubscription v-model:assign-modal-opened="assignModalOpened" />
</template>

<script setup>
import {onMounted, reactive, ref, watch} from "vue";
import VPagination from '@/components/base/VPagination.vue';
import Swal from "sweetalert2";
import {storeToRefs} from "pinia";
import {toast} from "@/helpers/toast";
import showErrors from "@/helpers/showErrors";
import AssignSubscription from "./Assign.vue";
import {useSubscriptionStore} from "@/stores/super-admin/subscription";
import {formatSuperAdminMoney} from '@/helpers/formatSuperAdminMoney.js';

const subscriptionStore = useSubscriptionStore();
const {subscriptions, summary} = storeToRefs(subscriptionStore);

const assignModalOpened = ref(false);
const filter = reactive({
    search: '',
    status: '',
    page: 1,
    limit: 25,
});

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Company', key: 'company'},
    {title: 'Plan', key: 'plan'},
    {title: 'Billing', dataIndex: 'billing_cycle_label'},
    {title: 'Price', key: 'price'},
    {title: 'Status', key: 'status'},
    {title: 'Expires At', key: 'expires_at'},
    {title: 'Action', key: 'action', align: 'center'},
];

onMounted(() => {
    fetchSubscriptions();
});

const fetchSubscriptions = (refetch = false) => {
    if (refetch) {
        filter.page = 1;
    }
    subscriptionStore.getSubscriptions({filter});
};

watch(() => [filter.page, filter.limit], () => {
    fetchSubscriptions();
});

const formatPrice = formatSuperAdminMoney;

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'});
};

const isExpired = (dateStr) => {
    if (!dateStr) return false;
    return new Date(dateStr) < new Date();
};

const statusBadgeClass = (status) => {
    const map = {
        active: 'badge badge-success badge-sm',
        trialing: 'badge badge-info badge-sm',
        cancelled: 'badge badge-warning badge-sm',
        expired: 'badge badge-danger badge-sm',
    };

    return map[status] || 'badge badge-secondary badge-sm';
};

const cancelSubscription = async (id) => {
    Swal.fire({
        title: 'Cancel this subscription?',
        input: 'textarea',
        inputPlaceholder: 'Optional notes',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Cancel subscription',
    }).then(async (result) => {
        if (result.value !== undefined && result.isConfirmed) {
            try {
                const res = await subscriptionStore.cancelSubscription(id, {notes: result.value || ''});
                toast(res.status, res.data.message);
            } catch (e) {
                showErrors(e);
            }
        }
    });
};

const renewSubscription = async (id) => {
    try {
        const res = await subscriptionStore.renewSubscription(id, {});
        toast(res.status, res.data.message);
    } catch (e) {
        showErrors(e);
    }
};
</script>
