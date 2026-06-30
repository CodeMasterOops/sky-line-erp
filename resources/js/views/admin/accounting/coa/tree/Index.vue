<template>
    <div class="card-body">
        <VLoader v-if="coaTree.loading" />
        <a-tree
            v-else
            class="coa-tree"
            :tree-data="treeData"
            :titleRender="titleRender"
            default-expand-all
        />
    </div>
</template>

<script setup>
import { h, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useAccountStore } from '@/stores/admin/accounting/account.js';

const accountStore = useAccountStore();
const { coaTree } = storeToRefs(accountStore);

onMounted(() => {
    refresh();
});

const refresh = () => {
    accountStore.getCoaTree();
};

const TYPE_BADGE_CLASSES = {
    asset: 'bg-primary',
    liability: 'bg-danger',
    equity: 'bg-purple',
    income: 'bg-success',
    expense: 'bg-warning text-dark',
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
                normalBalance: account.normal_balance,
                isLeaf: true,
            });
        });
    }

    return {
        title: `${group.name} (${group.code})`,
        key: `group-${group.id}`,
        accountType: group.account_type,
        children,
        isGroup: true,
    };
};

const titleRender = (node) => {
    if (node.isGroup && node.accountType) {
        const badgeClass = TYPE_BADGE_CLASSES[node.accountType] || 'bg-secondary';
        return h('span', {}, [
            node.title,
            h('span', {
                class: `badge ${badgeClass} ms-2 fw-normal`,
                style: 'font-size:0.7em;vertical-align:middle;',
            }, node.accountType.charAt(0).toUpperCase() + node.accountType.slice(1)),
        ]);
    }

    if (node.isLeaf && node.normalBalance) {
        const label = node.normalBalance === 'debit' ? 'Dr' : 'Cr';
        return h('span', {}, [
            node.title,
            h('span', { class: 'text-muted ms-1', style: 'font-size:0.85em;' }, `(${label})`),
        ]);
    }

    return node.title;
};

const treeData = computed(() => {
    if (!Array.isArray(coaTree.value.data)) {
        return [];
    }
    return coaTree.value.data.map((group) => buildNode(group));
});

defineExpose({ refresh });
</script>
