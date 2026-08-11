<?php

namespace App\Services\Gym;

use App\Models\Unit;
use App\Models\Product;
use App\Enums\EntityCodeType;
use App\Enums\ProductTypeEnum;
use App\Models\MembershipPlan;
use App\Models\ProductVariant;
use App\Enums\DurationUnitEnum;
use App\Models\ProductCategory;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use App\Services\EntityCodeGenerator;
use App\Enums\MembershipDurationPresetEnum;
use Illuminate\Validation\ValidationException;

/**
 * Membership plans, and the service product each one bills through.
 *
 * Keeping a real `Product` behind every plan is what lets a membership invoice
 * be an ordinary invoice: price, tax linkage and revenue account all come from
 * the product, so no gym-specific accounting exists anywhere.
 */
class MembershipPlanService
{
    public function __construct(private readonly EntityCodeGenerator $codeGenerator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MembershipPlan
    {
        return DB::transaction(function () use ($data): MembershipPlan {
            $data = $this->normaliseDuration($data);

            $plan = MembershipPlan::create([
                'code' => $data['code'] ?? $this->nextCode(),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'duration_unit' => $data['duration_unit'],
                'duration_value' => $data['duration_value'],
                'preset' => $data['preset'],
                'price' => $data['price'] ?? 0,
                'joining_fee' => $data['joining_fee'] ?? 0,
                'grace_days' => $data['grace_days'] ?? 0,
                'max_freeze_days' => $data['max_freeze_days'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $plan->update(['product_id' => $this->syncProduct($plan)->id]);

            return $plan->fresh(['product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MembershipPlan $plan, array $data): MembershipPlan
    {
        return DB::transaction(function () use ($plan, $data): MembershipPlan {
            $data = $this->normaliseDuration(array_merge([
                'duration_unit' => $plan->duration_unit->value,
                'duration_value' => $plan->duration_value,
                'preset' => $plan->preset?->value,
            ], $data));

            $plan->update(array_intersect_key($data, array_flip([
                'name',
                'description',
                'duration_unit',
                'duration_value',
                'preset',
                'price',
                'joining_fee',
                'grace_days',
                'max_freeze_days',
                'is_active',
                'sort_order',
            ])));

            $this->syncProduct($plan->fresh());

            return $plan->fresh(['product']);
        });
    }

    /**
     * Plans are never hard-deleted once sold — the invoices behind past terms
     * point at them. Deactivate instead; the guard only allows a real delete
     * while the plan has never been used.
     */
    public function delete(MembershipPlan $plan): void
    {
        if ($this->hasMemberships($plan)) {
            throw ValidationException::withMessages([
                'membership_plan' => ['This plan has been sold and cannot be deleted. Deactivate it instead.'],
            ]);
        }

        $plan->delete();
    }

    public function nextCode(?int $companyId = null): string
    {
        $companyId ??= $this->companyId();

        $generate = fn (): string => $this->codeGenerator->generate(
            MembershipPlan::class,
            $companyId,
            'MPLAN-',
            'code',
            4,
        );

        return DB::transactionLevel() > 0 ? $generate() : DB::transaction($generate);
    }

    /**
     * Create or refresh the service product a plan sells through, so its price
     * and name never drift from the plan's.
     */
    public function syncProduct(MembershipPlan $plan): Product
    {
        $product = $plan->product_id
            ? Product::query()->find($plan->product_id)
            : null;

        if (! $product) {
            $product = Product::create([
                'product_category_id' => $this->membershipCategory()->id,
                'product_type' => ProductTypeEnum::SERVICE,
                'name' => $this->productName($plan),
                'code' => $this->codeGenerator->generateForType(EntityCodeType::Product, (int) $plan->company_id),
                'unit_id' => $this->membershipUnit()->id,
                'is_saleable' => true,
                'is_purchasable' => false,
                'has_variants' => false,
                'description' => $plan->description,
            ]);
        } else {
            $product->update([
                'name' => $this->productName($plan),
                'description' => $plan->description,
            ]);
        }

        $variant = ProductVariant::query()
            ->where('product_id', $product->id)
            ->orderByDesc('is_default')
            ->first();

        if ($variant) {
            $variant->update(['sales_price' => $plan->price]);
        } else {
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $plan->code,
                'sales_price' => $plan->price,
                'purchase_price' => 0,
                'is_default' => true,
            ]);
        }

        return $product;
    }

    /**
     * Fill in the duration from a preset, or mark the plan custom when the
     * caller supplied its own term.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseDuration(array $data): array
    {
        $preset = ! empty($data['preset'])
            ? MembershipDurationPresetEnum::from($data['preset'])
            : null;

        $duration = $preset?->duration();

        if ($duration) {
            $data['duration_unit'] = $duration['unit']->value;
            $data['duration_value'] = $duration['value'];
            $data['preset'] = $preset->value;

            return $data;
        }

        $unit = DurationUnitEnum::from($data['duration_unit'] ?? DurationUnitEnum::Month->value);
        $value = max(1, (int) ($data['duration_value'] ?? 1));

        $data['duration_unit'] = $unit->value;
        $data['duration_value'] = $value;
        // A hand-entered term that happens to match a standard one is still
        // labelled as that standard one.
        $data['preset'] = MembershipDurationPresetEnum::forDuration($unit, $value)->value;

        return $data;
    }

    private function productName(MembershipPlan $plan): string
    {
        return 'Membership — '.$plan->name;
    }

    private function membershipCategory(): ProductCategory
    {
        return ProductCategory::firstOrCreate(
            [
                'company_id' => $this->companyId(),
                'name' => config('provisioning.gym.product_category', 'Memberships'),
            ],
            ['description' => 'Gym membership plans sold as services.'],
        );
    }

    private function membershipUnit(): Unit
    {
        return Unit::firstOrCreate(
            ['company_id' => $this->companyId(), 'code' => 'MEM'],
            ['name' => 'Membership'],
        );
    }

    private function hasMemberships(MembershipPlan $plan): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('memberships')) {
            return false;
        }

        return DB::table('memberships')->where('membership_plan_id', $plan->id)->exists();
    }

    private function companyId(): int
    {
        $companyId = TenantService::companyId() ?? auth('admin')->user()?->company_id;

        if (! $companyId) {
            throw ValidationException::withMessages([
                'company' => ['Company context is not available.'],
            ]);
        }

        return (int) $companyId;
    }
}
