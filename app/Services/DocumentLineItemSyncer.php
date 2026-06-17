<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eliminates the duplicate item-create loop that appears in every document service
 * (InvoiceService, PurchaseBillService, CreditNoteService, etc.).
 *
 * Usage:
 *   DocumentLineItemSyncer::sync(
 *       $invoice->invoiceItems(),
 *       $formData['items'] ?? [],
 *       fn ($item) => ['product_variant_id' => ..., ...],
 *       fn ($item) => $this->resolveItemTax($item, ...),  // optional preprocessor
 *   );
 */
class DocumentLineItemSyncer
{
    /**
     * Create line items on a document from the given array.
     *
     * @param  HasMany  $relationship  The document's item relationship (e.g. invoiceItems()).
     * @param  array  $items  Raw item data from the request.
     * @param  callable(array): array  $attributeMapper  Returns the DB column map for a single item.
     * @param  callable(array): array|null  $preprocessor  Optional per-item transform applied before mapping (e.g. tax resolution).
     */
    public static function sync(
        HasMany $relationship,
        array $items,
        callable $attributeMapper,
        ?callable $preprocessor = null,
    ): void {
        foreach ($items as $item) {
            if ($preprocessor !== null) {
                $item = $preprocessor($item);
            }

            $docItem = $relationship->create($attributeMapper($item));

            static::applyLineDiscount($item, $docItem);
        }
    }

    /**
     * Apply a per-line discount if the relevant fields are present on the item.
     * Safe to call when fields are always present (Purchase services) or optional (Sales services).
     */
    public static function applyLineDiscount(array $item, Model $docItem): void
    {
        if (! isset($item['line_discount_type']) && ! isset($item['line_discount_value'])) {
            return;
        }

        $docItem->saveDiscount(
            $item['line_discount_type'] ?? 'fixed',
            isset($item['line_discount_value']) ? (float) $item['line_discount_value'] : null,
            $item['discount_amount'] ?? 0,
        );
    }
}
