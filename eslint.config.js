import js from "@eslint/js";
import vue from "eslint-plugin-vue";

const noRawDateDisplay = {
    selector:
        "CallExpression[callee.property.name='format'][callee.object.callee.name=/^(moment|dayjs)$/]:not(:has(> Literal[value='YYYY-MM-DD']))",
    message:
        "Do not format dates for display with moment()/dayjs() in views. Use useDisplayDate() (or formatDate from helpers) so the AD/BS preference is honored. Machine formats like 'YYYY-MM-DD' are exempt.",
};

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
    },

    {
        files: ["resources/js/views/**/*.{js,vue}", "resources/js/components/**/*.{js,vue}"],
        rules: {
            "no-restricted-syntax": ["error", noRawDateDisplay]
        }
    }
];
