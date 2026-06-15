<template>
    <div class="form-document-amount-words">
        <span class="form-document-amount-words-label">{{ label }}:</span>
        <strong>{{ words }}</strong>
    </div>
</template>

<script setup>
import {computed} from 'vue';
import {amountToWordsEn} from '@/helpers/amountToWordsEn.js';

const props = defineProps({
    amount: {
        type: [Number, String],
        default: 0,
    },
    label: {
        type: String,
        default: 'Amount in words',
    },
});

const words = computed(() => amountToWordsEn(props.amount));
</script>
