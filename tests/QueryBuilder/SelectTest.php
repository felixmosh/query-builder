<?php

namespace QueryBuilder\Tests\QueryBuilder;

use PHPUnit\Framework\TestCase;
use QueryBuilder\QueryBuilder\Raw;
use QueryBuilder\QueryBuilder\Select;

final class SelectTest extends TestCase {
	public function testSelectWithAsterisk() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals("Select * From `$table`", $select->toString());
	}

	public function testColumnSelection() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals("Select `col` From `$table`", $select->column('col')->toString());
	}

	public function testTableNameAlias() {
		$table = 'table-name';
		$select = new Select(array('alias' => $table), null);
		$this->assertEquals("Select `col` From `$table` as `alias`", $select->column('col')->toString());
	}

	public function testTableNameAsArray() {
		$table = 'table-name';
		$select = new Select(array($table), null);
		$this->assertEquals("Select `col` From `$table`", $select->column('col')->toString());
	}

	public function testColumnSelectionWithAlias() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals(
			"Select `col` as `alias` From `$table`",
			$select->column(array('alias' => 'col'))->toString()
		);
	}

	public function testColumnsSelectionWithMixed() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals(
			"Select `col` as `alias`, `without-alias`, `addition`, `addition-2` From `$table`",
			$select
				->columns(array('alias' => 'col', 'without-alias'))
				->column('addition')
				->columns(array('addition-2'))
				->toString()
		);
	}

	public function testCount() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals("Select Count(*) as `c` From `$table`", $select->count()->toString());
	}

	public function testCountWithAlias() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals("Select Count(*) as `alias` From `$table`", $select->count('', 'alias')->toString());
	}

	public function testCountWithSpecificColumn() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals("Select Count(`col`) as `alias` From `$table`", $select->count('col', 'alias')->toString());
	}

	public function testCountWithSpecificColumnAsAssoc() {
		$table = 'table-name';
		$select = new Select($table, null);
		$this->assertEquals(
			"Select Count(`col`) as `alias` From `$table`",
			$select->count(array('alias' => 'col'))->toString()
		);
	}

	public function testSubQueryAsColumn() {
		$table = 'table-name';
		$select = new Select($table);
		$this->assertEquals(
			"Select (Select * From `sub-table`) as `alias` From `$table`",
			$select->column(array('alias' => new Select('sub-table')))->toString()
		);
	}

    public function testRawColumn() {
        $table = 'table-name';
        $column = 'column-name';

        $select = new Select($table);
        $this->assertEquals(
            "Select `$column` From `$table`",
            $select->column(new Raw('??', array($column)))->toString()
        );
    }

	public function testRawColumns() {
		$table = 'table-name';
		$column = 'column-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select `$column` as `alias` From `$table`",
			$select->column(array('alias' => new Raw('??', array($column))))->toString()
		);
	}

	public function testRawFuncColumns() {
		$table = 'table-name';
		$column = 'column-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select Count(`column-name`) as `alias` From `$table`",
			$select->count(array('alias' => new Raw('??', array($column))))->toString()
		);
	}

	public function testDistinctColumns() {
		$table = 'table-name';
		$column = 'column-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select Distinct Count(`column-name`) as `alias` From `$table`",
			$select
				->count(array('alias' => new Raw('??', array($column))))
				->distinct()
				->toString()
		);
	}

	public function testToString() {
		$table = 'table-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select Count(*) as `c` From `$table` Where `foo` = \"baz\" Or `bar` > \"2\" Limit 1",
			$select
				->count()
				->where('foo', 'baz')
				->orWhere(new Raw('?? > ?', array('bar', 2)))
				->limit(1)
				->toString()
		);
	}

	public function testLimit() {
		$table = 'table-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select Count(*) as `c` From `$table` Limit 1",
			$select
				->count()
				->limit(1)
				->toString()
		);
	}

	public function testLimitWithOffset() {
		$table = 'table-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select Count(*) as `c` From `$table` Limit 2, 1",
			$select
				->count()
				->limit(1, 2)
				->toString()
		);
	}

	public function testTableAliasEscape() {
		$table = 'table-name as p';

		$select = new Select($table);
		$this->assertEquals('Select * From `table-name` as `p`', $select->toString());
	}

	public function testOrderBy() {
		$table = 'table-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select * From `$table` Order By `bar` Desc, `foo` Asc, `baz` Asc",
			$select
				->orderBy(array('bar' => 'desc', 'foo'))
				->orderBy('baz')
				->toString()
		);
	}

	public function testGroupBy() {
		$table = 'table-name';

		$select = new Select($table);
		$this->assertEquals(
			"Select * From `$table` Group By `foo`, `bar`, `baz`",
			$select
				->groupBy(array('foo', 'bar'))
				->groupBy('baz')
				->toString()
		);
	}

	public function testBasicWhere() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->where('foo', 'someValue')
			->where(array('bla' => 1, 'boo' => '2020-09-18'))
			->where('bar', '>=', 'other')
			->where(new Raw('?? <> ?', array('col1', 'value1')))
			->where('bar', '>', 'other2')
			->build();

		$this->assertEquals(
			"Select * From `$table` Where `foo` = ? And `bla` = ? And `boo` = ? And `bar` >= ? And `col1` <> ? And `bar` > ?",
			$sql
		);

		$this->assertEquals(array('someValue', 1, '2020-09-18', 'other', 'value1', 'other2'), $params);
	}

	public function testOrWhere() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->orWhere(array('bla' => 1, 'boo' => '2020-09-18'))
			->orWhere('foo', 'someValue')
			->orWhere('bar', '>=', 'other')
			->orWhere(new Raw('?? <> ?', array('col1', 'value1')))
			->orWhere('bar', '>', 'other2')
			->build();

		$this->assertEquals(
			"Select * From `$table` Where `bla` = ? Or `boo` = ? Or `foo` = ? Or `bar` >= ? Or `col1` <> ? Or `bar` > ?",
			$sql
		);

		$this->assertEquals(array(1, '2020-09-18', 'someValue', 'other', 'value1', 'other2'), $params);
	}

	public function testMixedWhere() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->where(array('bla' => 1, 'boo' => '2020-09-18'))
			->orWhere('foo', 'someValue')
			->build();

		$this->assertEquals("Select * From `$table` Where `bla` = ? And `boo` = ? Or `foo` = ?", $sql);

		$this->assertEquals(array(1, '2020-09-18', 'someValue'), $params);
	}

	public function testRawWhere() {
		$table = 'table-name';

		$select = (new Select($table))
			->where(new Raw('?? is not Null', array('foo')))
			->orWhere(new Raw('?? > ?', array('bar', 2)));

		$this->assertEquals("Select * From `$table` Where `foo` is not Null Or `bar` > \"2\"", $select->toString());
	}

	public function testAndWhere() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->andWhere(array('bla' => 1, 'boo' => '2020-09-18'))
			->andWhere('foo', 'someValue')
			->andWhere('bar', '>=', 'other')
			->andWhere(new Raw('?? <> ?', array('col1', 'value1')))
			->andWhere('bar', '>', 'other2')
			->build();

		$this->assertEquals(
			"Select * From `$table` Where `bla` = ? And `boo` = ? And `foo` = ? And `bar` >= ? And `col1` <> ? And `bar` > ?",
			$sql
		);

		$this->assertEquals(array(1, '2020-09-18', 'someValue', 'other', 'value1', 'other2'), $params);
	}

	public function testWhereIn() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->where('foo', 'bar')
			->whereIn('empty')
			->whereIn('baz', array('boo', 1))
			->build();

		$this->assertEquals("Select * From `$table` Where `foo` = ? And `baz` In (?, ?)", $sql);

		$this->assertEquals(array('bar', 'boo', 1), $params);
	}

	public function testWhereNotIn() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->whereNotIn('empty')
			->whereNotIn('baz', array('boo', 1))
			->build();

		$this->assertEquals("Select * From `$table` Where `baz` Not In (?, ?)", $sql);

		$this->assertEquals(array('boo', 1), $params);
	}

	public function testWhereNull() {
		$table = 'table-name';

		list($sql) = (new Select($table))
			->whereNull('foo')
			->whereNotNull('bar')
			->build();

		$this->assertEquals("Select * From `$table` Where `foo` Is Null And `bar` Is Not Null", $sql);
	}

	public function testWhereBetween() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->whereBetween('foo', '2020-01-01', '2020-09-20')
			->whereNotBetween('bar', '2020-02-01', '2020-10-20')
			->build();

		$this->assertEquals("Select * From `$table` Where `foo` Between ? And ? And `bar` Not Between ? And ?", $sql);

		$this->assertEquals(array('2020-01-01', '2020-09-20', '2020-02-01', '2020-10-20'), $params);
	}

	public function testRawQuery() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->raw('Select * from ?? Where ??=?', array($table, 'col', 1))
			->build();

		$this->assertEquals("Select * from `$table` Where `col`=?", $sql);

		$this->assertEquals(array(1), $params);
	}

	public function testSubQueryInWhere() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->where('foo', '=', (new Select('sub-table'))->column('id')->where('id', 1))
			->build();

		$this->assertEquals("Select * From `$table` Where `foo` = (Select `id` From `sub-table` Where `id` = ?)", $sql);

		$this->assertEquals(array(1), $params);
	}

	public function testWhereExists() {
		$table = 'table-name';

		list($sql, $params) = (new Select($table))
			->whereExists((new Select('sub-table'))->column('id')->where('id', 1))
			->whereNotExists((new Select('sub-table2'))->column('id')->where('id', 2))
			->build();

		$this->assertEquals(
			"Select * From `$table` Where Exists (Select `id` From `sub-table` Where `id` = ?) And Not Exists (Select `id` From `sub-table2` Where `id` = ?)",
			$sql
		);

		$this->assertEquals(array(1, 2), $params);
	}

	public function testSubQueryInFrom() {
		list($sql, $params) = (new Select(array(
			'alias' => (new Select('innerTable'))->column('id')->where('id', 1),
		)))->build();

		$this->assertEquals('Select * From (Select `id` From `innerTable` Where `id` = ?) as `alias`', $sql);

		$this->assertEquals(array(1), $params);
	}

	public function testGet() {
		$called = false;
		$args = array();

		$select = new Select('test');
		$select->setCallback(function () use (&$args, &$called) {
			$called = true;
			$args = func_get_args();

			return array('col' => 'value');
		});

		$resultSet = $select->debug()->get();

		$this->assertTrue($called);

		$this->assertEquals(array('Select * From `test`', array(), true), $args);
		$this->assertEquals(array('col' => 'value'), $resultSet);
	}

	public function testWithColumnHavingAsterisk() {
		$table = 'table-name';

		list($sql, $params) = (new Select(array('t' => $table)))->column('t.* ')->build();

		$this->assertEquals("Select `t`.* From `$table` as `t`", $sql);

		$this->assertEmpty($params);
	}

	public function testSubQueryWithIn() {
		$table = 'table-name';

		list($sql, $params) = (new Select(array('t' => $table)))
			->column('t.* ')
			->whereIn(
				'col',
				(new Select("$table as inner"))
					->column('id')
					->where('inner.col', 1)
					->limit(50)
			)
			->build();

		$this->assertEquals(
			"Select `t`.* From `$table` as `t` Where `col` In (Select `id` From `$table` as `inner` Where `inner`.`col` = ? Limit 50)",
			$sql
		);

		$this->assertEquals(array(1), $params);
	}
}
