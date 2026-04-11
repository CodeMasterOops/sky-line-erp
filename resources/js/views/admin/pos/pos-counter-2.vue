<template>
    <a href="javascript:void(0);" @click="decrement" class="dec d-flex justify-content-center align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="minus">
        <vue-feather type="minus-circle"></vue-feather>
    </a>
    <input type="text" class="form-control text-center" name="qty" value="3" v-model="quantity">
    <a href="javascript:void(0);" @click="increment" class="inc d-flex justify-content-center align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="plus">
        <vue-feather type="plus-circle"></vue-feather>
    </a>
</template>

<script>
export default {
    data() {
        return {
            quantity: 0,
        }
    },
    methods: {
        increment() {
            this.quantity += 1;
        },
        decrement() {
            if (this.quantity > 0) {
                this.quantity -= 1;
            }
        },
    },
}
</script>