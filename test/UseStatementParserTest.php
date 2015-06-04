<?php
/**
 * Created by PhpStorm.
 * User: Buga
 * Date: 2014.07.01.
 * Time: 17:05
 */

namespace Modules\Annotation;

class UseStatementParserTest extends \PHPUnit_Framework_TestCase
{
    public function testGlobalNamespaceIsReturned()
    {
        $source = '<?php ';
        $parser = new UseStatementParser($source);

        $this->assertEquals('\\', $parser->getNamespace());
    }

    public function testNamespaceIsReturned()
    {
        $source = '<?php namespace Foo;';
        $parser = new UseStatementParser($source);

        $this->assertEquals('Foo', $parser->getNamespace());
    }

    public function testLastNamespaceIsReturned()
    {
        $source = '<?php namespace Foo; namespace Bar;';
        $parser = new UseStatementParser($source);;

        $this->assertEquals('Bar', $parser->getNamespace());
    }

    public function testEmptyArrayIsReturnedWhenNoUseStatementIsPresent()
    {
        $source = '<?php namespace Foo;';
        $parser = new UseStatementParser($source);

        $uses = array();

        $this->assertEquals($uses, $parser->getImports());
    }

    public function testUseStatementsAreParsed()
    {
        $source = '<?php namespace Foo; use Foo\Bar as Foobar; use Bar\Baz;';
        $parser = new UseStatementParser($source);

        $uses = array(
            'Foobar' => 'Foo\Bar',
            'Baz'    => 'Bar\Baz'
        );

        $this->assertEquals($uses, $parser->getImports());
    }

    public function testUseStatementsAreParsedInTheLastNamespaceOnly()
    {
        $source = '<?php namespace Foo; use Foo\Bar as Foobar; namespace Foobar; use Bar\Baz;';
        $parser = new UseStatementParser($source);

        $uses = array(
            'Baz' => 'Bar\Baz'
        );

        $this->assertEquals($uses, $parser->getImports());
    }

    public function testCommaSeparatedUseStatements()
    {
        $source = '<?php namespace Foo; use Foo\Bar as Foobar, Bar\Baz;';
        $parser = new UseStatementParser($source);

        $uses = array(
            'Foobar' => 'Foo\Bar',
            'Baz' => 'Bar\Baz'
        );

        $this->assertEquals($uses, $parser->getImports());
    }

    public function testCommentInUseStatements()
    {
        $source = '<?php namespace Foo;
        use /** @noinspection PhpUnusedAliasInspection */
        Foo\Bar as Foobar; use Bar\Baz;';
        $parser = new UseStatementParser($source);

        $uses = array(
            'Foobar' => 'Foo\Bar',
            'Baz'    => 'Bar\Baz'
        );

        $this->assertEquals($uses, $parser->getImports());
    }
}
