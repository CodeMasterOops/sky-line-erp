<template>
  <VModal
    :show-modal="showModal"
    size="md"
    title="Select Warehouse"
    :close-on-backdrop="false"
    @close-click="cancel"
  >
    <template #modal-body>
      <p class="text-muted small mb-3">
        <span v-if="variantName" class="fw-medium text-body">{{ variantName }}</span>
        is available in multiple warehouses. Choose a warehouse.
      </p>
      <div class="list-group mb-4">
        <button
          v-for="opt in options"
          :key="opt.warehouse_id"
          type="button"
          class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
          :class="{ active: selectedId === opt.warehouse_id }"
          @click="selectedId = opt.warehouse_id"
        >
          <span>{{ opt.warehouse_name }}</span>
          <span class="badge rounded-pill" :class="selectedId === opt.warehouse_id ? 'bg-white text-primary' : 'bg-primary'">
            {{ opt.quantity }} in stock
          </span>
        </button>
      </div>
      <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" @click="cancel">Cancel</button>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="!selectedId"
          @click="confirm"
        >
          {{ confirmLabel }}
        </button>
      </div>
    </template>
  </VModal>
</template>

<script>
export default {
  props: {
    confirmLabel: {
      type: String,
      default: 'Confirm',
    },
  },

  emits: ['confirm', 'cancel'],

  data() {
    return {
      showModal: false,
      options: [],
      variantName: '',
      selectedId: null,
    }
  },

  methods: {
    open({ options, variantName }) {
      this.options = options ?? []
      this.variantName = variantName ?? ''
      this.selectedId = this.options.length === 1 ? this.options[0].warehouse_id : null
      this.showModal = true
    },

    confirm() {
      const opt = this.options.find((o) => o.warehouse_id === this.selectedId)
      if (!opt) {
        return
      }
      this.showModal = false
      this.$emit('confirm', opt)
      this.options = []
      this.variantName = ''
      this.selectedId = null
    },

    cancel() {
      this.showModal = false
      this.$emit('cancel')
      this.options = []
      this.variantName = ''
      this.selectedId = null
    },
  },
}
</script>
