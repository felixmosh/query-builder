<?php

namespace QueryBuilder\QueryBuilder;

use QueryBuilder\Exception\QueryBuilderException;

class Func {
	/**
	 * the function name
	 *
	 * @var string
	 */
	protected $name = null;

	/**
	 * The function arguments
	 *
	 * @var array
	 */
	protected $arguments = [];

	/**
	 * The constructor that assigns our value
	 *
	 * @throws QueryBuilderException
	 */
	public function __construct() {
		$arguments = func_get_args();

		// throw an error when no arguments are given
		if (empty($arguments)) {
			throw new QueryBuilderException('Cannot create function expression without arguments.');
		}

		// the first argument is always the function name
		$this->name = ucwords(array_shift($arguments));

		// and assign the arguments
		$this->arguments = $arguments;
	}

	/**
	 * Return the functions name
	 *
	 * @return string
	 */
	public function name(): ?string {
		return $this->name;
	}

	/**
	 * Return the functions arguments
	 *
	 * @return array
	 */
	public function arguments(): array {
		return $this->arguments;
	}
}
