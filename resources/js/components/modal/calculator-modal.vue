<template>
  <!-- Modal for the calculator -->
  <div class="modal fade pos-modal" id="calculator" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-body p-0">
          <div class="calculator-wrap">
            <div class="p-3">
              <div class="d-flex align-items-center">
                <h3>Calculator</h3>
                <button
                  type="button"
                  class="close"
                  @click="closeModal"
                  aria-label="Close"
                >
                  <span aria-hidden="true">×</span>
                </button>
              </div>
              <!-- Display field for current expression or result -->
              <div>
                <input
                  v-model="inputValue"
                  class="input"
                  type="text"
                  placeholder="0"
                  readonly
                />
              </div>
            </div>
            <!-- Calculator buttons -->
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
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      inputValue: "", // Holds the current value or expression in the input field
    };
  },
  methods: {
    // Append a value (number or operator) to the input value
    appendValue(value) {
      this.inputValue += value;
    },
    // Clear the input value
    clear() {
      this.inputValue = "";
    },
    // Backspace functionality to remove the last character
    backspace() {
      this.inputValue = this.inputValue.slice(0, -1);
    },
    // Evaluate the input value and calculate the result
    solve() {
      try {
        // Using eval is a simple way to evaluate math expressions
        this.inputValue = eval(this.inputValue).toString();
      } catch (error) {
        this.inputValue = "Error"; // Display "Error" if evaluation fails
      }
    },
    // Close the modal
    closeModal() {
      this.inputValue = ""; // Clear the input before closing the modal
      const modal = document.getElementById("calculator");
      const bootstrapModal = new bootstrap.Modal(modal);
      bootstrapModal.hide();
    },
  },
};
</script>
