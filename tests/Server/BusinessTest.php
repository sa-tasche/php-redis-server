<?php

use Clue\Redis\Server\Business;
use Clue\Redis\Server\Storage;
use Clue\Redis\Protocol\Model\StatusReply;

class BusinessTest extends TestCase
{
    private $business;
    private $storage;

    public function setUp()
    {
        $this->storage = new Storage();
        $this->business = new Business($this->storage);
    }

    public function testPing()
    {
        $this->assertEquals(new StatusReply('PONG'), $this->business->ping());
    }

    public function testStorage()
    {
        $this->assertEquals(0, $this->business->exists('test'));
        $this->assertEquals(new StatusReply('OK'), $this->business->set('test', 'value'));
        $this->assertEquals(1, $this->business->exists('test'));
        $this->assertEquals('value', $this->business->get('test'));
    }

    public function testSetNx()
    {
        $this->assertEquals(1, $this->business->setnx('test', 'value1'));
        $this->assertEquals(0, $this->business->setnx('test', 'value2'));
        $this->assertEquals('value1', $this->business->get('test'));
    }

    public function testStorageParams()
    {
        $this->assertNull($this->business->set('test', 'value', 'xx'));
        $this->assertNull($this->business->get('test'));

        $this->assertEquals(new StatusReply('OK'), $this->business->set('test', 'value', 'nx'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertNull($this->business->set('test', 'newvalue', 'nx'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertEquals(new StatusReply('OK'), $this->business->set('test', 'newvalue', 'xx'));
        $this->assertEquals('newvalue', $this->business->get('test'));
    }

    public function testIncrement()
    {
        $this->assertEquals(1, $this->business->incr('counter'));
        $this->assertEquals(2, $this->business->incr('counter'));

        $this->assertEquals(12, $this->business->incrby('counter', 10));

        $this->assertEquals(11, $this->business->decr('counter'));

        $this->assertEquals(9, $this->business->decrby('counter', 2));
    }

    /**
     *
     * @expectedException Clue\Redis\Server\InvalidDatatypeException
     */
    public function testIncrementInvalid()
    {
        $this->business->set('a', 'hello');
        $this->business->incr('a');
    }

    public function testMultiGetSet()
    {
        $this->assertEquals(array(null, null), $this->business->mget('a', 'b'));

        $this->assertEquals(new StatusReply('OK'), $this->business->mset('a', 'value1', 'c', 'value2'));

        $this->assertEquals(array('value1', null, 'value2'), $this->business->mget('a', 'b', 'c'));
    }

    public function testMsetNx()
    {
        $this->assertEquals(1, $this->business->msetnx('a', 'b', 'c', 'd'));
        $this->assertEquals(array('b', 'd'), $this->business->mget('a', 'c'));

        $this->assertEquals(0, $this->business->msetnx('b', 'c', 'c', 'e'));
    }

    /**
     * @expectedException Exception
     */
    public function testMsetInvalidNumberOfArguments()
    {
        $this->business->mset('a', 'b', 'c');
    }

    public function testStrlen()
    {
        $this->assertEquals(0, $this->business->strlen('key'));

        $this->business->set('key', 'value');

        $this->assertEquals(5, $this->business->strlen('key'));
    }

    public function testDel()
    {
        $this->assertEquals(0, $this->business->del('a', 'b', 'c'));

        $this->business->set('a', 'a');
        $this->business->set('c', 'c');

        $this->assertEquals(2, $this->business->del('a', 'b', 'c', 'd'));
    }

    public function testList()
    {
        $this->assertEquals(0, $this->business->llen('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'b'));
        $this->assertEquals(2, $this->business->rpush('list', 'c'));
        $this->assertEquals(3, $this->business->lpush('list', 'a'));

        $this->assertEquals(3, $this->business->llen('list'));
        $this->assertEquals(new StatusReply('list'), $this->business->type('list'));

        $this->assertEquals('c', $this->business->rpop('list'));
        $this->assertEquals('a', $this->business->lpop('list'));
        $this->assertEquals('b', $this->business->lpop('list'));

        $this->assertNull($this->business->lpop('list'));

        $this->assertEquals(0, $this->business->exists('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'a'));
        $this->assertEquals('a', $this->business->rpop('list'));
        $this->assertEquals(null, $this->business->rpop('list'));

        $this->assertEquals(0, $this->business->exists('list'));
        $this->assertEquals(new StatusReply('none'), $this->business->type('list'));
    }

    public function testLpushOrder()
    {
        $this->assertEquals(3, $this->business->lpush('list', 'a', 'b', 'c'));
        $this->assertEquals('c', $this->business->lpop('list'));
        $this->assertEquals('b', $this->business->lpop('list'));
        $this->assertEquals('a', $this->business->lpop('list'));
        $this->assertNull($this->business->lpop('list'));
    }

    public function testAppend()
    {
        $this->assertEquals(5, $this->business->append('test', 'value'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertEquals(8, $this->business->append('test', '123'));
        $this->assertEquals('value123', $this->business->get('test'));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testAppendListFails()
    {
        $this->assertEquals(1, $this->business->lpush('list', 'value'));

        $this->business->append('list', 'invalid');
    }

    public function testGetrange()
    {
        $this->assertEquals(new StatusReply('OK'), $this->business->set('test', 'This is a string'));

        $this->assertEquals('This', $this->business->getrange('test', 0, 3));
        $this->assertEquals('ing', $this->business->getrange('test', -3, -1));
        $this->assertEquals('This is a string', $this->business->getrange('test', 0, -1));
        $this->assertEquals('string', $this->business->getrange('test', 10, 100));
        $this->assertEquals('', $this->business->getrange('test', 100, 200));

        $this->assertEquals('', $this->business->getrange('unknown', 0, 3));
    }

    public function testSetrange()
    {
        $this->assertEquals(11, $this->business->setrange('test', 6, 'world'));
        $this->assertEquals("\0\0\0\0\0\0world", $this->business->get('test'));

        $this->assertEquals(11, $this->business->setrange('test', 0, 'hello'));
        $this->assertEquals("hello\0world", $this->business->get('test'));

        $this->assertEquals(12, $this->business->setrange('test', 5, ' world!'));
        $this->assertEquals("hello world!", $this->business->get('test'));
    }

    public function testGetset()
    {
        $this->assertEquals(null, $this->business->getset('test', 'a'));
        $this->assertEquals('a', $this->business->getset('test', 'b'));
    }
}
