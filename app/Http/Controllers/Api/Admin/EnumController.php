<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GenderEnum;
use App\Enums\PartyTypeEnum;
use App\Enums\BloodGroupEnum;
use App\Enums\TaskStatusEnum;
use App\Enums\JournalTypeEnum;
use App\Enums\TdsCategoryEnum;
use App\Enums\TaskPriorityEnum;
use App\Enums\CrmLeadStatusEnum;
use App\Enums\FollowUpStatusEnum;
use App\Enums\FollowUpChannelEnum;
use App\Http\Controllers\Controller;

class EnumController extends Controller
{
    public function genders()
    {
        return response()->json(['data' => GenderEnum::options()]);
    }

    public function bloodGroups()
    {
        return response()->json(['data' => BloodGroupEnum::options()]);
    }

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

    public function taskStatuses()
    {
        $statuses = collect(TaskStatusEnum::cases())->map(fn (TaskStatusEnum $s) => [
            'id' => $s->value,
            'name' => $s->label(),
        ]);

        return response()->json(['data' => $statuses]);
    }

    public function taskPriorities()
    {
        $priorities = collect(TaskPriorityEnum::cases())->map(fn (TaskPriorityEnum $p) => [
            'id' => $p->value,
            'name' => $p->label(),
        ]);

        return response()->json(['data' => $priorities]);
    }

    public function followUpChannels()
    {
        $channels = collect(FollowUpChannelEnum::cases())->map(fn (FollowUpChannelEnum $c) => [
            'id' => $c->value,
            'name' => $c->label(),
        ]);

        return response()->json(['data' => $channels]);
    }

    public function followUpStatuses()
    {
        $statuses = collect(FollowUpStatusEnum::cases())->map(fn (FollowUpStatusEnum $s) => [
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
