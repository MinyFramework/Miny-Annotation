<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation\Annotations;

use Modules\Annotation\Exceptions\AnnotationException;

class Enum
{
    public $values;

    public function checkValue($name, $value)
    {
        if (!in_array($value, $this->values)) {
            $values = implode(', ', $this->values);
            throw new AnnotationException("Attribute {$name} must be one of the following: {$values}");
        }
    }
}
