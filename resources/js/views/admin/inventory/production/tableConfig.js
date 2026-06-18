export const productionOrderColumns = [
    { title: 'SN',           key: 'sn',             width: 60 },
    { title: 'Order No',     dataIndex: 'order_no',  key: 'order_no' },
    { title: 'Product',      key: 'product' },
    { title: 'Warehouse',    key: 'warehouse',       dataIndex: ['warehouse', 'name'] },
    { title: 'Qty (Done/Planned)', key: 'qty',       width: 150 },
    { title: 'Status',       key: 'status',          width: 130 },
    { title: 'Planned Start', dataIndex: 'planned_start', key: 'planned_start', width: 130 },
    { title: 'Action',       key: 'action',          width: 130 },
];

export const STATUS_BADGE = {
    draft:       'bg-secondary',
    in_progress: 'bg-warning text-dark',
    completed:   'bg-success',
    cancelled:   'bg-danger',
};

/**
 * @param {{
 *   onView: Function,
 *   onStart: Function,
 *   onDetail: Function,
 *   onCancel: Function,
 * }} handlers
 */
export function createRowActions({ onView, onStart, onDetail, onCancel }) {
    return [
        {
            key:     'view',
            icon:    'ti-eye',
            title:   'View Detail',
            handler: (record) => onView(record),
        },
        {
            key:       'start',
            icon:      'ti-player-play',
            title:     'Start Order',
            class:     'text-success',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onStart(record.id),
        },
        {
            key:       'complete',
            icon:      'ti-check',
            title:     'Complete Order',
            class:     'text-primary',
            condition: (record) => record.status === 'in_progress',
            handler:   (record) => onDetail(record),
        },
        {
            key:       'cancel',
            icon:      'ti-x',
            title:     'Cancel Order',
            class:     'text-danger',
            condition: (record) => !['completed', 'cancelled'].includes(record.status),
            handler:   (record) => onCancel(record.id),
        },
    ];
}
