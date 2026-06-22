export const stockTransferColumns = [
    { title: 'SN',        key: 'sn',           width: 60 },
    { title: 'Reference', dataIndex: 'reference_no', sorter: true },
    { title: 'Date',      dataIndex: 'date',         sorter: true, width: 120 },
    { title: 'From',      dataIndex: 'from_warehouse' },
    { title: 'To',        dataIndex: 'to_warehouse' },
    { title: 'Status',    key: 'status',         width: 120 },
    { title: 'Action',    key: 'action',         width: 140 },
];

export const STATUS_BADGE = {
    draft:       'bg-secondary',
    in_transit:  'bg-warning text-dark',
    approved:    'bg-success',
};

export const STATUS_TABS = [
    { value: '',           label: 'All' },
    { value: 'draft',      label: 'Draft' },
    { value: 'in_transit', label: 'In Transit' },
    { value: 'approved',   label: 'Approved' },
];

/**
 * @param {{
 *   onEdit: Function,
 *   onDispatch: Function,
 *   onReceive: Function,
 *   onDelete: Function,
 * }} handlers
 */
export function createRowActions({ onEdit, onDispatch, onReceive, onDelete }) {
    return [
        {
            key:       'edit',
            icon:      'ti-edit',
            title:     'Edit',
            class:     'edit-icon',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onEdit(record.id),
        },
        {
            key:       'dispatch',
            icon:      'ti-truck-delivery',
            title:     'Dispatch (Send)',
            class:     'text-primary',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onDispatch(record.id),
        },
        {
            key:       'receive',
            icon:      'ti-package-import',
            title:     'Receive',
            class:     'text-success',
            condition: (record) => record.status === 'in_transit',
            handler:   (record) => onReceive(record.id),
        },
        {
            key:       'delete',
            icon:      'ti-trash',
            title:     'Delete',
            class:     'text-danger',
            condition: (record) => record.status !== 'approved',
            handler:   (record) => onDelete(record.id),
        },
    ];
}
