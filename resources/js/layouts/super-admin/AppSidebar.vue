<template>
    <aside id="sidebar" class="sidebar">
        <ul class="sidebar-nav" id="sidebar-nav">
            <li class="nav-item">
                <router-link :to="{ name: 'super-admin.dashboard' }" class="nav-link">
                    <i class="fa fa-dashboard"></i>
                    <span>Dashboard</span>
                </router-link>
            </li>
            <li class="nav-item">
                <router-link :to="{ name: 'super-admin.company-list' }" class="nav-link">
                    <i class="fa fa-building-o"></i>
                    <span>Company</span>
                </router-link>
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
                        <router-link :to="{ name: 'super-admin.setting' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span> Setting</span>
                        </router-link>
                    </li>
                    <li>
                        <router-link :to="{ name: 'super-admin.fiscal-year-list' }">
                            <i class="fa fa-angle-double-right"></i>
                            <span> Fiscal Year</span>
                        </router-link>
                    </li>
                </ul>
            </li>
        </ul>
    </aside>
</template>
<script setup>
</script>
