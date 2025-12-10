<template>
    <header id="header" class="header fixed-top d-flex align-items-center">
        <div class="d-flex align-items-center justify-content-between logo-area">
            <router-link :to="{ name: 'vendor.dashboard' }" class="square-logo d-flex align-items-center">
                <img :src="appLogo" alt="logo"/>
                <span class="app-name">INDIBE</span>
            </router-link>
            <i class="fa fa-bars toggle-sidebar-btn pe-2" @click="sidebarToggle()"></i>
        </div>

        <nav class="header-nav ms-auto">
            <ul class="d-flex align-items-center">
                <li class="nav-item dropdown">
                    <template v-if="notifications.data.length">
                        <a href="javascript:void(0)" class="nav-link nav-icon"
                           data-bs-toggle="dropdown" title="Notifications">
                            <i class="fa fa-bell-o"></i>
                            <span class="badge bg-warning badge-number">
                                {{ notifications.data.length }}
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow notifications">
                            <li class="dropdown-header">
                                You have {{ notifications.data.length }} new notifications
                                <a style="cursor: pointer;" @click.prevent="viewAllNotifications">
                                    <span class="badge rounded-pill bg-primary p-2 ms-2">View all</span>
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <template v-for="notification in notifications.data" :key="notification.id">
                                <li @click.prevent="notificationClick(notification)" style="cursor: pointer;" class="notification-item">
                                    <i class="fa fa-exclamation-circle text-info"></i>
                                    <div>
                                        <h6 class="mb-1">{{ notification.notification_type }}</h6>
                                        <p class="fw-bolder">{{ notification.data.notification_title }}</p>
                                        <p>{{ notification.time }}</p>
                                    </div>
                                </li>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            </template>
                        </ul>
                    </template>
                    <template v-else>
                        <router-link :to="{name:'vendor.notification-list'}" class="nav-link nav-icon" title="Notifications">
                            <i class="fa fa-bell-o"></i>
                        </router-link>
                    </template>
                </li>

                <li class="nav-item dropdown pe-3">
                    <a
                        class="nav-link nav-profile d-flex align-items-center pe-0"
                        href="#"
                        data-bs-toggle="dropdown"
                    >
                        <img :src="profile.data.profile_photo_url || userIcon" alt="Profile" class="rounded-circle"/>
                        <span class="d-none d-md-block dropdown-toggle ps-2">
                            {{ profile.data.name }}
                        </span>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                        <li class="dropdown-header">
                            <h6>{{ profile.data.name }}</h6>
                        </li>
                        <li>
                            <hr class="dropdown-divider"/>
                        </li>

                        <li>
                            <router-link
                                :to="{ name: 'vendor.profile' }"
                                class="dropdown-item d-flex align-items-center"
                            >
                                <i class="fa fa-user"></i>
                                <span>My Profile</span>
                            </router-link>
                        </li>
                        <li>
                            <hr class="dropdown-divider"/>
                        </li>

                        <li>
                            <button @click.prevent="logout"
                                    type="button"
                                    class="dropdown-item d-flex align-items-center">
                                <i class="fa fa-sign-out"></i>
                                <span>Sign Out</span>
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>
    </header>
</template>

<script setup>
import userIcon from '@/assets/images/user-icon.png';
import appLogo from '@/assets/images/logo.png';
import {onMounted} from "vue";
import {storeToRefs} from "pinia";
import showErrors from "@/helpers/showErrors";
import {toast} from "@/helpers/toast";
import {useRouter} from "vue-router";
import {useVendorNotificationStore} from "@/stores/vendor/notification.js";
import {useVendorProfileStore} from "@/stores/vendor/profile.js";
import {useVendorAuthStore} from "@/stores/vendor/auth.js";

const notificationStore = useVendorNotificationStore();
const profileStore = useVendorProfileStore();
const authStore = useVendorAuthStore();
const router = useRouter();

const sidebarToggle = () => {
    document.body.classList.toggle("toggle-sidebar");
}

onMounted(() => {
    profileStore.getProfile();
    notificationStore.getUnreadNotifications();
})

const {profile} = storeToRefs(profileStore);
const {unreadNotifications: notifications} = storeToRefs(notificationStore);

const logout = async () => {
    try {
        const res = await authStore.logout();
        toast(res.status, res.data.message);
        await router.push({name: 'vendor.login'});
    } catch (e) {
        showErrors(e);
    }
}

const notificationClick = async (notification) => {
    try {
        console.log('clicked'+notification)
    } catch (e) {
        showErrors(e);
    }
}

const viewAllNotifications = () => {
    try {
        notificationStore.markAsRead();
        router.push({name: 'vendor.notification-list'})
    } catch (e) {
        showErrors(e);
    }
}

</script>
