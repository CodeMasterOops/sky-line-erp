<template>
  <pos-header></pos-header>

  <div class="page-wrapper pos-pg-wrapper ms-0">
    <div class="content pos-design p-0">
      <div class="row align-items-start pos-wrapper">
        <!-- Products -->
        <div class="col-md-12 col-lg-7 col-xl-8">
          <div class="pos-categories tabs_wrapper pb-0">
            <div class="card pos-button">
              <div class="d-flex align-items-center flex-wrap">
                <a
                  href="javascript:void(0);"
                  class="btn btn-teal btn-md mb-xs-3"
                  data-bs-toggle="modal"
                  data-bs-target="#orders"
                  ><i class="ti ti-shopping-cart me-1"></i>View Orders</a
                >
                <a
                  href="javascript:void(0);"
                  class="btn btn-md btn-indigo"
                  data-bs-toggle="modal"
                  data-bs-target="#reset"
                  ><i class="ti ti-reload me-1"></i>Reset</a
                >
                <a
                  href="javascript:void(0);"
                  class="btn btn-md btn-info"
                  data-bs-toggle="modal"
                  data-bs-target="#recents"
                  ><i class="ti ti-refresh-dot me-1"></i>Transaction</a
                >
              </div>
            </div>
            <div class="d-flex align-items-center justify-content-between">
              <h4 class="mb-3">Categories</h4>
            </div>
            <ul class="tabs owl-carousel pos-category">
              <Carousel
                :wrap-around="true"
                :settings="settings"
                :breakpoints="breakpoints"
              >
                <Slide v-for="item in pos2Carousel" :key="item.id">
                  <div class="owl-item">
                    <li
                      :id="item.IdName"
                      :class="{ active: activeTab === item.IdName }"
                      @click="setActiveTab(item.IdName)"
                    >
                      <a href="javascript:void(0);">
                        <img :src="getImageUrl(item.Image)"
                          alt="Categories"
                        />
                      </a>
                      <h6>
                        <a href="javascript:void(0);">{{ item.Title }}</a>
                      </h6>
                      <span>{{ item.Items }}</span>
                    </li>
                  </div>
                </Slide>
                <template #addons>
                  <Navigation />
                </template>
              </Carousel>
            </ul>
            <div class="pos-products">
              <div class="d-flex align-items-center justify-content-between">
                <h4 class="mb-3">Products</h4>
                <div class="input-icon-start pos-search position-relative mb-3">
                  <span class="input-icon-addon">
                    <i class="ti ti-search"></i>
                  </span>
                  <input type="text" class="form-control" placeholder="Search Product" />
                </div>
              </div>
              <div class="tabs_container">
                <div
                  class="tab_content"
                  data-tab="all"
                  v-if="activeTab === 'all'"
                  :style="{ display: activeTab === 'all' ? 'block' : 'none' }"
                >
                  <all-pos2></all-pos2>
                </div>
                <div
                  class="tab_content"
                  data-tab="headphones"
                  v-if="activeTab === 'headphones'"
                  :style="{ display: activeTab === 'headphones' ? 'block' : 'none' }"
                >
                  <headphones-pos2></headphones-pos2>
                </div>
                <div
                  class="tab_content"
                  data-tab="shoes"
                  v-if="activeTab === 'shoes'"
                  :style="{ display: activeTab === 'shoes' ? 'block' : 'none' }"
                >
                  <shoes-pos2></shoes-pos2>
                </div>
                <div
                  class="tab_content"
                  data-tab="mobiles"
                  v-if="activeTab === 'mobiles'"
                  :style="{ display: activeTab === 'mobiles' ? 'block' : 'none' }"
                >
                  <mobiles-pos2></mobiles-pos2>
                </div>
                <div
                  class="tab_content"
                  data-tab="watches"
                  v-if="activeTab === 'watches'"
                  :style="{ display: activeTab === 'watches' ? 'block' : 'none' }"
                >
                  <watches-pos2></watches-pos2>
                </div>
                <div
                  class="tab_content"
                  data-tab="laptops"
                  v-if="activeTab === 'laptops'"
                  :style="{ display: activeTab === 'laptops' ? 'block' : 'none' }"
                >
                  <div class="row">
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3">
                      <div class="product-info card">
                        <a href="javascript:void(0);" class="pro-img">
                          <img
                            :src="productImg('pos-product-02.png')"
                            alt="Products"
                          />
                          <span><i class="ti ti-circle-check-filled"></i></span>
                        </a>
                        <h6 class="cat-name">
                          <a href="javascript:void(0);">Computer</a>
                        </h6>
                        <h6 class="product-name">
                          <a href="javascript:void(0);">MacBook Pro</a>
                        </h6>
                        <div
                          class="d-flex align-items-center justify-content-between price"
                        >
                          <span>140 Pcs</span>
                          <p>$1000</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3">
                      <div class="product-info card">
                        <a href="javascript:void(0);" class="pro-img">
                          <img
                            :src="productImg('pos-product-07.png')"
                            alt="Products"
                          />
                          <span><i class="ti ti-circle-check-filled"></i></span>
                        </a>
                        <h6 class="cat-name"><a href="javascript:void(0);">Laptop</a></h6>
                        <h6 class="product-name">
                          <a href="javascript:void(0);">IdeaPad Slim 5 Gen 7</a>
                        </h6>
                        <div
                          class="d-flex align-items-center justify-content-between price"
                        >
                          <span>74 Pcs</span>
                          <p>$1454</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3">
                      <div class="product-info card">
                        <a href="javascript:void(0);" class="pro-img">
                          <img
                            :src="productImg('pos-product-10.png')"
                            alt="Products"
                          />
                          <span><i class="ti ti-circle-check-filled"></i></span>
                        </a>
                        <h6 class="cat-name">
                          <a href="javascript:void(0);">Computer</a>
                        </h6>
                        <h6 class="product-name">
                          <a href="javascript:void(0);">Tablet 1.02 inch</a>
                        </h6>
                        <div
                          class="d-flex align-items-center justify-content-between price"
                        >
                          <span>14 Pcs</span>
                          <p>$4744</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3">
                      <div class="product-info card">
                        <a href="javascript:void(0);" class="pro-img">
                          <img
                            :src="productImg('pos-product-13.png')"
                            alt="Products"
                          />
                          <span><i class="ti ti-circle-check-filled"></i></span>
                        </a>
                        <h6 class="cat-name"><a href="javascript:void(0);">Laptop</a></h6>
                        <h6 class="product-name">
                          <a href="javascript:void(0);">Yoga Book 9i</a>
                        </h6>
                        <div
                          class="d-flex align-items-center justify-content-between price"
                        >
                          <span>65 Pcs</span>
                          <p>$4784</p>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-6 col-md-6 col-lg-4 col-xl-3">
                      <div class="product-info card">
                        <a href="javascript:void(0);" class="pro-img">
                          <img
                            :src="productImg('pos-product-14.png')"
                            alt="Products"
                          />
                          <span><i class="ti ti-circle-check-filled"></i></span>
                        </a>
                        <h6 class="cat-name"><a href="javascript:void(0);">Laptop</a></h6>
                        <h6 class="product-name">
                          <a href="javascript:void(0);">IdeaPad Slim 3i</a>
                        </h6>
                        <div
                          class="d-flex align-items-center justify-content-between price"
                        >
                          <span>47 Pcs</span>
                          <p>$1245</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /Products -->

        <!-- Order Details -->
        <div class="col-md-12 col-lg-5 col-xl-4 ps-0 theiaStickySidebar">
          <aside class="product-order-list">
            <div
              class="order-head bg-light d-flex align-items-center justify-content-between w-100"
            >
              <div>
                <h3>Order List</h3>
                <span>Transaction ID : #65565</span>
              </div>
              <div>
                <a class="link-danger fs-16" href="javascript:void(0);"
                  ><i class="ti ti-trash-x-filled"></i
                ></a>
              </div>
            </div>
            <div class="customer-info block-section">
              <h4 class="mb-3">Customer Information</h4>
              <div class="input-block d-flex align-items-center">
                <div class="flex-grow-1">
                  <vue-select
                    :options="Information"
                    v-model="selected"
                    placeholder="Walk in Customer"
                  />
                </div>
                <a
                  href="#"
                  class="btn btn-primary btn-icon"
                  data-bs-toggle="modal"
                  data-bs-target="#create"
                >
                  <vue-feather type="user-plus" class="feather-16"></vue-feather>
                </a>
              </div>
              <div class="input-block">
                <vue-select
                  :options="Oldest"
                  v-model="selectedOne"
                  placeholder="Search Products"
                />
              </div>
            </div>
            <div class="product-added block-section">
              <div class="head-text d-flex align-items-center justify-content-between">
                <h5 class="d-flex align-items-center mb-0">
                  Product Added<span class="count">2</span>
                </h5>
                <a
                  href="javascript:void(0);"
                  class="d-flex align-items-center link-danger"
                  ><span class="me-2"><i data-feather="x" class="feather-16"></i></span
                  >Clear all</a
                >
              </div>
              <div class="product-wrap">
                <div class="empty-cart">
                  <div class="fs-24 mb-1">
                    <i class="ti ti-shopping-cart"></i>
                  </div>
                  <p class="fw-bold">No Products Selected</p>
                </div>
                <div class="product-list align-items-center justify-content-between">
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="productImg('pos-product-16.png')"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <span>PT0005</span>
                      <h6><a href="javascript:void(0);">Red Nike Laser</a></h6>
                      <p class="fw-bold text-teal">$2000</p>
                    </div>
                  </div>
                  <div class="qty-item text-center">
                    <pos-counter-2></pos-counter-2>
                  </div>
                  <div class="d-flex align-items-center action">
                    <a
                      class="btn-icon edit-icon me-1"
                      href="#"
                      data-bs-toggle="modal"
                      data-bs-target="#edit-product"
                    >
                      <vue-feather type="edit"></vue-feather>
                    </a>
                    <a
                      class="btn-icon delete-icon"
                      href="javascript:void(0);"
                      data-bs-toggle="modal"
                      data-bs-target="#delete"
                    >
                      <vue-feather type="trash-2"></vue-feather>
                    </a>
                  </div>
                </div>
                <div class="product-list align-items-center justify-content-between">
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="productImg('pos-product-17.png')"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <span>PT0235</span>
                      <h6><a href="javascript:void(0);">Iphone 14</a></h6>
                      <p class="fw-bold text-teal">$3000</p>
                    </div>
                  </div>
                  <div class="qty-item text-center">
                    <pos-counter-2></pos-counter-2>
                  </div>
                  <div class="d-flex align-items-center action">
                    <a
                      class="btn-icon edit-icon me-1"
                      href="#"
                      data-bs-toggle="modal"
                      data-bs-target="#edit-product"
                    >
                      <vue-feather type="edit"></vue-feather>
                    </a>
                    <a
                      class="btn-icon delete-icon"
                      href="javascript:void(0);"
                      data-bs-toggle="modal"
                      data-bs-target="#delete"
                    >
                      <vue-feather type="trash-2"></vue-feather>
                    </a>
                  </div>
                </div>
                <div class="product-list align-items-center justify-content-between">
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="productImg('pos-product-09.png')"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <span>PT0242</span>
                      <h6><a href="javascript:void(0);">Timex Black Silver</a></h6>
                      <p class="fw-bold text-teal">$1457</p>
                    </div>
                  </div>
                  <div class="qty-item text-center">
                    <pos-counter-2></pos-counter-2>
                  </div>
                  <div class="d-flex align-items-center action">
                    <a
                      class="btn-icon edit-icon me-1"
                      href="#"
                      data-bs-toggle="modal"
                      data-bs-target="#edit-product"
                    >
                      <vue-feather type="edit"></vue-feather>
                    </a>
                    <a
                      class="btn-icon delete-icon"
                      href="javascript:void(0);"
                      data-bs-toggle="modal"
                      data-bs-target="#delete"
                    >
                      <vue-feather type="trash-2"></vue-feather>
                    </a>
                  </div>
                </div>
                <div class="product-list align-items-center justify-content-between">
                  <div
                    class="d-flex align-items-center product-info"
                    data-bs-toggle="modal"
                    data-bs-target="#products"
                  >
                    <a href="javascript:void(0);" class="pro-img">
                      <img
                        :src="productImg('pos-product-08.png')"
                        alt="Products"
                      />
                    </a>
                    <div class="info">
                      <span>PT0005</span>
                      <h6><a href="javascript:void(0);">SWAGME</a></h6>
                      <p class="fw-bold text-teal">$6587</p>
                    </div>
                  </div>
                  <div class="qty-item text-center">
                    <pos-counter-2></pos-counter-2>
                  </div>
                  <div class="d-flex align-items-center action">
                    <a
                      class="btn-icon edit-icon me-1"
                      href="#"
                      data-bs-toggle="modal"
                      data-bs-target="#edit-product"
                    >
                      <vue-feather type="edit"></vue-feather>
                    </a>
                    <a
                      class="btn-icon delete-icon"
                      href="javascript:void(0);"
                      data-bs-toggle="modal"
                      data-bs-target="#delete"
                    >
                      <vue-feather type="trash-2"></vue-feather>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <div class="block-section">
              <div class="selling-info">
                <div class="row g-3">
                  <div class="col-12 col-sm-4">
                    <div>
                      <label class="form-label">Order Tax</label>
                      <vue-select
                        :options="Ordertax"
                        v-model="selectedTwo"
                        placeholder="Choose"
                      />
                    </div>
                  </div>
                  <div class="col-12 col-sm-4">
                    <div>
                      <label class="form-label">Shipping</label>
                      <vue-select :options="Shipping" v-model="selectedThree" placeholder="0" />
                    </div>
                  </div>
                  <div class="col-12 col-sm-4">
                    <div>
                      <label class="form-label">Discount</label>
                      <vue-select :options="Discount" v-model="selectedFour" placeholder="0" />
                    </div>
                  </div>
                </div>
              </div>
              <div class="order-total">
                <table class="table table-borderless">
                  <tbody>
                    <tr>
                      <td>Sub Total</td>
                      <td class="text-end">$60,454</td>
                    </tr>
                    <tr>
                      <td>Tax (GST 5%)</td>
                      <td class="text-end">$40.21</td>
                    </tr>
                    <tr>
                      <td>Shipping</td>
                      <td class="text-end">$40.21</td>
                    </tr>
                    <tr>
                      <td>Sub Total</td>
                      <td class="text-end">$60,454</td>
                    </tr>
                    <tr>
                      <td class="text-danger">Discount (10%)</td>
                      <td class="text-danger text-end">$15.21</td>
                    </tr>
                    <tr>
                      <td>Total</td>
                      <td class="text-end">$64,024.5</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="block-section payment-method">
              <h4>Payment Method</h4>
              <div class="row align-items-center justify-content-center methods g-3">
                <div class="col-sm-6 col-md-4">
                  <a
                    href="javascript:void(0);"
                    class="payment-item"
                    data-bs-toggle="modal"
                    data-bs-target="#payment-cash"
                  >
                    <i class="ti ti-cash-banknote fs-18"></i>
                    <span>Cash</span>
                  </a>
                </div>
                <div class="col-sm-6 col-md-4">
                  <a
                    href="javascript:void(0);"
                    class="payment-item"
                    data-bs-toggle="modal"
                    data-bs-target="#payment-card"
                  >
                    <i class="ti ti-credit-card fs-18"></i>
                    <span>Debit Card</span>
                  </a>
                </div>
                <div class="col-sm-6 col-md-4">
                  <a
                    href="javascript:void(0);"
                    class="payment-item"
                    data-bs-toggle="modal"
                    data-bs-target="#scan-payment"
                  >
                    <i class="ti ti-scan fs-18"></i>
                    <span>Scan</span>
                  </a>
                </div>
              </div>
            </div>
            <div class="btn-block">
              <a class="btn btn-secondary w-100" href="javascript:void(0);">
                Grand Total : $64,024.5
              </a>
            </div>
            <div class="btn-row d-sm-flex align-items-center justify-content-between">
              <a
                href="javascript:void(0);"
                class="btn btn-purple d-flex align-items-center justify-content-center flex-fill"
                data-bs-toggle="modal"
                data-bs-target="#hold-order"
                ><i class="ti ti-player-pause me-1"></i>Hold</a
              >
              <a
                href="javascript:void(0);"
                class="btn btn-danger d-flex align-items-center justify-content-center flex-fill"
                ><i class="ti ti-trash me-1"></i>Void</a
              >
              <a
                href="javascript:void(0);"
                class="btn btn-success d-flex align-items-center justify-content-center flex-fill"
                data-bs-toggle="modal"
                data-bs-target="#payment-completed"
                ><i class="ti ti-cash-banknote me-1"></i>Payment</a
              >
            </div>
          </aside>
        </div>
        <!-- /Order Details -->
      </div>
    </div>
  </div>

  <pos-two-modal></pos-two-modal>
