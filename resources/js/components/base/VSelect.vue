<template>
  <label v-if="label" :for="id" class="form-label">
    {{ label }}
    <VRequiredMark v-if="required" />
  </label>
  <select
      :id="id"
      v-bind:class="[selectClass,{'is-invalid':error}]"
      :disabled="disabled"
      :value="modelValue"
      @input="updateInputValue"
  >
    <option v-if="label || placeholder" value="">Select {{ placeholder || label }}</option>
    <option
        v-for="(option,index) in options"
        :value="option[valueProp]??option[nameProp]??option" :key="index">
      {{
        option[nameProp]?.[secondNameProp]?.[thirdNameProp] || option[nameProp]?.[secondNameProp] || option[nameProp] || option
      }}
    </option>
  </select>
  <div v-if="error" class="invalid-feedback">
    {{ error }}
  </div>
</template>

<script setup>

const emit = defineEmits(['update:modelValue', 'validate','onInput']);

defineProps({
  id: {
    type: String
  },
  selectClass: {
    type: String,
    default: 'form-select'
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
    type: [String, Number],
    required: true
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
  secondNameProp: {
    type: String,
    default: 'name'
  },
  thirdNameProp: {
    type: String,
    default: 'name'
  },
  disabled: {
    type: Boolean,
    default: false
  },
  required: {
    type: Boolean,
    default: false,
  },
})

const updateInputValue = (event) => {
  emit('update:modelValue', event.target.value)
  emit('onInput', event.target.value)
  emit('validate')
}

</script>
