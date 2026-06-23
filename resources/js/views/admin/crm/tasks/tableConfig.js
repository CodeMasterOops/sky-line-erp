export const taskColumns = [
    { title: "SN",        key: "sn",              width: 60 },
    { title: "Title",     dataIndex: "title",     sorter: true },
    { title: "Priority",  key: "priority" },
    { title: "Status",    key: "status" },
    { title: "Assignee",  dataIndex: "assigned_to_name" },
    { title: "Due Date",  dataIndex: "due_date" },
    { title: "Linked To", dataIndex: "party_name" },
    { title: "Action",    key: "action", align: "center" },
];

const CLOSED_STATUSES = ['done', 'cancelled'];

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
            condition: (record) => !CLOSED_STATUSES.includes(record.status),
            handler:   (record) => onComplete(record),
        },
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            handler: (record) => onEdit(record),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}

export const PRIORITY_BADGE = {
    low:    'badge bg-outline-secondary',
    medium: 'badge bg-outline-info',
    high:   'badge bg-outline-danger',
};

export const STATUS_BADGE = {
    open:        'badge bg-outline-primary',
    in_progress: 'badge bg-outline-warning',
    done:        'badge bg-outline-success',
    cancelled:   'badge bg-outline-secondary',
};
