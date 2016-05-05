<?php

namespace Annotiny;

use Annotiny\Annotations\Attribute;
use Annotiny\Annotations\Enum;
use Annotiny\Annotations\Target;
use Annotiny\Exceptions\AnnotationException;

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
    private $reflectors = [];

    public function __construct(AnnotationReader $reader)
    {
        $this->reader = $reader;

        $this->annotations = [
            Attribute::class => (new AnnotationMetadata('name'))
                ->addAttribute('name', new Attribute(['required' => true, 'type' => 'string']))
                ->addAttribute('setter', new Attribute(['required' => false, 'type' => 'string']))
                ->addAttribute('type', new Attribute(['required' => false, 'type' => 'mixed']))
                ->addAttribute('nullable', new Attribute(['required' => false, 'type' => 'bool']))
                ->addAttribute('required', new Attribute(['required' => false, 'type' => 'bool']))
                ->addAttribute('default', new Attribute(['required' => false, 'type' => 'mixed'])),
            Enum::class      => (new AnnotationMetadata('values', Target::TARGET_ANNOTATION))
                ->addAttribute('values', new Attribute(['required' => true, 'type' => []])),
            Target::class    => (new AnnotationMetadata('target', Target::TARGET_CLASS, ['target']))
                ->addAttribute('target', new Attribute(['required' => true, 'type' => 'mixed']))
        ];
    }

    public function registerAnnotation($class, array $metadata)
    {
        $annotationMetadata          = new AnnotationMetadata($metadata);
        $this->annotations[ $class ] = $annotationMetadata;

        return $annotationMetadata;
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
        if (!isset($this->reflectors[ $class ])) {
            $this->reflectors[ $class ] = new \ReflectionClass($class);
        }

        return $this->reflectors[ $class ];
    }

    private function createAnnotationMetadataInstance(\ReflectionClass $reflector)
    {
        $parent = $reflector->getParentClass();
        if ($parent) {
            $parentClassName = $parent->getName();

            $this->reflectors[ $parentClassName ] = $parent;

            $metadata = clone $this->readClassMetadata($parentClassName, true);
        } else {
            $metadata = new AnnotationMetadata;
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
        if (!isset($this->annotations[ $class ])) {
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

            $this->annotations[ $class ] = $metadata;
        }

        return $this->annotations[ $class ];
    }

    /**
     * @param Comment $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectAttributeMetadata(Comment $comment, AnnotationMetadata $metadata)
    {
        //@Attribute annotations
        $attributeClassName = Attribute::class;
        if ($comment->hasAnnotationType($attributeClassName)) {
            foreach ($comment->getAnnotationType($attributeClassName) as $annotation) {
                /** @var $annotation Attribute */
                $metadata->attributes[ $annotation->name ] = $annotation;
            }
        }
    }

    /**
     * @param Comment $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectTargetMetadata(Comment $comment, AnnotationMetadata $metadata)
    {
        //@Target
        $targetClassName = Target::class;
        if ($comment->hasAnnotationType($targetClassName)) {
            $metadata->target = array_reduce(
                $comment->getAnnotationType($targetClassName),
                function ($value, Target $target) {
                    return $value | $target->target;
                }
            );
        }
    }

    /**
     * @param Comment $comment
     * @param AnnotationMetadata $metadata
     */
    private function collectDefaultAttributeMetadata(Comment $comment, AnnotationMetadata $metadata)
    {
        //@DefaultAttribute
        if ($comment->has('DefaultAttribute')) {
            $metadata->defaultAttribute = $comment->get('DefaultAttribute');
        }
    }

    /**
     * @param \ReflectionClass $reflector
     * @param AnnotationMetadata $metadata
     */
    private function getConstructorInfo(\ReflectionClass $reflector, AnnotationMetadata $metadata)
    {
        $constructor = $reflector->getConstructor();
        if (!$constructor || $constructor->getNumberOfParameters() === 0) {
            return;
        }

        $metadata->constructor = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (!isset($metadata->attributes[ $name ])) {
                $metadata->addAttribute($name, new Attribute());
            }

            $metadata->constructor[] = $name;
            if ($metadata->attributes[ $name ]->default === null) {
                if ($parameter->isDefaultValueAvailable()) {
                    $metadata->attributes[ $name ]->default  = $parameter->getDefaultValue();
                    $metadata->attributes[ $name ]->required = false;
                } else if (!$parameter->allowsNull()) {
                    $metadata->attributes[ $name ]->required = true;
                }
            } else {
                $metadata->attributes[ $name ]->required = false;
            }
        }
    }

    /**
     * @param string $class The fully qualified class name
     * @param array $attributes
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
     * @param array $attributes
     * @param AnnotationMetadata $metadata
     *
     * @throws AnnotationException
     *
     * @return object
     */
    private function createAnnotationInstance($class, $attributes, AnnotationMetadata $metadata)
    {
        //instantiate annotation class
        if (is_array($metadata->constructor)) {
            $arguments = [];
            //$metadata->constructor has the constructor parameter names in order
            foreach ($metadata->constructor as $i => $key) {
                if (!isset($attributes[ $key ])) {
                    $arguments[ $key ] = $metadata->attributes[ $key ]->default;
                } else {
                    $arguments[ $key ] = $attributes[ $key ];
                    unset($attributes[ $key ]);
                }
            }
            $annotation = $this->getClassReflector($class)->newInstanceArgs($arguments);
        } else {
            $annotation = new $class;
        }

        foreach ($attributes as $key => $value) {
            if (isset($metadata->attributes[ $key ]->setter)) {
                $annotation->{$metadata->attributes[ $key ]->setter}($value);
            } else {
                $annotation->$key = $value;
            }
        }

        return $annotation;
    }

    /**
     * @param AnnotationMetadata $metadata
     * @param array $attributes
     *
     * @return Attribute[]
     *
     * @throws AnnotationException
     */
    private function filterAttributes(AnnotationMetadata $metadata, $attributes)
    {
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                unset($attributes[ $name ]);
                $attributes[ $metadata->defaultAttribute ] = $value;
            }
        }
        $unsetRequiredAttributes = $metadata->attributes;
        foreach ($attributes as $name => $value) {
            if (!isset($metadata->attributes[ $name ])) {
                throw new AnnotationException("Unknown attribute: {$name}");
            }
            unset($unsetRequiredAttributes[ $name ]);
            $attribute = $metadata->attributes[ $name ];
            if (!($value === null && $attribute->nullable)) {
                Attribute::checkType($name, $value, $attribute->type);
            }
        }

        //Filter for required attributes
        $unsetRequiredAttributes = array_filter($unsetRequiredAttributes, function (Attribute $attribute) {
            return $attribute->required;
        });

        if (empty($unsetRequiredAttributes)) {
            return $attributes;
        }

        //get attribute names
        $unsetRequiredAttributeNames = array_map(function (Attribute $attribute) {
            return $attribute->name;
        }, $unsetRequiredAttributes);

        if (count($unsetRequiredAttributes) === 1) {
            throw new AnnotationException("Required parameter {$unsetRequiredAttributeNames[0]} is not set");
        } else {
            $joined = implode(', ', $unsetRequiredAttributeNames);
            throw new AnnotationException("Required parameters {$joined} are not set");
        }
    }
}
