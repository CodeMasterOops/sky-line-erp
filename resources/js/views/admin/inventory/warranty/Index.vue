<template>
    <PageHeader title="Warranty" subtitle="Manage your warranty">
        <template #actions>
            <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-units">
                <i class="ti ti-circle-plus me-2"></i>Add New Warranty
            </a>
        </template>
    </PageHeader>
    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset"><i class="ti ti-search"></i></a>
                    <input type="search" class="form-control form-control-sm" placeholder="Search">
                </div>
            </div>
            <div class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Period
                    </a>
                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Month</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Year</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Status
                    </a>
                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Computers</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Electronics</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Shoe</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Electronics</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Sort By : Last 7 Days
                    </a>
                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Recently Added</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Ascending</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Desending</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Last Month</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Last 7 Days</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <a-table class="table datanew table-hover table-center mb-0" :columns="columns" :data-source="data" :row-selection="{}">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Warranty'">
                            <div class="text-gray-9">{{record.Warranty}}</div>
                        </template>
                        <template v-if="column.key === 'Description'">
                            <p class="description-para">{{record.Description}}</p>
                        </template>
                        <template v-if="column.key === 'Status'">
                            <span class="badge table-badge bg-success fw-medium fs-10">{{record.Status}}</span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#edit-units">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a data-bs-toggle="modal" data-bs-target="#delete-modal" class="p-2" href="javascript:void(0);">
                                        <i class="ti ti-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </template>
                </a-table>
            </div>
        </div>
    </div>
    <warranty-modal></warranty-modal>
</template>
<script setup>
const columns = [
  {
    title: "Warranty",
    dataIndex: "Warranty",
    key: "Warranty",
    sorter: {
      compare: (a, b) => {
        a = a.Warranty.toLowerCase();
        b = b.Warranty.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Description",
    dataIndex: "Description",
    key: "Description",
    sorter: {
      compare: (a, b) => {
        a = a.Description.toLowerCase();
        b = b.Description.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Duration",
    dataIndex: "Duration",
    sorter: {
      compare: (a, b) => {
        a = a.Duration.toLowerCase();
        b = b.Duration.toLowerCase();
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
const data = [
  {
    "Id": "1",
    "Warranty": "Replacement Warranty",
    "Description": "Covers replacement of faulty items",
    "Duration": "2 Year",
    "Status": "Active"
  },
  {
    "Id": "2",
    "Warranty": "On-Site Warranty",
    "Description": "Product repairs done at the customer’s location",
    "Duration": "1 Year",
    "Status": "Active"
  },
  {
    "Id": "3",
    "Warranty": "Accidental Protection Plan",
    "Description": "Coverage for accidental damage",
    "Duration": "6 Months",
    "Status": "Active"
  },
  {
    "Id": "4",
    "Warranty": "Labor-Only Warranty",
    "Description": "Covers only labor costs, not parts",
    "Duration": "6 Months",
    "Status": "Active"
  },
  {
    "Id": "5",
    "Warranty": "No-Cost Repairs",
    "Description": "No charge for repairs during warranty period",
    "Duration": "3 Months",
    "Status": "Active"
  },
  {
    "Id": "6",
    "Warranty": "Accidental Damage",
    "Description": "Coverage for unexpected damage",
    "Duration": "6 Months",
    "Status": "Active"
  },
  {
    "Id": "7",
    "Warranty": "Wear & Tear Warranty",
    "Description": "Covers specific product aging issues",
    "Duration": "1 Year",
    "Status": "Active"
  },
  {
    "Id": "8",
    "Warranty": "Money-Back Guarantee",
    "Description": "Refund within a specified period",
    "Duration": "3 Months",
    "Status": "Active"
  },
  {
    "Id": "9",
    "Warranty": "Water Damage Warranty",
    "Description": "Coverage for water-related issues",
    "Duration": "6 Months",
    "Status": "Active"
  },
  {
    "Id": "10",
    "Warranty": "Power Surge Protection",
    "Description": "Covers damage from power surges",
    "Duration": "6 Months",
    "Status": "Active"
  }
];
</script>
