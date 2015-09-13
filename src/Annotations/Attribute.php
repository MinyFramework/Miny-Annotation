<?php

namespace Annotiny\Annotations;

use Annotiny\Exceptions\AnnotationException;

class Attribute
{
    public static function checkType($name, $value, $type)
    {
        switch ($type) {
            case 'mixed':
                break;

            case 'string':
                if (!is_string($value)) {
                    throw new AnnotationException("Attribute {$name} must be a string");
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    throw new AnnotationException("Attribute {$name} must be a number or numeric string");
                }
                break;

            case 'int':
            case 'integer':
                if (!is_int($value)) {
                    throw new AnnotationException("Attribute {$name} must be an integer");
                }
                break;

            case 'float':
                if (!is_float($value)) {
                    throw new AnnotationException("Attribute {$name} must be a floating point number");
                }
                break;

            case 'bool':
            case 'boolean':
                if (!is_bool($value)) {
                    throw new AnnotationException("Attribute {$name} must be a boolean");
                }
                break;

            default:
                if ($type instanceof Enum) {
                    $type->checkValue($name, $value);
                } else if (is_array($type)) {
                    self::checkArrayType($name, $value, $type);
                } else if (!$value instanceof $type) {
                    throw new AnnotationException("Attribute {$name} must be an instance of {$type}");
                }
                break;
        }
    }

    private static function checkArrayType($name, array $value, $type)
    {
        $count = count($type);
        switch ($count) {
            case 0:
                break;

            case 1:
                foreach ($value as $key => $val) {
                    self::checkType("{$name}[{$key}]", $value[ $key ], $type[0]);
                }
                break;

            case count($value):
                foreach ($type as $key => $expected) {
                    self::checkType("{$name}[{$key}]", $value[ $key ], $expected);
                }
                break;

            default:
                throw new AnnotationException("Attribute {$name} must be an array with {$count} elements.");
        }
    }

    private static $defaults = [
        'required' => false,
        'type'     => 'mixed',
        'setter'   => null,
        'nullable' => false,
        'default'  => null
    ];

    public static function getDefaults()
    {
        return static::$defaults;
    }

    public $name;
    public $type     = 'mixed';
    public $setter;
    public $nullable = false;
    public $required = false;
    public $default;

    public function toArray()
    {
        return [
            'required' => $this->required,
            'type'     => $this->type,
            'setter'   => $this->setter,
            'nullable' => $this->nullable,
            'default'  => $this->default
        ];
    }
}
