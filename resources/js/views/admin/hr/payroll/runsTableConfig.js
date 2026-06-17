import { formatMoney } from '@/helpers/formatMoney.js';

export const runColumns = [
    { title: 'SN',          key: 'sn',              width: 60 },
    { title: 'Period',      key: 'period' },
    { title: 'Status',      key: 'status' },
    { title: 'Total Gross', dataIndex: 'total_gross', customRender: ({ text }) => formatMoney(text) },
    { title: 'Deductions',  dataIndex: 'total_deductions', customRender: ({ text }) => formatMoney(text) },
    { title: 'Net Pay',     dataIndex: 'total_net',   customRender: ({ text }) => formatMoney(text) },
    { title: 'Action',      key: 'action',            align: 'center' },
];

export function createRowActions({ onView, onProcess, onConfirm, onDelete }) {
    return [
        {
            key:     'view',
            icon:    'ti-eye',
            title:   'View',
            handler: (record) => onView(record.id),
        },
        {
            key:       'process',
            icon:      'ti-calculator',
            title:     'Calculate',
            condition: (record) => (record.status?.value ?? record.status) !== 'paid',
            handler:   (record) => onProcess(record.id),
        },
        {
            key:       'confirm',
            icon:      'ti-check',
            title:     'Mark Paid',
            class:     'text-success',
            condition: (record) => (record.status?.value ?? record.status) === 'processed',
            handler:   (record) => onConfirm(record.id),
        },
        {
            key:       'delete',
            icon:      'ti-trash',
            title:     'Delete',
            condition: (record) => (record.status?.value ?? record.status) !== 'paid',
            handler:   (record) => onDelete(record.id),
        },
    ];
}
