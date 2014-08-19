<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Modules\Annotation\Annotations\Attribute;
use Modules\Annotation\Annotations\Target;
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
                        'target'           => Target::TARGET_CLASS
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
                        'target'           => Target::TARGET_ANNOTATION
                    )
                ),
            'Modules\\Annotation\\Annotations\\Target'    => AnnotationMetadata::create(
                    array(
                        'defaultAttribute' => 'target',
                        'constructor'      => array(
                            'target'
                        ),
                        'attributes'       => array(
                            'target' => array(
                                'required' => true,
                                'type'     => 'mixed'
                            )
                        ),
                        'target'           => Target::TARGET_CLASS
                    )
                )
        );
    }

    public function registerAnnotation($class, array $metadata)
    {
        $this->annotations[$class] = AnnotationMetadata::create($metadata);
    }

    /**
     * @param $class
     *
     * @return \ReflectionClass
     */
    public function getClassReflector($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }
        if (!isset($this->reflectors[$class])) {
            $this->reflectors[$class] = new \ReflectionClass($class);
        }

        return $this->reflectors[$class];
    }

    private function createAnnotationMetadataInstance(\ReflectionClass $reflector)
    {
        $parent = $reflector->getParentClass();
        if ($parent) {
            $this->reflectors[$parent->getName()] = $parent;

            $metadata = $this->readClassMetadata($parent->getName(), true);
        } else {
            $metadata = new AnnotationMetadata();
        }

        return $metadata;
    }

    /**
     * @param      $class
     * @param bool $isParent
     *
     * @throws AnnotationException
     *
     * @return AnnotationMetadata
     */
    private function readClassMetadata($class, $isParent = false)
    {
        if (!isset($this->annotations[$class])) {
            $reflector = $this->getClassReflector($class);
            $metadata  = $this->createAnnotationMetadataInstance($reflector);
            $comment   = $this->reader->readClass($class);

            if (!$comment->has('Annotation')) {
                if ($isParent) {
                    return $metadata;
                }
                throw new AnnotationException("Class {$class} has not been marked with @Annotation");
            }

            $this->collectAttributeMetadata($comment, $metadata);
            $this->collectTargetMetadata($comment, $metadata);
            $this->collectDefaultAttributeMetadata($comment, $metadata);
            $this->getConstructorInfo($reflector, $metadata);

            $this->annotations[$class] = $metadata;
        }

        return $this->annotations[$class];
    }

    /**
     * @param Comment            $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectAttributeMetadata($comment, $metadata)
    {
        //@Attribute annotations
        $attributeClassName = 'Modules\\Annotation\\Annotations\\Attribute';
        if (!$comment->hasAnnotationType($attributeClassName)) {
            return;
        }
        foreach ($comment->getAnnotationType($attributeClassName) as $annotation) {
            /** @var $annotation Attribute */
            $metadata->attributes[$annotation->name] = $annotation->toArray();
        }
    }

    /**
     * @param Comment            $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectTargetMetadata($comment, $metadata)
    {
        //@Target
        $targetClassName = 'Modules\\Annotation\\Annotations\\Target';
        if (!$comment->hasAnnotationType($targetClassName)) {
            return;
        }

        $metadata->target = array_reduce(
            $comment->getAnnotationType($targetClassName),
            function ($value, Target $target) {
                return $value | $target->target;
            }
        );
    }

    /**
     * @param Comment            $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectDefaultAttributeMetadata($comment, $metadata)
    {
        //@DefaultAttribute
        if ($comment->has('DefaultAttribute')) {
            $metadata->defaultAttribute = $comment->get('DefaultAttribute');
        }
    }

    /**
     * @param \ReflectionClass   $reflector
     * @param AnnotationMetadata $metadata
     */
    private function getConstructorInfo($reflector, $metadata)
    {
        $constructor = $reflector->getConstructor();
        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            return;
        }

        $metadata->constructor = array();
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!isset($metadata->attributes[$name])) {
                $metadata->attributes[$name] = Attribute::getDefaults();
            }

            $metadata->constructor[] = $name;
            if (!$parameter->allowsNull() && !$parameter->isDefaultValueAvailable()) {
                $metadata->attributes[$name]['required'] = true;
            }
        }
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
        if (!Target::check($target, $metadata->target)) {
            throw new AnnotationException("Annotation {$class} can not be applied to {$target} target");
        }
        $attributes = $this->filterAttributes($metadata, $attributes);

        return $this->createAnnotationInstance($class, $attributes, $metadata);
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
    private function createAnnotationInstance($class, $attributes, $metadata)
    {
        //instantiate annotation class
        if (is_array($metadata->constructor)) {
            $arguments = array();
            //$metadata->constructor has the constructor parameter names in order
            foreach ($metadata->constructor as $key) {
                if (isset($attributes[$key])) {
                    $arguments[$key] = $attributes[$key];
                    unset($attributes[$key]);
                }
            }
            $annotation = $this->getClassReflector($class)->newInstanceArgs($arguments);
        } else {
            $annotation = new $class;
        }

        foreach ($attributes as $key => $value) {
            if (isset($metadata->attributes[$key]['setter'])) {
                $annotation->{$metadata->attributes[$key]['setter']}($value);
            } else {
                $annotation->$key = $value;
            }
        }

        return $annotation;
    }

    /**
     * @param AnnotationMetadata $metadata
     * @param array              $attributes
     *
     * @return array
     *
     * @throws AnnotationException
     */
    private function filterAttributes($metadata, $attributes)
    {
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                unset($attributes[$name]);
                $attributes[$metadata->defaultAttribute] = $value;
            }
        }
        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[$name])) {
                throw new AnnotationException("Unknown attribute: {$name}");
            }
            if ($value === null && $metadata->attributes[$name]['nullable']) {
                continue;
            }
            Attribute::checkType($name, $value, $metadata->attributes[$name]['type']);
        }

        $unsetAttributes = array_diff_key($metadata->attributes, $attributes);
        foreach ($unsetAttributes as $name => $data) {
            if ($data['required']) {
                throw new AnnotationException("Required parameter {$name} is not set");
            }
        }

        return $attributes;
    }
}
