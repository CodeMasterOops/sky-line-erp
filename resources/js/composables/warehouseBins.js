import {apiAdmin} from '@/helpers/api.js';

export const DEFAULT_BIN_CODE = '__DEFAULT__';

export function defaultBinIdFromList(bins) {
    if (!Array.isArray(bins) || !bins.length) {
        return '';
    }
    const d = bins.find((b) => b.code === DEFAULT_BIN_CODE);
    return String((d ?? bins[0]).id);
}

export async function fetchBinsForWarehouse(warehouseId) {
    if (!warehouseId) {
        return [];
    }
    const {data} = await apiAdmin(`bin?warehouse_id=${warehouseId}`);
    return data.data ?? [];
}
