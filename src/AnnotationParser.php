<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Modules\Annotation\Exceptions\SyntaxException;

/**
 * AnnotationParser parses annotations from documentation comments.
 */
class AnnotationParser
{
    /**
     * @var AnnotationContainer
     */
    private $container;

    //state variables
    private $parts;
    private $position;
    private $imports;
    private $currentNamespace;
    private $defaultNamespace;

    //state stack
    private $stack = array();

    public function __construct(AnnotationContainer $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $imports
     */
    public function setImports(array $imports)
    {
        $this->imports = $imports;
    }

    public function setNamespaces($default, $current)
    {
        $this->defaultNamespace = $default;
        $this->currentNamespace = $current;
    }

    private function stripCommentDecoration($comment)
    {
        return preg_replace('/^\s*\*\s?/m', '', trim($comment, '/*'));
    }

    /**
     * Parses a documentation comment.
     *
     * @param string $commentString
     * @param        $target
     *
     * @return Comment
     */
    public function parse($commentString, $target)
    {
        // Extract the description part of the comment block
        $commentString = $this->stripCommentDecoration($commentString);
        $parts         = preg_split('/^\s*(?=@[a-zA-Z]+)/m', $commentString, 2);

        $comment = new Comment(trim($parts[0]));

        if (!isset($parts[1])) {
            return $comment;
        }
        $trimmed = trim($parts[1]);
        if (empty($trimmed)) {
            return $comment;
        }

        $pattern        = '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|[@(),={}]|\s+|(?<![:])[:](?![:]))/';
        $flags          = PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY;
        $this->parts    = preg_split($pattern, $parts[1], -1, $flags);
        $this->position = -1;

        while (isset($this->parts[++$this->position])) {
            if ($this->parts[$this->position] === '@') {
                list($name, $parameters, $isClass) = $this->parseTag();
                if ($isClass) {
                    $className = $this->getFullyQualifiedName($name);

                    $this->stack[] = array(
                        $this->parts,
                        $this->position,
                        $this->imports,
                        $this->defaultNamespace,
                        $this->currentNamespace
                    );
                    $comment->addAnnotation(
                        $className,
                        $this->container->readClass($className, $parameters, $target)
                    );

                    list($this->parts,
                        $this->position,
                        $this->imports,
                        $this->defaultNamespace,
                        $this->currentNamespace) = array_pop($this->stack);
                } else {
                    $comment->add($name, $parameters);
                }
            }
        }

        return $comment;
    }

    private function parseTag()
    {
        $return = array(
            $this->parts[++$this->position]
        );
        if ($this->parts[++$this->position] === '(') {
            $return[1] = $this->parseList(')');
            $return[2] = true;
        } else {
            $parameters = '';
            while (isset($this->parts[++$this->position])) {
                $part = $this->parts[$this->position];
                if ($part === '{') {
                    $parameters = $this->parseList('}');
                } elseif (is_string($parameters)) {
                    if (strstr($part, "\n")) {
                        --$this->position;
                        break;
                    } elseif ($part === '@') {
                        --$this->position;
                        break;
                    } else {
                        $parameters .= $part;
                    }
                }
            }
            if (is_string($parameters)) {
                $parameters = trim($parameters);
            }
            $return[1] = $parameters === '' ? true : $parameters;
            $return[2] = false;
        }

        return $return;
    }

    private function getValue($currentValue)
    {
        if (is_array($currentValue)) {
            return $currentValue;
        }
        switch ($currentValue) {
            case 'true':
                return true;

            case 'false':
                return false;

            case 'null':
                return null;

            default:
                if (is_object($currentValue)) {
                    return $currentValue;
                }
                if (is_numeric($currentValue)) {
                    $number = (float)$currentValue;
                    //check whether the number can be represented as an integer
                    if (ctype_digit($currentValue) && $number <= PHP_INT_MAX) {
                        $number = (int)$currentValue;
                    }

                    return $number;
                }
                if (defined($currentValue)) {
                    return constant($currentValue);
                }
                if (strpos($currentValue, '::') !== false) {
                    list($class, $constant) = explode('::', $currentValue, 2);
                    $class = $this->getFullyQualifiedName($class);
                    if (defined($class . '::' . $constant)) {
                        return constant($class . '::' . $constant);
                    }
                }

                switch ($currentValue[0]) {
                    case '"':
                    case "'":
                        return substr($currentValue, 1, -1);

                    default:
                        return $this->getFullyQualifiedName($currentValue);
                }
        }
    }

    private function parseList($closing)
    {
        $array        = array();
        $currentKey   = null;
        $currentValue = null;
        while (isset($this->parts[++$this->position])) {
            switch ($this->parts[$this->position]) {
                case '{':
                    if (isset($currentValue)) {
                        throw new SyntaxException('Unexpected { found.');
                    }
                    $currentValue = $this->parseList('}');
                    break;

                case ',':
                    if (!isset($currentValue)) {
                        throw new SyntaxException('Unexpected = found.');
                    }
                    $currentValue = $this->getValue($currentValue);
                    if (isset($currentKey)) {
                        $array[$currentKey] = $currentValue;
                    } else {
                        $array[] = $currentValue;
                    }
                    unset($currentKey, $currentValue);
                    break;

                case ':':
                    if (!isset($currentValue)) {
                        throw new SyntaxException('Unexpected : found.');
                    }
                    if (!ctype_alpha($currentValue)) {
                        throw new SyntaxException('Keys must be alphanumeric.');
                    }

                    $currentKey = $currentValue;
                    unset($currentValue);
                    break;

                case $closing:
                    if (isset($currentValue)) {
                        $currentValue = $this->getValue($currentValue);
                        if (isset($currentKey)) {
                            $array[$currentKey] = $currentValue;
                        } else {
                            $array[] = $currentValue;
                        }
                    }

                    return $array;

                case '@':
                    list($className, $parameters, $isClass) = $this->parseTag();
                    if (!$isClass) {
                        throw new \UnexpectedValueException('Inner annotations must be class type annotations');
                    }
                    $currentValue = $this->container->readClass(
                        $this->getFullyQualifiedName($className),
                        $parameters,
                        'annotation'
                    );
                    break;

                default:
                    if (ctype_space($this->parts[$this->position])) {
                        continue;
                    }
                    if (isset($currentValue)) {
                        throw new SyntaxException('Unexpected data found');
                    }
                    $currentValue = $this->parts[$this->position];
                    break;
            }
        }
        throw new SyntaxException('Unexpected end of comment');
    }

    /**
     * @param $class
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function getFullyQualifiedName($class)
    {
        if (class_exists($class)) {
            return $class;
        }

        //determine if class belongs to the default namespace
        if (class_exists($this->defaultNamespace . '\\' . $class)) {
            return $this->defaultNamespace . '\\' . $class;
        }

        //determine if class belongs to current namespace
        if (class_exists($this->currentNamespace . '\\' . $class)) {
            return $this->currentNamespace . '\\' . $class;
        }

        //if not, check imports
        if (isset($this->imports[$class])) {
            //determine if class is aliased directly
            $class = $this->imports[$class];

        } elseif (($nsDelimiter = strpos($class, '\\')) !== false) {
            //if not, determine if class is part of one of the imported namespaces
            $namespace = substr($class, 0, $nsDelimiter);
            if (!isset($this->imports[$namespace])) {
                throw new \InvalidArgumentException("Class {$class} is not found");
            }
            $class = $this->imports[$namespace] . substr($class, $nsDelimiter);
        }

        //if class still doesn't exist, throw exception
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Class {$class} is not found");
        }

        return $class;
    }
}
