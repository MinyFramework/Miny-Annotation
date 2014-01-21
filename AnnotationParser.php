<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

/**
 * AnnotationParser parses annotations from documentation comments.
 */
class AnnotationParser
{
    private static $tag   = '[a-zA-Z]+[a-zA-Z0-9\-\_]*';
    private static $value = '[ \t]*(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|[^\'"\n\r,()@]+)[ \t]*';

    private function stripCommentDecoration($comment)
    {
        return preg_replace('/^\s*\*\s?/m', '', trim($comment, '/*'));
    }

    private function processValue($value)
    {
        preg_match_all('/(?<=,|^)' . self::$value . '(?=,|$)/', $value, $matches);
        $result = array();
        foreach ($matches[0] as $match) {
            $result[] = trim($match, '"\' ');
        }

        return $result;
    }

    public function parse($comment)
    {
        $result  = array('tags' => array());
        $comment = $this->stripCommentDecoration($comment);

        // Extract the description part of the comment block
        $parts = preg_split('/^\s*(?=@' . self::$tag . ')/m', $comment, 2);

        $description           = trim($parts[0]);
        $result['description'] = $description;

        if (!isset($parts[1]) || empty(trim($parts[1]))) {
            return $result;
        }
        $result['tags'] = array();

        $parts_pattern = '/(?<=^)\s*                                            # line start
                 @(' . self::$tag . ')[ \t]*                                    # tag name
                 (
                    (?:\((?:' . self::$value . '(?:,' . self::$value . ')*)?\)) # value(s) in parentheses
                    |' . self::$value . '|                                      # value or nothing
                 )/xm';

        preg_match_all($parts_pattern, $parts[1], $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tag_name = $match[1];
            $value    = trim($match[2]);

            if (empty($value)) {
                $value = null;
            } elseif (substr($value, 0, 1) === '(') {
                $value = $this->processValue(trim($value, '()'));
            } else {
                $value = trim($value, '"\'');
            }

            $result['tags'][$tag_name] = $value;
        }

        return $result;
    }
}
