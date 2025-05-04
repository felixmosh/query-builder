<?php

namespace QueryBuilder\QueryBuilder;

class JoinOn extends Base {
	/**
	 * join on items
	 *
	 * @var array
	 */
	protected $_ons = [];

	public function build(): array {
		$compiler = new Compiler();

		if ($this->isRaw($this->_rawQuery)) {
			$sql = $compiler->buildRaw($this->_rawQuery);
			return [$sql, $compiler->params()];
		}

		$ons = $compiler->buildJoinOns($this->_ons);

		$params = $compiler->params();

		return [$ons, $params];
	}

	/**
	 * Add an on condition to the join object
	 *
	 * @param string $localKey
	 * @param string|null $operatorOrRefKey
	 * @param string|null $referenceKey
	 * @param string $type
	 * @return static
	 */
	public function on($localKey, $operatorOrRefKey = null, $referenceKey = null, $type = 'and'): JoinOn {
		if (!$this->isAllowedOperator($operatorOrRefKey) && is_null($referenceKey)) {
			$referenceKey = $operatorOrRefKey;
			$operatorOrRefKey = '=';
		}

		$this->_ons[] = [$type, $localKey, $operatorOrRefKey, $referenceKey];
		return $this;
	}

	/**
	 * Add an or on condition to the join object
	 *
	 * @param string $localKey
	 * @param string|null $operatorOrRefKey
	 * @param string|null $referenceKey
	 *
	 * @return static
	 */
	public function orOn($localKey, $operatorOrRefKey = null, $referenceKey = null): JoinOn {
		$this->on($localKey, $operatorOrRefKey, $referenceKey, 'or');
		return $this;
	}

	/**
	 * Add an and on condition to the join object
	 *
	 * @param string $localKey
	 * @param string|null $operatorOrRefKey
	 * @param string|null $referenceKey
	 *
	 * @return static
	 */
	public function andOn($localKey, $operatorOrRefKey = null, $referenceKey = null): JoinOn {
		$this->on($localKey, $operatorOrRefKey, $referenceKey, 'and');
		return $this;
	}
}
