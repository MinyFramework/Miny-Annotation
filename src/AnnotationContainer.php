<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Modules\Annotation\Annotations\Attribute;
use Modules\Annotation\Annotations\Enum;
use Modules\Annotation\Exceptions\AnnotationException;

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
                                'required' => true,
                                'type'     => array()
                            )
                        ),
                        'target'           => 'annotation'
                    )
                )
        );
    }

    /**
     * @param $class
     * @return \ReflectionClass
     */
    public function getClassReflector($class)
    {
        if (!isset($this->reflectors[$class])) {
            $this->reflectors[$class] = new \ReflectionClass($class);
        }

        return $this->reflectors[$class];
    }

    /**
     * @param $class
     *
     * @throws AnnotationException
     *
     * @return AnnotationMetadata
     */
    private function readClassMetadata($class)
    {
        if (!isset($this->annotations[$class])) {
            $comment = $this->reader->readClass($class);
            if (!$comment->has('Annotation')) {
                throw new AnnotationException("Class {$class} has not been marked with @Annotation");
            }
            $metadata = new AnnotationMetadata;

            //get constructor info
            $reflector    = $this->getClassReflector($class);
            $constructor  = $reflector->getConstructor();
            $markRequired = array();
            if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
                $parameters = array();
                foreach ($constructor->getParameters() as $parameter) {
                    $parameters[] = $parameter->getName();
                    if (!$parameter->allowsNull() && !$parameter->isDefaultValueAvailable()) {
                        $markRequired[] = $parameter->getName();
                    }
                }
                $metadata->constructor = $parameters;
            }

            //@Attribute annotations
            $attributeClassName = 'Modules\\Annotation\\Annotations\\Attribute';
            if ($comment->hasAnnotationType($attributeClassName)) {
                foreach ($comment->getAnnotationType($attributeClassName) as $annotation) {
                    /** @var $annotation Attribute */
                    $metadata->attributes[$annotation->name] = $annotation->toArray();
                }
            }

            foreach ($markRequired as $attribute) {
                $metadata->attributes[$attribute]['required'] = true;
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

        return $this->annotations[$class];
    }

    /**
     * @param string $class The fully qualified class name
     * @param array  $attributes
     * @param        $target
     *
     * @throws AnnotationException
     *
     * @return object
     */
    public function readClass($class, array $attributes, $target)
    {
        $metadata = $this->readClassMetadata($class);
        $this->enforceTarget($class, $target, $metadata->target);
        $attributes = $this->filterAttributes($metadata, $attributes);

        return $this->injectAttributes($class, $attributes, $metadata);
    }

    /**
     * @param                    $class
     * @param array              $attributes
     * @param AnnotationMetadata $metadata
     *
     * @throws AnnotationException
     *
     * @return object
     */
    private function injectAttributes($class, array $attributes, $metadata)
    {
        $attributesSet = array();
        //instantiate annotation class
        if (is_array($metadata->constructor)) {
            $arguments = array();
            //$metadata->constructor has the constructor parameter names in order
            foreach ($metadata->constructor as $key) {
                if (!isset($attributes[$key])) {
                    if ($metadata->attributes[$key]['required']) {
                        throw new AnnotationException("Required parameter {$key} is not set");
                    }
                    continue;
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
                throw new AnnotationException("Attribute {$key} is required but not set");
            }
        }

        return $annotation;
    }

    /**
     * @param $class
     * @param $target
     * @param $expected
     *
     * @throws AnnotationException
     */
    private function enforceTarget($class, $target, $expected)
    {
        if ($expected === 'all') {
            return;
        }
        if ($expected !== $target) {
            if (!is_array($expected) || !in_array($target, $expected)) {
                throw new AnnotationException("Annotation {$class} can not be applied to {$target} target");
            }
        }
    }

    /**
     * @param AnnotationMetadata $metadata
     * @param                    $attributes
     *
     * @return mixed
     *
     * @throws AnnotationException
     */
    private function filterAttributes(AnnotationMetadata $metadata, $attributes)
    {
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                unset($attributes[$name]);
                $name              = $metadata->defaultAttribute;
                $attributes[$name] = $value;
            }
            if (!isset($metadata->attributes[$name])) {
                throw new AnnotationException("Unknown attribute: {$name}");
            }
            if ($value === null && $metadata->attributes[$name]['nullable']) {
                continue;
            }
            $this->checkType($name, $value, $metadata->attributes[$name]['type']);
        }

        return $attributes;
    }

    /**
     * @param $target
     *
     * @throws AnnotationException
     */
    private function checkTarget($target)
    {
        $validTargets = array('all', 'class', 'method', 'property', 'function', 'annotation');
        if (!in_array($target, $validTargets)) {
            throw new AnnotationException("Invalid target: {$target}");
        }
    }

    private function checkType($name, $value, $type)
    {
        switch ($type) {
            case 'mixed':
                break;

            case 'string':
                if (!is_string($value)) {
                    throw new AnnotationException("Attribute {$name} must be a string");
                }
                break;

            case 'int':
            case 'number':
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
                    if (!in_array($value, $type->values)) {
                        $values = implode(', ', $type->values);
                        throw new AnnotationException("Attribute {$name} must be one of the following: {$values}");
                    }
                } elseif (is_array($type)) {
                    $this->checkArrayType($name, $value, $type);
                } elseif (!$value instanceof $type) {
                    throw new AnnotationException("Attribute {$name} must be an instance of {$type}");
                }
                break;
        }
    }

    private function checkArrayType($name, $value, $type)
    {
        if (!is_array($value)) {
            throw new AnnotationException("Attribute {$name} must be an array");
        }
        $count = count($type);
        switch ($count) {
            case 0:
                break;

            case 1:
                foreach ($value as $key => $val) {
                    $this->checkType($name . '[' . $key . ']', $value[$key], $type[0]);
                }
                break;

            case count($value):
                foreach ($type as $key => $expected) {
                    $this->checkType($name . '[' . $key . ']', $value[$key], $expected);
                }
                break;

            default:
                throw new AnnotationException("Attribute {$name} must be an array with {$count} elements.");
                break;
        }
    }
}
