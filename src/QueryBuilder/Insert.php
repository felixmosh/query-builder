<?php

namespace QueryBuilder\QueryBuilder;

class Insert extends Base {
	private $_values = array();
	private $_duplicateUpdates = array();
	private $_ignore = false;

	public function columns($columns) {
		foreach ($columns as $column) {
			$this->addColumn($column, null);
		}

		return $this;
	}

	public function values($values = array()) {
		if (empty($values)) {
			return $this;
		}

		if (is_array($values) && (!array_key_exists(0, $values) || !is_array($values[0]))) {
			$values = array($values);
		}

		if (empty($this->_columns) && $this->isAssocArray($values[0])) {
			$this->columns(array_keys($values[0]));
		}

		$this->_values = array_merge($this->_values, $values);

		return $this;
	}

	public function onDuplicateUpdate($columns) {
		if (!is_array($columns)) {
			return $this;
		}

		$this->_duplicateUpdates = array_merge($this->_duplicateUpdates, $columns);

		return $this;
	}

	public function build() {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return array($sql, $compiler->params());
		}

		$table = $compiler->buildTable($this->_table_name, $this->_database);
		$ignore = $this->_ignore ? ' Ignore' : '';
		$columns = $compiler->buildColumns($this->_columns);
		$columns = $columns ? ' (' . $columns . ')' : '';
		$values = $compiler->buildInsertValues($this->_values);
		$duplicates = $compiler->buildDuplicateUpdates($this->_duplicateUpdates);

		$sql = "Insert{$ignore} Into {$table}{$columns} Values {$values}{$duplicates}";
		$params = $compiler->params();

		return array($sql, $params);
	}

	public function ignore($ignore = true) {
		$this->_ignore = $ignore;

		return $this;
	}

	private function isAssocArray($arr) {
		if (empty($arr)) {
			return false;
		}
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
}
