<template>
    <label v-if="label" :for="id" class="form-label">
        {{ label }}
        <VRequiredMark v-if="required" />
    </label>
    <Multiselect
        :id="id"
        :label="nameProp"
        :value="modelValue"
        :customLabel="label"
        @input="updateModelValue"
        @search-change="onSearchChange"
        :disabled="disabled || loading"
        :loading="loading"
        :valueProp="valueProp"
        :searchable="true"
        :track-by="nameProp"
        :options="flattenOptions(options)"
        :placeholder="'Select '+ (placeholder??label??'')"
        :mode="mode"
        :multiple-label=formatMultipleLabel
        :hide-selected="false"
        :filter-results="filterResults"
        :min-chars="minChars"
    />
    <div v-if="error" class="text-danger">
        {{ error }}
    </div>
</template>

<script setup>
import Multiselect from "@vueform/multiselect"

const emit = defineEmits(['update:modelValue', 'validate', 'onInput', 'searchChange']);

const props = defineProps({
    id: {
        type: String
    },
    label: {
        type: String
    },
    placeholder: {
        type: String
    },
    error: {
        type: String,
        default: ''
    },

    modelValue: {
        required: true,
        type: [String, Number, Array, null]
    },
    options: {
        required: true,
        type: Array
    },
    valueProp: {
        type: String,
        default: 'id'
    },
    nameProp: {
        type: String,
        default: 'name'
    },
    disabled: {
        type: Boolean,
        default: false
    },
    loading: {
        type: Boolean,
        default: false
    },
    mode: {
        default: 'single'
    },
    filterResults: {
        type: Boolean,
        default: true,
    },
    minChars: {
        type: Number,
        default: 0,
    },
    required: {
        type: Boolean,
        default: false,
    },
})

const onSearchChange = (query) => {
    emit('searchChange', query);
};

const updateModelValue = (value) => {
    emit('update:modelValue', value ?? '');
    emit('onInput', value ?? '')
    emit('validate');
}

const formatMultipleLabel = (value) => {
    if (!value || value.length === 0) {
        return (props.placeholder ?? props.label ?? 'data') + ' selected';
    }

    const labels = value.map(item => item[props.nameProp]);
    return labels.join(", ");
}


const flattenOptions = (options, level = 0) => {
    return options.flatMap(opt => {
        const label = `${'— '.repeat(level)}${opt[props.nameProp]}`;
        const flat = [{
            [props.valueProp]: opt[props.valueProp],
            [props.nameProp]: label
        }];
        if (opt.children && opt.children.length) {
            flat.push(...flattenOptions(opt.children, level + 1));
        }
        return flat;
    });
}
</script>
