<?php

namespace App\Http\Controllers\Api\Admin;

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
}
