<?php

namespace QueryBuilder\QueryBuilder;

use QueryBuilder\Exception\QueryBuilderException;

class Compiler {
	private $_parameters = array();

	public function params() {
		return $this->_parameters;
	}

	public function buildRaw($_raw_query) {
		list($str, $params) = $this->escapeRaw($_raw_query);

		$this->addParameter($params);

		return $str;
	}

	public function buildOrderBy($_orderBy) {
		if (empty($_orderBy)) {
			return '';
		}

		$orders = array();
		foreach ($_orderBy as $col => $direction) {
			if (is_int($col)) {
				$col = $direction;
				$direction = 'asc';
			}

			$orders[] = $this->escape($col) . ' ' . ucwords($direction);
		}

		return ' Order By ' . implode(', ', $orders);
	}

	public function buildLimit($_limit) {
		if (empty($_limit)) {
			return '';
		}

		list($limit, $offset) = $_limit;
		if (is_null($limit)) {
			return '';
		}

		$parts = array();
		if (!is_null($offset)) {
			$parts[] = $offset;
		}

		$parts[] = $limit;
		return ' Limit ' . implode(', ', $parts);
	}

	public function buildWhere($_wheres) {
		if (empty($_wheres)) {
			return '';
		}

		$wheres = array();

		foreach ($_wheres as $index => $where) {
			$where[0] = $index === 0 ? ' Where' : ucwords($where[0]);

			if ($this->isRaw($where[1])) {
				list($raw, $params) = $this->escapeRaw($where[1]);
				$where = array($where[0], $raw);
				$this->addParameter($params);
			} elseif ($this->isWhere($where[1])) {
				list($innerClause, $params) = $where[1]->build();
				$this->addParameter($params);

				$where[1] = '(' . $innerClause . ')';
			} elseif ($this->isSelect($where[3])) {
				list($subquery, $params) = $where[3]->build();
				$this->addParameter($params);
				if ($where[1] === null) {
					// exists case
					unset($where[1]);
				} else {
					$where[1] = $this->escape($where[1]);
				}
				$where[3] = '(' . $subquery . ')';
			} else {
				$wrap_with_parenthesis = is_array($where[3]);
				$where[1] = $this->escape($where[1]);
				$where[3] = $this->parameterize($where[3]);
				if ($wrap_with_parenthesis) {
					$where[3] = '(' . $where[3] . ')';
				}
			}

			$wheres[] = implode(' ', $where);
		}

		return implode(' ', $wheres);
	}

	public function buildColumns($_columns) {
		if (empty($_columns)) {
			return '*';
		}

		$columns = array();
		foreach ($_columns as $column) {
			list($columnName, $alias) = $column;

			if ($this->isSelect($columnName)) {
				//Subquery
				list($sub_query, $params) = $columnName->build();
				$this->addParameter($params);
				$columnName = "({$sub_query})";
			} elseif (is_string($columnName) && substr(trim($columnName), -1 * strlen('.*')) === '.*') {
				$parts = explode('.', $columnName);
				$lastPart = array_pop($parts);
				$columnName = $this->escape(implode('.', $parts)) . '.' . trim($lastPart);
			} else {
				$columnName = $this->escape($columnName);
			}

			$columnName .= $alias ? " as {$this->escape($alias)}" : '';

			$columns[] = $columnName;
		}

		return implode(', ', $columns);
	}

	public function buildTable($_table, $_database = null) {
		return $this->escapeTable($_table, $_database);
	}

	public function buildGroupBy($_groupBy) {
		if (empty($_groupBy)) {
			return '';
		}

		$groups = array();
		foreach ($_groupBy as $col) {
			$groups[] = $this->escape($col);
		}

		return ' Group By ' . implode(', ', $groups);
	}

	public function isRaw($raw) {
		return $raw instanceof Raw;
	}

	public function buildUpdateValues($_values) {
		if (!is_array($_values)) {
			return '';
		}

		$values = array();
		foreach ($_values as $column => $value) {
			$values[] = $this->escape($column) . ' = ' . $this->parameterize($value);
		}

		return implode(', ', $values);
	}

	public function buildInsertValues($_values) {
		if (!is_array($_values)) {
			return '';
		}

		$values = array();
		foreach ($_values as $value) {
			$values[] = '(' . $this->parameterize($value) . ')';
		}

		return implode(', ', $values);
	}

	public function buildDuplicateUpdates($_duplicateUpdates) {
		if (empty($_duplicateUpdates)) {
			return '';
		}

		$duplicates = array();

		foreach ($_duplicateUpdates as $column => $value) {
			if (is_int($column)) {
				$column = $value;
				$value = $this->escape(new Func('Values', $column));
			} elseif ($this->isRaw($value)) {
				list($raw, $params) = $this->escapeRaw($value);
				$value = $raw;
				$this->addParameter($params);
			} else {
				$value = $this->parameterize($value);
			}

			$column = $this->escape($column);

			$duplicates[] = implode(' = ', array($column, $value));
		}

		return ' On Duplicate Key Update ' . implode(', ', $duplicates);
	}

	public function buildJoins(array $_joins) {
		if (empty($_joins)) {
			return '';
		}

		$joins = array();
		foreach ($_joins as $join) {
			$type = $join[0];
			$table = $this->escapeTable($join[1]);

			if ($this->isJoinOn($join[2])) {
				list($ons, $params) = $join[2]->build();
				$this->addParameter($params);
			} else {
				$localKey = $this->escape($join[2]);
				$referenceKey = $this->escape($join[4]);
				$ons = implode(' ', array($localKey, $join[3], $referenceKey));
			}

			$joins[] = ' ' . implode(' ', array(ucwords($type), 'Join', $table, 'On', $ons));
		}

		return implode(' ', $joins);
	}

