<?php
namespace QueryBuilder\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder\Delete;
use QueryBuilder\QueryBuilder\Raw;

final class DeleteTest extends TestCase {
	public function testDeleteEntireTable() {
		$table = 'table-name';
		$delete = new Delete($table);

		$this->assertEquals("Delete From `$table`", $delete->toString());
	}

	public function testDeleteWithWhere() {
		$table = 'table-name';
		list($sql, $params) = (new Delete($table))
			->where('foo', 'someValue')
			->where(array('bla' => 1, 'boo' => '2020-09-18'))
			->where('bar', '>=', 'other')
			->where(new Raw('?? <> ?', array('col1', 'value1')))
			->where('bar', '>', 'other2')
			->build();

		$this->assertEquals(
			"Delete From `$table` Where `foo` = ? And `bla` = ? And `boo` = ? And `bar` >= ? And `col1` <> ? And `bar` > ?",
			$sql
		);

		$this->assertEquals(array('someValue', 1, '2020-09-18', 'other', 'value1', 'other2'), $params);
	}

	public function testDeleteWithLimit() {
		$table = 'table-name';
		list($sql, $params) = (new Delete($table))
			->where('foo', 'someValue')
			->limit(1)
			->build();

		$this->assertEquals("Delete From `$table` Where `foo` = ? Limit 1", $sql);

		$this->assertEquals(array('someValue'), $params);
	}

	public function testDeleteWithOrder() {
		$table = 'table-name';
		list($sql, $params) = (new Delete($table))
			->where('foo', 'someValue')
			->orderBy(array('bla'))
			->build();

		$this->assertEquals("Delete From `$table` Where `foo` = ? Order By `bla` asc", $sql);
	}

	public function testDeleteFullBlown() {
		$table = 'table-name';
		list($sql, $params) = (new Delete(array('alias' => $table)))
			->where('foo', 'someValue')
			->orderBy(array('bla'))
			->limit(1, 2)
			->build();

		$this->assertEquals("Delete From `$table` as `alias` Where `foo` = ? Order By `bla` asc Limit 2, 1", $sql);
		$this->assertEquals(array('someValue'), $params);
	}

	public function testDeleteRaw() {
		$table = 'table-name';

		list($sql, $params) = (new Delete($table))
			->raw('Delete from ?? where ?? = ?', array($table, 'col', 2))
			->build();

		$this->assertEquals("Delete from `$table` where `col` = ?", $sql);
		$this->assertEquals(array(2), $params);
	}

	public function testExecute() {
		$called = false;
		$args = array();

		$delete = new Delete('test');
		$delete->setCallback(function () use (&$args, &$called) {
			$called = true;
			$args = func_get_args();

			return 1;
		});

		$resultSet = $delete->debug()->execute();

		$this->assertTrue($called);

		$this->assertEquals(array('Delete From `test`', array(), true), $args);
		$this->assertEquals(1, $resultSet);
	}
}
