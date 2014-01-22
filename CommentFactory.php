<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <bugadani@gmail.com>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

class CommentFactory
{

    /**
     * Creates a new Comment instance.
     *
     * @param string $description
     * @param array $tags
     *
     * @return Comment
     */
    public function create($description, array $tags)
    {
        return new Comment($description, $tags);
    }
}
