<template>
    <li class="nav-item dropdown nav-item-box notification-dropdown">
        <a
            ref="toggleRef"
            href="javascript:void(0);"
            class="dropdown-toggle nav-link"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Notifications"
        >
            <i class="fa fa-bell"></i>
            <span v-if="count > 0" class="badge bg-warning badge-number">{{ count }}</span>
        </a>
        <div class="dropdown-menu notifications dropdown-menu-end">
            <div class="topnav-dropdown-header">
                <h5 class="notification-title mb-0">
                    <template v-if="count > 0">
                        You have {{ count }} new notification{{ count === 1 ? '' : 's' }}
                    </template>
                    <template v-else>
                        Notifications
                    </template>
                </h5>
                <a
                    v-if="count > 0"
                    href="javascript:void(0)"
                    class="clear-noti"
                    @click.prevent="$emit('mark-all-read')"
                >
                    Mark all as read
                </a>
            </div>
            <div class="noti-content" :class="{ 'noti-content--empty': !count }">
                <div v-if="loading" class="notification-empty-state">
                    <span class="notification-empty-icon notification-empty-icon--loading" aria-hidden="true">
                        <i class="ti ti-loader-2"></i>
                    </span>
                    <p class="notification-empty-title">Loading notifications</p>
                </div>
                <div v-else-if="!count" class="notification-empty-state">
                    <span class="notification-empty-icon" aria-hidden="true">
                        <i class="ti ti-bell-off"></i>
                    </span>
                    <p class="notification-empty-title">You're all caught up</p>
                    <p class="notification-empty-text">No new notifications right now</p>
                </div>
                <ul v-else class="notification-list">
                    <li
                        v-for="notification in notifications"
                        :key="notification.id"
                        class="notification-message"
                    >
                        <a
                            href="javascript:void(0);"
                            class="recent-msg"
                            @click.prevent="$emit('notification-click', notification)"
                        >
                            <div class="media d-flex">
                                <span class="avatar flex-shrink-0">
                                    <img alt="" src="@/assets/images/profiles/avatar-10.jpg">
                                </span>
                                <div class="flex-grow-1">
                                    <p class="noti-details">
                                        <span v-if="notification.notification_type" class="noti-title">
                                            {{ notification.notification_type }}
                                        </span>
                                        {{ notification.data?.notification_title }}
                                    </p>
                                    <p v-if="notification.time" class="noti-time">{{ notification.time }}</p>
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="topnav-dropdown-footer notification-dropdown-footer">
                <div
                    class="notification-dropdown-actions"
                    :class="{ 'notification-dropdown-actions--single': !viewAllRoute }"
                >
                    <button type="button" class="btn btn-secondary btn-md" @click.prevent="closeDropdown">
                        Close
                    </button>
                    <router-link
                        v-if="viewAllRoute"
                        :to="{ name: viewAllRoute }"
                        class="btn btn-primary btn-md"
                        @click="closeDropdown"
                    >
                        View all
                    </router-link>
                </div>
            </div>
        </div>
    </li>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { initBootstrapUi } from '@/helpers/initBootstrap';

const props = defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    viewAllRoute: {
        type: String,
        default: null,
    },
});

defineEmits(['notification-click', 'mark-all-read']);

const toggleRef = ref(null);

const count = computed(() => props.notifications.length);

const scheduleBootstrapInit = () => {
    nextTick(() => initBootstrapUi());
};

onMounted(scheduleBootstrapInit);

watch(
    () => [props.notifications.length, props.loading],
    scheduleBootstrapInit,
);

const closeDropdown = () => {
    const toggle = toggleRef.value;

    if (!toggle) {
        return;
    }

    window.bootstrap?.Dropdown?.getInstance(toggle)?.hide();
};
</script>
