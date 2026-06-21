<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\StockReservation;
use Illuminate\Database\Eloquent\Model;

class StockReservationService
{
    public function __construct(private StockQuantityService $quantities) {}

    /**
     * Reserve stock for each item, incrementing on_hold on the stocks row.
     * Must be called inside a DB::transaction().
     *
     * @param  array<int, array{product_variant_id: int, warehouse_id: int, quantity: int}>  $items
     */
    public function reserve(Company $company, Model $reservable, array $items, int $userId): void
    {
        foreach ($items as $item) {
            $variantId = (int) $item['product_variant_id'];
            $warehouseId = (int) $item['warehouse_id'];
            $qty = (float) $item['quantity'];

            if ($qty <= 0) {
                continue;
            }

            $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $warehouseId);
            $this->quantities->holdOnHand($company->id, $variantId, $warehouseId, $qty);

            StockReservation::create([
                'company_id' => $company->id,
                'product_variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
                'reservable_type' => $reservable->getMorphClass(),
                'reservable_id' => $reservable->getKey(),
                'quantity' => $qty,
                'reserved_at' => now(),
                'reserved_by' => $userId,
            ]);
        }
    }

    /**
     * Release all open reservations for a reservable document, decrementing on_hold.
     * Must be called inside a DB::transaction().
     */
    public function release(Company $company, Model $reservable): void
    {
        $reservations = StockReservation::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('reservable_type', $reservable->getMorphClass())
            ->where('reservable_id', $reservable->getKey())
            ->whereNull('released_at')
            ->get();

        foreach ($reservations as $reservation) {
            $this->quantities->releaseOnHold(
                $company->id,
                $reservation->product_variant_id,
                $reservation->warehouse_id,
                $reservation->quantity,
            );

            $reservation->update(['released_at' => now()]);
        }
    }
}
