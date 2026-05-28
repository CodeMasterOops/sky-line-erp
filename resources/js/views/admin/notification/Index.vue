<template>
    <PageHeader title="Notifications" subtitle="View all notifications" @refresh="fetchNotifications" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <a-table
                        class="table datanew table-hover table-center mb-0"
                        :columns="columns"
                        :data-source="notifications.data"
                        :pagination="false"
                        :loading="notifications.loading"
                    >
                        <template #bodyCell="{ column, record, index }">
                            <template v-if="column.key === 'sn'">
                                {{ (notifications.meta.from || ((filter.page - 1) * filter.limit + 1)) + index }}
                            </template>
                            <template v-else-if="column.key === 'type'">
                                {{ record.notification_type }} : {{ record.data?.notification_title }}
                            </template>
                            <template v-else-if="column.key === 'read_at'">
                                {{ record.read_at ?? 'Not read' }}
                            </template>
                        </template>
                    </a-table>
                    <VPagination v-model:page="filter.page" v-model:limit="filter.limit" :meta="notifications.meta" />
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import {onMounted, reactive, watch} from 'vue';
import {storeToRefs} from 'pinia';
import VPagination from '@/components/base/VPagination.vue';
import {useAdminNotificationStore} from '@/stores/admin/notification';

const notificationStore = useAdminNotificationStore();
const {allNotifications: notifications} = storeToRefs(notificationStore);

const filter = reactive({
    page: 1,
    limit: 25,
});

const columns = [
    {title: 'SN', key: 'sn', width: 60},
    {title: 'Type', key: 'type'},
    {title: 'Time', dataIndex: 'time', key: 'time'},
    {title: 'Read At', key: 'read_at'},
];

const fetchNotifications = () => {
    notificationStore.getAllNotifications({filter});
};

onMounted(() => {
    fetchNotifications();
});

watch(() => [filter.page, filter.limit], () => {
    fetchNotifications();
});
</script>
