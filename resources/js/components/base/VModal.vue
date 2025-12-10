<template>
  <Transition name="modal">
    <div v-if="showModal" class="modal-mask" role="document">
      <div v-bind:class="{'modal-wrapper':modalClass!=='full-screen-modal'}">
        <div class="modal-container" v-bind:class="[modalClass]">
          <div class="v-modal-header">
            <template v-if="$slots.header">
              <slot name="header"/>
            </template>
            <template v-else>
              <h5 class="v-modal-title">
                {{ title }}
              </h5>
              <button
                  v-if="$emit"
                  class="btn-close"
                  type="button"
                  @click.prevent="$emit('closeClick')"
                  aria-label="Close"
              ></button>
            </template>
          </div>
          <div class="v-modal-body">
            <slot name="modal-body"/>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
defineEmits(['closeClick'])
defineProps({
  showModal: {
    type: Boolean,
    default: false
  },
  modalClass: {
    type: String,
    default: 'medium-modal'
  },
  title: {
    type: String
  }
})
</script>
