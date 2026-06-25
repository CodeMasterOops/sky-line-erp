<template>
  <VModal
    :show-modal="posStore.modals.calculator"
    size="md"
    title="Calculator"
    @close-click="closeModal"
  >
    <template #modal-body>
      <div class="calculator-wrap">
        <div class="mb-3">
          <input
            v-model="inputValue"
            class="input form-control"
            type="text"
            placeholder="0"
            readonly
          />
        </div>
        <div class="calculator-body d-flex justify-content-between">
          <div class="text-center">
            <button class="btn btn-clear" @click="clear">C</button>
            <button class="btn btn-number" @click="appendValue('7')">7</button>
            <button class="btn btn-number" @click="appendValue('4')">4</button>
            <button class="btn btn-number" @click="appendValue('1')">1</button>
            <button class="btn btn-number" @click="appendValue(',')">,</button>
          </div>
          <div class="text-center">
            <button class="btn btn-expression" @click="appendValue('/')">÷</button>
            <button class="btn btn-number" @click="appendValue('8')">8</button>
            <button class="btn btn-number" @click="appendValue('5')">5</button>
            <button class="btn btn-number" @click="appendValue('2')">2</button>
            <button class="btn btn-number" @click="appendValue('00')">00</button>
          </div>
          <div class="text-center">
            <button class="btn btn-expression" @click="appendValue('%')">%</button>
            <button class="btn btn-number" @click="appendValue('9')">9</button>
            <button class="btn btn-number" @click="appendValue('6')">6</button>
            <button class="btn btn-number" @click="appendValue('3')">3</button>
            <button class="btn btn-number" @click="appendValue('.')">.</button>
          </div>
          <div class="text-center">
            <button class="btn btn-clear" @click="backspace">
              <i class="ti ti-backspace"></i>
            </button>
            <button class="btn btn-expression" @click="appendValue('*')">x</button>
            <button class="btn btn-expression" @click="appendValue('-')">-</button>
            <button class="btn btn-expression" @click="appendValue('+')">+</button>
            <button class="btn btn-clear" @click="solve">=</button>
          </div>
        </div>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { usePosStore } from '@/stores/admin/pos/pos.js'

const posStore = usePosStore()
const inputValue = ref('')

watch(() => posStore.modals.calculator, (isOpen) => {
  if (!isOpen) {
    inputValue.value = ''
  }
})

const appendValue = (value) => {
  inputValue.value += value
}

const clear = () => {
  inputValue.value = ''
}

const backspace = () => {
  inputValue.value = inputValue.value.slice(0, -1)
}

const solve = () => {
  try {
    inputValue.value = eval(inputValue.value).toString()
  } catch {
    inputValue.value = 'Error'
  }
}

const closeModal = () => {
  inputValue.value = ''
  posStore.closeModal('calculator')
}
</script>
