import { formatMoney } from '@/helpers/formatMoney.js';

export const receiptColumns = [
    { title: 'SN',              key: 'sn',              width: 60 },
    { title: 'Receipt No',      dataIndex: 'receipt_no',      sorter: true },
    { title: 'Date',            dataIndex: 'receipt_date',    sorter: true },
    { title: 'Customer',        dataIndex: 'party_name',      sorter: true },
    { title: 'Payment Method',  dataIndex: 'payment_method',  sorter: true },
    { title: 'Account',         dataIndex: 'account_name',    sorter: true },
    { title: 'Total Amount',    dataIndex: 'total_amount',    key: 'total_amount', customRender: ({ text }) => formatMoney(text), sorter: true },
    { title: 'Status',          key: 'status' },
    { title: 'Action',          key: 'action' },
];

/**
 * @param {{ onView: Function, onApprove: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onApprove, onDelete }) {
    return [
        {
            key:       'view',
            icon:      'ti-printer',
            title:     'View / Print',
            handler:   (record) => onView(record.id),
        },
        {
            key:       'approve',
            icon:      'ti-check',
            title:     'Approve',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onApprove(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
