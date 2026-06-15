import {defineStore} from 'pinia'

export const useDatePreferenceStore = defineStore('date-preference', {
    state: () => ({
        mode: localStorage.getItem('date_mode') ?? 'ne',
    }),
    actions: {
        setMode(mode) {
            this.mode = mode;
            localStorage.setItem('date_mode', mode);
        },
    },
})
