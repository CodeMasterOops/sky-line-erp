<template>
  <label :for="id" class="form-label">{{ label }}</label>
  <textarea
      :id="id"
      v-bind:class="[inputClass, { 'is-invalid': error }]"
      :placeholder="label"
      :value="modelValue"
      @input="updateInputValue"
      :cols="cols"
      @blur="updateInputValue"
      :rows="rows"></textarea>
  <div v-if="error" class="invalid-feedback">
    {{ error }}
  </div>
</template>

<script setup>
const emit = defineEmits(['update:modelValue', 'validate']);

defineProps({
  id: {
    type: String,
  },
  inputClass: {
    type: String,
    default: "form-control",
  },
  label: {
    type: String,
  },
  rows: {
    type: Number,
    default: 3
  },
  cols: {
    type: Number,
    default: 30
  },
  error: {
    type: String,
    default: ''
  },

  modelValue: {
    required: true,
  },
});

const updateInputValue = (event) => {
  emit('update:modelValue', event.target.value)
  emit('validate')
}
</script>
