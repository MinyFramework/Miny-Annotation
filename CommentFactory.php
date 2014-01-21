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

    public function create($description, $tags)
    {
        new Comment($description, $parsedtags);
    }
}
