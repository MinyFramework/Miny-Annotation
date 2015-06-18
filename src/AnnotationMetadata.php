<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Modules\Annotation\Annotations\Target;

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
