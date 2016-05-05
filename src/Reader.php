<?php

namespace Annotiny;

use Annotiny\Annotations\Attribute;
use Annotiny\Annotations\Enum;
use Annotiny\Annotations\Target;

abstract class Reader
{
    private $globalImports = [
        'Attribute' => Attribute::class,
        'Enum'      => Enum::class,
        'Target'    => Target::class
    ];

    /**
     * @param       $class
     * @param array $metadata
     */
    abstract public function registerAnnotation($class, array $metadata);

    /**
     * Reads and parses documentation comments from classes.
     *
     * @param string|object $class
     * @return Comment
     */
    abstract public function readClass($class);

    /**
     * Reads and parses documentation comments from functions.
     *
     * @param string|\Closure $function
     * @return Comment
     */
    abstract public function readFunction($function);

    /**
     * Reads and parses documentation comments from methods.
     *
     * @param string|object $class
     * @param string $method
     * @return Comment
     */
    abstract public function readMethod($class, $method);

    /**
     * Reads and parses documentation comments from properties.
     *
     * @param string|object $class
     * @param string $property
     * @return Comment
     */
    abstract public function readProperty($class, $property);

    /**
     * Reads and parses documentation comments from methods.
     *
     * @param string|object $class
     * @param int $filter
     *
     * @return Comment[]
     */
    abstract public function readMethods($class, $filter = \ReflectionMethod::IS_PUBLIC);

    /**
     * Reads and parses documentation comments from properties.
     *
     * @param string|object $class
     * @param int $filter
     *
     * @return Comment[]
     */
    abstract public function readProperties($class, $filter = \ReflectionProperty::IS_PUBLIC);

    public function addGlobalImport($fqn, $class = null)
    {
        if ($class === null) {
            $class = substr($fqn, strrpos($fqn, '\\'));
        }
        $this->globalImports[ $class ] = $fqn;
    }

    public function getGlobalImports()
    {
        return $this->globalImports;
    }
}
