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
                array(
                    'description' => '',
                    'tags'        => array()
                )
            ),
            array(
                '/** no annotations */',
                array(
                    'description' => 'no annotations',
                    'tags'        => array()
                )
            ),
            array(
                '/**
                  * multiline
                  */',
                array(
                    'description' => 'multiline',
                    'tags'        => array()
                )
            ),
            array(
                '/** @something */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'something' => null
                    )
                )
            ),
            array(
                '/** @something UTTERLY_WEIRD */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'something' => 'UTTERLY_WEIRD'
                    )
                )
            ),
            array(
                '/** description
                   * @something weird */',
                array(
                    'description' => 'description',
                    'tags'        => array(
                        'something' => 'weird'
                    )
                )
            ),
            array(
                '/** @something() */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'something' => array()
                    )
                )
            ),
            array(
                '/** @something(\'value\') */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'something' => array('value')
                    )
                )
            ),
            array(
                '/** @something(with, "values") */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'something' => array('with', 'values')
                    )
                )
            ),
            array(
                '/** @multiple
                     @and_more(asd)
                     @annotations("param1", "param2") */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'multiple'    => null,
                        'and_more'    => array('asd'),
                        'annotations' => array('param1', 'param2')
                    )
                )
            ),
            array(
                '/** @multiple(tags) @in the @same line */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'multiple' => array('tags'),
                        'in'       => 'the',
                        'same'     => 'line',
                    )
                )
            ),
            array(
                '/** @named(name=value, other="other value") */',
                array(
                    'description' => '',
                    'tags'        => array(
                        'named' => array(
                            'name'  => 'value',
                            'other' => 'other value',
                        ),
                    )
                )
            ),
        );
    }

    /**
     * @dataProvider annotationProvider
     */
    public function testParseSimpleAnnotation($comment, $expected_result)
    {
        $result = $this->object->parse($comment);
        $this->assertEquals($expected_result, $result);
    }

    /**
     * @expectedException \Modules\Annotation\Exceptions\SyntaxException
     */
    public function testStringsNeedToBeTerminated()
    {
        $this->object->parse('/** @something(invalid") */');
    }
}
