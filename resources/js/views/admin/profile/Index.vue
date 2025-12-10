<template>
  <div class="row">
    <div class="col-12">
      <div class="page-title-box">
        <div class="page-title">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <router-link :to="{name:'admin.dashboard'}">
                <i class="fa fa-home"></i>  Home
              </router-link>
            </li>
            <li class="breadcrumb-item active">Profile</li>
          </ol>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xl-4 col-lg-5">
      <div class="card text-center">
        <div class="card-body">
          <img :src="profile.data.profile_photo_url || userIcon" class="rounded-circle"
              height="120"
               alt="profile-image">

          <h4 class="mb-0 mt-2">{{ profile.data.name }}</h4>

          <div class="text-start mt-3">
            <p class="text-muted mb-2 font-13">
              <strong>Email :</strong> {{ profile.data.email }}
            </p>
            <p class="text-muted mb-2 font-13">
              <strong>Phone :</strong> {{ profile.data.phone }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-8 col-lg-7">
      <div class="card">
        <div class="card-body">
          <ul class="nav nav-pills bg-nav-pills nav-justified mb-3">
            <li class="nav-item ">
              <a href="#edit-profile" data-bs-toggle="tab" aria-expanded="true"
                 class="nav-link active rounded-0">
                Edit Profile
              </a>
            </li>
            <li class="nav-item">
              <a href="#change-password" data-bs-toggle="tab" aria-expanded="false"
                 class="nav-link rounded-0">
                Change Password
              </a>
            </li>
          </ul>
          <div class="tab-content">
            <div class="tab-pane show active" id="edit-profile">
              <EditProfile/>
            </div>

            <div class="tab-pane" id="change-password">
              <EditPassword></EditPassword>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import EditProfile from "./EditProfile.vue"
import EditPassword from "./EditPassword.vue"
import userIcon from '@/assets/images/user-icon.png';
import {onMounted} from "vue";
import {useProfileStore} from "@/stores/admin/profile";
import {storeToRefs} from "pinia";


const profileStore = useProfileStore();


const {profile} = storeToRefs(profileStore);

onMounted(() => {
  profileStore.getProfile();
})
</script>
