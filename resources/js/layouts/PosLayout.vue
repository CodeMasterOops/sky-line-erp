<template>
    <div class="pos-layout">
        <router-view />
        <calculator-modal />
    </div>
</template>

<script setup>
import CalculatorModal from "@/components/modal/calculator-modal.vue";
</script>
