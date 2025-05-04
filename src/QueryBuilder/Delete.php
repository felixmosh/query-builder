<?php

namespace QueryBuilder\QueryBuilder;

class Delete extends Base {
	public function get(): array {
		return $this->build();
	}

	public function build(): array {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return [$sql, $compiler->params()];
		}

		$table = $compiler->buildTable($this->_table_name, $this->_database);
		$where = $compiler->buildWhere($this->_wheres);
		$orderBy = $compiler->buildOrderBy($this->_orderBy);
		$limit = $compiler->buildLimit($this->_limit);

		$sql = "Delete From {$table}{$where}{$orderBy}{$limit}";
		$params = $compiler->params();

		return [$sql, $params];
	}
}
