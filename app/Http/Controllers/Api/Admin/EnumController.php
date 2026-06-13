<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ChargeTypeEnum;
use App\Enums\JournalTypeEnum;
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

    public function chargeTypes()
    {
        $types = array_map(
            fn (ChargeTypeEnum $case) => ['value' => $case->value, 'label' => $case->label()],
            ChargeTypeEnum::cases(),
        );

        return response()->json(['data' => $types]);
    }
}
