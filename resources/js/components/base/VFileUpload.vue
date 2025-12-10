<style scoped>
.image-caption {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #3d3dc1;
}
</style>
<template>
    <label v-if="label" :for="id" class="form-label">{{ label }}</label>
    <template v-if="!errorMessage">
        <div v-if="fileDetail.imageUrl || (defaultPhoto && isImage(defaultPhoto))" class="image-preview mb-2">
            <div class="card border border-info">
                <div class="card-body text-center">
                    <img :src="fileDetail.imageUrl || defaultPhoto" alt="Image"
                         :style="[{'max-height':imageHeight,'max-width':'100%'}]">
                    <p v-if="fileDetail.file && showImageInfo" class="image-caption">
                        {{ fileDetail.name }} <br>
                        ({{ fileDetail.size }} KB)
                    </p>
                </div>
            </div>
        </div>
        <div v-else-if="fileDetail.pdfUrl || defaultPhoto">
            <div class="card border border-info text-center">
                <iframe :src="fileDetail.pdfUrl || defaultPhoto" width="100%" frameborder="0"></iframe>
                <p v-if="fileDetail.file && showImageInfo" class="image-caption">
                    {{ fileDetail.name }} <br>
                    ({{ fileDetail.size }} KB)
                </p>
            </div>
        </div>
    </template>
    <div class="input-group">
        <input
            type="text"
            :title="fileDetail.name"
            @click="selectFile" readonly
            :disabled="disabled"
            class="form-control"
            :placeholder="fileDetail.name ? fileDetail.name : 'Select file...'"
        >
        <button v-if="fileDetail.file" @click="resetFile" class="btn btn-outline-danger" type="button">
            <i class="fa fa-trash"></i> Remove
        </button>
        <button @click="selectFile" :disabled="disabled" class="btn btn-primary" type="button">
            <i class="fa fa-folder-open"> Browse..</i>
        </button>
    </div>
    <input
        type="file" ref="file_element"
        @change="onFileSelected"
        class="form-control" hidden
        v-bind:class="[{'is-invalid':error}]"
        :id="id"
    >
    <div v-if="errorMessage" class="invalid-feedback">
        {{ errorMessage }}
    </div>
    <div v-else-if="error" class="invalid-feedback">
        {{ error }}
    </div>
</template>

<script setup>
import {ref, watch} from "vue";
import {useFileUpload} from "@/composables/fileUpload";
import {isImage} from "@/helpers/helper.js";

const emit = defineEmits(['update:modelValue', 'validate']);

const props = defineProps({
    modelValue: {},
    id: {
        type: String
    },
    label: {
        type: String,
    },
    defaultPhoto: {
        type: String
    },
    disabled: {
        type: Boolean,
        default: false
    },
    error: {
        type: String,
        default: ''
    },
    imageHeight: {
        default: '150px'
    },
    maxSize: {
        type: Number,
        default: 5
    },
    mimes: {
        type: Array
    },
    showImageInfo: {
        type: Boolean,
        default: true
    }
})

const {fileDetail, onFileSelected, resetFile} = useFileUpload();

const file_element = ref('');

const errorMessage = ref('');

function selectFile() {
    file_element.value.click()
}

watch(() => fileDetail.value.file, (file) => {
    if (!file) {
        resetFile();
        emit('update:modelValue', '');
        emit('validate');
        return;
    }

    const {extension, size} = fileDetail.value;
    const errors = [];

    if (props.mimes?.length) {
        const allowedExtensions = props.mimes.map(e => e.toLowerCase());
        if (!allowedExtensions.includes(extension.toLowerCase())) {
            errors.push(`Only ${allowedExtensions.join(', ')} files are allowed.`);
        }
    }

    if (props.maxSize) {
        const maxBytes = props.maxSize * 1024;
        if (size > maxBytes) {
            errors.push(`File must be less than ${props.maxSize} MB.`);
        }
    }

    if (errors.length) {
        errorMessage.value = errors.join(' ');
        emit('update:modelValue', '');
        resetFile();
    } else {
        errorMessage.value = '';
        emit('update:modelValue', file);
    }

    emit('validate');
});

watch(() => props.modelValue, (file) => {
    if (file) {
        fileDetail.value.file = file;
    } else {
        resetFile();
    }
})
</script>
