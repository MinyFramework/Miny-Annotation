<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

/**
 * Annotation reads and parses annotations from documentation comments.
 *
 * @author Dániel Buga <bugadani@gmail.com>
 */
class AnnotationReader
{
    /**
     * @var AnnotationParser
     */
    private $parser;
    private $imports = array();
    private $namespaces = array();

    public function __construct()
    {
        $this->parser = new AnnotationParser($this, new AnnotationContainer($this));
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
        return $this->process(new \ReflectionClass($class), 'class');
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

        return $this->imports[$key];
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
