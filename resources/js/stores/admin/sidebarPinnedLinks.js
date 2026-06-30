import {defineStore} from 'pinia'
import {apiAdmin} from "@/helpers/api";
import {MAX_SIDEBAR_PINNED_LINKS} from "@/constants/sidebarPins";

const STORAGE_KEY = 'sidebar_pinned_links';

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

export const useSidebarPinnedLinksStore = defineStore('sidebar-pinned-links', {
    state: () => ({
        links: readStored(),
    }),
    actions: {
        /**
         * Apply links locally without writing to the server.
         * Used to seed the store from the authenticated user's saved preference.
         */
        applyLinks(links) {
            const next = (Array.isArray(links) ? links : []).slice(0, MAX_SIDEBAR_PINNED_LINKS);
            this.links = next;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        },
        /**
         * Persist the pinned sidebar links to the user's profile.
         * Optimistically updates the UI and reverts if the request fails.
         */
        setLinks(links) {
            const previous = [...this.links];
            this.applyLinks(links);

            return apiAdmin('profile/sidebar-pinned-links', 'put', {links: this.links})
                .catch((err) => {
                    this.applyLinks(previous);
                    throw err;
                });
        },
    },
})
