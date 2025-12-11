<?php

namespace App\Tenancy;

use Illuminate\Validation\Rules\In;
use Illuminate\Validation\NestedRules;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Validation\Rules\ExcludeIf;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\ProhibitedIf;

class TRule
{
    use Macroable;

    public static function when($condition, $rules, $defaultRules = []): ConditionalRules
    {
        return new ConditionalRules($condition, $rules, $defaultRules);
    }

    public static function dimensions(array $constraints = []): Dimensions
    {
        return new Dimensions($constraints);
    }

    public static function exists($table, $column = 'NULL'): TenantExists
    {
        return new TenantExists($table, $column);
    }

    public static function in($values): In
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new In(is_array($values) ? $values : func_get_args());
    }

    public static function notIn($values): NotIn
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        return new NotIn(is_array($values) ? $values : func_get_args());
    }

    public static function forEach($callback): NestedRules
    {
        return new NestedRules($callback);
    }

    public static function requiredIf($callback): RequiredIf
    {
        return new RequiredIf($callback);
    }

    public static function excludeIf($callback): ExcludeIf
    {
        return new ExcludeIf($callback);
    }

    public static function prohibitedIf($callback): ProhibitedIf
    {
        return new ProhibitedIf($callback);
    }

    public static function unique($table, $column = 'NULL'): TenantUnique
    {
        return new TenantUnique($table, $column);
    }
}
