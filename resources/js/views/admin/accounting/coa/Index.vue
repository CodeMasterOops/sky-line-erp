<template>
    <PageHeader title="Chart Of Accounts" subtitle="Manage your chart of accounts" @refresh="onRefresh" />

    <div class="card table-list-card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs px-3 pt-2 border-bottom-0">
                <li class="nav-item">
                    <button
                        :class="['nav-link', { active: activeTab === 'tree' }]"
                        type="button"
                        @click="activeTab = 'tree'"
                    >
                        <i class="ti ti-sitemap me-1"></i> Tree View
                    </button>
                </li>
                <li class="nav-item">
                    <button
                        :class="['nav-link', { active: activeTab === 'accounts' }]"
                        type="button"
                        @click="activeTab = 'accounts'"
                    >
                        <i class="ti ti-list me-1"></i> Accounts
                    </button>
                </li>
                <li class="nav-item">
                    <button
                        :class="['nav-link', { active: activeTab === 'account-group' }]"
                        type="button"
                        @click="activeTab = 'account-group'"
                    >
                        <i class="ti ti-folder me-1"></i> Account Groups
                    </button>
                </li>
            </ul>
        </div>

        <CoaTree v-if="activeTab === 'tree'" ref="treeRef" />
        <Accounts v-else-if="activeTab === 'accounts'" ref="accountsRef" />
        <AccountGroups v-else ref="accountGroupsRef" />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import AccountGroups from './account-group/Index.vue';
import Accounts from './accounts/Index.vue';
import CoaTree from './tree/Index.vue';

const activeTab = ref('tree');
const treeRef = ref(null);
const accountsRef = ref(null);
const accountGroupsRef = ref(null);

const onRefresh = () => {
    if (activeTab.value === 'tree') treeRef.value?.refresh();
    else if (activeTab.value === 'accounts') accountsRef.value?.refresh();
    else accountGroupsRef.value?.refresh();
};
</script>
