<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PartyTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Enums\CrmLeadStatusEnum;
use App\Http\Controllers\Controller;

class EnumController extends Controller
{
    public function partyTypes()
    {
        $types = collect(PartyTypeEnum::cases())->map(fn (PartyTypeEnum $t) => [
            'id' => $t->value,
            'name' => $t->label(),
        ]);

        return response()->json(['data' => $types]);
    }

    public function crmLeadStatuses()
    {
        $statuses = collect(CrmLeadStatusEnum::cases())->map(fn (CrmLeadStatusEnum $s) => [
            'id' => $s->value,
            'name' => $s->label(),
        ]);

        return response()->json(['data' => $statuses]);
    }

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
