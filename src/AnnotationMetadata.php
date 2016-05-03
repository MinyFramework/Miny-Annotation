<?php

namespace Annotiny;

use Annotiny\Annotations\Attribute;
use Annotiny\Annotations\Target;

class AnnotationMetadata
{
    public $constructor;
    public $target;
    public $defaultAttribute;

    /**
     * @var Attribute[]
     */
    public $attributes = [];

    public function __construct($defaultAttribute = null, $target = Target::TARGET_CLASS, $constructor = false)
    {
        $this->defaultAttribute = $defaultAttribute;
        $this->target           = $target;
        $this->constructor      = $constructor;
    }

    public function addAttribute($name, Attribute $attribute)
    {
        $this->attributes[ $name ] = $attribute;

        return $this;
    }
}
