<template>
    <div class="dataTables_wrapper">
        <div class="dataTables_top">
            <div class="dataTables_length">
                <label class="d-inline-flex align-items-center gap-1">
                    Show
                    <span style="width: 5rem; display: inline-block;">
                        <VMultiselect
                            size="sm"
                            v-model="params.limit"
                            :options="showEntryOptions"
                        />
                    </span>
                    entries
                </label>
            </div>
            <div class="dataTables_filter">
                <label>
                    Search :
                    <input type="text" v-model="params.search" placeholder="">
                </label>
            </div>
        </div>
        <div class="table-responsive" :id="responsiveId">
            <slot></slot>
        </div>
        <div v-if="meta.total>meta.per_page" class="dataTables_bottom">
            <div class="dataTables_info">
                Showing {{ meta.from ?? 0 }} to {{ meta.to ?? 0 }} of {{ meta.total }} entries
            </div>
            <div class="dataTable_paginate">
                <ul class="pagination mb-0">
                    <li v-for="(link,index) in meta.links"
                        class="page-item"
                        v-bind:class="{'active' : link.active,'disabled':!link.url}"
                        :key="index"
                    >
                        <button type="button" :disabled="link.active" class="page-link" @click="paginateEvent(link.url)"
                                v-html="link.label">
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<style>
.dataTables_top {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.dataTables_bottom {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
}

.dataTables_length select {
    border: 1px solid #aaa;
    border-radius: 3px;
    padding: 4px;
    background-color: transparent;
}

.dataTables_filter input {
    border: 1px solid #aaa;
    border-radius: 3px;
    padding: 4px;
    background-color: transparent;
    margin-left: 3px;
}

</style>

<script setup>

import {computed, reactive, watch} from 'vue';

const props = defineProps({
    meta: {
        required: true,
        type: Object
    },
    responsiveId: {
        type: String
    }
})

const showEntries = [10, 25, 50, 100, 500];

const showEntryOptions = computed(() =>
    showEntries.map((n) => ({ id: n, name: String(n) }))
);

const params = reactive({
    limit: props.filter.limit || 25,
    search: '',
    page: 1
})

const filter = defineModel('filter');

watch(() => params, () => {
    Object.assign(filter.value, {
        search: params.search,
        limit: params.limit,
        page: params.page
    })
}, {deep: true})

const paginateEvent = (url) => {
    let link = new URL(url);
    params.page = parseInt(new URLSearchParams(link.search).get('page'))
}
</script>
