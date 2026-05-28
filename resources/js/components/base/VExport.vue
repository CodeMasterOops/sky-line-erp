<template>
  <button type="button" @click="exportExcel" :class="btnClass">
    <i class="fa fa-file-excel-o"> </i>
    {{ buttonLabel }}
  </button>
</template>

<script setup>
import {toast} from "@/helpers/toast";

// xlsx is ~1.3 MB; load it on-demand when the user actually clicks export
// rather than shipping it in the initial bundle of every page that imports
// this component.

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

const exportExcel = async () => {
  const table = document.getElementById(props.target)
  if (!table) {
    toast(400, 'Table Not Found')
    return
  }

  const XLSX = await import('xlsx')
  const ws = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
  XLSX.write(ws, {bookType: props.fileType, bookSST: true, type: 'base64'});
  XLSX.writeFile(ws, `${props.title}.${props.fileType}`);
}

</script>
