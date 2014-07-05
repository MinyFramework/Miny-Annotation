<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

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

    private $imports = array();
    private $namespaces = array();
    private $defaultNamespace = '';

    public function __construct()
    {
        $this->container = new AnnotationContainer($this);
        $this->parser    = new AnnotationParser($this, $this->container);
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
     * @return Comment
     */
    protected function process($reflector, $target)
    {
        if (method_exists($reflector, 'getFileName')) {
            $filename  = $reflector->getFileName();
            $startLine = $reflector->getStartLine();
        } else {
            /** @var $reflector \ReflectionProperty */
            $class     = $reflector->getDeclaringClass();
            $filename  = $class->getFileName();
            $startLine = $class->getStartLine();
        }
        $this->parser->setImports(
            $this->getImports($filename, $startLine)
        );
        $this->parser->setNamespaces(
            $this->defaultNamespace,
            $this->namespaces[$filename]
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
        return $this->process($this->container->getClassReflector($class), 'class');
    }

    /**
     * @inheritdoc
     */
    public function readFunction($function)
    {
        return $this->process(new \ReflectionFunction($function), 'function');
    }

    /**
     * @inheritdoc
     */
    public function readMethod($class, $method)
    {
        return $this->process(new \ReflectionMethod($class, $method), 'method');
    }

    /**
     * @inheritdoc
     */
    public function readProperty($class, $property)
    {
        return $this->process(new \ReflectionProperty($class, $property), 'property');
    }

    private function getImports($fileName, $startLine)
    {
        $key = $fileName . $startLine;
        if (!isset($this->imports[$key])) {
            $parser = new UseStatementParser(
                $this->getLines($fileName, $startLine)
            );

            $this->imports[$key]         = $parser->getImports();
            $this->namespaces[$fileName] = $parser->getNamespace();
        }

        return $this->imports[$key] + $this->getGlobalImports();
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
    public function readMethods($class)
    {
        $methods        = array();
        $classReflector = $this->container->getClassReflector($class);
        foreach ($classReflector->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methods[$method->getName()] = $this->process($method, 'method');
        }

        return $methods;
    }

    /**
     * @inheritdoc
     */
    public function readProperties($class)
    {
        $properties     = array();
        $classReflector = $this->container->getClassReflector($class);
        foreach ($classReflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[$property->getName()] = $this->process($property, 'property');
        }

        return $properties;
    }
}
