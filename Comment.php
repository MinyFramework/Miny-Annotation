<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use OutOfBoundsException;

class Comment
{
    private $description;
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
            throw new OutOfBoundsException(sprintf('Comment doesn\'t have @%s annotation.', $tag));
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
}
