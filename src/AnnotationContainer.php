<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Modules\Annotation\Annotations\Enum;

class AnnotationContainer
{
    /**
     * @var AnnotationMetadata[]
     */
    private $annotations;

    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * @var \ReflectionClass[]
     */
    private $reflectors = array();

    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;

        $this->annotations = array(
            'Modules\\Annotation\\Annotations\\Attribute' => AnnotationMetadata::create(
                    array(
                        'defaultAttribute' => 'name',
                        'attributes'       => array(
                            'name'     => array(
                                'required' => true,
                                'type'     => 'string'
                            ),
                            'setter'   => array(
                                'required' => false,
                                'type'     => 'string'
                            ),
                            'type'     => array(
                                'required' => false,
                                'type'     => 'mixed'
                            ),
                            'nullable' => array(
                                'required' => false,
                                'type'     => 'bool'
                            ),
                            'required' => array(
                                'required' => false,
                                'type'     => 'bool'
                            )
                        ),
                        'target'           => 'class'
                    )
                ),
            'Modules\\Annotation\\Annotations\\Enum'      => AnnotationMetadata::create(
                    array(
                        'defaultAttribute' => 'values',
                        'attributes'       => array(
                            'values' => array(
                                'required'   => true,
                                'type'       => 'array',
                                'array_type' => 'mixed'
                            )
                        ),
                        'target'           => 'parameter'
                    )
                )
        );
    }

    /**
     * @param string $class The fully qualified class name
     * @param array  $attributes
     * @param        $target
     *
     * @throws \InvalidArgumentException
     *
     * @return object
     */
    public function readClass($class, array $attributes, $target)
    {
        if (!isset($this->annotations[$class])) {
            $this->readClassMetadata($class);
        }
        $metadata = $this->annotations[$class];
        if ($metadata->target !== $target) {
            if (!is_array($metadata->target) || !in_array($target, $metadata->target)) {
                throw new \InvalidArgumentException("Annotation {$class} can not be applied to {$target} target");
            }
        }
        foreach ($attributes as $key => $attribute) {
            if (!is_string($key)) {
                $attributes[$metadata->defaultAttribute] = $attribute;
                unset($attributes[$key]);
            }
        }
        $this->checkAttributeTypes($metadata, $attributes);
        $attributesSet = array();
        if (is_array($metadata->constructor)) {
            $arguments = array();
            foreach ($metadata->constructor as $key) {
                if (!isset($attributes[$key]) && $metadata->attributes[$key]['required'] === true) {
                    throw new \InvalidArgumentException("Required parameter {$key} is not set");
                }
                $attributesSet[$key] = true;
                $arguments[$key]     = $attributes[$key];
                unset($attributes[$key]);
            }
            $reflector  = $this->getClassReflector($class);
            $annotation = $reflector->newInstanceArgs($arguments);
        } else {
            $annotation = new $class;
        }
        foreach ($attributes as $key => $value) {
            if (!isset($metadata->attributes[$key])) {
                continue;
            }
            $attributesSet[$key] = true;
            if (isset($metadata->attributes[$key]['setter'])) {
                $setter = $metadata->attributes[$key]['setter'];
                $annotation->$setter($value);
            } else {
                $annotation->$key = $value;
            }
        }
        foreach ($metadata->attributes as $key => $data) {
            if ($data['required'] && !isset($attributesSet[$key])) {
                throw new \InvalidArgumentException("Attribute {$key} is required but not set");
            }
        }

        return $annotation;
    }

    /**
     * @param $class
     * @throws \UnexpectedValueException
     */
    private function readClassMetadata($class)
    {
        $comment = $this->reader->readClass($class);
        if (!$comment->has('Annotation')) {
            throw new \UnexpectedValueException("Class {$class} has not been marked with @Annotation");
        }
        $metadata = new AnnotationMetadata;

        //get constructor info
        $reflector   = $this->getClassReflector($class);
        $constructor = $reflector->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
            $parameters = array();
            foreach ($constructor->getParameters() as $parameter) {
                $parameters[] = $parameter->getName();
            }
            $metadata->constructor = $parameters;
        }

        //@Attribute annotations
        $attributeClassName = 'Modules\\Annotation\\Annotations\\Attribute';
        if ($comment->hasAnnotationType($attributeClassName)) {
            foreach ($comment->getAnnotationType($attributeClassName) as $annotation) {
                $metadata->attributes[$annotation->name] = array(
                    'required'   => $annotation->required,
                    'type'       => $annotation->type,
                    'array_type' => $annotation->arrayType,
                    'setter'     => $annotation->setter,
                    'nullable'   => $annotation->nullable
                );
            }
        }

        //@Target
        if ($comment->has('Target')) {
            $target = $comment->get('Target');
            if (is_array($target)) {
                foreach ($target as $tg) {
                    $this->checkTarget($tg);
                }
            } else {
                $this->checkTarget($target);
            }
            $metadata->target = $target;
        }

        //@DefaultAttribute
        if ($comment->has('DefaultAttribute')) {
            $metadata->defaultAttribute = $comment->get('DefaultAttribute');
        }

        $this->annotations[$class] = $metadata;
    }

    /**
     * @param $class
     * @return \ReflectionClass
     */
    private function getClassReflector($class)
    {
        if (!isset($this->reflectors[$class])) {
            $this->reflectors[$class] = new \ReflectionClass($class);
        }

        return $this->reflectors[$class];
    }

    private function checkAttributeTypes(AnnotationMetadata $metadata, $attributes)
    {
        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[$name])) {
                throw new \InvalidArgumentException("Unknown attribute: {$name}");
            }
            if ($value === null && $metadata->attributes[$name]['nullable']) {
                continue;
            }
            if ($metadata->attributes[$name]['type'] === 'array') {
                if (!is_array($value)) {
                    throw new \InvalidArgumentException("Attribute {$name} must be an array");
                }
                $arrayType = $metadata->attributes[$name]['array_type'];
                if ($arrayType !== 'mixed') {
                    foreach ($value as $subValue) {
                        if ($value === null && $metadata->attributes[$name]['nullable']) {
                            continue;
                        }
                        $this->checkType($name, $subValue, $arrayType);
                    }
                }
            } else {
                $this->checkType($name, $value, $metadata->attributes[$name]['type']);
            }
        }
    }

    /**
     * @param $target
     * @throws \UnexpectedValueException
     */
    private function checkTarget($target)
    {
        if (!in_array($target, array('class', 'method', 'property', 'function'))) {
            throw new \UnexpectedValueException("Invalid target: {$target}");
        }
    }

    private function checkType($name, $value, $type)
    {
        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    throw new \InvalidArgumentException("Attribute {$name} must be a string");
                }
                break;
            case 'int':
            case 'number':
                if (!is_int($value)) {
                    throw new \InvalidArgumentException("Attribute {$name} must be an integer");
                }
                break;
            case 'float':
                if (!is_float($value)) {
                    throw new \InvalidArgumentException("Attribute {$name} must be a floating point number");
                }
                break;
            case 'bool':
            case 'boolean':
                if (!is_bool($value)) {
                    throw new \InvalidArgumentException("Attribute {$name} must be a boolean");
                }
                break;
            case 'mixed':
                break;
            default:
                if ($type instanceof Enum) {
                    if (!in_array($value, $type->values)) {
                        $values = implode(', ', $type->values);
                        throw new \InvalidArgumentException("Attribute {$name} must be one of the following: {$values}");
                    }
                } elseif (is_array($type)) {
                    if (!is_array($value)) {
                        throw new \InvalidArgumentException("Attribute {$name} must be an array");
                    }
                    foreach ($type as $key => $expected) {
                        $this->checkType($name . '[' . $key . ']', $value[$key], $expected);
                    }
                } elseif (!$value instanceof $type) {
                    throw new \InvalidArgumentException("Attribute {$name} must be an instance of {$type}");
                }
                break;
        }
    }
}
