<?php

namespace QueryBuilder\QueryBuilder;

class Where extends Base {
	public function __construct() {
		parent::__construct('');
	}

	/**
	 * where items
	 *
	 * @var array
	 */
	protected $_wheres = [];

	public function isEmpty(): bool {
		return count($this->_wheres) === 0;
	}

	public function build(): array {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return [$sql, $compiler->params()];
		}

		$where = $compiler->buildWhere($this->_wheres);
		$whereParts = explode(' ', trim($where));
		if (strtolower($whereParts[0]) === 'where') {
			$whereParts = array_slice($whereParts, 1);
		}
		$where = implode(' ', $whereParts);
		$params = $compiler->params();

		return [$where, $params];
	}
}
