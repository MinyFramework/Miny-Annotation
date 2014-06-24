<?php

namespace Modules\Annotation;

/**
 * Test class.
 * @see foo
 */
class TestClass {
    /**
     * Property
     */
    public $property;

    /**
     * @foo
     */
    public function method(){}
}

/**
 * Function docs.
 */
function fooFunction(){}

class AnnotationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Annotation
     */
    protected $object;

    protected function setUp()
    {
        $this->object = new Annotation(new AnnotationParser, new CommentFactory);
    }

    public function testReadClass()
    {
        $this->assertInstanceOf('Modules\Annotation\Comment', $this->object->readClass('Modules\Annotation\TestClass'));
    }

    public function testReadFunction()
    {
        $this->assertInstanceOf('Modules\Annotation\Comment', $this->object->readFunction('Modules\Annotation\fooFunction'));
    }

    public function testReadMethod()
    {
        $this->assertInstanceOf('Modules\Annotation\Comment', $this->object->readMethod('Modules\Annotation\TestClass', 'method'));
    }

    public function testReadProperty()
    {
        $this->assertInstanceOf('Modules\Annotation\Comment', $this->object->readProperty('Modules\Annotation\TestClass','property'));
    }
}
