<?php

namespace QueryBuilder\QueryBuilder;

use Closure;

/**
 * Based on https://github.com/ClanCats/Hydrahon
 * Class Base
 * @package QueryBuilder
 */
abstract class Base {
	public static $ALLOWED_OPERATIONS = [
		'=',
		'<',
		'>',
		'<=',
		'>=',
		'<>',
		'!=',
		'like',
		'in',
		'not in',
		'is',
		'is not',
		'between',
		'not between',
		'exists',
		'not exists',
	];
	protected $_debug = false;
	protected $_table_name;
	protected $_database;
	protected $_columns = [];
	protected $_wheres = [];
	protected $_limit = [];
	protected $_orderBy = [];
	protected $_rawQuery = null;
	protected $callback;

	public function __construct($table_name) {
		$this->_table_name = $table_name;
	}

	public function setCallback($callback) {
		$this->callback = $callback;
		return $this;
	}

	public function where($col, $param1 = null, $param2 = null, $type = 'and') {
		if ($param1 !== null && $param2 !== null && $this->isAllowedOperator($param1)) {
			$this->addWhere($type, $col, $param1, $param2);
		} elseif ($param1 !== null && !$this->isAllowedOperator($param1)) {
			$this->addWhere($type, $col, '=', $param1);
		} elseif ($param1 === null && $param2 === null && is_array($col)) {
			foreach ($col as $columnName => $param2) {
				$this->where($columnName, '=', $param2, $type);
			}
		} elseif ($param1 === null && $param2 === null && $this->isRaw($col)) {
			$this->addWhere($type, $col, null, null);
		} elseif ($param1 === null && $param2 === null && $col instanceof \Closure) {
			// create new query object
			$innerWhere = new Where();

			// run the closure callback on the sub query
			call_user_func_array($col, [&$innerWhere]);

			$this->_wheres[] = [$type, $innerWhere];
			return $this;
		}
		return $this;
	}

	public function orWhere($col, $param1 = null, $param2 = null) {
		return $this->where($col, $param1, $param2, 'or');
	}

	public function orWhereIn($column, $values = [], $not = false) {
		if (is_array($values) && empty($values)) {
			return $this;
		}

		return $this->where($column, ($not ? 'Not ' : '') . 'In', $values, 'or');
	}

	public function orWhereNotIn($column, array $values = []) {
		return $this->orWhereIn($column, $values, true);
	}

	public function orWhereNull($column, $not = false) {
		return $this->where($column, 'Is' . ($not ? ' Not' : ''), new Raw('Null'), 'or');
	}

	public function orWhereNotNull($column) {
		return $this->orWhereNull($column, true);
	}

	public function orWhereBetween($column, $value1, $value2, $not = false) {
		return $this->where($column, ($not ? 'Not ' : '') . 'Between', new Raw('? And ?', [$value1, $value2]), 'or');
	}

	public function orWhereNotBetween($column, $value1, $value2) {
		return $this->orWhereBetween($column, $value1, $value2, true);
	}

	public function orWhereExists($subquery, $not = false) {
		return $this->where(null, ($not ? 'Not ' : '') . 'Exists', $subquery, 'or');
	}

	public function orWhereNotExists($subquery) {
		return $this->orWhereExists($subquery, true);
	}

	public function andWhere($col, $param1 = null, $param2 = null) {
		return $this->where($col, $param1, $param2, 'and');
	}

	public function whereIn($column, $values = [], $not = false) {
		if (is_array($values) && empty($values)) {
			return $this;
		}

		return $this->where($column, ($not ? 'Not ' : '') . 'In', $values);
	}

	public function whereNotIn($column, array $values = []) {
		return $this->whereIn($column, $values, true);
	}

	public function whereNull($column, $not = false) {
		return $this->where($column, 'Is' . ($not ? ' Not' : ''), new Raw('Null'));
	}

	public function whereNotNull($column) {
		return $this->whereNull($column, true);
	}

	public function whereBetween($column, $value1, $value2, $not = false) {
		return $this->where($column, ($not ? 'Not ' : '') . 'Between', new Raw('? And ?', [$value1, $value2]));
	}

	public function whereNotBetween($column, $value1, $value2) {
		return $this->whereBetween($column, $value1, $value2, true);
	}

	public function whereExists($subquery, $not = false) {
		return $this->where(null, ($not ? 'Not ' : '') . 'Exists', $subquery);
	}

	public function whereNotExists($subquery) {
		return $this->whereExists($subquery, true);
	}

	public function limit($limit = null, $offset = null) {
		$this->_limit = [$limit, $offset];
		return $this;
	}

	public function orderBy($params = []) {
		$this->addToList($params, $this->_orderBy);

		return $this;
	}

	public function execute() {
		list($sql, $params) = $this->build();

		if (is_callable($this->callback) && $this->callback instanceof Closure) {
			return call_user_func_array($this->callback, [$sql, $params, $this->_debug]);
		}

		return null;
	}

	public function toString() {
		list($sql, $params) = $this->build();

		$param_index = 0;
		return preg_replace_callback(
			'%\?%',
			function () use (&$param_index, &$params) {
				$str = '"' . addslashes($params[$param_index]) . '"';
				$param_index++;

				return $str;
			},
			$sql,
			count($params)
		);
	}

	public function raw($string, array $params) {
		$this->_rawQuery = new Raw($string, $params);

		return $this;
	}

	public function debug($flag = true) {
		$this->_debug = $flag;

		return $this;
	}

	abstract public function build();

	protected function isRaw($raw) {
		return $raw instanceof Raw;
	}

	protected function addWhere($type, $columnName, $operation, $value) {
		$this->_wheres[] = [$type, $columnName, $operation, $value];
	}

	protected function addColumn($columnName, $alias) {
		$this->_columns[] = [$columnName, $alias];
	}

	protected function addToList($params, &$list) {
		if (is_array($params)) {
			$list = array_merge($list, $params);
		} elseif (is_string($params)) {
			$list[] = $params;
		}
	}

	protected function isAllowedOperator($operator) {
		return is_string($operator) && in_array(strtolower($operator), Base::$ALLOWED_OPERATIONS);
	}
}
