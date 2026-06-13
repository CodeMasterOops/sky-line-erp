import { formatMoney } from '@/helpers/formatMoney.js';

export const advanceColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Advance No', dataIndex: 'advance_no', sorter: true },
    { title: 'Date', dataIndex: 'advance_date', sorter: true },
    { title: 'Customer', dataIndex: 'party_name', sorter: true },
    { title: 'Payment Method', dataIndex: 'payment_method_label', sorter: false },
    { title: 'Amount', dataIndex: 'amount', customRender: ({ text }) => formatMoney(text), sorter: true },
    { title: 'Balance', dataIndex: 'balance', customRender: ({ text }) => formatMoney(text), sorter: false },
    { title: 'Status', key: 'status' },
    { title: 'Action', key: 'action' },
];

/**
 * @param {{ onView: Function, onApprove: Function, onAdjust: Function, onVoid: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onApprove, onAdjust, onVoid, onDelete }) {
    return [
        {
            key: 'view',
            icon: 'ti-eye',
            title: 'View',
            handler: (record) => onView(record),
        },
        {
            key: 'approve',
            icon: 'ti-check',
            title: 'Approve',
            condition: (record) => record.status === 'draft',
            handler: (record) => onApprove(record.id),
        },
        {
            key: 'adjust',
            icon: 'ti-transfer',
            title: 'Adjust to Invoice',
            condition: (record) => record.status === 'approved' && record.balance > 0,
            handler: (record) => onAdjust(record),
        },
        {
            key: 'void',
            icon: 'ti-ban',
            title: 'Void',
            condition: (record) => record.status === 'approved',
            handler: (record) => onVoid(record.id),
        },
        {
            key: 'delete',
            icon: 'ti-trash',
            title: 'Delete',
            condition: (record) => record.status === 'draft',
            handler: (record) => onDelete(record.id),
        },
    ];
}
