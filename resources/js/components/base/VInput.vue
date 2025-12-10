<template>
  <label v-if="label" :for="id" class="form-label">{{ label }}</label>
  <input
      :type="inputType"
      v-bind:step="inputType==='number'?'any':null"
      :id="id"
      v-bind:class="[inputClass, { 'is-invalid': error }]"
      v-bind:min="minValue"
      v-bind:max="maxValue ? maxValue: null"
      :value="modelValue"
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

const emit = defineEmits(['update:modelValue', 'validate','onInput']);

defineProps({
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
  minValue: {
    type: Number,
    default: 0
  },
  maxValue: {
    type: Number
  },

  modelValue: {
    type: [String, Number],
    required: true,
  },
});

const updateInputValue = (event) => {
  emit('update:modelValue', event.target.value)
  emit('onInput', event.target.value)
  emit('validate')
}
</script>
