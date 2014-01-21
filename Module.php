<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <daniel@bugadani.hu>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

use Miny\Application\BaseApplication;

class Module extends \Miny\Application\Module
{

    public function defaultConfiguration()
    {
        return array();
    }

    public function init(BaseApplication $app)
    {
        $factory = $app->getFactory();

        $factory->add('comment_factory', __NAMESPACE__.'\CommentFactory');
        $factory->add('annotation_parser', __NAMESPACE__ . '\AnnotationParser');
        $factory->add('annotation', __NAMESPACE__.'\Annotation')
            ->setArguments('&annotation_parser', '&comment_factory');
    }
}
