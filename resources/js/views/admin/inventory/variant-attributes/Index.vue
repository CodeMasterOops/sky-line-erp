<template>
    <PageHeader title="Variant Attributes" subtitle="Manage your variant attributes">
        <template #actions>
            <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-units">
                <i class="ti ti-circle-plus me-2"></i>Add New Variant
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
                        Variant
                    </a>
                    <ul class="dropdown-menu  dropdown-menu-end p-3">
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Lenovo</a>
                        </li>
                        <li>
                            <a href="javascript:void(0);" class="dropdown-item rounded-1">Beats</a>
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
                        <template v-if="column.key === 'Status'">
                            <span class="badge table-badge bg-success fw-medium fs-10">{{
                    record.Status
                  }}</span></template>
                        <template v-else-if="column.key === 'action'">
                            <div class="action-table-data">
                                <div class="edit-delete-action">
                                    <a class="me-2 p-2" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#edit-units">
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
    <varriant-attributes-modal></varriant-attributes-modal>
</template>
<script setup>
const columns = [
  {
    title: "Variant",
    dataIndex: "Variant",
    sorter: {
      compare: (a, b) => {
        a = a.Variant.toLowerCase();
        b = b.Variant.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Values",
    dataIndex: "Values",
    sorter: {
      compare: (a, b) => {
        a = a.Values.toLowerCase();
        b = b.Values.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Created On",
    dataIndex: "CreatedOn",
    sorter: {
      compare: (a, b) => {
        a = a.CreatedOn.toLowerCase();
        b = b.CreatedOn.toLowerCase();
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
    Variant: "Size (T-shirts)",
    Values: "S,M,L,XL",
    CreatedOn: "25 May 2024",
    Status: "Active",
  },
  {
    Variant: "Size (Shoes)",
    Values: "5,6,7,8,9",
    CreatedOn: "24 Jun 2024",
    Status: "Active",
  },
  {
    Variant: "Color",
    Values: "Red, Blue, Green",
    CreatedOn: "23 Jul 2024",
    Status: "Active",
  },
  {
    Variant: "Memory",
    Values: "64 GB, 128 GB, 512 GB",
    CreatedOn: "22 Aug 2024",
    Status: "Active",
  },
  {
    Variant: "Storage",
    Values: "250GB, 1TB",
    CreatedOn: "21 Sep 2024",
    Status: "Active",
  },
];
</script>
