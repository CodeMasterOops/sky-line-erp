<template>
  <label v-if="label" :for="id" class="form-label">
    {{ label }}
    <VRequiredMark v-if="required" />
  </label>
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
import VRequiredMark from '@/components/base/VRequiredMark.vue';

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
  required: {
    type: Boolean,
    default: false,
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
