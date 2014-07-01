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
    private $parts;
    private $position;

    private function stripCommentDecoration($comment)
    {
        return preg_replace('/^\s*\*\s?/m', '', trim($comment, '/*'));
    }

    /**
     * Parses a documentation comment.
     *
     * @param string $comment
     *
     * @return array
     */
    public function parse($comment)
    {
        $comment = $this->stripCommentDecoration($comment);

        // Extract the description part of the comment block
        $parts = preg_split('/^\s*(?=@[a-zA-Z]+)/m', $comment, 2);

        $result = array(
            'tags'        => array(),
            'description' => trim($parts[0])

        );

        if (!isset($parts[1])) {
            return $result;
        }
        $trimmed = trim($parts[1]);
        if (empty($trimmed)) {
            return $result;
        }

        $result['tags'] = $this->parseTags($parts[1]);

        return $result;
    }

    /**
     * @param $tagString
     *
     * @throws Exceptions\SyntaxException
     * @return array
     */
    private function parseTags($tagString)
    {
        $result = array();

        $pattern        = '/(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|[@(),={}:]|\s+)/';
        $flags          = PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY;
        $this->parts    = preg_split($pattern, $tagString, -1, $flags);
        $this->position = -1;

        $tagName    = null;
        $parameters = null;
        while (isset($this->parts[++$this->position])) {
            switch ($this->parts[$this->position]) {
                case '@':
                    if (isset($tagName)) {
                        $result[$tagName] = $parameters;
                        $parameters       = null;
                    }
                    $tagName = $this->parts[++$this->position];
                    break;

                case '(':
                    $parameters = $this->parseList(')');
                    break;

                default:
                    if (!ctype_space($this->parts[$this->position])) {
                        throw new SyntaxException("Unexpected {$this->parts[$this->position]} found.");
                    }
                    if (!isset($parameters) && isset($tagName) && isset($this->parts[++$this->position])) {
                        if ($this->parts[$this->position][0] === '@') {
                            --$this->position;
                        } else {
                            $result[$tagName] = $this->parts[$this->position];
                            $tagName          = null;
                        }
                    }
                    break;
            }
        }
        if (isset($tagName)) {
            $result[$tagName] = $parameters;
        }

        return $result;
    }

    private function getKey($currentValue)
    {
        if (!ctype_alpha($currentValue)) {
            throw new SyntaxException("Keys must be alphanumeric.");
        }

        return $currentValue;
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
                if (is_numeric($currentValue)) {
                    $number = (float)$currentValue;
                    //check whether the number can be represented as an integer
                    if (ctype_digit($currentValue) && $number <= PHP_INT_MAX) {
                        $number = (int)$currentValue;
                    }

                    return $number;
                } elseif (preg_match('/^[a-zA-Z0-9_]+$/', $currentValue)) {
                    return $currentValue;
                }
                switch ($currentValue[0]) {
                    case '"':
                    case "'":
                        return substr($currentValue, 1, -1);
                    default:
                        throw new SyntaxException("Unexpected {$currentValue} found");
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
                    $currentKey = $this->getKey($currentValue);
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

                default:
                    if(trim($this->parts[$this->position]) === '') {
                        continue;
                    }
                    if (isset($currentValue)) {
                        throw new SyntaxException('Unexpected data found');
                    }
                    $currentValue = $this->parts[$this->position];
                    break;
            }
        }
        throw new SyntaxException("Unexpected end of comment");
    }
}
