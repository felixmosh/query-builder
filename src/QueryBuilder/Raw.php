<?php
namespace QueryBuilder\QueryBuilder;

class Raw {
	/**
	 * The value holder
	 *
	 * @var string
	 */
	protected $value = null;
	protected $params = null;

	/**
	 * The constructor that assigns our value
	 *
	 * @param string $value
	 * @param array $params
	 */
	public function __construct($value, $params = array()) {
		$this->value = $value;
		$this->params = $params;
	}

	/**
	 * Return the expressions value
	 *
	 * @return string
	 */
	public function value() {
		return $this->value;
	}

	/**
	 * Return the expressions value
	 *
	 * @return array
	 */
	public function params() {
		return $this->params;
	}

	/**
	 * To string magic returns the expression value
	 */
	public function __toString() {
		return $this->value();
	}
}
