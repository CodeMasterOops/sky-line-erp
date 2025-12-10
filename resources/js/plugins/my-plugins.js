import Toast from 'vue-toastification'

const toastOptions = {
    transition: "my-custom-fade",
    timeout: 3000,
    maxToasts: 20,
    newestOnTop: true
};

export default {
    install: (app => {
        app.use(Toast, toastOptions)
    })
}
