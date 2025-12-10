<style scoped>
.image-preview img {
    border: 1px solid blue;
    border-radius: 2%;
    padding: 5px;
}
</style>
<template>
    <label v-if="label" :for="id" class="form-label">
        {{ label }}
        {{ files.length ? '(' + files.length + ' files selected)' : ''}}
    </label>
    <div class="input-group">
        <input
            type="text"
            :title="files.map(f=>f.name)"
            @click="selectFile" readonly
            class="form-control"
            :placeholder="files.length ? files.map(f=>f.name) : 'Select file...'"
        >
        <button v-if="modelValue?.length" @click="resetFileData" class="btn btn-outline-danger"
                type="button">
            <i class="fa fa-trash"></i> Remove
        </button>
        <button @click="selectFile" class="btn btn-primary" type="button">
            <i class="fa fa-folder-open"> Browse..</i>
        </button>
    </div>
    <input
        type="file" ref="file_element"
        multiple
        @change="onFileSelected"
        class="form-control" hidden
        v-bind:class="[{'is-invalid':error}]"
        :id="id"
    >
    <p v-if="error" class="text-danger">
        {{ error }}
    </p>
</template>

<script setup>
import {ref, watch} from "vue";
import {useMultiFileUpload} from "@/composables/multiFileUpload.js";

const emit = defineEmits(['update:modelValue', 'validate', 'afterSelect']);

defineProps({
    modelValue: {
        required: true,
        type: [String, Number, Array, null]
    },
    id: {
        type: String
    },
    label: {
        type: String,
    },
    defaultPhoto: {
        type: String
    },
    isSelector: {
        type: Boolean,
        default: true
    },
    error: {
        type: String,
        default: ''
    },
    imageHeight: {
        default: '120px'
    },
})

const {files, onFileSelected, resetFile} = useMultiFileUpload();

const file_element = ref('');

function selectFile() {
    file_element.value.click();
}

watch(() => files.value, (list) => {
    let data = [];
    list.forEach(f => {
        data.push(f.file);
    })
    emit('update:modelValue', data || []);
}, {deep: true})

const resetFileData = () => {
    resetFile();
    emit('update:modelValue', []);
}
</script>
