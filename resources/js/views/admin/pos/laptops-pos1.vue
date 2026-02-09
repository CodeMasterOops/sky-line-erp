<template>
  <div class="row">
    <div
      class="col-sm-6 col-md-6 col-lg-6 col-xl-4 col-xxl-3"
      v-for="(product, index) in products"
      :key="index"
    >
      <div
        :class="['product-info card', { active: product.isActive }]"
        @click="toggleActive(product)"
      >
        <a href="javascript:void(0);" class="pro-img">
          <img :src="getImageUrl(product.image)" alt="Products" />
          <span><i class="ti ti-circle-check-filled"></i></span>
        </a>
        <h6 class="cat-name">
          <a href="javascript:void(0);">{{ product.name }}</a>
        </h6>
        <h6 class="product-name">
          <a href="javascript:void(0);">{{ product.modal }}</a>
        </h6>
        <div class="d-flex align-items-center justify-content-between price">
          <p class="text-gray-9 mb-0">${{ product.price }}</p>
          <div class="qty-item m-0">
            <pos-counter></pos-counter>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
  data() {
    return {
      products: [
        {
          name: "Computer",
          modal: "MacBook Pro",
          price: 1000,
          quantity: 140,
          image: "pos-product-02.png",
          isActive: true,
        },
        {
          name: "Laptop",
          modal: "IdeaPad Slim 5 Gen 7",
          price: 1454,
          quantity: 74,
          image: "pos-product-10.png",
          isActive: false,
        },
        {
          name: "Computer",
          modal: "Tablet 1.02 inch",
          price: 4744,
          quantity: 14,
          image: "pos-product-07.png",
          isActive: false,
        },
        {
          name: "Laptop",
          modal: "Yoga Book 9i",
          price: 4784,
          quantity: 65,
          image: "pos-product-13.png",
          isActive: false,
        },
        {
          name: "Laptop",
          modal: "IdeaPad Slim 3i",
          price: 1245,
          quantity: 47,
          image: "pos-product-14.png",
          isActive: false,
        },
      ],
      showActiveOnly: false,
    };
  },
  computed: {
    filteredProducts() {
      if (this.showActiveOnly) {
        return this.products.filter((product) => product.isActive);
      }
      return this.products;
    },
  },
  methods: {
    toggleActive(product) {
      product.isActive = !product.isActive;
    },
    getImageUrl(imageName) {
      return new URL(`/src/assets/img/products/${imageName}`, import.meta.url).href;
    },
  },
};
</script>
