<template>
  <div class="row">
    <div
      class="col-sm-6 col-md-6 col-lg-4 col-xl-3"
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
          <span>{{ product.quantity }} Pcs</span>
          <p>${{ product.price }}</p>
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
          quantity: 30,
          image: "pos-product-01.png",
          isActive: true,
        },
        {
          name: "Computer",
          modal: "MacBook Pro",
          price: 1000,
          quantity: 140,
          image: "pos-product-02.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Rolex Tribute V3",
          price: 6800,
          quantity: 220,
          image: "pos-product-03.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Red Nike Angelo",
          price: 7800,
          quantity: 78,
          image: "pos-product-04.png",
          isActive: false,
        },
        {
          name: "Headphones",
          modal: "Airpod 2",
          price: 5478,
          quantity: 47,
          image: "pos-product-05.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Blue White OGR",
          price: 987,
          quantity: 54,
          image: "pos-product-06.png",
          isActive: false,
        },
        {
          name: "Laptop",
          modal: "IdeaPad Slim 5 Gen 7",
          price: 1454,
          quantity: 74,
          image: "pos-product-07.png",
          isActive: false,
        },
        {
          name: "Headphones",
          modal: "SWAGME",
          price: 6587,
          quantity: 14,
          image: "pos-product-08.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Timex Black Silver",
          price: 1457,
          quantity: 24,
          image: "pos-product-09.png",
          isActive: false,
        },
        {
          name: "Computer",
          modal: "Tablet 1.02 inch",
          price: 4744,
          quantity: 14,
          image: "pos-product-10.png",
          isActive: false,
        },
        {
          name: "Watches",
          modal: "Fossil Pair Of 3 in 1",
          price: 789,
          quantity: 40,
          image: "pos-product-11.png",
          isActive: false,
        },
        {
          name: "Shoes",
          modal: "Green Nike Fe",
          price: 7847,
          quantity: 78,
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
      return `/img/products/${imageName}`;
    },
  },
};
</script>
