<template>
  <layout-header></layout-header>
  <layout-sidebar></layout-sidebar>
  <div class="page-wrapper">
    <div class="content">
      <div class="page-header">
        <div class="add-item d-flex">
          <div class="page-title">
            <h4>POS Orders</h4>
            <h6>Manage Your pos orders</h6>
          </div>
        </div>
        <ul class="table-top-head">
          <li>
            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"
              ><img src="@/assets/img/icons/pdf.svg" alt="img"
            /></a>
          </li>
          <li>
            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"
              ><img src="@/assets/img/icons/excel.svg" alt="img"
            /></a>
          </li>
          <li>
            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"
              ><i class="ti ti-refresh"></i
            ></a>
          </li>
          <li>
            <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header" @click="toggleHeader"><i class="ti ti-chevron-up"></i></a>
          </li>
        </ul>
        <div class="page-btn">
          <a
            href="#"
            class="btn btn-primary btn-md d-inline-flex align-items-center"
            data-bs-toggle="modal"
            data-bs-target="#add-sales-new"
            ><vue-feather type="plus-circle" class="me-2"></vue-feather> Add Sales</a
          >
        </div>
      </div>

      <!-- /product list -->
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
            class="d-flex table-dropdown my-xl-auto right-content align-items-center flex-wrap row-gap-3"
          >
            <div class="dropdown me-2">
              <a
                href="javascript:void(0);"
                class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center"
                data-bs-toggle="dropdown"
              >
                Customer
              </a>
              <ul class="dropdown-menu dropdown-menu-end p-3">
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Carl Evans</a
                  >
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Minerva Rameriz</a
                  >
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Robert Lamon</a
                  >
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Patricia Lewis</a
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
                Staus
              </a>
              <ul class="dropdown-menu dropdown-menu-end p-3">
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Completed</a
                  >
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Pending</a
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
                Payment Status
              </a>
              <ul class="dropdown-menu dropdown-menu-end p-3">
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1">Paid</a>
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1">Unpaid</a>
                </li>
                <li>
                  <a href="javascript:void(0);" class="dropdown-item rounded-1"
                    >Overdue</a
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
              class="table datanew"
              :columns="columns"
              :data-source="data"
              :row-selection="{}"
            >
              <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'CustomerName'">
                  <div class="d-flex align-items-center">
                    <a href="javascript:void(0);" class="avatar avatar-md me-2">
                      <img
                        :src="getImageUrl(record.Image)"
                        alt="product"
                      />
                    </a>
                    <a href="javascript:void(0);">{{ record.CustomerName }}</a>
                  </div>
                </template>
                <template v-if="column.key === 'Status'">
                  <div>
                    <span :class="record.StatusClass">{{ record.Status }}</span>
                  </div>
                </template>
                <template v-if="column.key === 'PaymentStatus'">
                  <span :class="record.PaymentClass"
                    ><i class="ti ti-point-filled me-1"></i
                    >{{ record.PaymentStatus }}</span
                  >
                </template>
                <template v-if="column.key === 'action'">
                  <div class="text-center">
                    <a
                      class="action-set"
                      href="javascript:void(0);"
                      data-bs-toggle="dropdown"
                      aria-expanded="true"
                    >
                      <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                    </a>
                    <ul class="dropdown-menu">
                      <li>
                        <a
                          href="javascript:void(0);"
                          class="dropdown-item"
                          data-bs-toggle="modal"
                          data-bs-target="#sales-details-new"
                          ><i data-feather="eye" class="info-img"></i>Sale Detail</a
                        >
                      </li>
                      <li>
                        <a
                          href="javascript:void(0);"
                          class="dropdown-item"
                          data-bs-toggle="modal"
                          data-bs-target="#edit-sales-new"
                          ><i data-feather="edit" class="info-img"></i>Edit Sale</a
                        >
                      </li>
                      <li>
                        <a
                          href="javascript:void(0);"
                          class="dropdown-item"
                          data-bs-toggle="modal"
                          data-bs-target="#showpayment"
                          ><i data-feather="dollar-sign" class="info-img"></i>Show
                          Payments</a
                        >
                      </li>
                      <li>
                        <a
                          href="javascript:void(0);"
                          class="dropdown-item"
                          data-bs-toggle="modal"
                          data-bs-target="#createpayment"
                          ><i class="ti ti-circle-plus me-1"></i>Create
                          Payment</a
                        >
                      </li>
                      <li>
                        <a href="javascript:void(0);" class="dropdown-item"
                          ><i data-feather="download" class="info-img"></i>Download pdf</a
                        >
                      </li>
                      <li>
                        <a
                          href="javascript:void(0);"
                          class="dropdown-item mb-0"
                          data-bs-toggle="modal"
                          data-bs-target="#delete"
                          ><i data-feather="trash-2" class="info-img"></i>Delete Sale</a
                        >
                      </li>
                    </ul>
                  </div>
                </template>
              </template>
            </a-table>
          </div>
        </div>
      </div>
      <!-- /product list -->
    </div>
    <div
      class="footer d-sm-flex align-items-center justify-content-between border-top bg-white p-3"
    >
      <p class="mb-0">2014-2025 © DreamsPOS. All Right Reserved</p>
      <p>
        Designed &amp; Developed By
        <a href="javascript:void(0);" class="text-primary">Dreams</a>
      </p>
    </div>
  </div>
  <online-orders-modal></online-orders-modal>
