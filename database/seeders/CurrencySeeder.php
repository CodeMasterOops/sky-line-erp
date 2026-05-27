<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->where('is_base', true)->update(['is_base' => false]);

        Currency::query()->updateOrCreate(
            ['code' => 'NPR'],
            [
                'name' => 'Nepalese Rupee',
                'symbol' => 'Rs.',
                'exchange_rate' => 1,
                'is_base' => true,
                'is_active' => true,
                'rate_date' => now()->toDateString(),
            ]
        );
    }
}
