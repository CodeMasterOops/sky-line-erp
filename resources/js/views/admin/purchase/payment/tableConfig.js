export const paymentColumns = [
    { title: 'SN',             key: 'sn',               width: 60 },
    { title: 'Payment No',     dataIndex: 'payment_no',       sorter: true },
    { title: 'Date',           dataIndex: 'payment_date',     sorter: true },
    { title: 'Supplier',       dataIndex: 'party_name',       sorter: true },
    { title: 'Payment Mode',   dataIndex: 'payment_mode_name', sorter: true },
    { title: 'Account',        dataIndex: 'account_name',     sorter: true },
    { title: 'Total Amount',   dataIndex: 'total_amount',     sorter: true },
    { title: 'Status',         key: 'status' },
    { title: 'Action',         key: 'action' },
];

/**
 * @param {{ onApprove: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onApprove, onDelete }) {
    return [
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