</template>
<script>
const columns = [
  {
    title: "Customer Name",
    dataIndex: "CustomerName",
    key: "CustomerName",
    sorter: {
      compare: (a, b) => {
        a = a.CustomerName.toLowerCase();
        b = b.CustomerName.toLowerCase();
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
    dataIndex: "GrandTotal",
    sorter: {
      compare: (a, b) => {
        a = a.GrandTotal.toLowerCase();
        b = b.GrandTotal.toLowerCase();
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
    dataIndex: "PaymentStatus",
    key: "PaymentStatus",
    sorter: {
      compare: (a, b) => {
        a = a.PaymentStatus.toLowerCase();
        b = b.PaymentStatus.toLowerCase();
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
    title: "",
    key: "action",
    sorter: true,
  },
];
const data = [
  {
    CustomerName: "Carl Evans",
    Image: "user-27.jpg",
    Reference: "SL001",
    Date: "24 Dec 2024",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$1000",
    Paid: "$1000",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Minerva Rameriz",
    Image: "user-02.jpg",
    Reference: "SL002",
    Date: "10 Dec 2024",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    GrandTotal: "$1500",
    Paid: "$0.00",
    Due: "$1500",
    PaymentStatus: "Unpaid",
    PaymentClass: "badge badge-soft-danger shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Robert Lamon",
    Image: "user-05.jpg",
    Reference: "SL003",
    Date: "08 Feb 2023",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$1500",
    Paid: "$0.00",
    Due: "$1500",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Patricia Lewis",
    Image: "user-22.jpg",
    Reference: "SL004",
    Date: "12 Feb 2023",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$2000",
    Paid: "$1000",
    Due: "$1000",
    PaymentStatus: "Overdue",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Mark Joslyn",
    Image: "user-03.jpg",
    Reference: "SL005",
    Date: "17 Mar 2023",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$800",
    Paid: "$800",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Marsha Betts",
    Image: "user-12.jpg",
    Reference: "SL006",
    Date: "24 Mar 2023",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    GrandTotal: "$750",
    Paid: "$0.00",
    Due: "$750",
    PaymentStatus: "Unpaid",
    PaymentClass: "badge badge-soft-danger shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Daniel Jude",
    Image: "user-06.jpg",
    Reference: "SL007",
    Date: "06 Apr 2023",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$1300",
    Paid: "$1300",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Emma Bates",
    Image: "user-21.jpg",
    Reference: "SL008",
    Date: "16 Apr 2023",
    Status: "Completed",
    StatusClass: "badge badge-success",
    GrandTotal: "$1100",
    Paid: "$1100",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Richard Fralick",
    Image: "user-16.jpg",
    Reference: "SL009",
    Date: "04 May 2023",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    GrandTotal: "$2300",
    Paid: "$2300",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
  {
    CustomerName: "Michelle Robison",
    Image: "user-26.jpg",
    Reference: "SL010",
    Date: "29 May 2023",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    GrandTotal: "$1700",
    Paid: "$1700",
    Due: "$0.00",
    PaymentStatus: "Paid",
    PaymentClass: "badge badge-soft-success shadow-none badge-xs",
    Biller: "Admin",
  },
];
export default {
  data() {
    return {
      data,
      columns,
    };
  },
  methods: {
    toggleHeader() {
      document.getElementById("collapse-header").classList.toggle("active");
      document.body.classList.toggle("header-collapse");
    },
    getImageUrl(imageName) {
      return new URL(`/src/assets/img/users/${imageName}`, import.meta.url).href;
    },
  },
};
</script>
