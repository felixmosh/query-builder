<?php

namespace QueryBuilder\QueryBuilder;

class Update extends Base {
	private $_values = [];

	public function set($param1, $param2 = null) {
		if (empty($param1)) {
			return $this;
		}

		// like: set( 'name', 'Lu' ); instead of set( array( 'name' => 'Lu' ) );
		if (!is_null($param2)) {
			$param1 = [$param1 => $param2];
		}

		$this->_values = array_merge($this->_values, $param1);

		return $this;
	}

	public function build() {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return [$sql, $compiler->params()];
		}

		$table = $compiler->buildTable($this->_table_name, $this->_database);
		$updateValues = $compiler->buildUpdateValues($this->_values);
		$where = $compiler->buildWhere($this->_wheres);
		$orderBy = $compiler->buildOrderBy($this->_orderBy);
		$limit = $compiler->buildLimit($this->_limit);

		$sql = "Update {$table} Set {$updateValues}{$where}{$orderBy}{$limit}";
		$params = $compiler->params();

		return [$sql, $params];
	}
}
