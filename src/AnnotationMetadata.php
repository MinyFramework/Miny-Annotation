<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

class AnnotationMetadata
{
    public $constructor = false;
    public $target = 'class';
    public $defaultAttribute;
    public $attributes = array();

    public static function create(array $properties)
    {
        $object = new AnnotationMetadata;
        foreach ($properties as $property => $value) {
            $object->$property = $value;
        }

        return $object;
    }
}
