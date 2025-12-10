<template>
    <ApexChart :options="chartOptions" :series="series"/>
</template>

<script setup>
import ApexChart from 'vue3-apexcharts';

const props = defineProps({
    labels: {
        type: Array
    },
    series: {
        type: Array
    },
    chartType: {
        type: String,
        default: 'pie'
    },
    showLegend: {
        type: Boolean,
        default: true
    },
    chartTitle: {
        type: String
    }
})

const chartOptions = {
    chart: {
        type: props.chartType,
    },
    legend: {
        show: props.showLegend,
        position: 'bottom',
        offsetX: 0,
        offsetY: 0,
    },
    title: {
        text: props.chartTitle,
        align: 'center',
        style: {
            fontSize: '14px',
            //fontWeight:  'bold',
            //fontFamily:  undefined,
            color: 'blue'
        },
    },
    labels: props.labels
}
</script>

