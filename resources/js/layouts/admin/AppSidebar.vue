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
        <router-link :to="{ name: 'admin.dashboard' }" class="nav-link">
          <i class="fa fa-dashboard"></i>
          <span>Dashboard</span>
        </router-link>
      </li>
      <li class="nav-item">
        <router-link :to="{ name: 'admin.order-list' }" class="nav-link">
          <i class="fa fa-shopping-cart"></i>
          <span>Orders</span>
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
          <li v-can="'list_product_category'">
            <router-link :to="{ name: 'admin.product-category-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Category</span>
            </router-link>
          </li>
          <li v-can="'list_brand'">
            <router-link :to="{ name: 'admin.brand-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Brands</span>
            </router-link>
          </li>
          <li v-can="'list_attribute'">
            <router-link :to="{ name: 'admin.attribute-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Attributes</span>
            </router-link>
          </li>
          <li v-can="'list_tag'">
            <router-link :to="{ name: 'admin.tag-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Tags</span>
            </router-link>
          </li>
          <li v-can="'list_product'">
            <router-link :to="{ name: 'admin.product-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Products</span>
            </router-link>
          </li>
          <li v-can="'list_collection'">
            <router-link :to="{ name: 'admin.collection-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Collections</span>
            </router-link>
          </li>
          <li v-can="'list_stock'">
            <router-link :to="{ name: 'admin.stock-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Stock</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarBlog"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-map-pin"></i>
          <span>Blogs</span
          >
        </a>
        <ul
            id="sidebarBlog"
            class="nav-content collapse"
            data-bs-parent="#sidebarBlog"
        >
          <li v-can="'list_blog'">
            <router-link :to="{ name: 'admin.blog-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Blog</span>
            </router-link>
          </li>
          <li v-can="'list_author'">
            <router-link :to="{ name: 'admin.author-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Author</span>
            </router-link>
          </li>
          <li v-can="'list_blog_category'">
            <router-link :to="{ name: 'admin.blog-category-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Category</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li v-can="'list_announcement'" class="nav-item">
        <router-link :to="{ name: 'admin.announcement-list' }" class="nav-link">
          <i class="fa fa-bell-slash-o"></i>
          <span>Announcements</span>
        </router-link>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarMarketing"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-bullhorn"></i>
          <span>Marketing</span
          >
        </a>
        <ul
            id="sidebarMarketing"
            class="nav-content collapse"
            data-bs-parent="#sidebarMarketing"
        >
          <li v-can="'list_subscriber'">
            <router-link :to="{ name: 'admin.subscriber-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Newsletters</span>
            </router-link>
          </li>
          <li v-can="'list_subscriber'">
            <router-link :to="{ name: 'admin.subscriber-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Subscriber</span>
            </router-link>
          </li>
          <li v-can="'list_coupon'">
            <router-link :to="{ name: 'admin.coupon-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Coupon</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li v-can="'list_vendor'" class="nav-item">
        <router-link :to="{ name: 'admin.vendor-list' }" class="nav-link">
          <i class="fa fa-users"></i>
          <span>Vendors</span>
        </router-link>
      </li>
      <li v-can="'list_customer'" class="nav-item">
        <router-link :to="{ name: 'admin.customer-list' }" class="nav-link">
          <i class="fa fa-user"></i>
          <span>Customers</span>
        </router-link>
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
          <li v-can="'list_file'">
            <router-link :to="{ name: 'admin.media-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>List</span>
            </router-link>
          </li>
          <li v-can="'list_trashed_file'">
            <router-link :to="{ name: 'admin.media-trashed-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Trashed</span>
            </router-link>
          </li>
        </ul>
      </li>
      <li v-can="'list_page'" class="nav-item">
        <router-link :to="{ name: 'admin.page-list' }" class="nav-link">
          <i class="fa fa-file-pdf-o"></i>
          <span>Page</span>
        </router-link>
      </li>
      <li v-can="'list_faq'" class="nav-item">
        <router-link :to="{ name: 'admin.faq-list' }" class="nav-link">
          <i class="fa fa-question-circle"></i>
          <span>FAQ</span>
        </router-link>
      </li>
      <li v-can="'list_banner'" class="nav-item">
        <router-link :to="{ name: 'admin.banner-list' }" class="nav-link">
          <i class="fa fa-image"></i>
          <span>Banners</span>
        </router-link>
      </li>
      <li class="nav-item">
        <a
            class="nav-link collapsed"
            data-bs-target="#sidebarAuthorization"
            data-bs-toggle="collapse"
            href="#"
        >
          <i class="fa fa-users"></i>
          <span>User & Role</span
          >
        </a>
        <ul
            id="sidebarAuthorization"
            class="nav-content collapse"
            data-bs-parent="#sidebarAuthorization"
        >
          <li v-can="'list_role'">
            <router-link :to="{ name: 'admin.role-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Role List</span>
            </router-link>
          </li>
          <li v-can="'list_user'">
            <router-link :to="{ name: 'admin.user-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>User List</span>
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
          <li v-can="'list_setting'">
            <router-link :to="{ name: 'admin.setting' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Site Setting</span>
            </router-link>
          </li>
          <li v-can="'list_menu'">
            <router-link :to="{ name: 'admin.menu-list' }">
              <i class="fa fa-angle-double-right"></i>
              <span>Menu</span>
            </router-link>
          </li>
          <li v-can="'list_shipping_setting'">
            <router-link :to="{ name: 'admin.shipping-setting' }">
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
