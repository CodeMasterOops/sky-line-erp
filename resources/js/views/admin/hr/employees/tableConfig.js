export const employeeColumns = [
    { title: 'SN',          key: 'sn',                  width: 60 },
    { title: 'Code',        dataIndex: 'employee_code' },
    { title: 'Name',        dataIndex: 'full_name' },
    { title: 'Department',  key: 'department' },
    { title: 'Designation', key: 'designation' },
    { title: 'Type',        key: 'type' },
    { title: 'Status',      key: 'status' },
    { title: 'Action',      key: 'action',               align: 'center' },
];

export function createRowActions({ onEdit, onDelete, onLedger }) {
    return [
        {
            key:     'ledger',
            icon:    'ti-report-money',
            title:   'Salary Ledger',
            class:   'text-success',
            handler: (record) => onLedger(record.id),
        },
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            class:   'edit-icon',
            handler: (record) => onEdit(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
