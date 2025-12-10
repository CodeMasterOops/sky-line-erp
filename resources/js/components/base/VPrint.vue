<template>
    <button type="button" @click="printContent(target,title)" class="btn btn-sm btn-outline-primary">
    <span
        v-if="printLoading"
        class="spinner-border spinner-border-sm"
        role="status"
        aria-hidden="true"
    ></span>
        <i v-else class="fa fa-print"> </i>
        {{ buttonLabel }}
    </button>
</template>

<script setup>
import {ref} from "vue";
import {usePrint} from "@/composables/print.js";

defineProps({
    title: {
        type: String,
        default: 'Document'
    },
    target: {
        type: String
    },
    buttonLabel: {
        type: String,
        default: 'PRINT'
    }
})

const {printContent}=usePrint();

const printLoading = ref(false);

</script>
