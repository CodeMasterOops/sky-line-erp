<template>
  <PageHeader title="Sales Return" subtitle="Manage your returns">
    <template #actions>
      <a
        href="javascript:void(0);"
        class="btn btn-primary"
        data-bs-toggle="modal"
        data-bs-target="#add-sales-new"
        ><i class="ti ti-circle-plus me-2"></i>Add Sales
        Return</a
      >
    </template>
  </PageHeader>

  <!-- /product list -->
  <div class="card table-list-card">
    <div
      class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3"
    >
      <div class="search-set">
        <div class="search-input">
          <a href="javascript:void(0);" class="btn-searchset"
            ><i class="ti ti-search"></i
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
            Status
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
        <a-table :columns="columns" :data-source="data" :row-selection="{}">
          <template #bodyCell="{ column, record }">
            <template v-if="column.key === 'ProductName'">
              <div class="d-flex align-items-center">
                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                  <img :src="getImageUrl(record.Image)"
                    alt="product"
                  />
                </a>
                <a href="javascript:void(0);">{{ record.ProductName }}</a>
              </div>
            </template>
            <template v-if="column.key === 'Customer'">
              <div class="d-flex align-items-center">
                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                  <img :src="getImageUrlOne(record.CustomerImage)"
                    alt="product"
                  />
                </a>
                <a href="javascript:void(0);">{{ record.Customer }}</a>
              </div>
            </template>
            <template v-if="column.key === 'Status'">
                <span :class="record.StatusClass">{{ record.Status }}</span>
            </template>
            <template v-if="column.key === 'PaymentStatus'">
              <span :class="record.PaymentClass"
                ><i class="ti ti-point-filled me2"></i
                >{{ record.PaymentStatus }}</span
              >
            </template>
            <template v-if="column.key === 'action'">
              <div class="action-table-data">
                <div class="edit-delete-action">
                  <a
                    class="me-2 p-2"
                    href="#"
                    data-bs-toggle="modal"
                    data-bs-target="#edit-sales-new"
                  >
                    <i class="ti ti-edit"></i>
                  </a>
                  <a
                    class="p-2"
                    href="javascript:void(0);"
                    data-bs-toggle="modal"
                    data-bs-target="#delete"
                  >
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

  <sales-returns-modal></sales-returns-modal>
</template>

<script setup>

const columns = [
  {
    sorter: true,
  },
  {
    title: "Product Name",
    dataIndex: "ProductName",
    key: "ProductName",
    sorter: {
      compare: (a, b) => {
        a = a.ProductName.toLowerCase();
        b = b.ProductName.toLowerCase();
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
    title: "Customer",
    dataIndex: "Customer",
    key: "Customer",
    sorter: {
      compare: (a, b) => {
        a = a.Customer.toLowerCase();
        b = b.Customer.toLowerCase();
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
    title: "",
    key: "action",
    sorter: true,
  },
];
const data = [
  {
    ProductName: "Lenovo IdeaPad 3",
    Image: "pos-product-07.svg",
    Date: "19 Nov 2022",
    Customer: "Carl Evans",
    CustomerImage: "user-27.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$1000",
    Paid: "$1000",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Apple tablet",
    Image: "pos-product-10.svg",
    Date: "19 Nov 2022",
    Customer: "Minerva Rameriz",
    CustomerImage: "user-02.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan shadow-none",
    Total: "$1500",
    Paid: "$0.00",
    Due: "$1500",
    PaymentClass: "badge badge-soft-danger badge-xs shadow-none",
    PaymentStatus: "Unpaid",
  },
  {
    ProductName: "Headphone",
    Image: "product-02.jpg",
    Date: "19 Nov 2022",
    Customer: "Robert Lamon",
    CustomerImage: "user-05.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$2000",
    Paid: "$1000",
    Due: "$1000",
    PaymentClass: "badge badge-soft-warning badge-xs shadow-none",
    PaymentStatus: "Overdue",
  },
  {
    ProductName: "Nike Jordan",
    Image: "stock-img-02.png",
    Date: "19 Nov 2022",
    Customer: "Mark Joslyn",
    CustomerImage: "user-03.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$1500",
    Paid: "$1500",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Macbook Pro",
    Image: "product6.jpg",
    Date: "19 Nov 2022",
    Customer: "Patricia Lewis",
    CustomerImage: "user-22.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$800",
    Paid: "$800",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Red Premium Satchel",
    Image: "expire-product-01.png",
    Date: "19 Nov 2022",
    Customer: "Marsha Betts",
    CustomerImage: "user-12.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan shadow-none",
    Total: "$750",
    Paid: "$0.00",
    Due: "$750",
    PaymentClass: "badge badge-soft-danger badge-xs shadow-none",
    PaymentStatus: "Unpaid",
  },
  {
    ProductName: "Apple Earpods",
    Image: "product7.jpg",
    Date: "19 Nov 2022",
    Customer: "Daniel Jude",
    CustomerImage: "user-06.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$1300",
    Paid: "$1300",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Iphone 14 Pro",
    Image: "expire-product-02.png",
    Date: "19 Nov 2022",
    Customer: "Emma Bates",
    CustomerImage: "user-21.jpg",
    Status: "Received",
    StatusClass: "badge badge-success shadow-none",
    Total: "$1100",
    Paid: "$1100",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Gaming Chair",
    Image: "expire-product-03.png",
    Date: "19 Nov 2022",
    Customer: "Richard Fralick",
    CustomerImage: "user-16.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan shadow-none",
    Total: "$2300",
    Paid: "$2300",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
  {
    ProductName: "Borealis Backpack",
    Image: "expire-product-04.png",
    Date: "19 Nov 2022",
    Customer: "Michelle Robison",
    CustomerImage: "user-26.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan shadow-none",
    Total: "$1700",
    Paid: "$1700",
    Due: "$0.00",
    PaymentClass: "badge badge-soft-success badge-xs shadow-none",
    PaymentStatus: "Paid",
  },
];

const getImageUrl = (imageName) => {
  return new URL(`/src/assets/img/products/${imageName}`, import.meta.url).href;
};
const getImageUrlOne = (imageName) => {
  return new URL(`/src/assets/img/users/${imageName}`, import.meta.url).href;
};
</script>
