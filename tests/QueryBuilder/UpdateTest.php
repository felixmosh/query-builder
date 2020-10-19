<?php
namespace QueryBuilder\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder\Raw;
use QueryBuilder\QueryBuilder\Update;

final class UpdateTest extends TestCase {
	public function testUpdate() {
		$table = 'table-name';
		list($sql, $params) = (new Update($table, null))->set('name', 'foo')->build();

		$this->assertEquals("Update `$table` Set `name` = ?", $sql);
		$this->assertEquals(array('foo'), $params);
	}

	public function testUpdateWithMultipleInvocations() {
		$table = 'table-name';
		list($sql, $params) = (new Update($table, null))
			->set('name', 'foo')
			->set('email', 'my@email.com')
			->build();

		$this->assertEquals("Update `$table` Set `name` = ?, `email` = ?", $sql);
		$this->assertEquals(array('foo', 'my@email.com'), $params);
	}

	public function testUpdateWithAssocArray() {
		$table = 'table-name';
		list($sql, $params) = (new Update($table, null))
			->set(array('name' => 'foo', 'email' => 'my@email.com'))
			->build();

		$this->assertEquals("Update `$table` Set `name` = ?, `email` = ?", $sql);
		$this->assertEquals(array('foo', 'my@email.com'), $params);
	}

	public function testUpdateWithWhere() {
		$table = 'table-name';
		list($sql, $params) = (new Update($table))
			->set('name', 'foo')
			->where('foo', 'someValue')
			->where(array('bla' => 1, 'boo' => '2020-09-18'))
			->where('bar', '>=', 'other')
			->where(new Raw('?? <> ?', array('col1', 'value1')))
			->where('bar', '>', 'other2')
			->build();

		$this->assertEquals(
			"Update `$table` Set `name` = ? Where `foo` = ? And `bla` = ? And `boo` = ? And `bar` >= ? And `col1` <> ? And `bar` > ?",
			$sql
		);

		$this->assertEquals(array('foo', 'someValue', 1, '2020-09-18', 'other', 'value1', 'other2'), $params);
	}

	public function testUpdateRaw() {
		$table = 'table-name';

		list($sql, $params) = (new Update($table))
			->raw('Update ?? Set ?? = ? where ?? = ?', array($table, 'foo', 'bar', 'col', 2))
			->build();

		$this->assertEquals("Update `$table` Set `foo` = ? where `col` = ?", $sql);
		$this->assertEquals(array('bar', 2), $params);
	}

	public function testExecute() {
		$called = false;
		$args = array();

		$update = new Update('test');
		$update->setCallback(function () use (&$args, &$called) {
			$called = true;
			$args = func_get_args();

			return 1;
		});

		$resultSet = $update
			->set(array('col' => 'value'))
			->debug()
			->execute();

		$this->assertTrue($called);

		$this->assertEquals(array('Update `test` Set `col` = ?', array('value'), true), $args);
		$this->assertEquals(1, $resultSet);
	}
}
