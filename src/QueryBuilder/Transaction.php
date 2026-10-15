<?php

namespace QueryBuilder\QueryBuilder;

/**
 * Transaction class
 *
 * Thin wrapper around the underlying mysqli transaction api, it keeps track
 * of its own state so double commits/rollbacks are safely ignored.
 *
 * @author by felixmosh
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 **/
class Transaction {
	/**
	 * The mysqli connection used by this transaction.
	 *
	 * @var mysqli
	 */
	private $mysqli;
	/**
	 * Whether the transaction is still open.
	 *
	 * @var bool
	 */
	private $open = false;

	/**
	 * @param mysqli $mysqli
	 * @throws \Exception When the transaction can't be started.
	 */
	public function __construct($mysqli) {
		$this->mysqli = $mysqli;

		if (!$this->mysqli) {
			throw new \Exception('There was a problem starting the transaction - no db connection.');
		}

		if (!$this->mysqli->begin_transaction()) {
			throw new \Exception('There was a problem starting the transaction');
		}

		$this->open = true;
	}

	/**
	 * Whether this transaction is still open.
	 *
	 * @return bool
	 */
	public function isOpen(): bool {
		return $this->open;
	}

	/**
	 * Commit the transaction.
	 *
	 * @return bool true on success, false when the transaction isn't open anymore.
	 */
	public function commit(): bool {
		if (!$this->open) {
			return false;
		}

		$this->open = false;

		return (bool) $this->mysqli->commit();
	}

	/**
	 * Roll the transaction back.
	 *
	 * @return bool true on success, false when the transaction isn't open anymore.
	 */
	public function rollback(): bool {
		if (!$this->open) {
			return false;
		}

		$this->open = false;

		return (bool) $this->mysqli->rollback();
	}
}