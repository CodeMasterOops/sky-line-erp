import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";

const STORAGE_KEY = 'report_pinned_links';

const readStored = () => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        localStorage.removeItem(STORAGE_KEY);
        return [];
    }
};

export const useReportPinnedLinksStore = defineStore('report-pinned-links', {
    state: () => ({
        links: readStored(),
    }),
    actions: {
        /**
         * Apply links locally without writing to the server.
         * Used to seed the store from the authenticated user's saved preference.
         */
        applyLinks(links) {
            const next = Array.isArray(links) ? links : [];
            this.links = next;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        },
        /**
         * Persist the pinned reports to the user's profile.
         * Optimistically updates the UI and reverts if the request fails.
         */
        setLinks(links) {
            const previous = [...this.links];
            this.applyLinks(links);

            return apiAdmin('profile/report-pinned-links', 'put', {links: this.links})
                .catch((err) => {
                    this.applyLinks(previous);
                    throw err;
                });
        },
        /**
         * Toggle a single report's pinned state and persist the change.
         */
        toggle(name) {
            const next = this.links.includes(name)
                ? this.links.filter((link) => link !== name)
                : [...this.links, name];

            return this.setLinks(next);
        },
    },
})
