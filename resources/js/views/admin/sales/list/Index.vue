<template>
    <PageHeader title="Sales List" subtitle="Manage Your Sales">
        <template #actions>
            <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-sales-new">
                <i class="ti ti-circle-plus me-2"></i>Add New Sales
            </a>
        </template>
    </PageHeader>
    <!-- /product list -->
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
                        Customer
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Macbook" :key="item.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item.label }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Status
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Fruits" :key="item.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item.label }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Payment Status
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Computers" :key="item.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item.label }}</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Sort By : Last 7 Days
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li v-for="item in Sortdate" :key="item.value">
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">{{ item.label }}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <a-table :columns="columns" :data-source="data" :row-selection="{}">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'Status'">
                            <div>
                                <span :class="record.Status_class">{{ record.Status }}</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'Payment_Status'">
                            <div>
                                <span :class="record.Payment_Class">{{ record.Payment_Status }}</span>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#sales-details-new">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#edit-sales-new">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#showpayment">
                                        <i class="ti ti-currency-dollar"></i>
                                    </a>
                                    <a class="me-2 p-2" href="#" data-bs-toggle="modal" data-bs-target="#createpayment">
                                        <i class="ti ti-circle-plus"></i>
                                    </a>
                                    <a class="me-2 p-2" href="#">
                                        <i class="ti ti-download"></i>
                                    </a>
                                    <a class="p-2" href="#" data-bs-toggle="modal" data-bs-target="#delete-modal">
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
    <!-- /product list -->

    <sales-list-modal></sales-list-modal>
</template>



<script setup>
const Sortdate = [
  { label: "Sort by Date", value: "Sort by Date" },
  { label: "07 09 23", value: "07 09 23" },
  { label: "21 09 23", value: "21 09 23" },
];
const Macbook = [
  { label: "Choose Customer Name", value: "Choose Customer Name" },
  { label: "Macbook pro", value: "Macbook pro" },
  { label: "Orange", value: "Orange" },
];
const Fruits = [
  { label: "Choose Status", value: "Choose Status" },
  { label: "Computers", value: "Computers" },
  { label: "Fruits", value: "Fruits" },
];
const Computers = [
  { label: "Choose Payment Status", value: "Choose Payment Status" },
  { label: "Computers", value: "Computers" },
  { label: "Fruits", value: "Fruits" },
];

const columns = [
  {
    sorter: true,
  },
  {
    title: "Customer Name",
    dataIndex: "Customer_Name",
    sorter: {
      compare: (a, b) => {
        a = a.Customer_Name.toLowerCase();
        b = b.Customer_Name.toLowerCase();
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
    title: "Biller",
    dataIndex: "Biller",
    sorter: {
      compare: (a, b) => {
        a = a.Biller.toLowerCase();
        b = b.Biller.toLowerCase();
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
    Customer_Name: "Thomas",
    Reference: "SL0101",
    Date: "19 Jan 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$550",
    Paid: "$550",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
  {
    Customer_Name: "Rose",
    Reference: "SL0102",
    Date: "26 Jan 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$250",
    Paid: "$250",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
  {
    Customer_Name: "Benjamin",
    Reference: "SL0103",
    Date: "08 Feb 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$570",
    Paid: "$570",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
  {
    Customer_Name: "Lilly",
    Reference: "SL0104",
    Date: "12 Feb 2023",
    Status: "Pending",
    Status_class: "badge badge-bgdanger",
    Grand_Total: "$300",
    Paid: "$0.00",
    Due: "$300",
    Payment_Status: "Due",
    Payment_Class: "badge badge-linedanger",
    Biller: "Admin",
  },
  {
    Customer_Name: "Freda",
    Reference: "SL0105",
    Date: "17 Mar 2023",
    Status: "Pending",
    Status_class: "badge badge-bgdanger",
    Grand_Total: "$700",
    Paid: "$0.00",
    Due: "$700",
    Payment_Status: "Due",
    Payment_Class: "badge badge-linedanger",
    Biller: "Admin",
  },
  {
    Customer_Name: "Alwin",
    Reference: "SL0106",
    Date: "24 Mar 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$400",
    Paid: "$400",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
  {
    Customer_Name: "Maybelle",
    Reference: "SL0107",
    Date: "06 Apr 2023",
    Status: "Pending",
    Status_class: "badge badge-bgdanger",
    Grand_Total: "$120",
    Paid: "$0.00",
    Due: "$120",
    Payment_Status: "Due",
    Payment_Class: "badge badge-linedanger",
    Biller: "Admin",
  },
  {
    Customer_Name: "Ellen",
    Reference: "SL0108",
    Date: "16 Apr 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$830",
    Paid: "$830",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
  {
    Customer_Name: "Kaitlin",
    Reference: "SL0109",
    Date: "04 May 2023",
    Status: "Pending",
    Status_class: "badge badge-bgdanger",
    Grand_Total: "$800",
    Paid: "$0.00",
    Due: "$800",
    Payment_Status: "Due",
    Payment_Class: "badge badge-linedanger",
    Biller: "Admin",
  },
  {
    Customer_Name: "Grace",
    Reference: "SL0110",
    Date: "29 May 2023",
    Status: "Completed",
    Status_class: "badge badge-bgsuccess",
    Grand_Total: "$460",
    Paid: "$460",
    Due: "$0.00",
    Payment_Status: "Paid",
    Payment_Class: "badge badge-linesuccess",
    Biller: "Admin",
  },
];
</script>
