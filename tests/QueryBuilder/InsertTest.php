<?php
namespace QueryBuilder\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder\Func;
use QueryBuilder\QueryBuilder\Insert;
use QueryBuilder\QueryBuilder\Raw;


final class InsertTest extends TestCase {
	public function testInsertSimple() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->columns(array('name', 'email'))
			->values(array('foo', 'my@email.com'))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?)", $sql);
		$this->assertEquals(array('foo', 'my@email.com'), $params);
	}

	public function testInsertUsingAssoc() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array('name' => 'foo', 'email' => 'my@email.com'))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?)", $sql);
		$this->assertEquals(array('foo', 'my@email.com'), $params);
	}

	public function testInsertUsingAssocAndColumns() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->columns(array('name', 'email'))
			->values(array('name_assoc' => 'foo', 'email_assoc' => 'my@email.com'))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?)", $sql);
		$this->assertEquals(array('foo', 'my@email.com'), $params);
	}

	public function testInsertUsingAssocMultiInvoke() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array('name' => 'foo', 'email' => 'foo@email.com'))
			->values(array('name' => 'bar', 'email' => 'bar@email.com'))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?), (?, ?)", $sql);
		$this->assertEquals(array('foo', 'foo@email.com', 'bar', 'bar@email.com'), $params);
	}

	public function testInsertMultiInsert() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->columns(array('name', 'email'))
			->values(array(
				array('name' => 'foo', 'email' => 'foo@email.com'),
				array('name' => 'bar', 'email' => 'bar@email.com'),
			))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?), (?, ?)", $sql);
		$this->assertEquals(array('foo', 'foo@email.com', 'bar', 'bar@email.com'), $params);
	}

	public function testInsertUsingAssocMultiInsert() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array(
				array('name' => 'foo', 'email' => 'foo@email.com'),
				array('name' => 'bar', 'email' => 'bar@email.com'),
			))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `email`) Values (?, ?), (?, ?)", $sql);
		$this->assertEquals(array('foo', 'foo@email.com', 'bar', 'bar@email.com'), $params);
	}

	public function testInsertIgnore() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->ignore()
			->values(array(
				array('name' => 'foo', 'email' => 'foo@email.com'),
				array('name' => 'bar', 'email' => 'bar@email.com'),
			))
			->build();

		$this->assertEquals("Insert Ignore Into `$table` (`name`, `email`) Values (?, ?), (?, ?)", $sql);
		$this->assertEquals(array('foo', 'foo@email.com', 'bar', 'bar@email.com'), $params);
	}

	public function testInsertOnDuplicateUpdateSimple() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array(array('name' => 'foo', 'email' => 'foo@email.com')))
			->onDuplicateUpdate(array('name' => 'bar', 'email' => 'bar@email.com'))
			->build();

		$this->assertEquals(
			"Insert Into `$table` (`name`, `email`) Values (?, ?) On Duplicate Key Update `name` = ?, `email` = ?",
			$sql
		);
		$this->assertEquals(array('foo', 'foo@email.com', 'bar', 'bar@email.com'), $params);
	}

	public function testInsertOnDuplicateUpdateUsingValues() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array(array('name' => 'foo', 'email' => 'foo@email.com')))
			->onDuplicateUpdate(array('name'))
			->build();

		$this->assertEquals(
			"Insert Into `$table` (`name`, `email`) Values (?, ?) On Duplicate Key Update `name` = Values(`name`)",
			$sql
		);
		$this->assertEquals(array('foo', 'foo@email.com'), $params);
	}

	public function testInsertOnDuplicateUpdateUsingRaw() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array(array('name' => 'foo', 'email' => 'foo@email.com')))
			->onDuplicateUpdate(array('name' => new Raw('Replace(?, ?, ??)', array('bla', '', 'name'))))
			->build();

		$this->assertEquals(
			"Insert Into `$table` (`name`, `email`) Values (?, ?) On Duplicate Key Update `name` = Replace(?, ?, `name`)",
			$sql
		);
		$this->assertEquals(array('foo', 'foo@email.com', 'bla', ''), $params);
	}

	public function testInsertRaw() {
		$table = 'table-name';

		list($sql, $params) = (new Insert($table))
			->raw('Insert Into ?? (??,??) values (?,?)', array($table, 'name', 'email', 'foo', 'foo@email.com'))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`,`email`) values (?,?)", $sql);
		$this->assertEquals(array('foo', 'foo@email.com'), $params);
	}

	public function testInsertUsingFunc() {
		$table = 'table-name';
		list($sql, $params) = (new Insert($table, null))
			->values(array('name' => 'foo', 'last_update' => new Func('Now')))
			->build();

		$this->assertEquals("Insert Into `$table` (`name`, `last_update`) Values (?, Now())", $sql);
		$this->assertEquals(array('foo'), $params);
	}

	public function testExecute() {
		$called = false;
		$args = array();

		$insert = new Insert('test');
		$insert->setCallback(function () use (&$args, &$called) {
			$called = true;
			$args = func_get_args();

			return 1;
		});

		$resultSet = $insert
			->values(array('col' => 'value'))
			->debug()
			->execute();

		$this->assertTrue($called);

		$this->assertEquals(array('Insert Into `test` (`col`) Values (?)', array('value'), true), $args);
		$this->assertEquals(1, $resultSet);
	}
}
