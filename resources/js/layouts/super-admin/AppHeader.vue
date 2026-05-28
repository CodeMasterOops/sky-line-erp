<template>
    <header class="header">
        <div class="header-left active">
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo logo-normal">
                <img src="@/assets/images/logo.svg" alt="">
            </router-link>
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo logo-white">
                <img src="@/assets/images/logo-white.svg" alt="">
            </router-link>
            <router-link :to="{ name: 'super-admin.dashboard' }" class="logo-small">
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

            <!-- Search -->
            <li class="nav-item nav-searchinputs">
                <div class="top-nav-search">
                    <a href="javascript:void(0);" class="responsive-search">
                        <i class="fa fa-search"></i>
                    </a>
                    <form action="#" class="dropdown">
                        <div class="searchinputs input-group dropdown-toggle" id="dropdownMenuClickable"
                            data-bs-toggle="dropdown" data-bs-auto-close="false">
                            <input type="text" placeholder="Search">
                            <div class="search-addon">
                                <span><i class="ti ti-search"></i></span>
                            </div>
                            <span class="input-group-text">
                                <kbd class="d-flex align-items-center"><img src="@/assets/images/icons/command.svg"
                                        alt="img" class="me-1">K</kbd>
                            </span>
                        </div>
                    </form>
                </div>
            </li>
            <!-- /Search -->

            <li class="nav-item nav-item-box">
                <a href="javascript:void(0);" id="btnFullscreen" @click.prevent="initFullScreen">
                    <i class="fa fa-arrows-alt"></i>
                </a>
            </li>

            <HeaderNotificationDropdown />
            
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
                            <p>Super Admin</p>
                        </div>
                    </div>
                    <router-link class="dropdown-item" :to="{ name: 'super-admin.profile' }">
                        <i class="ti ti-user-circle me-2"></i>My Profile
                    </router-link>
                    <router-link class="dropdown-item" :to="{ name: 'super-admin.setting' }">
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
                <router-link class="dropdown-item" :to="{ name: 'super-admin.profile' }">My Profile</router-link>
                <router-link class="dropdown-item" :to="{ name: 'super-admin.setting' }">Settings</router-link>
                <button class="dropdown-item" type="button" @click.prevent="logout">Logout</button>
            </div>
        </div>
    </header>
</template>

<script setup>
import userIcon from '@/assets/images/user-icon.png';
import { onMounted } from "vue";
import { storeToRefs } from "pinia";
import { useSuperAdminAuthStore } from "@/stores/super-admin/auth";
import { useSuperAdminProfileStore } from "@/stores/super-admin/profile";
import showErrors from "@/helpers/showErrors";
import { toast } from "@/helpers/toast";
import { useRouter } from "vue-router";
import HeaderNotificationDropdown from "@/components/shared/HeaderNotificationDropdown.vue";

const profileStore = useSuperAdminProfileStore();
const authStore = useSuperAdminAuthStore();
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
})

const { profile } = storeToRefs(profileStore);

const logout = async () => {
    try {
        const res = await authStore.logout();
        toast(res.status, res.data.message);
        await router.push({ name: 'super-admin.login' });
    } catch (e) {
        showErrors(e);
    }
}

</script>
