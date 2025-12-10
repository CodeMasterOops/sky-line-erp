<template>
    <header id="header" class="header fixed-top d-flex align-items-center">
        <div class="d-flex align-items-center justify-content-between logo-area">
            <router-link :to="{ name: 'super-admin.dashboard' }" class="square-logo d-flex align-items-center">
                <img :src="appLogo" alt="logo"/>
            </router-link>
            <i class="fa fa-bars toggle-sidebar-btn pe-2" @click="sidebarToggle()"></i>
        </div>

        <nav class="header-nav ms-auto">
            <ul class="d-flex align-items-center">
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
                                :to="{ name: 'super-admin.profile' }"
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
import appLogo from '@/assets/images/app-logo.png';
import {onMounted} from "vue";
import {storeToRefs} from "pinia";
import showErrors from "@/helpers/showErrors";
import {toast} from "@/helpers/toast";
import {useRouter} from "vue-router";
import {useSuperAdminProfileStore} from "@/stores/super-admin/profile.js";
import {useSuperAdminAuthStore} from "@/stores/super-admin/auth.js";

const profileStore = useSuperAdminProfileStore();
const authStore = useSuperAdminAuthStore();
const router = useRouter();

const sidebarToggle = () => {
    document.body.classList.toggle("toggle-sidebar");
}

onMounted(() => {
    profileStore.getProfile();
})

const {profile} = storeToRefs(profileStore);

const logout = async () => {
    try {
        const res = await authStore.logout();
        toast(res.status, res.data.message);
        await router.push({name: 'super-admin.login'});
    } catch (e) {
        showErrors(e);
    }
}

</script>
