<template>
    <div class="action-table-data">
        <div class="edit-delete-action">
            <template v-for="action in visibleActions" :key="action.key">
                <a
                    href="javascript:void(0);"
                    class="p-2"
                    :class="action.class"
                    :title="action.title"
                    @click="action.handler(record)"
                >
                    <i :class="`ti ${action.icon}`"></i>
                </a>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

/**
 * Reusable action buttons for table rows.
 *
 * Each action in the `actions` array supports:
 *   key       {string}    Unique key for v-for (required)
 *   icon      {string}    Tabler icon class, e.g. 'ti-edit' (required)
 *   title     {string}    Tooltip text (optional)
 *   class     {string}    Extra CSS classes, e.g. 'edit-icon' (optional)
 *   condition {Function}  (record) => boolean — hide when false (optional)
 *   handler   {Function}  (record) => void — called on click (required)
 *
 * Usage:
 *   <VTableActions :actions="rowActions" :record="record" />
 */
const props = defineProps({
    actions: {
        type: Array,
        required: true,
    },
    record: {
        type: Object,
        required: true,
    },
});

const visibleActions = computed(() =>
    props.actions.filter(a => !a.condition || a.condition(props.record))
);
</script>
