import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import { resolve } from "path";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/js/app.js"],
            refresh: ["resources/js/**"],
        }),
        vue(),
    ],
    server: {
        overlay: true,
    },
    resolve: {
        alias: {
            "@": resolve(__dirname, "resources/js"),
            moment: "moment/moment.js",
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: [
                    "import",
                    "mixed-decls",
                    "color-functions",
                    "global-builtin",
                ],
            },
        },
    },
});
