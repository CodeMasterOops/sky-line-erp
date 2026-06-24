import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";

export const useDatePreferenceStore = defineStore('date-preference', {
    state: () => ({
        mode: localStorage.getItem('date_mode') ?? 'ne',
    }),
    actions: {
        /**
         * Apply a mode locally without writing to the server.
         * Used to seed the store from the authenticated user's saved preference.
         */
        applyMode(mode) {
            if (!mode || mode === this.mode) {
                return;
            }
            this.mode = mode;
            localStorage.setItem('date_mode', mode);
        },
        /**
         * Change the mode and persist it to the user's profile.
         * Optimistically updates the UI and reverts if the request fails.
         */
        setMode(mode) {
            const previous = this.mode;
            this.mode = mode;
            localStorage.setItem('date_mode', mode);

            return apiAdmin('profile/date-mode', 'put', {date_mode: mode})
                .catch((err) => {
                    this.mode = previous;
                    localStorage.setItem('date_mode', previous);
                    throw err;
                });
        },
    },
})
