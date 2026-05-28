import {containsHtmlTag} from "@/helpers/helper.js";

// print-js is loaded on-demand at the moment the user actually triggers a
// print, keeping it out of the initial bundle of every screen that imports
// this composable.
export const usePrint = () => {
    const printContent = async (printable, title = '') => {
        const {default: printJS} = await import('print-js')
        printJS({
            printable,
            type: containsHtmlTag(printable) ? 'raw-html' : 'html',
            documentTitle: title || 'document',
            showModal: true,
            targetStyles: ['*'],
            css: [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'
            ],
            scanStyles: false,
            honorMarginPadding: false,
            modalMessage: 'Your document is ready to print.',
            onPrintDialogClose: () => {
                //alert('printed')
            }
        })
    }

    return {
        printContent
    }
}
