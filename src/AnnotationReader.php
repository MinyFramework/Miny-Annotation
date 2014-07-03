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
class AnnotationReader
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
    private $globalImports = array(
        'Attribute' => 'Modules\\Annotation\\Annotations\\Attribute',
        'Enum'      => 'Modules\\Annotation\\Annotations\\Enum'
    );

    public function __construct()
    {
        $this->container = new AnnotationContainer($this);
        $this->parser    = new AnnotationParser($this, $this->container);
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
        $this->parser->setNamespace(
            $this->namespaces[$filename]
        );

        return $this->parser->parse(
            $reflector->getDocComment(),
            $target
        );
    }

    /**
     * Reads and parses documentation comments from classes.
     *
     * @param string|object $class
     * @return Comment
     */
    public function readClass($class)
    {
        return $this->process($this->container->getClassReflector($class), 'class');
    }

    /**
     * Reads and parses documentation comments from functions.
     *
     * @param string|\Closure $function
     * @return Comment
     */
    public function readFunction($function)
    {
        return $this->process(new \ReflectionFunction($function), 'function');
    }

    /**
     * Reads and parses documentation comments from methods.
     *
     * @param string|object $class
     * @param string        $method
     * @return Comment
     */
    public function readMethod($class, $method)
    {
        return $this->process(new \ReflectionMethod($class, $method), 'method');
    }

    /**
     * Reads and parses documentation comments from properties.
     *
     * @param string|object $class
     * @param string        $property
     * @return Comment
     */
    public function readProperty($class, $property)
    {
        return $this->process(new \ReflectionProperty($class, $property), 'property');
    }

    public function addGlobalImport($fqn, $class = null)
    {
        if ($class === null) {
            $class = substr($fqn, strrpos($fqn, '\\'));
        }
        $this->globalImports[$class] = $fqn;
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

        return $this->imports[$key] + $this->globalImports;
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
}
