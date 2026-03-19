<template>
    <PageHeader title="COA Tree" subtitle="Account groups and accounts tree" @refresh="refreshTree" />

    <section class="section">
        <div class="card">
            <div class="card-body">
                <VLoader v-if="coaTree.loading" />
                <a-tree
                    v-else
                    class="coa-tree"
                    :tree-data="treeData"
                    default-expand-all
                />
            </div>
        </div>
    </section>
</template>

<script setup>
import {computed, onMounted} from 'vue';
import {storeToRefs} from 'pinia';
import {useAccountStore} from '@/stores/admin/accounting/account.js';

const accountStore = useAccountStore();
const {coaTree} = storeToRefs(accountStore);

onMounted(() => {
    refreshTree();
});

const refreshTree = () => {
    accountStore.getCoaTree();
};

const buildNode = (group) => {
    const children = [];

    if (Array.isArray(group.children) && group.children.length) {
        group.children.forEach((child) => {
            children.push(buildNode(child));
        });
    }

    if (Array.isArray(group.accounts) && group.accounts.length) {
        group.accounts.forEach((account) => {
            children.push({
                title: `${account.name} (${account.code})`,
                key: `account-${account.id}`,
                isLeaf: true,
            });
        });
    }

    return {
        title: `${group.name} (${group.code})`,
        key: `group-${group.id}`,
        children,
    };
};

const treeData = computed(() => {
    if (!Array.isArray(coaTree.value.data)) {
        return [];
    }
    return coaTree.value.data.map((group) => buildNode(group));
});
</script>
