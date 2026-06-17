export const applicationColumns = [
    { title: 'SN',         key: 'sn',         width: 60 },
    { title: 'Employee',   key: 'employee' },
    { title: 'Leave Type', key: 'leave_type' },
    { title: 'From',       dataIndex: 'from_date' },
    { title: 'To',         dataIndex: 'to_date' },
    { title: 'Days',       dataIndex: 'days' },
    { title: 'Status',     key: 'status' },
    { title: 'Action',     key: 'action',     align: 'center' },
];

export function createRowActions({ onApprove, onReject, onDelete }) {
    return [
        {
            key:       'approve',
            icon:      'ti-check',
            title:     'Approve',
            class:     'text-success',
            condition: (record) => (record.status?.value ?? record.status) === 'pending',
            handler:   (record) => onApprove(record.id),
        },
        {
            key:       'reject',
            icon:      'ti-x',
            title:     'Reject',
            class:     'text-danger',
            condition: (record) => (record.status?.value ?? record.status) === 'pending',
            handler:   (record) => onReject(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
