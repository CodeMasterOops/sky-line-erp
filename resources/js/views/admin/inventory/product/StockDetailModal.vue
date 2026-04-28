<template>
    <teleport to="body">
        <div v-if="product" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true"
            style="background-color: rgba(0, 0, 0, 0.45);" @click.self="close">
            <div class="modal-dialog modal-dialog-centered" role="document" @click.stop>
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Stock by warehouse</h5>
                        <button type="button" class="btn-close" aria-label="Close" @click="close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-2">{{ product.name }}</p>
                        <div v-if="rows.length" class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>Warehouse</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Inventory value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in rows" :key="row.warehouse_id">
                                        <td>{{ row.warehouse_name || ('#' + row.warehouse_id) }}</td>
                                        <td class="text-end">{{ row.quantity }}</td>
                                        <td class="text-end">{{ formatMoney(row.inventory_value) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="product.total_inventory_value != null"
                                class="text-end small text-muted mb-0 mt-2">
                                Total inventory value:
                                <strong>{{ formatMoney(product.total_inventory_value) }}</strong>
                            </p>
                        </div>
                        <p v-else class="text-muted mb-0">
                            No on-hand stock for this product. Receive inventory with an approved bill (or use stock
                            adjustment).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed } from 'vue';
import { formatMoney } from '@/helpers/formatMoney.js';

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:product']);

const rows = computed(() => props.product?.stock_by_warehouse ?? []);

function close() {
    emit('update:product', null);
}
</script>
