import {ref} from 'vue';
import {containsHtmlTag} from '@/helpers/helper.js';
import {DOCUMENT_PRINT_CSS, DOCUMENT_PRINT_LANDSCAPE_CSS} from '@/composables/documentPrintStyles.js';

const resolvePrintableElement = (target) => {
    if (!target) {
        return null;
    }

    if (typeof target === 'string') {
        return document.querySelector(target);
    }

    if (target instanceof HTMLElement) {
        return target;
    }

    return null;
};

const buildPrintableHtml = (element, landscape = false) => {
    const clone = element.cloneNode(true);

    clone.querySelectorAll('.no-print').forEach((node) => node.remove());

    const landscapeCss = landscape ? DOCUMENT_PRINT_LANDSCAPE_CSS : '';

    return `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <style>${DOCUMENT_PRINT_CSS}${landscapeCss}</style>
</head>
<body>${clone.outerHTML}</body>
</html>`;
};

export const useDocumentPrint = () => {
    const printing = ref(false);

    const printContent = async (printable, title = '') => {
        const {default: printJS} = await import('print-js');

        printJS({
            printable,
            type: containsHtmlTag(printable) ? 'raw-html' : 'html',
            documentTitle: title || 'document',
            showModal: true,
            targetStyles: ['*'],
            css: [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
            ],
            scanStyles: false,
            honorMarginPadding: false,
            modalMessage: 'Your document is ready to print.',
        });
    };

    const printElement = async (target, title = '', options = {}) => {
        const element = resolvePrintableElement(target);

        if (!element) {
            return;
        }

        printing.value = true;

        try {
            const html = buildPrintableHtml(element, options.landscape === true);
            await printContent(html, title);
        } finally {
            printing.value = false;
        }
    };

    return {
        printing,
        printContent,
        printElement,
    };
};
