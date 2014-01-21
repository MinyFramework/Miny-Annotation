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
        $comment = new Comment('description', array('tag' => null, 'array' => array('a', 'b')));
        $this->assertEquals('description', $comment->getDescription());
        $this->assertFalse($comment->has('random tag'));
        $this->assertTrue($comment->has('tag'));
        $this->assertTrue($comment->equals('tag', null));
        $this->assertTrue($comment->contains('array', 'a'));
        $this->assertTrue($comment->containsAll('array', array('a', 'b')));
        $this->assertFalse($comment->containsAll('array', array('a', 'c')));
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
     * @expectedExceptionMessage Comment doesn't have @tag annotation.
     */
    public function testGetException()
    {
        $comment = new Comment('');
        $comment->get('tag');
    }
}
