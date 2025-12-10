import js from "@eslint/js";
import vue from "eslint-plugin-vue";

export default [
    {
        ignores: [
            "node_modules/**",
            "public/build/**"
        ]
    },

    js.configs.recommended,

    ...vue.configs["flat/essential"],

    {
        files: ["**/*.{js,vue}"],
        rules: {
            "vue/multi-word-component-names": "off"
        }
    }
];