	public function buildJoinOns(array $_joinOns) {
		if (empty($_joinOns)) {
			return '';
		}

		$ons = array();

		// Remove the type of the first element
		$_joinOns[0][0] = '';

		foreach ($_joinOns as $on) {
			list($type, $localKey, $operator, $referenceKey) = $on;

			if ($this->isRaw($referenceKey)) {
				list($raw, $params) = $this->escapeRaw($referenceKey);
				$referenceKey = $raw;
				$this->addParameter($params);
			} else {
				$referenceKey = $this->escape($referenceKey);
			}

			$ons[] = trim(implode(' ', array(ucwords($type), $this->escape($localKey), $operator, $referenceKey)));
		}

		return implode(' ', $ons);
	}

	/**
	 * Function to escape identifier names (columns and tables)
	 * Doubles backticks, removes null bytes
	 * https://dev.mysql.com/doc/refman/8.0/en/identifiers.html
	 *
	 * @return string
	 * @var string
	 */
	protected function escapeIdentifier($identifier) {
		return '`' . str_replace(array('`', "\0"), array('``', ''), $identifier) . '`';
	}

	protected function escapeRaw($raw) {
		$value = $raw->value();
		$params = $raw->params();

		$param_index = 0;
		$str = preg_replace_callback(
			'%\?{1,2}%',
			function ($matches) use (&$param_index, &$params) {
				if ($matches[0] === '?') {
					$param_index++;
					return '?';
				}

				$param = $params[$param_index];
				array_splice($params, $param_index, 1);
				return $this->escape($param);
			},
			$value,
			count($params),
			$param_index
		);

		return array($str, $params);
	}

	/**
	 * Escape an array of items a separate them with a comma
	 *
	 * @param array $array
	 * @return string
	 * @throws QueryBuilderException
	 */
	protected function escapeList($array) {
		foreach ($array as $key => $item) {
			$array[$key] = $this->escape($item);
		}

		return implode(', ', $array);
	}

	protected function escape($string) {
		if (is_object($string)) {
			if ($this->isRaw($string)) {
				list($str) = $this->escapeRaw($string);
				return $str;
			} elseif ($this->isFunction($string)) {
				return $this->escapeFunction($string);
			} else {
				throw new QueryBuilderException('Cannot escape object of class: ' . get_class($string));
			}
		}

		// the string might contain an 'as' statement that we wil have to split.
		if (strpos($string, ' as ') !== false) {
			$string = explode(' as ', $string);

			return $this->escape(trim($string[0])) . ' as ' . $this->escape(trim($string[1]));
		}

		// it also might contain dot separations we have to split
		if (strpos($string, '.') !== false) {
			$string = explode('.', $string);

			foreach ($string as $key => $item) {
				$string[$key] = $this->escapeIdentifier($item);
			}

			return implode('.', $string);
		}

		return $this->escapeIdentifier($string);
	}

	/**
	 * Escapes an sql function object
	 *
	 * @param Func $function
	 * @return string
	 */
	protected function escapeFunction($function) {
		$buffer = $function->name() . '(';

		$arguments = $function->arguments();

		foreach ($arguments as &$argument) {
			$argument = $this->escape($argument);
		}

		return $buffer . implode(', ', $arguments) . ')';
	}

	/**
	 * get and escape the table name
	 *
	 * @return string
	 */
	protected function escapeTable($table, $database = null, $allowAlias = true) {
		$buffer = '';

		if (!is_null($database)) {
			$buffer .= $this->escape($database) . '.';
		}

		// when the table is an array we have a table with alias
		if (is_array($table)) {
			reset($table);

			//the table might be a subselect so check that
			if ($table[key($table)] instanceof Select) {
				list($subQuery, $subQueryParameters) = $table[key($table)]->build();

				$this->addParameter($subQueryParameters);

				return '(' . $subQuery . ') as ' . $this->escape(key($table));
			}

			// otherwise continue with normal table
			if ($allowAlias && !is_int(key($table))) {
				$table = current($table) . ' as ' . key($table);
			} else {
				$table = current($table);
			}
		}

		return $buffer . $this->escape($table);
	}

	protected function isFunction($function) {
		return $function instanceof Func;
	}

	protected function addParameter($params) {
		if (!is_array($params)) {
			$params = array($params);
		}

		foreach ($params as $param) {
			$this->_parameters[] = $param;
		}
	}

	/**
	 * creates an parameter and adds it
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function param($value) {
		if ($this->isRaw($value)) {
			list($raw, $params) = $this->escapeRaw($value);
			$this->addParameter($params);
			return $raw;
		} elseif ($this->isFunction($value)) {
			return $value->name() . '(' . $this->parameterize($value->arguments()) . ')';
		}

		$this->addParameter($value);
		return '?';
	}

	private function isSelect($any) {
		return $any instanceof Select;
	}

	private function isWhere($any) {
		return $any instanceof Where;
	}

	private function isJoinOn($any) {
		return $any instanceof JoinOn;
	}

	/**
	 * Convert data to parameters and bind them to the query
	 *
	 * @param array $params
	 * @return string
	 */
	private function parameterize($params) {
		if (!is_array($params)) {
			$params = array($params);
		}

		foreach ($params as $key => $param) {
			$params[$key] = $this->param($param);
		}

		return implode(', ', $params);
	}
}
