<template>
  <ApexChart :options="chartOptions" :series="series"/>
</template>

<script setup>
import ApexChart from 'vue3-apexcharts';

const props = defineProps({
  categories: {
    type: Array
  },
  series: {
    type: Array
  },
  chartTitle: {
    type: String
  },
  height: {
    type: Number,
    default: 250
  }
})

const chartOptions = {
  chart: {
    type: "bar",
    height: props.height,
    stacked: true,
    toolbar: {
      show: true
    },
    zoom: {
      enabled: true
    }
  },
  responsive: [{
    breakpoint: 480,
    options: {
      legend: {
        position: 'bottom',
        offsetX: -10,
        offsetY: 0
      }
    }
  }],
  title: {
    text: props.chartTitle
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 10,
      dataLabels: {
        total: {
          enabled: true,
          style: {
            fontSize: '13px',
            fontWeight: 900
          }
        }
      }
    },
  },
  xaxis: {
    categories: props.categories,
  },
  fill: {
    opacity: 1
  },
}
</script>

