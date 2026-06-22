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

            <li class="nav-item fiscal-year-nav">
                <router-link
                    class="fiscal-year-link"
                    :to="{ name: 'admin.branch-select', query: { redirect: currentPath } }"
                    :title="contextTooltip"
                >
                    <span class="fiscal-year-icon-wrap" aria-hidden="true">
                        <i class="fa fa-building"></i>
                    </span>
                    <span class="fiscal-year-label d-none d-md-flex flex-column">
                        <span class="fiscal-year-title">{{ selectedBranchLabel }}</span>
                        <span class="fiscal-year-sub">
                            <template v-if="currentFiscalYear.data.year_name">
                                {{ currentFiscalYear.data.year_name }}
                                <span v-if="currentFiscalYear.data.start_date && currentFiscalYear.data.end_date">
                                    · {{ adToBsDate(currentFiscalYear.data.start_date) }} -
                                    {{ adToBsDate(currentFiscalYear.data.end_date) }}
                                </span>
                            </template>
                            <template v-else>
                                Change branch context
                            </template>
                        </span>
                    </span>
                </router-link>
            </li>

            <li class="nav-item date-mode-nav">
                <div class="btn-group" role="group" aria-label="Date format">
                    <button
                        type="button"
                        class="btn"
                        :class="dateMode === 'en' ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="setDateMode('en')"
                    >AD</button>
                    <button
                        type="button"
                        class="btn"
                        :class="dateMode === 'ne' ? 'btn-primary' : 'btn-outline-secondary'"
                        @click="setDateMode('ne')"
                    >BS</button>
                </div>
            </li>

            <li class="nav-item d-flex align-items-center">
                <div class="vr" style="height: 24px;"></div>
            </li>

            <li class="nav-item nav-item-box">
                <a href="javascript:void(0);" id="btnFullscreen" @click.prevent="initFullScreen">
                    <i class="fa fa-arrows-alt"></i>
                </a>
            </li>

            <HeaderNotificationDropdown
                :notifications="notifications.data"
                :loading="notifications.loading"
                view-all-route="admin.notification-list"
                @notification-click="notificationClick"
                @mark-all-read="markAllAsRead"
            />

            <li class="nav-item nav-item-box">
                <router-link :to="{ name: 'admin.setting' }"><i class="fa fa-cog"></i></router-link>
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
                    <router-link class="dropdown-item" :to="{ name: 'admin.reports-hub' }">
                        <i class="ti ti-file-text me-2"></i>Reports
                    </router-link>
                    <router-link class="dropdown-item" :to="{ name: 'admin.setting' }">
                        <i class="ti ti-settings-2 me-2"></i>Settings
                    </router-link>
                    <router-link class="dropdown-item" :to="{ name: 'admin.data-transfer-list' }">
                        <i class="ti ti-file-export me-2"></i>Data transfer
                    </router-link>
                    <router-link class="dropdown-item" :to="{ name: 'admin.billing-pricing' }">
                        <i class="ti ti-crown me-2"></i>View plans
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
                <router-link class="dropdown-item" :to="{ name: 'admin.setting' }">Settings</router-link>
                <router-link class="dropdown-item" :to="{ name: 'admin.data-transfer-list' }">Data transfer</router-link>
                <router-link class="dropdown-item" :to="{ name: 'admin.billing-pricing' }">View plans</router-link>
                <button class="dropdown-item" type="button" @click.prevent="logout">Logout</button>
            </div>
        </div>
    </header>
</template>

<script setup>
import userIcon from '@/assets/images/user-icon.png';
import {computed, onMounted} from "vue";
import {useProfileStore} from "@/stores/admin/profile";
import {storeToRefs} from "pinia";
import {useAdminAuthStore} from "@/stores/admin/auth";
import showErrors from "@/helpers/showErrors";
import {toast} from "@/helpers/toast";
import {useRouter} from "vue-router";
import {useAdminNotificationStore} from "@/stores/admin/notification";
import {useAdminSettingStore} from "@/stores/admin/settings/admin-setting.js";
import {adToBsDate} from "@/helpers/helper.js";
import {useBranchStore} from "@/stores/admin/settings/branch.js";
import {useDatePreferenceStore} from "@/stores/admin/datePreference.js";
import {useRoute} from "vue-router";
import HeaderNotificationDropdown from "@/components/shared/HeaderNotificationDropdown.vue";
import {watch} from "vue";

const notificationStore = useAdminNotificationStore();
const profileStore = useProfileStore();
const authStore = useAdminAuthStore();
const adminSettingStore = useAdminSettingStore();
const branchStore = useBranchStore();
const datePreferenceStore = useDatePreferenceStore();
const router = useRouter();
const route = useRoute();

const {mode: dateMode} = storeToRefs(datePreferenceStore);
const setDateMode = (mode) => datePreferenceStore.setMode(mode);

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

onMounted(async () => {
    profileStore.getProfile();
    notificationStore.getUnreadNotifications();
    adminSettingStore.getCurrentFiscalYear();
    await branchStore.ensureSelectedBranchLoaded();
    if (branchStore.selectedBranchId) {
        authStore.refreshPermissions();
    }
});

watch(() => branchStore.selectedBranchId, (newId) => {
    if (newId) {
        authStore.refreshPermissions();
    }
});

watch(route, () => {
    document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(el => {
        window.bootstrap?.Dropdown?.getInstance(el)?.hide();
    });
});

const {profile} = storeToRefs(profileStore);
const {unreadNotifications: notifications} = storeToRefs(notificationStore);
const {currentFiscalYear} = storeToRefs(adminSettingStore);
const {selectedBranch} = storeToRefs(branchStore);

const currentPath = computed(() => route.fullPath);

const selectedBranchLabel = computed(() => {
    return selectedBranch.value?.name || 'Select Branch';
});

const contextTooltip = computed(() => {
    const branchName = selectedBranch.value?.name || 'No branch selected';
    const fiscalYearName = currentFiscalYear.value.data.year_name || 'No fiscal year';
    return `${branchName} · ${fiscalYearName}`;
});

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
