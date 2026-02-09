<template>
  <PageHeader title="Subscriptions" subtitle="Manage your subscriptions" />

  <div class="row">
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <div class="border-bottom pb-3 mb-3">
            <div class="row align-items-center">
              <div class="col-7">
                <div>
                  <span class="fs-14 fw-normal text-truncate mb-1">Total Transaction</span>
                  <h5>$5,340</h5>
                </div>
              </div>
              <div class="col-5">
                <div>
                  <apexchart
                    type="area"
                    width="50"
                    :options="totalChart.total"
                    :series="totalChart.series"
                  >
                  </apexchart>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex">
            <p class="fs-12 fw-normal d-flex align-items-center text-truncate">
              <span class="text-primary fs-12 d-flex align-items-center me-1">
                <i class="ti ti-arrow-wave-right-up me-1"></i>+19.01%</span
              >from last week
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <div class="border-bottom pb-3 mb-3">
            <div class="row align-items-center">
              <div class="col-7">
                <div>
                  <span class="fs-14 fw-normal text-truncate mb-1">Total Subscribers</span>
                  <h5>600</h5>
                </div>
              </div>
              <div class="col-5">
                <div>
                  <apexchart
                    type="area"
                    width="50"
                    :options="activeChart.active"
                    :series="activeChart.series"
                  >
                  </apexchart>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex">
            <p class="fs-12 fw-normal d-flex align-items-center text-truncate">
              <span class="text-primary fs-12 d-flex align-items-center me-1">
                <i class="ti ti-arrow-wave-right-up me-1"></i>+19.01%</span
              >from last week
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <div class="border-bottom pb-3 mb-3">
            <div class="row align-items-center">
              <div class="col-7">
                <div>
                  <span class="fs-14 fw-normal text-truncate mb-1">Active Subscribers</span>
                  <h5>560</h5>
                </div>
              </div>
              <div class="col-5">
                <div>
                  <apexchart
                    type="area"
                    width="50"
                    :options="inactiveChart.inactive"
                    :series="inactiveChart.series"
                  >
                  </apexchart>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex">
            <p class="fs-12 fw-normal d-flex align-items-center text-truncate">
              <span class="text-primary fs-12 d-flex align-items-center me-1">
                <i class="ti ti-arrow-wave-right-up me-1"></i>+19.01%</span
              >from last week
            </p>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body">
          <div class="border-bottom pb-3 mb-3">
            <div class="row align-items-center">
              <div class="col-7">
                <div>
                  <span class="fs-14 fw-normal text-truncate mb-1">Expired Subscribers</span>
                  <h5>40</h5>
                </div>
              </div>
              <div class="col-5">
                <div>
                  <apexchart
                    type="area"
                    width="50"
                    :options="locationChart.location"
                    :series="locationChart.series"
                  >
                  </apexchart>
                </div>
              </div>
            </div>
          </div>
          <div class="d-flex">
            <p class="fs-12 fw-normal d-flex align-items-center text-truncate">
              <span class="text-primary fs-12 d-flex align-items-center me-1">
                <i class="ti ti-arrow-wave-right-up me-1"></i>+19.01%</span
              >from last week
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card table-list-card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
      <div class="search-set">
        <div class="search-input">
          <a href="javascript:void(0);" class="btn-searchset"><i class="ti ti-search fs-14 feather-search"></i></a>
          <input type="search" class="form-control form-control-sm" placeholder="Search">
        </div>
      </div>
      <div class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3">
        <div class="dropdown me-2">
          <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
            Select Plan
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Advanced (Monthly)</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Basic (Yearly)</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Enterprise (Monthly)</a></li>
          </ul>
        </div>
        <div class="dropdown me-2">
          <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
            Select Status
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Paid</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Unpaid</a></li>
          </ul>
        </div>
        <div class="dropdown">
          <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
            Sort By : Last 7 Days
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Recently Added</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Ascending</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Desending</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Last Month</a></li>
            <li><a href="javascript:void(0);" class="dropdown-item rounded-1">Last 7 Days</a></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="custom-datatable-filter table-responsive">
        <a-table
          class="table datanew table-hover table-center mb-0"
          :columns="columns"
          :data-source="data"
          :row-selection="rowSelection"
          :pagination="true"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'Subscriber'">
              <div class="d-flex align-items-center file-name-icon">
                <a href="javascript:void(0);" class="avatar avatar-md border rounded-circle">
                  <img :src="getImageUrl(record.Image)" class="img-fluid" alt="img" />
                </a>
                <div class="ms-2">
                  <h6 class="fw-medium">
                    <a href="javascript:void(0);">{{ record.Subscriber }}</a>
                  </h6>
                </div>
              </div>
            </template>
            <template v-if="column.key === 'Status'">
              <span
                class="d-flex align-items-center badge-xs"
                :class="['badge', record.Status === 'Paid' ? 'badge-success' : 'badge-danger']"
              >
                <i class="ti ti-point-filled me-1"></i>{{ record.Status }}
              </span>
            </template>
            <template v-if="column.key === 'action'">
              <div class="action-icon d-inline-flex">
                <a href="javascript:void(0);" class="me-2" data-bs-toggle="modal" data-bs-target="#view_invoice">
                  <i class="ti ti-file-invoice"></i>
                </a>
                <a href="javascript:void(0);" class="me-2">
                  <i class="ti ti-download"></i>
                </a>
                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#delete_modal">
                  <i class="ti ti-trash"></i>
                </a>
              </div>
            </template>
          </template>
        </a-table>
      </div>
    </div>
  </div>

  <subscription-modal></subscription-modal>
