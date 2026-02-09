<template>
    <PageHeader title="Print Barcode" subtitle="Manage your barcodes" />
    <div class="barcode-content-list">
        <form>
            <div class="row">
                <div class="col-lg-6 col-12">
                    <div class="row seacrh-barcode-item">
                        <div class="col-sm-6 mb-3 seacrh-barcode-item-one">
                            <label class="form-label">Warehouse</label>
                            <vue-select :options="Choosebarcode" v-model="selected" placeholder="Choose" />
                        </div>
                        <div class="col-sm-6 mb-3 seacrh-barcode-item-one">
                            <label class="form-label">Select Store</label>
                            <vue-select :options="Choosebarstore" v-model="selectedOne" placeholder="Choose" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-6">
                    <div class="input-blocks search-form seacrh-barcode-item">
                        <div class="searchInput">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" placeholder="Search Product by Codename" />
                            <div class="resultBox"></div>
                            <div class="icon"><i class="ti ti-search"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="col-lg-12">
            <div class="modal-body-table search-modal-header">
                <div class="table-responsive">
                    <a-table class="table datanew" :columns="columns" :data-source="data" :row-selection="{}">
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'Product'">
                                <div class="productimgname">
                                    <a href="javascript:void(0);" class="product-img stock-img">
                                        <img :src="getImageUrl(record.Image)" alt="product" />
                                    </a>
                                    <a href="javascript:void(0);">{{ record.Product }}</a>
                                </div>
                            </template>
                            <template v-else-if="column.key === 'Qty'">
                                <div class="product-quantity">
                                    <span class="quantity-btn" @click="updateQuantity(record, '-')">
                                        <i class="ti ti-minus-circle"></i>
                                    </span>
                                    <input type="text" class="quantity-input" v-model="record.Qty" />
                                    <span class="quantity-btn" @click="updateQuantity(record, '+')">
                                        <i class="ti ti-plus-circle"></i>
                                    </span>
                                </div>
                            </template>
                            <template v-else-if="column.key === 'action'">
                                <div class="action-table-data justify-content-center">
                                    <div class="edit-delete-action">
                                        <a class="confirm-text barcode-delete-icon" @click="showConfirmation" href="javascript:void(0);">
                                            <i data-feather="trash-2" class="feather-trash-2"></i>
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </template>
                    </a-table>
                </div>
            </div>
        </div>

        <div class="paper-search-size">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <form class="mb-0">
                        <label class="form-label">Paper Size</label>
                        <vue-select :options="Paper" v-model="selectedTwo" placeholder="Choose" />
                    </form>
                </div>
                <div class="col-lg-6 pt-3">
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="search-toggle-list">
                                <p>Show Store Name</p>
                                <div class="input-blocks m-0">
                                    <div class="status-toggle modal-status d-flex justify-content-between align-items-center">
                                        <input type="checkbox" id="user7" class="check" checked />
                                        <label for="user7" class="checktoggle mb-0"> </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-4">
                            <div class="search-toggle-list">
                                <p>Show Product Name</p>
                                <div class="input-blocks m-0">
                                    <div class="status-toggle modal-status d-flex justify-content-between align-items-center">
                                        <input type="checkbox" id="user8" class="check" checked />
                                        <label for="user8" class="checktoggle mb-0"> </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-4">
                            <div class="search-toggle-list">
                                <p>Show Price</p>
                                <div class="input-blocks m-0">
                                    <div class="status-toggle modal-status d-flex justify-content-between align-items-center">
                                        <input type="checkbox" id="user9" class="check" checked />
                                        <label for="user9" class="checktoggle mb-0"> </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-barcode-button">
            <a href="javascript:void(0);" class="btn btn-submit me-2" data-bs-toggle="modal" data-bs-target="#prints-barcode">
                <span><i class="ti ti-eye me-2"></i></span>
                Generate Barcode</a>
            <a href="javascript:void(0);" class="btn btn-cancel me-2">
                <span><i class="ti ti-power me-2"></i></span>
                Reset Barcode</a>
            <a href="javascript:void(0);" class="btn btn-cancel close-btn">
                <span><i class="ti ti-printer me-2"></i></span>
                Print Barcode</a>
        </div>
    </div>
    <barcode-modal></barcode-modal>
</template>
<script setup>
import { ref } from "vue";

const selected = ref([]);
const selectedOne = ref([]);
const selectedTwo = ref([]);

const Choosebarcode = [
  { label: "Choose", value: "Choose" },
  { label: "Manufacture", value: "Manufacture" },
  { label: "Transport", value: "Transport" },
  { label: "Customs", value: "Customs" },
];
const Paper = [
  { label: "Choose", value: "Choose" },
  { label: "Recent1", value: "Recent1" },
  { label: "Recent2", value: "Recent2" },
];
const Choosebarstore = [
  { label: "Choose", value: "Choose" },
  { label: "Online", value: "Online" },
  { label: "offline", value: "offline" },
];

const columns = [
  {
    title: "Product",
    dataIndex: "Product",
    key: "Product",
    sorter: {
      compare: (a, b) => {
        a = a.Product.toLowerCase();
        b = b.Product.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "SKU",
    dataIndex: "SKU",
    sorter: {
      compare: (a, b) => {
        a = a.SKU.toLowerCase();
        b = b.SKU.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Code",
    dataIndex: "Code",
    sorter: {
      compare: (a, b) => {
        a = a.Code.toLowerCase();
        b = b.Code.toLowerCase();
        return a > b ? -1 : b > a ? 1 : 0;
      },
    },
  },
  {
    title: "Qty",
    dataIndex: "Qty",
    key: "Qty",
    sorter: {
      compare: (a, b) => {
        a = a.Qty.toLowerCase();
        b = b.Qty.toLowerCase();
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
const data = ref([
  {
    Product: "Nike Jordan",
    SKU: "PT002",
    Code: "HG3FK",
    Image: "stock-img-02.png",
    Qty: 10,
  },
  {
    Product: "Apple Series 5 Watch",
    SKU: "PT003",
    Code: "TEUIU7",
    Image: "stock-img-03.png",
    Qty: 10,
  },
]);

const updateQuantity = (record, action) => {
  if (action === "+" && record.Qty >= 0) {
    record.Qty++;
  } else if (action === "-" && record.Qty > 0) {
    record.Qty--;
  }
};

const getImageUrl = (imageName) => {
  return new URL(`/src/assets/img/products/${imageName}`, import.meta.url).href;
};

const showConfirmation = () => {
    // Implement delete logic
};
</script>
