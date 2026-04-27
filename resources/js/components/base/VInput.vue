<template>
  <label v-if="label" :for="id" class="form-label">
    {{ label }}
    <VRequiredMark v-if="required" />
  </label>
  <input
      :type="inputType"
      v-bind:step="inputType==='number'?'any':null"
      :id="id"
      v-bind:class="[inputClass, { 'is-invalid': error }]"
      v-bind:min="minValue"
      v-bind:max="maxValue ? maxValue: null"
      :value="displayValue"
      @input="updateInputValue"
      :placeholder="placeholder || label"
      :disabled="disabled"
      :readonly="readonly"
      @blur="updateInputValue"
  />
  <div v-if="error" class="invalid-feedback">
    {{ error }}
  </div>
</template>

<script setup>
import {computed} from 'vue';

const emit = defineEmits(['update:modelValue', 'validate','onInput']);

const props = defineProps({
  id: {
    type: String,
  },
  inputType: {
    type: String,
    default: "text",
  },
  inputClass: {
    type: String,
    default: "form-control",
  },
  label: {
    type: String,
  },
  placeholder: {
    type: String,
  },
  error: {
    type: String,
    default: ''
  },
  disabled: {
    type: Boolean,
    default: false
  },
  readonly: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false,
  },
  minValue: {
    type: Number,
    default: 0
  },
  maxValue: {
    type: Number
  },

  modelValue: {
    type: [String, Number],
    default: '',
  },
});

/** Avoid blank controlled inputs when parent passes null/undefined; normalize number fields to string. */
const displayValue = computed(() => {
    const v = props.modelValue;
    if (v === null || v === undefined) {
        return '';
    }
    if (props.inputType === 'number') {
        return String(v);
    }
    return v;
});

const updateInputValue = (event) => {
  emit('update:modelValue', event.target.value)
  emit('onInput', event.target.value)
  emit('validate')
}
</script>
