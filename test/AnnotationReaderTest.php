<?php

namespace Modules\Annotation;

/**
 * @Annotation
 * @DefaultAttribute value
 * @Attribute('value', required: true)
 * @Attribute('named', setter: 'constructor')
 * @Attribute('array', type: {'string', 'int'})
 * @Attribute('enum', type: @Enum({'foo', 'bar', 'foobar'}))
 */
class FooAnnotation
{
    public $value;
    public $enum;
    private $named;

    public function __construct($named)
    {
        $this->named = $named;
    }

    public function getNamed()
    {
        return $this->named;
    }
}

/**
 * Test class.
 * @see foo
 * @FooAnnotation('foo', named: 'foobar', enum: 'bar', array: {'string', 2})
 */
class TestClass
{
    /**
     * Property
     */
    public $property;

    /**
     * @foo
     */
    public function method()
    {
    }
}

/**
 * @FooAnnotation('foo', enum: 'br')
 */
class WrongEnumValueClass
{
}

/**
 * Function docs.
 */
function fooFunction()
{
}

class AnnotationReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AnnotationReader
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new AnnotationReader;
    }

    public function testReadClass()
    {
        $comment = $this->object->readClass('Modules\Annotation\TestClass');
        $this->assertInstanceOf('Modules\Annotation\Comment', $comment);
        $this->assertTrue($comment->hasAnnotationType('Modules\\Annotation\\FooAnnotation'));
        $annotations = $comment->getAnnotationType('Modules\\Annotation\\FooAnnotation');
        $this->assertEquals('foo', $comment->get('see'));
        $this->assertEquals('foo', $annotations[0]->value);
        $this->assertEquals('foobar', $annotations[0]->getNamed());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testExceptionIsThrownWhenValueIsNotInEnum()
    {
        $this->object->readClass('Modules\Annotation\WrongEnumValueClass');
    }

    public function testReadFunction()
    {
        $this->assertInstanceOf(
            'Modules\Annotation\Comment',
            $this->object->readFunction('Modules\Annotation\fooFunction')
        );
    }

    public function testReadMethod()
    {
        $this->assertInstanceOf(
            'Modules\Annotation\Comment',
            $this->object->readMethod('Modules\Annotation\TestClass', 'method')
        );
    }

    public function testReadProperty()
    {
        $this->assertInstanceOf(
            'Modules\Annotation\Comment',
            $this->object->readProperty('Modules\Annotation\TestClass', 'property')
        );
    }
}
