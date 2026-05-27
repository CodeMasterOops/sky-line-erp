<template>
    <div>
        <PageHeader
            title="Profile"
            subtitle="Manage your name, email, and phone"
            @refresh="refreshProfile"
        />
    </div>

    <div class="row">
        <div class="col-xl-12">
            <div class="settings-wrapper d-flex">
                <super-admin-settings-sidebar></super-admin-settings-sidebar>
                <div class="card flex-fill mb-0">
                    <div class="card-header">
                        <h4 class="fs-18 fw-bold">My profile</h4>
                    </div>
                    <div class="card-body">
                        <EditProfile ref="editProfileRef" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import EditProfile from "./EditProfile.vue";

const editProfileRef = ref(null);

function refreshProfile() {
    editProfileRef.value?.loadFromServer?.();
}
</script>
