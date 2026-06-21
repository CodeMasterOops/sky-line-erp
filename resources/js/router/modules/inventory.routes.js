export default [
    {
        path: "brand",
        name: "admin.brand-list",
        meta: {
            pageTitle: "Brand List",
        },
        component: () => import("@/views/admin/inventory/brand/Index.vue"),
    },
    {
        path: "unit",
        name: "admin.unit-list",
        meta: {
            pageTitle: "Unit List",
        },
        component: () => import("@/views/admin/inventory/unit/Index.vue"),
    },
    {
        path: "warehouse",
        name: "admin.warehouse-list",
        meta: {
            pageTitle: "Warehouse List",
        },
        component: () => import("@/views/admin/inventory/warehouse/Index.vue"),
    },
    {
        path: "stock-transfer",
        name: "admin.stock-transfer",
        meta: {
            pageTitle: "Stock Transfer",
        },
        component: () => import("@/views/admin/inventory/stock-transfer/Index.vue"),
    },
    {
        path: "stock-adjustment",
        name: "admin.stock-adjustment",
        meta: {
            pageTitle: "Stock Adjustment",
        },
        component: () => import("@/views/admin/inventory/stock-adjustment/Index.vue"),
    },
    {
        path: "opening-stock-entry",
        name: "admin.opening-stock-entry",
        meta: {
            pageTitle: "Opening Stock",
        },
        component: () => import("@/views/admin/inventory/opening-stock-entry/Index.vue"),
    },
    {
        path: "stock-reconciliation",
        name: "admin.stock-reconciliation",
        meta: {
            pageTitle: "Stock Reconciliation",
        },
        component: () => import("@/views/admin/inventory/stock-reconciliation/Index.vue"),
    },
    {
        path: "product-category",
        name: "admin.product-category-list",
        meta: {
            pageTitle: "Product Category",
        },
        component: () => import("@/views/admin/inventory/product-category/Index.vue"),
    },
    {
        path: "product/create",
        name: "admin.product-create",
        meta: {
            pageTitle: "Add Product",
        },
        component: () => import("@/views/admin/inventory/product/Create.vue"),
    },
    {
        path: "product/:id/edit",
        name: "admin.product-edit",
        meta: {
            pageTitle: "Edit Product",
        },
        component: () => import("@/views/admin/inventory/product/Edit.vue"),
    },
    {
        path: "product",
        name: "admin.product-list",
        meta: {
            pageTitle: "Product List",
        },
        component: () => import("@/views/admin/inventory/product/Index.vue"),
    },
    {
        path: "variant-attributes",
        name: "admin.variant-attributes",
        meta: {
            pageTitle: "Variant Attributes",
        },
        component: () => import("@/views/admin/inventory/attribute/Index.vue"),
    },
    {
        path: "barcode",
        name: "admin.barcode",
        meta: {
            pageTitle: "Print Barcode",
        },
        component: () => import("@/views/admin/inventory/barcode/Index.vue"),
    },
    {
        path: "grn",
        name: "admin.grn-list",
        meta: {pageTitle: "Goods Received Notes"},
        component: () => import("@/views/admin/inventory/grn/Index.vue"),
    },
    {
        path: "grn/create",
        name: "admin.grn-create",
        meta: {pageTitle: "Create GRN"},
        component: () => import("@/views/admin/inventory/grn/Create.vue"),
    },
    {
        path: "grn/:id",
        name: "admin.grn-view",
        meta: {pageTitle: "GRN Detail"},
        component: () => import("@/views/admin/inventory/grn/View.vue"),
    },
    {
        path: "delivery-challans",
        name: "admin.delivery-challan-list",
        meta: {pageTitle: "Delivery Challans"},
        component: () => import("@/views/admin/inventory/delivery-challan/Index.vue"),
    },
    {
        path: "delivery-challans/create",
        name: "admin.delivery-challan-create",
        meta: {pageTitle: "Create Delivery Challan"},
        component: () => import("@/views/admin/inventory/delivery-challan/Create.vue"),
    },
    {
        path: "delivery-challans/:id",
        name: "admin.delivery-challan-view",
        meta: {pageTitle: "Delivery Challan Detail"},
        component: () => import("@/views/admin/inventory/delivery-challan/View.vue"),
    },
    {
        path: "inventory-valuation",
        name: "admin.inventory-valuation",
        meta: {pageTitle: "Inventory Valuation"},
        component: () => import("@/views/admin/inventory/reports/InventoryValuation.vue"),
    },
    {
        path: "stock-aging",
        name: "admin.stock-aging",
        meta: {pageTitle: "Stock Aging"},
        component: () => import("@/views/admin/inventory/reports/StockAging.vue"),
    },
    {
        path: "reorder-alerts",
        name: "admin.reorder-alerts",
        meta: {pageTitle: "Reorder Alerts"},
        component: () => import("@/views/admin/inventory/reports/ReorderAlerts.vue"),
    },
    {
        path: "stock-movement",
        name: "admin.stock-movement-report",
        meta: {pageTitle: "Stock Movement Report"},
        component: () => import("@/views/admin/inventory/reports/StockMovement.vue"),
    },
    {
        path: "stock-ledger",
        name: "admin.stock-ledger-report",
        meta: {pageTitle: "Stock Ledger Report"},
        component: () => import("@/views/admin/inventory/reports/StockLedger.vue"),
    },
    {
        path: "warehouse-stock",
        name: "admin.warehouse-stock-report",
        meta: {pageTitle: "Warehouse Wise Stock Report"},
        component: () => import("@/views/admin/inventory/reports/WarehouseStock.vue"),
    },
    {
        path: "warehouse-transfer-report",
        name: "admin.warehouse-transfer-report",
        meta: {pageTitle: "Warehouse Transfer Report"},
        component: () => import("@/views/admin/inventory/reports/WarehouseTransfer.vue"),
    },
    {
        path: "expiry-stock",
        name: "admin.expiry-stock-report",
        meta: {pageTitle: "Expiry Stock Report"},
        component: () => import("@/views/admin/inventory/reports/ExpiryStock.vue"),
    },
    {
        path: "dead-stock",
        name: "admin.dead-stock-report",
        meta: {pageTitle: "Dead Stock Report"},
        component: () => import("@/views/admin/inventory/reports/DeadStock.vue"),
    },
    {
        path: "stock-opening-report",
        name: "admin.stock-opening-report",
        meta: {pageTitle: "Stock Opening Report"},
        component: () => import("@/views/admin/inventory/reports/StockOpening.vue"),
    },
    {
        path: "inventory-summary-report",
        name: "admin.inventory-summary-report",
        meta: {pageTitle: "Inventory Summary Report"},
        component: () => import("@/views/admin/inventory/reports/InventorySummary.vue"),
    },
    {
        path: "damage-stock-report",
        name: "admin.damage-stock-report",
        meta: {pageTitle: "Damage Stock Report"},
        component: () => import("@/views/admin/inventory/reports/DamageStockReport.vue"),
    },
    {
        path: "production-variance-report",
        name: "admin.production-variance-report",
        meta: {pageTitle: "Production Variance Report"},
        component: () => import("@/views/admin/inventory/reports/ProductionVarianceReport.vue"),
    },
    {
        path: "mrp-plan-report",
        name: "admin.mrp-plan-report",
        meta: {pageTitle: "Material Requirements Plan"},
        component: () => import("@/views/admin/inventory/reports/MrpPlanReport.vue"),
    },
    {
        path: "serial-numbers",
        name: "admin.serial-numbers",
        meta: {pageTitle: "Serial / Lot Numbers"},
        component: () => import("@/views/admin/inventory/serial-numbers/Index.vue"),
    },
    {
        path: "damage-reports",
        name: "admin.damage-report-list",
        meta: {pageTitle: "Damage Reports"},
        component: () => import("@/views/admin/inventory/damage-report/Index.vue"),
    },
    {
        path: "batch-stock-report",
        name: "admin.batch-stock-report",
        meta: {pageTitle: "Batch Stock Report"},
        component: () => import("@/views/admin/inventory/reports/BatchStock.vue"),
    },
    {
        path: "batch-traceability-report",
        name: "admin.batch-traceability-report",
        meta: {pageTitle: "Batch Traceability Report"},
        component: () => import("@/views/admin/inventory/reports/BatchTraceability.vue"),
    },
    {
        path: "inventory/batches",
        name: "admin.batch-list",
        meta: {pageTitle: "Batch / Lot Tracking"},
        component: () => import("@/views/admin/inventory/batch/Index.vue"),
    },
    {
        path: "inventory/batches/create",
        name: "admin.batch-create",
        meta: {pageTitle: "Record New Batch"},
        component: () => import("@/views/admin/inventory/batch/Create.vue"),
    },
    {
        path: "inventory/batches/:id/edit",
        name: "admin.batch-edit",
        meta: {pageTitle: "Edit Batch"},
        component: () => import("@/views/admin/inventory/batch/Edit.vue"),
    },
    {
        path: "inventory/bom",
        name: "admin.bom-list",
        meta: {pageTitle: "Bill of Materials"},
        component: () => import("@/views/admin/inventory/bom/Index.vue"),
    },
    {
        path: "inventory/production-orders",
        name: "admin.production-order-list",
        meta: {pageTitle: "Production Orders"},
        component: () => import("@/views/admin/inventory/production/Index.vue"),
    },
];
