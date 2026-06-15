import Toast, {createToastInterface} from 'vue-toastification';

export const toastOptions = {
    position: 'bottom-right',
    timeout: 3000,
    maxToasts: 20,
    newestOnTop: true,
};

export const toastInterface = createToastInterface(toastOptions);

export default {
    install: (app) => {
        app.use(Toast, toastOptions);
    },
};
