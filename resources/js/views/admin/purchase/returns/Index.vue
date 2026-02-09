<template>
    <PageHeader title="Purchase Return List" subtitle="Manage your Returns">
        <template #actions>
            <a href="javascript:void(0);" class="btn btn-primary d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#add-sales-new">
                <i class="ti ti-circle-plus me-2"></i>Add Purchase Return
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
                        <li v-for="item in PaymentStatus" :key="item">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Sort By : Last 7 Days
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in SortBy" :key="item">
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
                        <template v-if="column.key === 'Product_Image'">
                            <a class="avatar avatar-md me-2">
                                <img :src="getImageUrl(record.Product_Image)" alt="product" />
                            </a>
                        </template>
                        <template v-else-if="column.key === 'Status'">
                            <span :class="record.StatusClass">{{ record.Status }}</span>
                        </template>
                        <template v-else-if="column.key === 'Payment_Status'">
                            <span :class="record.PaymentClass">
                                <i class="ti ti-point-filled me-1 fs-11"></i>{{ record.Payment_Status }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#edit-sales-new">
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

    <purchase-returns-modal></purchase-returns-modal>
</template>

<script setup>
const getImageUrl = (imageName) => {
  return new URL(`/src/assets/img/products/${imageName}`, import.meta.url).href;
};

const Supplier = ["Choose Supplier", "Electro Mart", "Quantum Gadgets", "Prime Bazaar"];
const Status = ["Choose Status", "Received", "Ordered", "Pending"];
const PaymentStatus = ["Choose Payment Status", "Paid", "Unpaid", "Overdue"];
const SortBy = ["Sort By : Last 7 Days", "Recently Added", "Ascending", "Desending", "Last Month", "Last 7 Days"];

const columns = [
  {
    sorter: true,
  },
  {
    title: "Product Image",
    dataIndex: "Product_Image",
    key: "Product_Image",
    sorter: {
      compare: (a, b) => {
        a = a.Product_Image.toLowerCase();
        b = b.Product_Image.toLowerCase();
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
    title: "Total",
    dataIndex: "Total",
    sorter: {
      compare: (a, b) => {
        a = a.Total.toLowerCase();
        b = b.Total.toLowerCase();
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
    title: "Payment Status",
    dataIndex: "Payment_Status",
    key: "Payment_Status",
    sorter: {
      compare: (a, b) => {
        a = a.Payment_Status.toLowerCase();
        b = b.Payment_Status.toLowerCase();
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
   "Product_Image": "stock-img-01.png",
   "Date": "24 Dec 2024",
   "Supplier_Name": "Electro Mart",
   "Reference": "PT001",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$1000",
   "Paid": "$1000",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 },
 {
   "Id": "2",
   "Product_Image": "stock-img-06.png",
   "Date": "10 Dec 2024",
   "Supplier_Name": "Quantum Gadgets",
   "Reference": "PT002",
   "Status": "Pending",
   "StatusClass": "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
   "Total": "$1500",
   "Paid": "$0.00",
   "Due": "$1500",
   "PaymentClass": "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
   "Payment_Status": "Unpaid"
 },
 {
   "Id": "3",
   "Product_Image": "stock-img-02.png",
   "Date": "27 Nov 2024",
   "Supplier_Name": "Prime Bazaar",
   "Reference": "PT003",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$1500",
   "Paid": "$1800",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "paid"
 },
 {
   "Id": "4",
   "Product_Image": "stock-img-03.png",
   "Date": "18 Nov 2024",
   "Supplier_Name": "Gadget World",
   "Reference": "PT004",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$2000",
   "Paid": "$1000",
   "Due": "$1000",
   "PaymentClass": "p-1 pe-2 rounded-1 text-warning bg-warning-transparent fs-10",
   "Payment_Status": "Overdue"
 },
 {
   "Id": "5",
   "Product_Image": "stock-img-04.png",
   "Date": "06 Nov 2024",
   "Supplier_Name": "Volt Vault",
   "Reference": "PT005",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$800",
   "Paid": "$800",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 },
 {
   "Id": "6",
   "Product_Image": "stock-img-05.png",
   "Date": "25 Oct 2024",
   "Supplier_Name": "Elite Retail",
   "Reference": "PT006",
   "Status": "Pending",
   "StatusClass": "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
   "Total": "$750",
   "Paid": "$0.00",
   "Due": "$750",
   "PaymentClass": "p-1 pe-2 rounded-1 text-danger bg-danger-transparent fs-10",
   "Payment_Status": "Unpaid"
 },
 {
   "Id": "7",
   "Product_Image": "expire-product-01.png",
   "Date": "14 Oct 2024",
   "Supplier_Name": "Prime Mart",
   "Reference": "PT007",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$1300",
   "Paid": "$1300",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 },
 {
   "Id": "8",
   "Product_Image": "expire-product-02.png",
   "Date": "14 Oct 2024",
   "Supplier_Name": "NeoTech Store",
   "Reference": "PT008",
   "Status": "Received",
   "StatusClass": "badges status-badge fs-10 p-1 px-2 rounded-1",
   "Total": "$1100",
   "Paid": "$1100",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 },
 {
   "Id": "9",
   "Product_Image": "expire-product-03.png",
   "Date": "20 Sep 2024",
   "Supplier_Name": "Urban Mart",
   "Reference": "PT009",
   "Status": "Pending",
   "StatusClass": "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
   "Total": "$2300",
   "Paid": "$2300",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 },
 {
   "Id": "10",
   "Product_Image": "expire-product-04.png",
   "Date": "10 Sep 2024",
   "Supplier_Name": "Travel Mart",
   "Reference": "PT010",
   "Status": "Pending",
   "StatusClass": "badges status-badge badge-pending fs-10 p-1 px-2 rounded-1",
   "Total": "$1700",
   "Paid": "$1700",
   "Due": "$0.00",
   "PaymentClass": "p-1 pe-2 rounded-1 text-success bg-success-transparent fs-10",
   "Payment_Status": "Paid"
 }
];
</script>
