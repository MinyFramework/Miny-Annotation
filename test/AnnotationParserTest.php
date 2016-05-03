<?php

namespace Annotiny\Test;

use Annotiny\AnnotationContainer;
use Annotiny\AnnotationParser;

class AnnotationParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AnnotationParser
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new AnnotationParser(
            $this->getMockBuilder(AnnotationContainer::class)
                 ->disableOriginalConstructor()
                 ->getMock()
        );
    }

    public function annotationProvider()
    {
        return [
            [
                '/** */',
                '',
                []
            ],
            [
                '/** no annotations */',
                'no annotations',
                []
            ],
            [
                '/**
                  * multiline
                  */',
                'multiline',
                []
            ],
            [
                '/** @something */',
                '',
                [
                    'something' => true
                ]
            ],
            [
                '/** @something UTTERLY_WEIRD */',
                '',
                [
                    'something' => 'UTTERLY_WEIRD'
                ]
            ],
            [
                '/**
                @something with multiple words in a line
                @another
                */',
                '',
                [
                    'something' => 'with multiple words in a line',
                    'another'   => true
                ]
            ],
            [
                '/** description
                   * @something weird */',
                'description',
                [
                    'something' => 'weird'
                ]
            ],
            [
                '/** @tag {"with", "array"} */',
                '',
                [
                    'tag' => [
                        'with',
                        'array'
                    ]
                ]
            ],
        ];
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
