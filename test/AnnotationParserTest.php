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
        $this->object = new AnnotationParser(
            $this->getMock('\\Modules\\Annotation\\AnnotationReader'),
            $this->getMockBuilder('\\Modules\\Annotation\\AnnotationContainer')
                ->disableOriginalConstructor()
                ->getMock()
        );
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
                '/**
                @something with multiple words in a line
                @another
                */',
                '',
                array(
                    'something' => 'with multiple words in a line',
                    'another'   => null
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
                '/** @tag {"with", "array"} */',
                '',
                array(
                    'tag' => array(
                        'with',
                        'array'
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
        $comment = $this->object->parse($comment, 'class');

        $class = new \ReflectionClass($comment);
        $tags  = $class->getProperty('tags');
        $tags->setAccessible(true);

        $this->assertEquals($description, $comment->getDescription());
        $this->assertEquals($expectedTags, $tags->getValue($comment));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testStringsNeedToBeTerminated()
    {
        $this->object->parse('/** @something(invalid") */', 'class');
    }
}