</template>

<script setup>
import { ref } from "vue";
import { totalChart, activeChart, inactiveChart, locationChart } from "./data";

const data = ref([
  {
    Subscriber: "BrightWave Innovations",
    Image: "company-01.svg",
    Plan: "Advanced (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Credit Card",
    Amount: "$200",
    Created_Date: "12 Sep 2024",
    Expiring_On: "11 Oct 2024",
    Status: "Paid",
  },
  {
    Subscriber: "Stellar Dynamics",
    Image: "company-02.svg",
    Plan: "Basic (Yearly)",
    Billing_Cycle: "365 Days",
    Payment_Method: "Paypal",
    Amount: "$600",
    Created_Date: "24 Oct 2024",
    Expiring_On: "23 Oct 2025",
    Status: "Paid",
  },
  {
    Subscriber: "Quantum Nexus",
    Image: "company-03.svg",
    Plan: "Advanced (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Debit Card",
    Amount: "$200",
    Created_Date: "18 Feb 2024",
    Expiring_On: "17 Mar 2024",
    Status: "Paid",
  },
  {
    Subscriber: "EcoVision Enterprises",
    Image: "company-04.svg",
    Plan: "Advanced (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Paypal",
    Amount: "$200",
    Created_Date: "17 Oct 2024",
    Expiring_On: "16 Nov 2024",
    Status: "Paid",
  },
  {
    Subscriber: "Aurora Technologies",
    Image: "company-05.svg",
    Plan: "Enterprise (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Credit Card",
    Amount: "$400",
    Created_Date: "20 Jul 2024",
    Expiring_On: "19 Aug 2024",
    Status: "Paid",
  },
  {
    Subscriber: "BlueSky Ventures",
    Image: "company-06.svg",
    Plan: "Advanced (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Paypal",
    Amount: "$200",
    Created_Date: "10 Apr 2024",
    Expiring_On: "19 Aug 2024",
    Status: "Paid",
  },
  {
    Subscriber: "TerraFusion Energy",
    Image: "company-07.svg",
    Plan: "Enterprise (Yearly)",
    Billing_Cycle: "365 Days",
    Payment_Method: "Credit Card",
    Amount: "$4800",
    Created_Date: "29 Aug 2024",
    Expiring_On: "28 Aug 2025",
    Status: "Paid",
  },
  {
    Subscriber: "UrbanPulse Design",
    Image: "company-08.svg",
    Plan: "Basic (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Credit Card",
    Amount: "$50",
    Created_Date: "22 Feb 2024",
    Expiring_On: "21 Mar 2024",
    Status: "Unpaid",
  },
  {
    Subscriber: "Nimbus Networks",
    Image: "company-09.svg",
    Plan: "Basic (Yearly)",
    Billing_Cycle: "365 Days",
    Payment_Method: "Paypal",
    Amount: "$600",
    Created_Date: "03 Nov 2024",
    Expiring_On: "02 Nov 2025",
    Status: "Paid",
  },
  {
    Subscriber: "Epicurean Delights",
    Image: "company-10.svg",
    Plan: "Advanced (Monthly)",
    Billing_Cycle: "30 Days",
    Payment_Method: "Credit Card",
    Amount: "$200",
    Created_Date: "17 Dec 2024",
    Expiring_On: "16 Jan 2024",
    Status: "Paid",
  },
]);

const columns = [
  {
    sorter: false,
  },
  {
    title: "Subscriber",
    dataIndex: "Subscriber",
    key: "Subscriber",
    sorter: {
      compare: (a, b) => {
        a = a.Subscriber.toLowerCase();
        b = b.Subscriber.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Plan",
    dataIndex: "Plan",
    sorter: {
      compare: (a, b) => {
        a = a.Plan.toLowerCase();
        b = b.Plan.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Billing Cycle",
    dataIndex: "Billing_Cycle",
    sorter: {
      compare: (a, b) => {
        a = a.Billing_Cycle.toLowerCase();
        b = b.Billing_Cycle.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Payment Method",
    dataIndex: "Payment_Method",
    sorter: {
      compare: (a, b) => {
        a = a.Payment_Method.toLowerCase();
        b = b.Payment_Method.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Amount",
    dataIndex: "Amount",
    sorter: {
      compare: (a, b) => {
        a = a.Amount.toLowerCase();
        b = b.Amount.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Created Date",
    dataIndex: "Created_Date",
    sorter: {
      compare: (a, b) => {
        a = a.Created_Date.toLowerCase();
        b = b.Created_Date.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Expiring On",
    dataIndex: "Expiring_On",
    sorter: {
      compare: (a, b) => {
        a = a.Expiring_On.toLowerCase();
        b = b.Expiring_On.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Status",
    dataIndex: "Status",
    key: "Status",
    sorter: {
      compare: (a, b) => {
        a = a.Status.toLowerCase();
        b = b.Status.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "",
    key: "action",
    sorter: true,
  },
];

const rowSelection = {
  onChange: () => {},
  onSelect: () => {},
  onSelectAll: () => {},
};

const getImageUrl = (imageName) => {
  return new URL(`/src/assets/img/company/${imageName}`, import.meta.url).href;
};
</script>

<style scoped>
.action-icon .me-2 i {
    font-size: 16px;
}
.file-name-icon {
    display: flex;
    align-items: center;
}
</style>
