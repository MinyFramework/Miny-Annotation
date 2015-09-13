<?php

namespace Annotiny\Annotations;

use Annotiny\Exceptions\AnnotationException;

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
