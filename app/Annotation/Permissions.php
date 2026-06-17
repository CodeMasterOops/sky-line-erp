<?php

namespace App\Annotation;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Permissions
{
    public function __construct(
        public readonly string $value,
        public readonly string $group = '',
        public readonly string $desc = '',
        public readonly string $route = '',
        public readonly string $parent = '',
        /** @var string[] */
        public readonly array $child = [],
    ) {}

    public function getDesc(): string
    {
        return $this->desc;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getChild(): array
    {
        return $this->child;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
