<template>
  <div class="form-check">
    <input
        class="form-check-input"
        :value="value"
        @change="updateCheckbox"
        :checked="modelValue.includes(value)"
        type="checkbox"
        :id="id"
    />
    <label class="form-check-label" :for="id">
      {{ label }}
    </label>
  </div>
</template>

<script>
import {computed} from "vue";

export default {
  props: {
    id: {
      type: String,
    },
    label: {
      type: String,
      required: true,
    },
    modelValue: {
      required: true,
    },
    value: {
      required: true,
    },
  },
  setup(props, {emit}) {
    let data = computed(() => props.modelValue);

    function updateCheckbox($event) {
      if ($event.target.checked) {
        data.value.push(props.value);
      } else {
        data.value.splice(data.value.indexOf(props.value), 1);
      }
      emit("update:modelValue", data.value);
    }

    return {
      updateCheckbox,
    };
  },
};
</script>
