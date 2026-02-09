<template>
  <PageHeader title="Packages" subtitle="Manage your packages">
    <template #actions>
      <a
        href="#"
        data-bs-toggle="modal"
        data-bs-target="#add_plans"
        class="btn btn-primary"
        ><i class="ti ti-circle-plus me-1"></i>Add Packages</a
      >
    </template>
  </PageHeader>

  <div class="row">
    <!-- Total Plans -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center overflow-hidden">
            <div>
              <p class="fs-12 fw-medium mb-1 text-truncate">Total Plans</p>
              <h4>08</h4>
            </div>
          </div>
          <div>
            <span class="avatar avatar-lg bg-primary flex-shrink-0">
              <i class="ti ti-box fs-16"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
    <!-- /Total Plans -->

    <!-- Active Plans -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center overflow-hidden">
            <div>
              <p class="fs-12 fw-medium mb-1 text-truncate">Active Plans</p>
              <h4>08</h4>
            </div>
          </div>
          <div>
            <span class="avatar avatar-lg bg-success flex-shrink-0">
              <i class="ti ti-activity-heartbeat fs-16"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
    <!-- /Active Plans -->

    <!-- Inactive Plans -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center overflow-hidden">
            <div>
              <p class="fs-12 fw-medium mb-1 text-truncate">Inactive Plans</p>
              <h4>0</h4>
            </div>
          </div>
          <div>
            <span class="avatar avatar-lg bg-danger flex-shrink-0">
              <i class="ti ti-player-pause fs-16"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
    <!-- /Inactive Plans -->

    <!-- No of Plan Types -->
    <div class="col-lg-3 col-md-6 d-flex">
      <div class="card flex-fill">
        <div class="card-body d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center overflow-hidden">
            <div>
              <p class="fs-12 fw-medium mb-1 text-truncate">No of Plan Types</p>
              <h4>02</h4>
            </div>
          </div>
          <div>
            <span class="avatar avatar-lg bg-skyblue flex-shrink-0">
              <i class="ti ti-mask fs-16"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
    <!-- /No of Plan Types -->
  </div>

  <div class="card table-list-card">
    <div
      class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3"
    >
      <div class="search-set">
        <div class="search-input">
          <a href="javascript:void(0);" class="btn-searchset"
            ><i class="ti ti-search fs-14 feather-search"></i
          ></a>
          <input
            type="search"
            class="form-control form-control-sm"
            placeholder="Search"
          />
        </div>
      </div>
      <div
        class="d-flex my-xl-auto right-content align-items-center flex-wrap row-gap-3"
      >
        <div class="dropdown me-2">
          <a
            href="javascript:void(0);"
            class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
            data-bs-toggle="dropdown"
          >
            Select Plan
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Monthly</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Yearly</a
              >
            </li>
          </ul>
        </div>
        <div class="dropdown me-2">
          <a
            href="javascript:void(0);"
            class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
            data-bs-toggle="dropdown"
          >
            Select Status
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Active</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Inactive</a
              >
            </li>
          </ul>
        </div>
        <div class="dropdown">
          <a
            href="javascript:void(0);"
            class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
            data-bs-toggle="dropdown"
          >
            Sort By : Last 7 Days
          </a>
          <ul class="dropdown-menu dropdown-menu-end p-3">
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Recently Added</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Ascending</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Desending</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Last Month</a
              >
            </li>
            <li>
              <a href="javascript:void(0);" class="dropdown-item rounded-1"
                >Last 7 Days</a
              >
            </li>
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
            <template v-if="column.key === 'Plan_Name'">
              <h6 class="fw-medium">
                <a href="javascript:void(0);">{{ record.Plan_Name }}</a>
              </h6>
            </template>
            <template v-else-if="column.key === 'Status'">
              <span
                class="badge badge-success d-inline-flex align-items-center badge-sm"
              >
                <i class="ti ti-point-filled me-1"></i>{{ record.Status }}
              </span>
            </template>
            <template v-else-if="column.key === 'action'">
              <div class="action-icon d-inline-flex">
                <a
                  href="javascript:void(0);"
                  class="me-2"
                  data-bs-toggle="modal"
                  data-bs-target="#edit_plans"
                  ><i class="ti ti-edit"></i
                ></a>
                <a
                  href="javascript:void(0);"
                  data-bs-toggle="modal"
                  data-bs-target="#delete_modal"
                  ><i class="ti ti-trash"></i
                ></a>
              </div>
            </template>
          </template>
        </a-table>
      </div>
    </div>
  </div>

  <packages-modal></packages-modal>
</template>

<script setup>
import { ref } from "vue";

const data = ref([
  {
    Plan_Name: "Basic",
    Plan_Type: "Monthly",
    Total_Subscribers: "56",
    Price: "$50",
    Created_Date: "14 Jan 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Advanced",
    Plan_Type: "Monthly",
    Total_Subscribers: "99",
    Price: "$200",
    Created_Date: "21 Jan 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Premium",
    Plan_Type: "Monthly",
    Total_Subscribers: "58",
    Price: "$300",
    Created_Date: "10 Feb 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Enterprise",
    Plan_Type: "Monthly",
    Total_Subscribers: "67",
    Price: "$400",
    Created_Date: "18 Feb 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Basic",
    Plan_Type: "Yearly",
    Total_Subscribers: "78",
    Price: "$600",
    Created_Date: "15 Mar 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Advanced",
    Plan_Type: "Yearly",
    Total_Subscribers: "99",
    Price: "$2400",
    Created_Date: "26 Mar 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Premium",
    Plan_Type: "Yearly",
    Total_Subscribers: "48",
    Price: "$3600",
    Created_Date: "05 Apr 2024",
    Status: "Active",
  },
  {
    Plan_Name: "Enterprise",
    Plan_Type: "Yearly",
    Total_Subscribers: "17",
    Price: "$4800",
    Created_Date: "16 Apr 2024",
    Status: "Active",
  },
]);

const columns = [
  {
    sorter: false,
  },
  {
    title: "Plan Name",
    dataIndex: "Plan_Name",
    key: "Plan_Name",
    sorter: {
      compare: (a, b) => {
        a = a.Plan_Name.toLowerCase();
        b = b.Plan_Name.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Plan Type",
    dataIndex: "Plan_Type",
    sorter: {
      compare: (a, b) => {
        a = a.Plan_Type.toLowerCase();
        b = b.Plan_Type.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Total Subscribers",
    dataIndex: "Total_Subscribers",
    sorter: {
      compare: (a, b) => {
        a = a.Total_Subscribers.toLowerCase();
        b = b.Total_Subscribers.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Price",
    dataIndex: "Price",
    sorter: {
      compare: (a, b) => {
        a = a.Price.toLowerCase();
        b = b.Price.toLowerCase();
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
</script>

<style scoped>
.action-icon .me-2 i {
  font-size: 16px;
}
</style>
