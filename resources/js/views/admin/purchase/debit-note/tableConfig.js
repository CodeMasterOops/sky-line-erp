export const debitNoteColumns = [
    { title: 'SN',               key: 'sn',                width: 60 },
    { title: 'Debit Note No',    dataIndex: 'debit_note_no',   sorter: true },
    { title: 'Debit Note Date',  dataIndex: 'debit_note_date', sorter: true },
    { title: 'Supplier',         dataIndex: 'party_name',      sorter: true },
    { title: 'Grand Total',      dataIndex: 'grand_total',     sorter: true },
    { title: 'Status',           key: 'status' },
    { title: 'Action',           key: 'action' },
];

/**
 * @param {{
 *   onView: Function,
 *   onEdit: Function,
 *   onApprove: Function,
 *   onVoid: Function,
 *   onDelete: Function,
 *   canViewDebitNote?: () => boolean,
 *   canVoidDebitNote?: () => boolean,
 * }} handlers
 */
export function createRowActions({
    onView,
    onEdit,
    onApprove,
    onVoid,
    onDelete,
    canViewDebitNote = () => true,
    canVoidDebitNote = () => true,
}) {
    return [
        {
            key:       'view',
            icon:      'ti-eye',
            title:     'View',
            condition: () => canViewDebitNote(),
            handler:   (record) => onView(record.id),
        },
        {
            key:       'edit',
            icon:      'ti-edit',
            title:     'Edit',
            class:     'edit-icon',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onEdit(record.id),
        },
        {
            key:       'approve',
            icon:      'ti-check',
            title:     'Approve',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onApprove(record.id),
        },
        {
            key:       'void',
            icon:      'ti-ban',
            title:     'Void',
            class:     'text-warning',
            condition: (record) =>
                canVoidDebitNote() && record.status === 'approved' && !record.voided_at,
            handler: (record) => onVoid(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
