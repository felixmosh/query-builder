<?php

namespace QueryBuilder\QueryBuilder;

class Select extends Base {
	protected $_distinct = false;
	protected $_groupBy = array();
	protected $_joins = array();

	public function columns($cols) {
		if (!is_array($cols)) {
			return $this;
		}

		foreach ($cols as $alias => $columnName) {
			if (is_int($alias)) {
				$this->column($columnName);
			}
			else {
				$arr = array();
				$arr[$alias] = $columnName;
				$this->column($arr);
			}
		}

		return $this;
	}

	/**
	 * @param $column string|array
	 * @return $this
	 */
	public function column($column) {
		if (is_array($column)) {
			$alias = key($column);
			$columnName = current($column);
			$this->addColumn($columnName, $alias);

			reset($column);
		}
		elseif (is_string($column)) {
			$this->addColumn($column, null);
		}

		return $this;
	}

	public function distinct($flag = true) {
		$this->_distinct = $flag;
		return $this;
	}

	public function count($col = '*', $alias = 'c') {
		list($columnName, $alias) = $this->normalizeFunctionArgs($col, $alias);
		$columnName = new Func('Count', $columnName === '*' ? new Raw('*') : $columnName);

		$this->addColumn($columnName, $alias);

		return $this;
	}

	public function groupBy($params = array()) {
		$this->addToList($params, $this->_groupBy);

		return $this;
	}

	public function join($table, $localKey, $operatorOrRefKey = null, $referenceKey = null, $type = 'left') {
		if (!$this->isAllowedOperator($operatorOrRefKey) && is_null($referenceKey)) {
			$referenceKey = $operatorOrRefKey;
			$operatorOrRefKey = '=';
		}

		// to make nested joins possible you can pass an closure
		if (is_object($localKey) && ($localKey instanceof \Closure)) {
			// create new query object
			$joinOn = new JoinOn($table);

			// run the closure callback on the sub query
			call_user_func_array($localKey, array(&$joinOn));

			$this->_joins[] = array($type, $table, $joinOn);
			return $this;
		}

		$this->_joins[] = array($type, $table, $localKey, $operatorOrRefKey, $referenceKey);

		return $this;
	}

	public function leftJoin($table, $localKey, $operatorOrRefKey = null, $referenceKey = null) {
		return $this->join($table, $localKey, $operatorOrRefKey, $referenceKey, 'left');
	}

	public function rightJoin($table, $localKey, $operatorOrRefKey = null, $referenceKey = null) {
		return $this->join($table, $localKey, $operatorOrRefKey, $referenceKey, 'right');
	}

	public function innerJoin($table, $localKey, $operatorOrRefKey = null, $referenceKey = null) {
		return $this->join($table, $localKey, $operatorOrRefKey, $referenceKey, 'inner');
	}

	public function outerJoin($table, $localKey, $operatorOrRefKey = null, $referenceKey = null) {
		return $this->join($table, $localKey, $operatorOrRefKey, $referenceKey, 'outer');
	}

	public function build() {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return array($sql, $compiler->params());
		}

		$distinct = $this->_distinct ? ' Distinct' : '';
		$columns = $compiler->buildColumns($this->_columns);
		$table = $compiler->buildTable($this->_table_name);
		$joins = $compiler->buildJoins($this->_joins);
		$where = $compiler->buildWhere($this->_wheres);
		$orderBy = $compiler->buildOrderBy($this->_orderBy);
		$groupBy = $compiler->buildGroupBy($this->_groupBy);
		$limit = $compiler->buildLimit($this->_limit);

		$sql = "Select{$distinct} {$columns} From {$table}{$joins}{$where}{$groupBy}{$orderBy}{$limit}";
		$params = $compiler->params();

		return array($sql, $params);
	}

	public function get() {
		return $this->execute();
	}

	private function normalizeFunctionArgs($col, $alias) {
		if (is_string($col) || is_null($col)) {
			$arr = array();
			$arr[$alias] = $col;
			$col = $arr;
		}

		$alias = key($col);
		$columnName = current($col) ? current($col) : '*';

		reset($col);

		return array($columnName, $alias);
	}
}
