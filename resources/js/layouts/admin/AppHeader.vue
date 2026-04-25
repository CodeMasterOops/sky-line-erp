<template>
    <header class="header">
        <div class="header-left active">
            <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-normal">
                <img src="@/assets/images/logo.svg" alt="">
            </router-link>
            <router-link :to="{ name: 'admin.dashboard' }" class="logo logo-white">
                <img src="@/assets/images/logo-white.svg" alt="">
            </router-link>
            <router-link :to="{ name: 'admin.dashboard' }" class="logo-small">
                <img src="@/assets/images/logo-small.png" alt="">
            </router-link>
        </div>

        <a id="mobile_btn" class="mobile_btn" href="#" @click.prevent="toggleMobileBtn">
            <span class="bar-icon">
                <span></span>
                <span></span>
                <span></span>
            </span>
        </a>

        <ul class="nav user-menu">

            <!-- Fiscal year: static label until wired to API — see constants / TODO below -->
            <li class="nav-item fiscal-year-nav">
                <router-link
                    class="fiscal-year-link"
                    :to="{ name: 'admin.general-settings' }"
                    :title="STATIC_FISCAL_YEAR_TOOLTIP"
                >
                    <span class="fiscal-year-icon-wrap" aria-hidden="true">
                        <i class="fa fa-calendar"></i>
                    </span>
                    <span class="fiscal-year-label d-none d-md-flex flex-column">
                        <span class="fiscal-year-title">{{ STATIC_FISCAL_YEAR_TITLE }}</span>
                        <span class="fiscal-year-sub">{{ STATIC_FISCAL_YEAR_SUB }}</span>
                    </span>
                </router-link>
            </li>
            <li class="nav-item dropdown link-nav">
                <a href="javascript:void(0);" class="btn btn-primary btn-md d-inline-flex align-items-center"
                   data-bs-toggle="dropdown">
                    <i class="fa fa-plus-circle me-1"></i>Add New
                </a>
                <div class="dropdown-menu dropdown-xl dropdown-menu-center">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.product-category-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-brand-codepen"></i>
                                </span>
                                <p>Category</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.product-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-square-plus"></i>
                                </span>
                                <p>Product</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.purchase-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-shopping-bag"></i>
                                </span>
                                <p>Purchase</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.sales-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-shopping-cart"></i>
                                </span>
                                <p>Sale</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.expense-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-file-text"></i>
                                </span>
                                <p>Expense</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.quotation-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-device-floppy"></i>
                                </span>
                                <p>Quotation</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link to="/sales-returns" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-copy"></i>
                                </span>
                                <p>Return</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.user-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-user"></i>
                                </span>
                                <p>User</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.party-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-users"></i>
                                </span>
                                <p>Customer</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.party-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-shield"></i>
                                </span>
                                <p>Biller</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link :to="{name:'admin.party-list'}" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-user-check"></i>
                                </span>
                                <p>Supplier</p>
                            </router-link>
                        </div>
                        <div class="col-md-2">
                            <router-link to="/admin/stock-transfer" class="link-item">
                                <span class="link-icon">
                                    <i class="ti ti-truck"></i>
                                </span>
                                <p>Transfer</p>
                            </router-link>
                        </div>
                    </div>
                </div>
            </li>

            <li class="nav-item pos-nav">
                <router-link :to="{ name: 'admin.pos' }" class="btn btn-dark btn-md d-inline-flex align-items-center">
                    <i class="fa fa-laptop me-1"></i>POS
                </router-link>
            </li>

            <!-- Flag -->
            <li class="nav-item dropdown has-arrow flag-nav nav-item-box">
                <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="javascript:void(0);" role="button">
                    <img src="@/assets/images/flags/us-flag.svg" alt="Language" class="img-fluid">
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="javascript:void(0);" class="dropdown-item">
                        <img src="@/assets/images/flags/english.svg" alt="" height="16">English
                    </a>
                    <a href="javascript:void(0);" class="dropdown-item">
                        <img src="@/assets/images/flags/arabic.svg" alt="" height="16">Arabic
                    </a>
                </div>
            </li>
            <!-- /Flag -->

            <li class="nav-item nav-item-box">
                <a href="javascript:void(0);" id="btnFullscreen" @click.prevent="initFullScreen">
                    <i class="fa fa-arrows-alt"></i>
                </a>
            </li>
            <!-- Notifications -->
            <li class="nav-item dropdown nav-item-box">
                <template v-if="notifications.data.length">
                    <a href="javascript:void(0);" class="dropdown-toggle nav-link" data-bs-toggle="dropdown">
                        <i class="fa fa-bell"></i>
                        <span class="badge bg-warning badge-number">
                            {{ notifications.data.length }}
                        </span>
                    </a>
                    <div class="dropdown-menu notifications">
                        <div class="topnav-dropdown-header">
                            <h5 class="notification-title">
                                You have {{ notifications.data.length }} new notifications
                            </h5>
                            <a href="javascript:void(0)" class="clear-noti" @click.prevent="markAllAsRead">
                                Mark all as read
                            </a>
                        </div>
                        <div class="noti-content">
                            <ul class="notification-list">
                                <template v-for="notification in notifications.data" :key="notification.id">
                                    <li class="notification-message">
                                        <a href="javascript:void(0);" class="recent-msg"
                                           @click.prevent="notificationClick(notification)">
                                            <div class="media d-flex">
                                                <span class="avatar flex-shrink-0">
                                                    <img alt="" src="@/assets/images/profiles/avatar-10.jpg">
                                                </span>
                                                <div class="flex-grow-1">
                                                    <p class="noti-details">
                                                        <span class="noti-title">
                                                            {{ notification.notification_type }}
                                                        </span>
                                                        {{ notification.data.notification_title }}
                                                    </p>
                                                    <p class="noti-time">{{ notification.time }}</p>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div class="topnav-dropdown-footer d-flex align-items-center gap-3">
                            <a href="javascript:void(0);" class="btn btn-secondary btn-md w-100">Close</a>
                            <router-link :to="{ name: 'admin.notification-list' }"
                                         class="btn btn-primary btn-md w-100">
                                View all
                            </router-link>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <router-link :to="{ name: 'admin.notification-list' }" class="dropdown-toggle nav-link">
                        <i class="fa fa-bell"></i>
                    </router-link>
                </template>
            </li>
            <!-- /Notifications -->

            <li class="nav-item nav-item-box">
                <router-link :to="{ name: 'admin.general-settings' }"><i class="fa fa-cog"></i></router-link>
            </li>
            <li class="nav-item dropdown has-arrow main-drop profile-nav">
                <a href="javascript:void(0);" class="nav-link userset" data-bs-toggle="dropdown">
                    <span class="user-info p-0">
                        <span class="user-letter">
                            <img :src="profile.data.profile_photo_url || userIcon" alt="" class="img-fluid">
                        </span>
                    </span>
                </a>
                <div class="dropdown-menu menu-drop-user">
                    <div class="profileset d-flex align-items-center">
                        <span class="user-img me-2">
                            <img :src="profile.data.profile_photo_url || userIcon" alt="">
                        </span>
                        <div>
                            <h6 class="fw-medium">{{ profile.data.name }}</h6>
                            <p>Admin</p>
                        </div>
                    </div>
                    <router-link class="dropdown-item" :to="{ name: 'admin.profile' }">
                        <i class="ti ti-user-circle me-2"></i>My Profile
                    </router-link>
                    <router-link class="dropdown-item" :to="{ name: 'admin.sales-report' }">
                        <i class="ti ti-file-text me-2"></i>Reports
                    </router-link>
                    <router-link class="dropdown-item" to="/settings/general-settings">
                        <i class="ti ti-settings-2 me-2"></i>Settings
                    </router-link>
                    <hr class="my-2">
                    <button class="dropdown-item logout pb-0" type="button" @click.prevent="logout">
                        <i class="ti ti-logout me-2"></i>Logout
                    </button>
                </div>
            </li>
        </ul>

        <div class="dropdown mobile-user-menu">
            <a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown"
               aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
            <div class="dropdown-menu dropdown-menu-right">
                <router-link class="dropdown-item" :to="{ name: 'admin.profile' }">My Profile</router-link>
                <router-link class="dropdown-item" to="/settings/general-settings">Settings</router-link>
                <button class="dropdown-item" type="button" @click.prevent="logout">Logout</button>
            </div>
        </div>
    </header>
