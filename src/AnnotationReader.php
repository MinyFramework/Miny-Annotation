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
class AnnotationReader extends AbstractReader
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
