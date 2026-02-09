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
          name: "Mobiles",
          modal: "IPhone 14 64GB",
          price: 15800,
          image: "pos-product-01.png",
          isActive: true,
        },
        {
          name: "Computer",
          modal: "MacBook Pro",
          price: 1000,
          image: "pos-product-02.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Rolex Tribute V3",
          price: 6800,
          image: "pos-product-03.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Red Nike Angelo",
          price: 7800,
          image: "pos-product-04.png",
          isActive: false,
        },
        {
          name: "Headphones",
          modal: "Airpod 2",
          price: 5478,
          image: "pos-product-05.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Blue White OGR",
          price: 987,
          image: "pos-product-06.png",
          isActive: false,
        },
        {
          name: "Laptop",
          modal: "IdeaPad Slim 5 Gen 7",
          price: 1454,
          image: "pos-product-07.png",
          isActive: false,
        },
        {
          name: "Headphones",
          modal: "SWAGME",
          price: 6587,
          image: "pos-product-08.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Timex Black Silver",
          price: 1457,
          image: "pos-product-09.png",
          isActive: false,
        },
        {
          name: "Computer",
          modal: "Tablet 1.02 inch",
          price: 4744,
          image: "pos-product-10.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Fossil Pair Of 3 in 1",
          price: 789,
          image: "pos-product-11.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Green Nike Fe",
          price: 7847,
          image: "pos-product-13.png",
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
