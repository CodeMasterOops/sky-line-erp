import printJS from 'print-js';
import {containsHtmlTag} from "@/helpers/helper.js";

export const usePrint = () => {
    const printContent = (printable,title='') => {
        printJS({
            printable,
            type:containsHtmlTag(printable) ? 'raw-html' : 'html',
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
