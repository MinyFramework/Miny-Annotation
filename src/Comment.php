<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use ArrayAccess;
use OutOfBoundsException;

class Comment implements ArrayAccess
{
    /**
     * @var string
     */
    private $description;

    /**
     * @var array
     */
    private $tags;

    public function __construct($description, array $tags = array())
    {
        $this->description = $description;
        $this->tags        = $tags;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function add($tag, $value = null)
    {
        $this->tags[$tag] = $value;
    }

    public function has($tag)
    {
        return array_key_exists($tag, $this->tags);
    }

    public function get($tag)
    {
        if (!$this->has($tag)) {
            throw new OutOfBoundsException(sprintf('Comment does not have @%s annotation.', $tag));
        }

        return $this->tags[$tag];
    }

    public function equals($tag, $value)
    {
        return $this->get($tag) === $value;
    }

    public function contains($tag, $value)
    {
        return in_array($value, $this->get($tag));
    }

    public function containsAll($tag, array $values)
    {
        $diff = array_diff($values, $this->get($tag));

        return empty($diff);
    }

    public function __toString()
    {
        return $this->description;
    }

    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->add($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->tags[$offset]);
    }
}
