<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JournalTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Http\Controllers\Controller;

class EnumController extends Controller
{
    public function journalTypes()
    {
        $journalTypes = JournalTypeEnum::typeList();

        return response()->json([
            'data' => $journalTypes,
        ]);
    }

    public function tdsCategories()
    {
        $categories = collect(TdsCategoryEnum::cases())->map(fn (TdsCategoryEnum $c) => [
            'id' => $c->value,
            'name' => $c->label(),
            'rate' => $c->rate(),
        ]);

        return response()->json(['data' => $categories]);
    }
}
