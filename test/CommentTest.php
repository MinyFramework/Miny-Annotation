<?php

namespace Modules\Annotation;

class CommentTest extends \PHPUnit_Framework_TestCase
{

    public function testEmptyComment()
    {
        $comment = new Comment('');
        $this->assertEmpty($comment->getDescription());
        $this->assertFalse($comment->has('random tag'));
    }

    public function testComment()
    {
        $comment = new Comment('description');
        $comment->add('tag', null);
        $comment->add('array', array('a', 'b'));
        $this->assertEquals('description', $comment->getDescription());
        $this->assertFalse($comment->has('random tag'));
        $this->assertTrue($comment->has('tag'));
        $this->assertTrue($comment->equals('tag', null));
        $this->assertTrue($comment->contains('array', 'a'));
        $this->assertTrue($comment->containsAll('array', array('a', 'b')));
        $this->assertFalse($comment->containsAll('array', array('a', 'c')));
    }

    public function testToString()
    {
        $comment = new Comment('description');
        $this->assertEquals('description', (string)$comment);
    }

    public function testArrayInterface()
    {
        $comment = new Comment('description');
        $this->assertFalse(isset($comment['foo']));
        $comment['foo'] = 'foo value';
        $this->assertTrue(isset($comment['foo']));
        $this->assertEquals('foo value', $comment['foo']);
    }

    public function testAdd()
    {
        $comment = new Comment('');
        $this->assertFalse($comment->has('tag'));
        $comment->add('tag', 'value');
        $this->assertTrue($comment->has('tag'));
        $this->assertEquals('value', $comment->get('tag'));
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Comment does not have @tag annotation.
     */
    public function testGetException()
    {
        $comment = new Comment('');
        $comment->get('tag');
    }

    public function testClassAnnotationInterface()
    {
        $comment = new Comment('');
        $comment->addAnnotation('someclass', 'whatever');
        $comment->addAnnotation('someclass', 'also whatever');

        $this->assertFalse($comment->hasAnnotationType('whatever'));
        $this->assertTrue($comment->hasAnnotationType('someclass'));

        $this->assertEquals(
            array('whatever', 'also whatever'),
            $comment->getAnnotationType('someclass')
        );
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testClassAnnotationInterfaceException()
    {
        $comment = new Comment('');
        $comment->getAnnotationType('someclass');
    }
}