</template>

<script setup>
import userIcon from '@/assets/images/user-icon.png';
import {onMounted} from "vue";
import {useProfileStore} from "@/stores/admin/profile";
import {storeToRefs} from "pinia";
import {useAdminAuthStore} from "@/stores/admin/auth";
import showErrors from "@/helpers/showErrors";
import {toast} from "@/helpers/toast";
import {useRouter} from "vue-router";
import {useAdminNotificationStore} from "@/stores/admin/notification";

const notificationStore = useAdminNotificationStore();
const profileStore = useProfileStore();
const authStore = useAdminAuthStore();
const router = useRouter();

const toggleMobileBtn = () => {
    document.body.classList.toggle('slide-nav');
    document.body.classList.toggle('menu-opened');
};

const initFullScreen = () => {
    document.body.classList.toggle("fullscreen-enable");
    if (
        !document.fullscreenElement &&
        /* alternative standard method */
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement
    ) {
        // current working methods
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
            document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
            document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
    } else {
        if (document.cancelFullScreen) {
            document.cancelFullScreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitCancelFullScreen) {
            document.webkitCancelFullScreen();
        }
    }
};

onMounted(() => {
    profileStore.getProfile();
    notificationStore.getUnreadNotifications();
})

const {profile} = storeToRefs(profileStore);
const {unreadNotifications: notifications} = storeToRefs(notificationStore);

// TODO(backend): Load current fiscal year from API (`GET admin-setting/fiscal-year`, `is_current`, etc.).
// Replace STATIC_FISCAL_YEAR_TITLE / STATIC_FISCAL_YEAR_SUB (and tooltip) with computed values + loading state.
const STATIC_FISCAL_YEAR_TITLE = '2025-26';
const STATIC_FISCAL_YEAR_SUB = 'Apr 2025 – Mar 2026';
const STATIC_FISCAL_YEAR_TOOLTIP = `Fiscal year · ${STATIC_FISCAL_YEAR_TITLE} · ${STATIC_FISCAL_YEAR_SUB} (static — connect to backend)`;

const logout = async () => {
    try {
        const res = await authStore.logout();
        toast(res.status, res.data.message);
        await router.push({name: 'admin.login'});
    } catch (e) {
        showErrors(e);
    }
}

const notificationClick = async (notification) => {
    try {
        await notificationStore.markAsRead(notification.id);
        // Refresh notifications after marking as read
        await notificationStore.getUnreadNotifications();
    } catch (e) {
        showErrors(e);
    }
}

const markAllAsRead = async () => {
    try {
        await notificationStore.markAsRead();
        await notificationStore.getUnreadNotifications();
    } catch (e) {
        showErrors(e);
    }
}

</script>
