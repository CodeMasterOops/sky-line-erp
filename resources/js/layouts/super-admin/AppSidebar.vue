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
                <router-link :to="{ url: '#' }" class="nav-link">
                    <i class="fa fa-user"></i>
                    <span>Customers</span>
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
                </ul>
            </li>
        </ul>
    </aside>
</template>
<script setup>
</script>
