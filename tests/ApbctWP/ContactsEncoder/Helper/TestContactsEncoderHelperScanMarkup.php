<?php

namespace ApbctWP\ContactsEncoder\Helper;

use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderHelperScanMarkup extends TestCase
{
    private function names($content)
    {
        return array_map(static function ($token) {
            return ($token['closing'] ? '/' : '') . $token['name'];
        }, ContactsEncoderHelper::scanMarkup($content));
    }

    public function testTagsAndAttributes()
    {
        $content = '<a href="x>y" title=\'t\' data-x=1 hidden>text</a>';
        $tokens = ContactsEncoderHelper::scanMarkup($content);

        $this->assertSame(array('a', '/a'), $this->names($content));
        $this->assertSame(strpos($content, 'text'), $tokens[0]['end']);

        $attributes = array();
        foreach ( $tokens[0]['attributes'] as $attribute ) {
            $attributes[$attribute[0]] = substr($content, $attribute[2], $attribute[3] - $attribute[2]);
        }
        $this->assertSame(array('href' => 'x>y', 'title' => 't', 'data-x' => '1', 'hidden' => ''), $attributes);
    }

    public function testLessThanInTextIsNotATag()
    {
        $this->assertSame(array(), ContactsEncoderHelper::scanMarkup('2 < 3 and <3 and < b'));
    }

    public function testQuoteInAttributeNameDoesNotOpenAValue()
    {
        // For a browser the first quote is a part of the attribute name, the tag ends at the first ">".
        $content = '<b x" >text "</b>';
        $tokens = ContactsEncoderHelper::scanMarkup($content);

        $this->assertSame(strpos($content, 'text'), $tokens[0]['end']);
    }

    public function testUnclosedValueTakesTheRestOfTheContent()
    {
        $content = '<a title="never closed> [sc]';
        $tokens = ContactsEncoderHelper::scanMarkup($content);

        $this->assertCount(1, $tokens);
        $this->assertSame(strlen($content), $tokens[0]['end']);
    }

    /**
     * @dataProvider commentProvider
     */
    public function testCommentEnd($content, $expected_end)
    {
        $tokens = ContactsEncoderHelper::scanMarkup($content);

        $this->assertSame('!--', $tokens[0]['name']);
        $this->assertSame($expected_end, $tokens[0]['end']);
    }

    public function commentProvider()
    {
        return array(
            'regular' => array('<!-- a --> b', 10),
            'abrupt' => array('<!--> b --> c', 5),
            'abrupt dash' => array('<!---> b', 6),
            'bang' => array('<!-- a --!> b', 11),
            'unclosed' => array('<!-- a b', 8),
        );
    }

    public function testTagsInsideACommentAreNotTags()
    {
        $this->assertSame(array('!--', 'p'), $this->names('<!-- <a href="x"> --><p>'));
    }

    public function testRawTextElementsAreNotParsed()
    {
        $this->assertSame(
            array('script', '/script', 'p', 'textarea', '/textarea'),
            $this->names('<script>if (a<b && "c") {}</script><p><textarea><a href="x"></textarea>')
        );
    }

    public function testFindTokenIndex()
    {
        $content = 'x <a title="[sc]"> [sc] <!-- [sc] --> [sc]';
        $tokens = ContactsEncoderHelper::scanMarkup($content);
        $found = array();
        $offset = -1;
        while ( ($offset = strpos($content, '[sc]', $offset + 1)) !== false ) {
            $found[] = ContactsEncoderHelper::findTokenIndex($tokens, $offset) !== false;
        }

        $this->assertSame(array(true, false, true, false), $found);
    }

    public function testLinearOnManyUnclosedTags()
    {
        $start = microtime(true);
        ContactsEncoderHelper::scanMarkup(str_repeat('<a "', 16000));
        ContactsEncoderHelper::scanMarkup(str_repeat('<!-', 21000));
        ContactsEncoderHelper::scanMarkup(str_repeat('<script>', 8000));
        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    public function testGuardsDoNotDependOnContentSize()
    {
        $helper = new ContactsEncoderHelper();
        $email = 'a@b.cd';
        $content = 'Contacts: <abbr title="' . str_repeat($email . ' ', 9282) . '">x</abbr>';
        $helper->indexMarkup($content);
        $positions = array();
        $offset = 0;
        while ( ($offset = strpos($content, $email, $offset)) !== false ) {
            $positions[] = $offset;
            $offset += 6;
        }

        $start = microtime(true);
        foreach ( $positions as $position ) {
            $helper->isMailtoAdditionalCopy($email, $content, $position);
            $helper->hasAttributeExclusions($email, $content, $position);
            $helper->isInsideOptionTag($email, $content, $position);
            $helper->isInsideAttributeValue($email, $content, $position);
            $helper->enclosingTagHasAttribute($content, $position, 'title');
        }

        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    /**
     * @dataProvider guardProvider
     */
    public function testGuards($method, $content, $expected)
    {
        $helper = new ContactsEncoderHelper();

        $this->assertSame($expected, $helper->$method('a@b.cd', $content, strpos($content, 'a@b.cd')));
    }

    public function guardProvider()
    {
        return array(
            'cc' => array('isMailtoAdditionalCopy', 'mailto:x@y.zz?cc=a@b.cd', true),
            'bcc' => array('isMailtoAdditionalCopy', 'mailto:x@y.zz?bcc=a@b.cd', true),
            'not a copy' => array('isMailtoAdditionalCopy', 'succ a@b.cd', false),
            'option text' => array('isInsideOptionTag', '<select><option value="1">a@b.cd</option></select>', true),
            'option attribute' => array('isInsideOptionTag', '<option value="a@b.cd">x</option>', false),
            'after option' => array('isInsideOptionTag', '<option>x</option> a@b.cd', false),
            'input value' => array('hasAttributeExclusions', '<input type="text" value="a@b.cd">', true),
            'img alt' => array('hasAttributeExclusions', '<img src="x" alt="a@b.cd">', true),
            'abbr title' => array('hasAttributeExclusions', '<abbr title="a@b.cd">x</abbr>', false),
            'text' => array('hasAttributeExclusions', '<input value="x"> a@b.cd', false),
            'attribute with both quotes' => array('isInsideAttributeValue', '<a title="it\'s a@b.cd">x</a>', true),
            'text after a quoted > ' => array('isInsideAttributeValue', '<a title="x>y">a@b.cd</a>', false),
            'text after an unbalanced quote in a name' => array('isInsideAttributeValue', '<b x">a@b.cd</b>', false),
        );
    }
}
