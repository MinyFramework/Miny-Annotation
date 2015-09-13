<?php

namespace Annotiny;

use Annotiny\Annotations\Target;

class AnnotationMetadata
{
    public $constructor = false;
    public $target      = Target::TARGET_CLASS;
    public $defaultAttribute;
    public $attributes  = [];

    public static function create(array $properties)
    {
        $object = new AnnotationMetadata;
        foreach ($properties as $property => $value) {
            $object->$property = $value;
        }

        return $object;
    }
}