</template>

<script>
import { Carousel, Slide, Navigation } from "vue3-carousel";
import "vue3-carousel/dist/carousel.css";
import pos2Carousel from "@/assets/json/pos-two-carousel.json";
import PosHeader from "@/layouts/pos-header.vue";
import AllPos2 from "./all-pos2.vue";
import HeadphonesPos2 from "./headphones-pos2.vue";
import ShoesPos2 from "./shoes-pos2.vue";
import MobilesPos2 from "./mobiles-pos2.vue";
import WatchesPos2 from "./watches-pos2.vue";
import PosCounter2 from "./pos-counter-2.vue";
import PosTwoModal from "@/components/modal/pos-two-modal.vue";

export default {
  data() {
    return {
      selected: "Walk in Customer",
      selectedOne: "Search Products",
      selectedTwo: "Choose",
      selectedThree: "0",
      selectedFour: "0",
      Information: [
        {label: "Walk in Customer", value: "Walk in Customer"}, 
        {label: "John", value: "John"}, 
        {label: "Smith", value: "Smith"}, 
        {label: "Ana", value: "Ana"}, 
        {label: "Elza", value: "Elza"}
      ],
      Oldest: [
        {label: "Search Products", value: "Search Products"},
        {label: "IPhone 14 64GB", value: "IPhone 14 64GB"},
        {label: "MacBook Pro", value: "MacBook Pro"},
        {label: "Rolex Tribute V3", value: "Rolex Tribute V3"},
        {label: "Red Nike Angelo", value: "Red Nike Angelo"},
        {label: "Airpod 2", value: "Airpod 2"},
        {label: "Oldest", value: "Oldest"},
      ],
      Ordertax: [
        {label: "Choose", value: "Choose"},
        {label: "GST 5%", value: "GST 5%"}, 
        {label: "GST 15%", value: "GST 15%"}, 
        {label: "GST 20%", value: "GST 20%"}, 
        {label: "GST 25%", value: "GST 25%"}, 
        {label: "GST 30%", value: "GST 30%"},
      ],
      Discount: [
        {label: "0%", value: "0%"}, 
        {label: "10%", value: "10%"}, 
        {label: "15%", value: "15%"}, 
        {label: "20%", value: "20%"}, 
        {label: "25%", value: "25%"}, 
        {label: "30%", value: "30%"}, 
      ],
      Shipping: [
        {label: "0", value: "0"}, 
        {label: "15", value: "15"}, 
        {label: "20", value: "20"}, 
        {label: "25", value: "25"}, 
        {label: "30", value: "30"},
      ],
      pos2Carousel: pos2Carousel,
      activeTab: "all",
      settings: {
        itemsToShow: 1,
        snapAlign: "center",
        loop: true,
      },
      breakpoints: {
        575: {
          itemsToShow: 1,
          snapAlign: "center",
        },
        767: {
          itemsToShow: 3,
          snapAlign: "center",
        },
        991: {
          itemsToShow: 5,
          snapAlign: "center",
        },
        1024: {
          itemsToShow: 6,
          snapAlign: "start",
        },
      },
    };
  },
  methods: {
    setActiveTab(tabName) {
      this.activeTab = tabName;
    },
    productImg(file) {
      return `/img/products/${file}`;
    },
    getImageUrl(imageName) {
      return `/img/categories/${imageName}`;
    },
  },
  components: {
    PosHeader,
    AllPos2,
    HeadphonesPos2,
    ShoesPos2,
    MobilesPos2,
    WatchesPos2,
    PosCounter2,
    PosTwoModal,
    Carousel,
    Slide,
    Navigation,
  },
};
</script>
