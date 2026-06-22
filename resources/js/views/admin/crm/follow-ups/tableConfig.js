export const followUpColumns = [
    { title: "SN",        key: "sn",                  width: 60 },
    { title: "Contact",   dataIndex: "party_name" },
    { title: "Channel",   dataIndex: "channel_label" },
    { title: "Scheduled", key: "scheduled" },
    { title: "Status",    key: "status" },
    { title: "Owner",     dataIndex: "user_name" },
    { title: "Action",    key: "action", align: "center" },
];

/**
 * @param {{ onEdit: Function, onComplete: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onEdit, onComplete, onDelete }) {
    return [
        {
            key:       'complete',
            icon:      'ti-circle-check',
            title:     'Mark complete',
            class:     'edit-icon',
            condition: (record) => record.status === 'pending',
            handler:   (record) => onComplete(record),
        },
        {
            key:       'edit',
            icon:      'ti-edit',
            title:     'Edit',
            condition: (record) => record.status === 'pending',
            handler:   (record) => onEdit(record),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}

export const STATUS_BADGE = {
    pending: 'badge bg-outline-warning',
    done:    'badge bg-outline-success',
    missed:  'badge bg-outline-danger',
};
