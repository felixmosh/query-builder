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
	protected $_wheres = array();

	public function build() {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return array($sql, $compiler->params());
		}

		$where = $compiler->buildWhere($this->_wheres);
		$where = substr($where, strlen(' Where '));
		$params = $compiler->params();

		return array($where, $params);
	}
}
