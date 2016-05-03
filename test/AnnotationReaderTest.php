<?php

namespace Annotiny\Test;

use Annotiny\AnnotationReader;
use Annotiny\Comment;

class CallableTestClass {
    public static function method(){

    }
}

/**
 * @Annotation
 * @DefaultAttribute value
 * @Attribute('value', required: true)
 * @Attribute('named', setter: 'setNamed')
 * @Attribute('array', type: {'string', 'int'})
 * @Attribute('enum', type: @Enum({'foo', 'bar', 'foobar'}))
 * @Attribute('callback', type: 'callable')
 * @Target('class')
 */
class FooAnnotation
{
    const BAR = 'foobar';
    public  $value;
    public  $enum;
    public  $callback;
    private $named;

    public function setNamed($named)
    {
        $this->named = $named;
    }

    public function getNamed()
    {
        return $this->named;
    }
}

/**
 * @Annotation
 * @DefaultAttribute v2
 * @Attribute('v1', default: 8)
 * @Attribute('v2')
 * @Target('class')
 */
class ConstructorAnnotation
{
    public $v1;
    public $v2;

    public function __construct($v1 = 5, $v2 = 6)
    {
        $this->v1 = $v1;
        $this->v2 = $v2;
    }
}

/**
 * @Annotation
 * @Attribute('simple', type: {'string'})
 * @Attribute('complex', type: {{'string', 'int'}})
 */
class ArrayAnnotation
{
    public $simple;
}

/**
 * @Annotation
 */
class InheritedAnnotation extends ArrayAnnotation
{
}


/**
 * Test class.
 *
 * @see foo
 * @FooAnnotation('foo', named: 'foobar', enum: 'bar', array: {'string', 2})
 * @FooAnnotation(value: FooAnnotation::BAR, callback: CallableTestClass::method)
 * @ConstructorAnnotation(v2: 'something')
 * @ConstructorAnnotation()
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
 * @ArrayAnnotation(simple: {'foo', 'bar', 'baz'})
 */
class SimpleArray
{
}

/**
 * @InheritedAnnotation(complex: {{'foo', 1}, {'bar', 2}, {'baz', 3}})
 */
class ComplexArray
{
}

/**
 * @ArrayAnnotation(simple: {'foo', 'bar', 'baz', 2})
 */
class InvalidSimpleArray
{
}

/**
 * @InheritedAnnotation(complex: {{'foo', 'bar'}, {'bar', 2}, {'baz', 3}})
 */
class InvalidComplexArray
{
}

/**
 * @FooAnnotation('foo', enum: 'br')
 */
class WrongEnumValueClass
{
}

/**
 * @FooAnnotation(named: 'foo')
 */
class MissingAnnotationParameterClass
{
}

/**
 * @FooAnnotation(value: 5, callback: 'foo')
 */
class NotCallableCallbackAttributeClass
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
        $comment = $this->object->readClass(TestClass::class);
        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertTrue($comment->hasAnnotationType(FooAnnotation::class));
        $annotations = $comment->getAnnotationType(FooAnnotation::class);
        $this->assertEquals('foo', $comment->get('see'));
        $this->assertEquals('foo', $annotations[0]->value);
        $this->assertEquals('foobar', $annotations[1]->value);
        $this->assertEquals('foobar', $annotations[0]->getNamed());

        $annotations = $comment->getAnnotationType(ConstructorAnnotation::class);
        $this->assertEquals(8, $annotations[0]->v1);
        $this->assertEquals('something', $annotations[0]->v2);
        $this->assertEquals(8, $annotations[1]->v1);
        $this->assertEquals(6, $annotations[1]->v2);
    }

    public function testArrays()
    {
        $this->object->readClass(SimpleArray::class);
        $this->object->readClass(ComplexArray::class);
    }

    /**
     * @expectedException \Annotiny\Exceptions\AnnotationException
     */
    public function testArrayTypeExceptions()
    {
        $this->object->readClass(InvalidSimpleArray::class);
    }

    /**
     * @expectedException \Annotiny\Exceptions\AnnotationException
     */
    public function testArrayComplexTypeExceptions()
    {
        $this->object->readClass(InvalidComplexArray::class);
    }

    /**
     * @expectedException \Annotiny\Exceptions\AnnotationException
     */
    public function testExceptionIsThrownWhenValueIsNotInEnum()
    {
        $this->object->readClass(WrongEnumValueClass::class);
    }

    /**
     * @expectedException \Annotiny\Exceptions\AnnotationException
     */
    public function testExceptionIsThrownWhenRequiredParamIsNotSet()
    {
        $this->object->readClass(MissingAnnotationParameterClass::class);
    }

    /**
     * @expectedException \Annotiny\Exceptions\AnnotationException
     */
    public function testExceptionIsThrownWhenCallbackIsNotCallable()
    {
        $this->object->readClass(NotCallableCallbackAttributeClass::class);
    }

    public function testReadFunction()
    {
        $this->assertInstanceOf(
            Comment::class,
            $this->object->readFunction(__NAMESPACE__ . '\fooFunction')
        );
    }

    public function testReadMethod()
    {
        $this->assertInstanceOf(
            Comment::class,
            $this->object->readMethod(TestClass::class, 'method')
        );
    }

    public function testReadProperty()
    {
        $this->assertInstanceOf(
            Comment::class,
            $this->object->readProperty(TestClass::class, 'property')
        );
    }

    public function testReadProperties()
    {
        $result = $this->object->readProperties(TestClass::class);
        $this->assertEquals(['property'], array_keys($result));
    }

    public function testReadMethods()
    {
        $result = $this->object->readMethods(TestClass::class);
        $this->assertEquals(['method'], array_keys($result));
    }
}
