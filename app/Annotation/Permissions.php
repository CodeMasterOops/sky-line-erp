<?php

namespace App\Annotation;

/**
 * Class Permissions
 *
 * @Annotation
 */
class Permissions
{
    /**
     * @var string
     */
    public $value;

    /**
     * @var string
     */
    public $desc;

    /**
     * @var string
     */
    public $group;

    /**
     * @var array
     */
    public $child = [];

    /**
     * @var string
     */
    public $parent;

    /**
     * @var string
     */
    public $route;

    /**
     * @return string
     */
    public function getDesc()
    {
        return $this->desc;
    }

    /**
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @return array
     */
    public function getChild()
    {
        return $this->child;
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @return $this;
     */
    public function setValue(string $value)
    {
        $this->value = $value;

        return $this;
    }
}
