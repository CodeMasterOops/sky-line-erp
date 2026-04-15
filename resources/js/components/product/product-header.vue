<template>
  <div class="page-header">
    <div class="add-item d-flex">
      <div class="page-title">
        <h4 class="fw-bold">Product List</h4>
        <h6>Manage your products</h6>
      </div>
    </div>
    <ul class="table-top-head">
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Pdf"><img src="@/assets/images/icons/pdf.svg"
            alt="img"></a>
      </li>
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Excel"><img src="@/assets/images/icons/excel.svg"
            alt="img"></a>
      </li>
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Refresh"><i class="ti ti-refresh"></i></a>
      </li>
      <li>
        <a data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse" id="collapse-header"
          @click="toggleHeader"><i class="ti ti-chevron-up"></i></a>
      </li>
    </ul>
    <div class="page-btn d-flex">
      <router-link :to="{ name: 'admin.product-create' }" v-can="'create_product'"
        class="btn btn-primary d-flex align-items-center">
        <vue-feather type="plus-circle" class="me-2"></vue-feather>
        Add Product</router-link>
    </div>
    <div class="page-btn import d-flex">
      <a href="#" class="btn btn-secondary color d-flex align-items-center" data-bs-toggle="modal"
        data-bs-target="#view-notes"><vue-feather type="download" class="me-2"></vue-feather>Import Product</a>
    </div>
  </div>
</template>
<script>
export default {
  data() {
    return {};
  },
  methods: {
    toggleHeader() {
      document.getElementById("collapse-header").classList.toggle("active");
      document.body.classList.toggle("header-collapse");
    },
  },
};
</script>
