<template>
    <aside id="sidebar" class="sidebar">
        <ul class="sidebar-nav" id="sidebar-nav">
            <li class="nav-item">
                <router-link :to="{ name: 'admin.dashboard' }" class="nav-link">
                    <i class="fa fa-dashboard"></i>
                    <span>Dashboard</span>
                </router-link>
            </li>
            <li v-can="'list_party'" class="nav-item">
                <router-link :to="{ name: 'admin.party-list' }" class="nav-link">
                    <i class="fa fa-user"></i>
                    <span>Parties</span>
                </router-link>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link collapsed"
                    data-bs-target="#sidebarSales"
                    data-bs-toggle="collapse"
                    href="#"
                >
                    <i class="fa fa-tags"></i>
                    <span>Sales</span
                    >
                </a>
                <ul
                    id="sidebarSales"
                    class="nav-content collapse"
                    data-bs-parent="#sidebarSales"
                >
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Quotations</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Sales</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Invoice</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Customer Payment</span>
                        </router-link>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link collapsed"
                    data-bs-target="#sidebarPurchase"
                    data-bs-toggle="collapse"
                    href="#"
                >
                    <i class="fa fa-shopping-cart"></i>
                    <span>Purchase </span
                    >
                </a>
                <ul
                    id="sidebarPurchase"
                    class="nav-content collapse"
                    data-bs-parent="#sidebarPurchase"
                >
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Purchase Order</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Sales</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Purchase Bills</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Supplier Payment</span>
                        </router-link>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link collapsed"
                    data-bs-target="#sidebarAccounting"
                    data-bs-toggle="collapse"
                    href="#"
                >
                    <i class="fa fa-file-text-o"></i>
                    <span>Accounting</span
                    >
                </a>
                <ul
                    id="sidebarAccounting"
                    class="nav-content collapse"
                    data-bs-parent="#sidebarAccounting"
                >
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Chart Of Account</span>
                        </router-link>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link collapsed"
                    data-bs-target="#sidebarInventory"
                    data-bs-toggle="collapse"
                    href="#"
                >
                    <i class="fa fa-stack-exchange"></i>
                    <span>Inventory</span
                    >
                </a>
                <ul
                    id="sidebarInventory"
                    class="nav-content collapse"
                    data-bs-parent="#sidebarInventory"
                >
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Products</span>
                        </router-link>
                    </li>
                    <li v-can="'list_unit'">
                        <router-link :to="{ name: 'admin.unit-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Units</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product_category'">
                        <router-link :to="{ name: 'admin.product-category-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Product Category</span>
                        </router-link>
                    </li>
                    <li v-can="'list_brand'">
                        <router-link :to="{ name: 'admin.brand-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Brands</span>
                        </router-link>
                    </li>
                    <li v-can="'list_warehouse'">
                        <router-link :to="{ name: 'admin.warehouse-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Warehouse</span>
                        </router-link>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a
                    class="nav-link collapsed"
                    data-bs-target="#sidebarReport"
                    data-bs-toggle="collapse"
                    href="#"
                >
                    <i class="fa fa-bar-chart-o"></i>
                    <span>Reports</span
                    >
                </a>
                <ul
                    id="sidebarReport"
                    class="nav-content collapse"
                    data-bs-parent="#sidebarReport"
                >
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Sales Report</span>
                        </router-link>
                    </li>
                    <li v-can="'list_product'">
                        <router-link :to="{ name: 'admin.product-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span>Purchase Report</span>
                        </router-link>
                    </li>
                </ul>
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
                            <span> Setting</span>
                        </router-link>
                    </li>
                    <li v-can="'list_tax'">
                        <router-link :to="{ name: 'admin.tax-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span> Tax</span>
                        </router-link>
                    </li>
                </ul>
            </li>
        </ul>
    </aside>
</template>
<script setup>
</script>
