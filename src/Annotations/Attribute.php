<?php

namespace Modules\Annotation\Annotations;

class Attribute
{
    public $name;
    public $type = 'mixed';
    public $arrayType = 'mixed';
    public $setter;
    public $nullable = false;
    public $required = false;
}
