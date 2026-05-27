import { string } from 'yup';

export function warehouseIdForLineItem() {
    return string()
        .nullable()
        .test('warehouse-required', 'Warehouse is required for products.', function (value) {
            if (this.parent.is_service) {
                return true;
            }

            return value != null && String(value).trim() !== '';
        });
}

export function billHeaderWarehouseId(hasPhysicalItems) {
    return string()
        .nullable()
        .test('warehouse-required', 'Warehouse is required when purchasing stock products.', function () {
            if (!hasPhysicalItems()) {
                return true;
            }

            const value = this.parent.warehouse_id;

            return value != null && String(value).trim() !== '';
        });
}
