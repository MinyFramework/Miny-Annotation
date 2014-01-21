<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use Reflector;

/**
 * Annotation reads and parses annotations from documentation comments.
 *
 * @author Dániel Buga <bugadani@gmail.com>
 */
class Annotation
{
    private $parser;
    private $factory;

    public function __construct(AnnotationParser $parser, CommentFactory $factory)
    {
        $this->parser  = $parser;
        $this->factory = $factory;
    }

    protected function parseComment($comment)
    {
        return $this->parser->parse($comment);
    }

    protected function process(Reflector $reflector)
    {
        $comment = $reflector->getDocComment();
        $parsed  = $this->parseComment($comment);
        return $this->factory->create($parsed['description'], $parsed['tags']);
    }

    public function readClass($class)
    {
        return $this->process(new ReflectionClass($class));
    }

    public function readFunction($function)
    {
        return $this->process(new ReflectionFunction($function));
    }

    public function readMethod($class, $method)
    {
        return $this->process(new ReflectionMethod($class, $method));
    }

    public function readProperty($class, $property)
    {
        return $this->process(new ReflectionProperty($class, $property));
    }
}
