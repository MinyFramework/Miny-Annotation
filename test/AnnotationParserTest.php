<?php

namespace Modules\Annotation;

class AnnotationParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AnnotationParser
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new AnnotationParser;
    }

    public function annotationProvider()
    {
        return array(
            array(
                '/** */',
                '',
                array()
            ),
            array(
                '/** no annotations */',
                'no annotations',
                array()
            ),
            array(
                '/**
                  * multiline
                  */',
                'multiline',
                array()
            ),
            array(
                '/** @something */',
                '',
                array(
                    'something' => null
                )
            ),
            array(
                '/** @something UTTERLY_WEIRD */',
                '',
                array(
                    'something' => 'UTTERLY_WEIRD'
                )
            ),
            array(
                '/** description
                   * @something weird */',
                'description',
                array(
                    'something' => 'weird'
                )
            ),
            array(
                '/** @something() */',
                '',
                array(
                    'something' => array()
                )
            ),
            array(
                '/** @something(\'value\') */',
                '',
                array(
                    'something' => array('value')
                )
            ),
            array(
                '/** @something(with, "values") */',
                '',
                array(
                    'something' => array('with', 'values')
                )
            ),
            array(
                '/** @multiple
                     @and_more(asd)
                     @annotations("param1", "param2") */',
                '',
                array(
                    'multiple'    => null,
                    'and_more'    => array('asd'),
                    'annotations' => array('param1', 'param2')
                )
            ),
            array(
                '/** @multiple(tags) @in the @same line */',
                '',
                array(
                    'multiple' => array('tags'),
                    'in'       => 'the',
                    'same'     => 'line'
                )
            ),
            array(
                '/** @named(name: value, other: "other value") */',
                '',
                array(
                    'named' => array(
                        'name'  => 'value',
                        'other' => 'other value'
                    )
                )
            ),
            array(
                '/** @annotation({key: "value", other: {}, "only value"}) */',
                '',
                array(
                    'annotation' => array(
                        array(
                            'key'   => 'value',
                            'other' => array(),
                            'only value'
                        )
                    )
                )
            ),
        );
    }

    /**
     * @dataProvider annotationProvider
     */
    public function testParseSimpleAnnotation($comment, $description, $expectedTags)
    {
        $comment = $this->object->parse($comment);

        $class = new \ReflectionClass($comment);
        $tags = $class->getProperty('tags');
        $tags->setAccessible(true);

        $this->assertEquals($description, $comment->getDescription());
        $this->assertEquals($expectedTags, $tags->getValue($comment));
    }

    /**
     * @expectedException \Modules\Annotation\Exceptions\SyntaxException
     */
    public function testStringsNeedToBeTerminated()
    {
        $this->object->parse('/** @something(invalid") */');
    }
}
