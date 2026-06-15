<template>
  <div class="modal fade modal-default" :id="modalId" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Select Warehouse</h5>
          <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">×</span>
          </button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3">
            <span v-if="variantName" class="fw-medium text-body">{{ variantName }}</span>
            is available in multiple warehouses. Choose a warehouse.
          </p>
          <div class="list-group">
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
        </div>
        <div class="modal-footer d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button
            type="button"
            class="btn btn-primary"
            :disabled="!selectedId"
            @click="confirm"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Modal } from 'bootstrap';

export default {
  props: {
    modalId: {
      type: String,
      default: 'warehouse-picker-modal',
    },
    confirmLabel: {
      type: String,
      default: 'Confirm',
    },
  },

  emits: ['confirm', 'cancel'],

  data() {
    return {
      options: [],
      variantName: '',
      selectedId: null,
      modalInstance: null,
      confirmed: false,
    };
  },

  mounted() {
    const el = document.getElementById(this.modalId);
    if (el) {
      this.modalInstance = new Modal(el, { backdrop: false });
      el.addEventListener('hidden.bs.modal', () => {
        if (!this.confirmed) {
          this.$emit('cancel');
        }
        this.confirmed = false;
      });
    }
  },

  beforeUnmount() {
    this.modalInstance?.dispose();
  },

  methods: {
    open({ options, variantName }) {
      this.options = options ?? [];
      this.variantName = variantName ?? '';
      this.selectedId = this.options.length === 1 ? this.options[0].warehouse_id : null;
      this.confirmed = false;
      this.modalInstance?.show();
    },

    confirm() {
      const opt = this.options.find((o) => o.warehouse_id === this.selectedId);
      if (!opt) {
        return;
      }
      this.confirmed = true;
      this.modalInstance?.hide();
      this.$emit('confirm', opt);
      this.options = [];
      this.variantName = '';
      this.selectedId = null;
    },
  },
};
</script>
