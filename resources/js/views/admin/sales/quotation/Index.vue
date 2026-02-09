<template>
    <PageHeader title="Quotation List" subtitle="Manage Your Quotation">
        <template #actions>
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-units">
                <i class="ti ti-circle-plus me-2"></i>Add Quotation
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
                        Product
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Lenovo IdeaPad 3</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Beats Pro</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Nike Jordan</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Apple Series 5 Watch</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Customer
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Carl Evans</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Minerva Rameriz</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Robert Lamon</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Patricia Lewis</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Status
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Sent</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Pending</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Ordered</a>
                        </li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a href="javascript:void(0);" class="dropdown-toggle btn btn-white btn-md d-inline-flex align-items-center" data-bs-toggle="dropdown">
                        Sort By : Last 7 Days
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end p-3">
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
            <div class="custom-datatable-filter table-responsive">
                <a-table class="table datanew" :columns="columns" :data-source="data" :row-selection="{}">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'ProductName'">
                            <div class="d-flex align-items-center me-2">
                                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                                    <img :src="getImageUrl(record.Image)" alt="product" />
                                </a>
                                <a href="javascript:void(0);">{{ record.ProductName }}</a>
                            </div>
                        </template>
                        <template v-if="column.key === 'CustomerName'">
                            <div class="d-flex align-items-center">
                                <a href="javascript:void(0);" class="avatar avatar-md me-2">
                                    <img :src="getImageUrlOne(record.CustmerImage)" alt="product" />
                                </a>
                                <a href="javascript:void(0);">{{ record.CustomerName }}</a>
                            </div>
                        </template>
                        <template v-if="column.key === 'Status'">
                            <div>
                                <span :class="record.StatusClass">{{ record.Status }}</span>
                            </div>
                        </template>
                        <template v-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2 mb-0" href="javascript:void(0);">
                                        <i class="ti ti-eye"></i>
                                    </a>
                                    <a class="me-2 p-2 mb-0" data-bs-toggle="modal" data-bs-target="#edit-units">
                                        <i class="ti ti-edit"></i>
                                    </a>
                                    <a class="p-2 mb-0" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#delete">
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

    <quotation-list-modal></quotation-list-modal>
</template>

<script setup>




const columns = [
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
    title: "Action",
    key: "action",
    sorter: true,
  },
];
const data = [
  {
    ProductName: "Lenovo 3rd Generation",
    Image: "stock-img-01.png",
    CustomerName: "Carl Evans",
    CustmerImage: "user-27.jpg",
    Status: "Sent",
    StatusClass: "badge badge-success",
    Total: "$550",
  },
  {
    ProductName: "Bold V3.2",
    Image: "stock-img-06.png",
    CustomerName: "Minerva Rameriz",
    CustmerImage: "user-02.jpg",
    Status: "Sent",
    StatusClass: "badge badge-success",
    Total: "$430",
  },
  {
    ProductName: "Nike Jordan",
    Image: "stock-img-02.png",
    CustomerName: "Robert Lamon",
    CustmerImage: "user-05.jpg",
    Status: "Ordered",
    StatusClass: "badge badge-warning",
    Total: "$260",
  },
  {
    ProductName: "Apple Series 5 Watch",
    Image: "stock-img-03.png",
    CustomerName: "Mark Joslyn",
    CustmerImage: "user-03.jpg",
    Status: "sent",
    StatusClass: "badge badge-success",
    Total: "$470",
  },
  {
    ProductName: "Amazon Echo Dot",
    Image: "stock-img-04.png",
    CustomerName: "Patricia Lewis",
    CustmerImage: "user-22.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    Total: "$380",
  },
  {
    ProductName: "Lobar Handy",
    Image: "stock-img-05.png",
    CustomerName: "Marsha Betts",
    CustmerImage: "user-12.jpg",
    Status: "Sent",
    StatusClass: "badge badge-success",
    Total: "$190",
  },
  {
    ProductName: "Red Premium Handy",
    Image: "expire-product-01.png",
    CustomerName: "Daniel Jude",
    CustmerImage: "user-06.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    Total: "$540",
  },
  {
    ProductName: "Iphone 14 Pro",
    Image: "expire-product-02.png",
    CustomerName: "Emma Bates",
    CustmerImage: "user-21.jpg",
    Status: "Ordered",
    StatusClass: "badge badge-warning",
    Total: "$610",
  },
  {
    ProductName: "Black Slim 200",
    Image: "expire-product-03.png",
    CustomerName: "Richard Fralick",
    CustmerImage: "user-16.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    Total: "$220",
  },
  {
    ProductName: "Woodcraft Sandal",
    Image: "expire-product-04.png",
    CustomerName: "Michelle Robison",
    CustmerImage: "user-26.jpg",
    Status: "Sent",
    StatusClass: "badge badge-success",
    Total: "$460",
  },
  {
    ProductName: "Lobar Handy",
    Image: "expire-product-01.png",
    CustomerName: "Mark Joslyn",
    CustmerImage: "user-03.jpg",
    Status: "Pending",
    StatusClass: "badge badge-cyan",
    Total: "$250",
  },
  {
    ProductName: "Iphone 15 Pro",
    Image: "expire-product-02.png",
    CustomerName: "Robert Lamon",
    CustmerImage: "user-05.jpg",
    Status: "Sent",
    StatusClass: "badge badge-success",
    Total: "$550",
  },
];
const getImageUrl = (imageName) => {
  return new URL(`/src/assets/img/products/${imageName}`, import.meta.url).href;
};
const getImageUrlOne = (imageName) => {
  return new URL(`/src/assets/img/users/${imageName}`, import.meta.url).href;
};
</script>
