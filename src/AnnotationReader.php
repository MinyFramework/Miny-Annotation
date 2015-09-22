<?php

namespace Annotiny;

use Annotiny\Exceptions\AnnotationException;

/**
 * AnnotationReader reads and parses annotations from documentation comments.
 *
 * @author Dániel Buga <bugadani@gmail.com>
 */
class AnnotationReader extends Reader
{
    /**
     * @var AnnotationParser
     */
    private $parser;

    /**
     * @var AnnotationContainer
     */
    private $container;

    private $imports          = [];
    private $namespaces       = [];
    private $defaultNamespace = '';

    public function __construct()
    {
        $this->container = new AnnotationContainer($this);
        $this->parser    = new AnnotationParser($this->container);
    }

    /**
     * @inheritdoc
     */
    public function registerAnnotation($class, array $metadata)
    {
        $this->container->registerAnnotation($class, $metadata);
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod|\ReflectionFunction|\ReflectionProperty $reflector
     * @param string                                                                     $target
     *
     * @return Comment
     */
    protected function process($reflector, $target)
    {
        if (method_exists($reflector, 'getDeclaringClass')) {
            $classReflector = $reflector->getDeclaringClass();
        } else {
            $classReflector = $reflector;
        }
        $filename = $classReflector->getFileName();
        $this->parser->setImports(
            $this->getImports(
                $filename,
                $classReflector->getStartLine()
            )
        );
        $this->parser->setNamespaces(
            $this->defaultNamespace,
            $this->namespaces[ $filename ]
        );

        return $this->parser->parse(
            $reflector->getDocComment(),
            $target
        );
    }

    /**
     * @inheritdoc
     */
    public function readClass($class)
    {
        try {
            return $this->process($this->container->getClassReflector($class), 'class');
        } catch (\Exception $e) {
            if (is_object($class)) {
                $class = get_class($class);
            }
            throw new AnnotationException("An exception has occurred while reading class {$class}", 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function readFunction($function)
    {
        try {
            return $this->process(new \ReflectionFunction($function), 'function');
        } catch (\Exception $e) {
            throw new AnnotationException("An exception has occurred while reading function {$function}", 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function readMethod($class, $method)
    {
        try {
            return $this->process(new \ReflectionMethod($class, $method), 'method');
        } catch (\Exception $e) {
            throw new AnnotationException("An exception has occurred while reading method {$class}::{$method}", 0, $e);
        }
    }

    /**
     * @inheritdoc
     */
    public function readProperty($class, $property)
    {
        try {
            return $this->process(new \ReflectionProperty($class, $property), 'property');
        } catch (\Exception $e) {
            throw new AnnotationException(
                "An exception has occurred while reading property {$class}::{$property}",
                0,
                $e
            );
        }
    }

    private function getImports($fileName, $startLine)
    {
        $key = $fileName . $startLine;
        if (!isset($this->imports[ $key ])) {
            $parser = new UseStatementParser(
                $this->getLines($fileName, $startLine)
            );

            $this->imports[ $key ]         = $parser->getImports();
            $this->namespaces[ $fileName ] = $parser->getNamespace();
        }

        return $this->imports[ $key ] + $this->getGlobalImports();
    }

    public function setDefaultNamespace($namespace)
    {
        $this->defaultNamespace = $namespace;
    }

    private function getLines($file, $line)
    {
        $handle = fopen($file, 'rb');
        $output = '';
        while (--$line) {
            $output .= fgets($handle);
        }
        fclose($handle);

        return $output;
    }

    /**
     * @inheritdoc
     */
    public function readMethods($class, $filter = \ReflectionMethod::IS_PUBLIC)
    {
        $methods        = [];
        $classReflector = $this->container->getClassReflector($class);
        foreach ($classReflector->getMethods($filter) as $method) {
            try {
                $methods[ $method->getName() ] = $this->process($method, 'method');
            } catch (\Exception $e) {
                throw new AnnotationException(
                    "An exception has occurred while reading method {$class}::{$method}",
                    0,
                    $e
                );
            }
        }

        return $methods;
    }

    /**
     * @inheritdoc
     */
    public function readProperties($class, $filter = \ReflectionProperty::IS_PUBLIC)
    {
        $properties     = [];
        $classReflector = $this->container->getClassReflector($class);
        foreach ($classReflector->getProperties($filter) as $property) {
            try {
                $properties[ $property->getName() ] = $this->process($property, 'property');
            } catch (\Exception $e) {
                throw new AnnotationException(
                    "An exception has occurred while reading property {$class}::{$property}",
                    0,
                    $e
                );
            }
        }

        return $properties;
    }
}
