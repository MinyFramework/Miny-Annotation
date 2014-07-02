<?php

namespace Modules\Annotation\Annotations;

class Attribute
{
    public $name;
    public $type = 'mixed';
    public $setter;
    public $nullable = false;
    public $required = false;

    public function toArray()
    {
        return array(
            'required' => $this->required,
            'type'     => $this->type,
            'setter'   => $this->setter,
            'nullable' => $this->nullable
        );
    }
}
