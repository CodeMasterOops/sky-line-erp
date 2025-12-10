<template>
  <aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
      <li class="nav-item">
        <a :href="siteUrl" class="nav-link" target="_blank">
          <i class="fa fa-home"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <router-link :to="{ name: 'vendor.dashboard' }" class="nav-link">
          <i class="fa fa-dashboard"></i>
          <span>Dashboard</span>
        </router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{ name: 'vendor.order-list' }" class="nav-link">
          <i class="fa fa-shopping-cart"></i>
          <span>Orders</span>
        </router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{ name: 'vendor.coupon-list' }" class="nav-link">
          <i class="fa fa-gift"></i>
          <span>Coupons</span>
        </router-link>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarProducts"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-sitemap"></i>
          <span>Products</span
          >
        </a>
        <ul
            id="sidebarProducts"
            class="nav-content collapse"
            data-bs-parent="#sidebarProducts"
        >
          <li>
            <router-link :to="{ name: 'vendor.product-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Products</span>
            </router-link>
          </li>
          <li>
            <router-link :to="{ name: 'vendor.stock-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Stock</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarMedia"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-file-image-o"></i>
          <span>Media</span
          >
        </a>
        <ul
            id="sidebarMedia"
            class="nav-content collapse"
            data-bs-parent="#sidebarMedia"
        >
          <li>
            <router-link :to="{ name: 'vendor.media-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>List</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarSettings"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-cogs"></i>
          <span>Settings</span
          >
        </a>
        <ul
            id="sidebarSettings"
            class="nav-content collapse"
            data-bs-parent="#sidebarSettings"
        >
          <li>
            <router-link :to="{ name: 'vendor.setting' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Site Setting</span>
            </router-link>
          </li>
          <li>
            <router-link :to="{ name: 'vendor.shipping-setting' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Shipping</span>
            </router-link>
          </li>
        </ul>
      </li>
    </ul>
  </aside>
</template>
<script setup>
import {siteUrl} from "@/helpers/helper.js";
</script>
