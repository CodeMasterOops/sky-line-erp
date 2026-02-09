<template>
    <PageHeader title="Purchase List" subtitle="Manage your purchases">
        <template #actions>
            <a href="#" class="btn btn-primary d-inline-flex align-items-center me-2" data-bs-toggle="modal" data-bs-target="#add-units">
                <i class="ti ti-circle-plus me-2"></i>Add Product
            </a>
            <a href="#" class="btn btn-secondary form-btn-color d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#view-notes">
                <i class="ti ti-download me-2"></i>Import Purchase
            </a>
        </template>
    </PageHeader>

    <div class="card table-list-card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
            <div class="search-set">
                <div class="search-input">
                    <a href="javascript:void(0);" class="btn-searchset"><i class="ti ti-search"></i></a>
                    <input type="search" class="form-control form-control-sm" placeholder="Search" />
                </div>
            </div>
            <div class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3">
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Supplier
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Supplier" :key="item">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Status
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Status" :key="item">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Payment Status
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Paid" :key="item">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Sort By : Last 7 Days
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Sortby" :key="item">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <a-table class="table datanew" :columns="columns" :data-source="data" :row-selection="{}">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Status'">
                            <div>
                                <span :class="record.Status_class">{{ record.Status }}</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'Created_by'">
                            <div>
                                <span :class="record.Created_class">{{ record.Created_by }}</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2" href="javascript:void(0);">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#edit-units">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a class="p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#delete-modal">
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

    <purchase-list-modal></purchase-list-modal>
</template>

<script setup>
const Sortby = ["Sort by Date", "Newest", "Oldest"];
const Supplier = [
    "Choose Supplier Name",
    "Apex Computers",
    "Beats Headphones",
    "Dazzle Shoes",
    "Best Accessories",
];
const Status = ["Choose Status", "Received", "Ordered", "Pending"];
const Paid = ["Choose Payment Status", "Paid", "Partial", "Unpaid"];

const columns = [
  {
    sorter: true,
  },
  {
    title: "Supplier Name",
    dataIndex: "Supplier_Name",
    sorter: {
      compare: (a, b) => {
        a = a.Supplier_Name.toLowerCase();
        b = b.Supplier_Name.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Reference",
    dataIndex: "Reference",
    sorter: {
      compare: (a, b) => {
        a = a.Reference.toLowerCase();
        b = b.Reference.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Date",
    dataIndex: "Date",
    sorter: {
      compare: (a, b) => {
        a = a.Date.toLowerCase();
        b = b.Date.toLowerCase();
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
    title: "Grand Total",
    dataIndex: "Grand_Total",
    sorter: {
      compare: (a, b) => {
        a = a.Grand_Total.toLowerCase();
        b = b.Grand_Total.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Paid",
    dataIndex: "Paid",
    sorter: {
      compare: (a, b) => {
        a = a.Paid.toLowerCase();
        b = b.Paid.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Due",
    dataIndex: "Due",
    sorter: {
      compare: (a, b) => {
        a = a.Due.toLowerCase();
        b = b.Due.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Created by",
    dataIndex: "Created_by",
    key: "Created_by",
    sorter: {
      compare: (a, b) => {
        a = a.Created_by.toLowerCase();
        b = b.Created_by.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Action",
    key: "action",
    sorter: true,
  },
];

const data = [
  {
    id: "1",
    Supplier_Name: "Apex Computers",
    Reference: "PT001",
    Date: "19 Jan 2023",
    Status: "Received",
    Status_class: "badges status-badge fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$550",
    Paid: "$550",
    Due: "$0.00",
    Created_by: "Paid",
    Created_class: "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
  },
  {
    id: "2",
    Supplier_Name: "Beats Headphones",
    Reference: "PT002",
    Date: "27 Jan 2023",
    Status: "Received",
    Status_class: "badges status-badge fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$370",
    Paid: "$370",
    Due: "$0.00",
    Created_by: "Paid",
    Created_class: "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
  },
  {
    id: "3",
    Supplier_Name: "Dazzle Shoes",
    Reference: "PT003",
    Date: "08 Feb 2023",
    Status: "Ordered",
    Status_class: "badges status-badge bg-warning fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$400",
    Paid: "$400",
    Due: "$200",
    Created_by: "Partial",
    Created_class: "p-1 pe-2 rounded-1 text-warning bg-warning-transparent fs-10",
  },
  {
    id: "4",
    Supplier_Name: "Best Accessories",
    Reference: "PT004",
    Date: "16 Feb 2023",
    Status: "Pending",
    Status_class: "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$560",
    Paid: "$0.00",
    Due: "$560",
    Created_by: "Unpaid",
    Created_class: "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
  },
  {
    id: "5",
    Supplier_Name: "A-Z Store",
    Reference: "PT005",
    Date: "12 Mar 2023",
    Status: "Pending",
    Status_class: "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$240",
    Paid: "$0.00",
    Due: "$240",
    Created_by: "Unpaid",
    Created_class: "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
  },
  {
    id: "6",
    Supplier_Name: "Hatimi Hardwares",
    Reference: "PT006",
    Date: "24 Mar 2023",
    Status: "Received",
    Status_class: "badges status-badge fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$170",
    Paid: "$170",
    Due: "$0.00",
    Created_by: "Paid",
    Created_class: "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
  },
  {
    id: "7",
    Supplier_Name: "Aesthetic Bags",
    Reference: "PT007",
    Date: "06 Apr 2023",
    Status: "Pending",
    Status_class: "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$230",
    Paid: "$0.00",
    Due: "$230",
    Created_by: "Unpaid",
    Created_class: "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
  },
  {
    id: "8",
    Supplier_Name: "Alpha Mobiles",
    Reference: "PT008",
    Date: "14 Apr 2023",
    Status: "Ordered",
    Status_class: "badges status-badge bg-warning fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$300",
    Paid: "$150",
    Due: "$300",
    Created_by: "Partial",
    Created_class: "p-1 pe-2 rounded-1 text-warning bg-warning-transparent fs-10",
  },
  {
    id: "9",
    Supplier_Name: "Sigma Chairs",
    Reference: "PT009",
    Date: "02 May 2023",
    Status: "Pending",
    Status_class: "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$620",
    Paid: "$0.00",
    Due: "$620",
    Created_by: "Unpaid",
    Created_class: "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
  },
  {
    id: "10",
    Supplier_Name: "Zenith Bags",
    Reference: "PT010",
    Date: "23 May 2023",
    Status: "Received",
    Status_class: "badges status-badge fs-10 p-1 px-2 rounded-1",
    Grand_Total: "$200",
    Paid: "$200",
    Due: "$0.00",
    Created_by: "Paid",
    Created_class: "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
  },
];
</script>
