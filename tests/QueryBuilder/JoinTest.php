<?php

namespace QueryBuilder\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder\Raw;
use QueryBuilder\QueryBuilder\Select;


final class JoinTest extends TestCase {

	public function testJoin() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->join('joinedTable as j', 'j.col', '=', "$table.id")->build();

		$this->assertEquals("Select * From `$table` Left Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`", $sql);

		$this->assertEmpty($params);
	}

	public function testLeftJoin() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->leftJoin('joinedTable as j', 'j.col', '=', "$table.id")->build();

		$this->assertEquals("Select * From `$table` Left Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`", $sql);

		$this->assertEmpty($params);
	}

	public function testRightJoin() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->rightJoin('joinedTable as j', 'j.col', '=', "$table.id")->build();

		$this->assertEquals(
			"Select * From `$table` Right Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`",
			$sql
		);

		$this->assertEmpty($params);
	}

	public function testInnerJoin() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->innerJoin('joinedTable as j', 'j.col', '=', "$table.id")->build();

		$this->assertEquals(
			"Select * From `$table` Inner Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`",
			$sql
		);

		$this->assertEmpty($params);
	}

	public function testOuterJoin() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->outerJoin('joinedTable as j', 'j.col', '=', "$table.id")->build();

		$this->assertEquals(
			"Select * From `$table` Outer Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`",
			$sql
		);

		$this->assertEmpty($params);
	}

	public function testJoinWithoutOperator() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->leftJoin('joinedTable as j', 'j.col', "$table.id")->build();

		$this->assertEquals("Select * From `$table` Left Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`", $sql);

		$this->assertEmpty($params);
	}

	public function testJoinWithMultipleOns() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->leftJoin('joinedTable as j', function ($qb) use ($table) {
			return $qb->on('j.col', "$table.id")->orOn('j.col', new Raw('?', array(2)))->andOn('j.col', '>', new Raw('?', array(4)));
		})->build();

		$this->assertEquals("Select * From `$table` Left Join `joinedTable` as `j` On `j`.`col` = `$table`.`id` Or `j`.`col` = ? And `j`.`col` > ?", $sql);

		$this->assertEquals(array(2, 4), $params);
	}

	public function testJoinWithOneOns() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))->leftJoin('joinedTable as j', function ($qb) use ($table) {
			return $qb->on('j.col', "$table.id");
		})->build();

		$this->assertEquals("Select * From `$table` Left Join `joinedTable` as `j` On `j`.`col` = `$table`.`id`", $sql);

		$this->assertEmpty($params);
	}

}
