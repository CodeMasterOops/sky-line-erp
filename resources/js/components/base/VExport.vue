<template>
  <button type="button" @click="exportExcel" :class="btnClass">
    <i class="fa fa-file-excel-o"> </i>
    {{ buttonLabel }}
  </button>
</template>

<script setup>
import * as XLSX from 'xlsx';
import {toast} from "@/helpers/toast";

const props = defineProps({
  title: {
    type: String,
    default: 'Document'
  },
  target: {
    type: String
  },
  buttonLabel: {
    type: String,
    default: 'EXCEL'
  },
  btnClass: {
    type: String,
    default: 'btn btn-sm btn-outline-success'
  },
  fileType: {
    type: String,
    default: 'xlsx'
  }
})

const exportExcel = () => {
  const table = document.getElementById(props.target)
  if (table) {
    const ws = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
    XLSX.write(ws, {bookType: props.fileType, bookSST: true, type: 'base64'});
    XLSX.writeFile(ws, `${props.title}.${props.fileType}`);
  } else {
    toast(400, 'Table Not Found')
  }
}

</script>
