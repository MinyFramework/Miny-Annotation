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

            $parent = $reflector->getParentClass();
            if ($parent) {
                $this->reflectors[$parent->getName()] = $parent;

                $metadata = $this->readClassMetadata($parent->getName(), true);
            } else {
                $metadata = new AnnotationMetadata();
            }

            $comment = $this->reader->readClass($class);
            if (!$comment->has('Annotation')) {
                if ($isParent) {
                    return $metadata;
                }
                throw new AnnotationException("Class {$class} has not been marked with @Annotation");
            }

            //@Attribute annotations
            $attributeClassName = 'Modules\\Annotation\\Annotations\\Attribute';
            if ($comment->hasAnnotationType($attributeClassName)) {
                foreach ($comment->getAnnotationType($attributeClassName) as $annotation) {
                    /** @var $annotation Attribute */
                    $metadata->attributes[$annotation->name] = $annotation->toArray();
                }
            }

            //@Target
            $targetClassName = 'Modules\\Annotation\\Annotations\\Target';
            if ($comment->hasAnnotationType($targetClassName)) {
                $metadata->target = 0;
                foreach ($comment->getAnnotationType($targetClassName) as $annotation) {
                    /** @var $annotation Target */
                    $metadata->target |= $annotation->target;
                }
            }

            //@DefaultAttribute
            if ($comment->has('DefaultAttribute')) {
                $metadata->defaultAttribute = $comment->get('DefaultAttribute');
            }

            //get constructor info
            $constructor = $reflector->getConstructor();
            if ($constructor !== null && $constructor->getNumberOfParameters() > 0) {
                $metadata->constructor = array();
                foreach ($constructor->getParameters() as $parameter) {
                    $name = $parameter->getName();
                    if (!isset($metadata->attributes[$name])) {
                        $metadata->attributes[$name] = array(
                            'required' => false,
                            'type'     => 'mixed',
                            'setter'   => null,
                            'nullable' => false
                        );
                    }

                    $metadata->constructor[] = $name;
                    if (!$parameter->allowsNull() && !$parameter->isDefaultValueAvailable()) {
                        $metadata->attributes[$name]['required'] = true;
                    }
                }
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
        if (!Target::check($target, $metadata->target)) {
            throw new AnnotationException("Annotation {$class} can not be applied to {$target} target");
        }
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
            Attribute::checkType($name, $value, $metadata->attributes[$name]['type']);
        }

        return $attributes;
    }
}
