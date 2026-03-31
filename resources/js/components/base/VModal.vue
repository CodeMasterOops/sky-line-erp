<template>
    <Transition name="modal">
        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-x: hidden; overflow-y: auto;"
        >
            <!-- Backdrop -->
            <div class="modal-backdrop fade show" @click="handleBackdropClick"></div>

            <div
                class="modal-dialog modal-dialog-centered"
                :class="[sizeClass, modalClass]"
                role="document"
                @click.stop
                style="position: relative; z-index: 1055;"
            >
                <div class="modal-content">
                    <div class="page-wrapper-new p-0">
                        <div class="content">
                            <!-- Header -->
                            <div class="modal-header border-0 custom-modal-header">
                                <slot name="header">
                                    <div class="page-title">
                                        <h4>{{ title }}</h4>
                                    </div>
                                </slot>

                                <button
                                    type="button"
                                    class="close"
                                    aria-label="Close"
                                    @click="$emit('closeClick')"
                                >
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="modal-body custom-modal-body">
                                <slot name="modal-body"/>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { computed, watch } from 'vue'

const props = defineProps({
    showModal: {
        type: Boolean,
        default: false,
    },
    size: {
        type: String,
        default: 'lg',
        validator: (val) => ['sm', 'md', 'lg', 'xl', 'fullscreen'].includes(val),
    },
    modalClass: {
        type: String,
        default: '',
    },
    title: {
        type: String,
        default: '',
    },
    closeOnBackdrop: {
        type: Boolean,
        default: true,
    },
})

const sizeClassMap = {
    sm: 'modal-sm',
    md: '',
    lg: 'modal-lg',
    xl: 'modal-xl',
    fullscreen: 'modal-fullscreen',
}

const sizeClass = computed(() => sizeClassMap[props.size] ?? 'modal-lg')

const emit = defineEmits(['closeClick'])

const handleBackdropClick = () => {
    if (props.closeOnBackdrop) {
        emit('closeClick')
    }
}

// Lock body scroll when modal is open
watch(() => props.showModal, (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
}, {immediate: true})
</script>
