<?php

namespace QueryBuilder\Tests;

class FakeConnection {
	public $beginTransactionCalls = 0;
	public $commitCalls = 0;
	public $rollbackCalls = 0;
	public $prepared = [];

	public function begin_transaction(): bool {
		$this->beginTransactionCalls++;
		return true;
	}

	public function commit(): bool {
		$this->commitCalls++;
		return true;
	}

	public function rollback(): bool {
		$this->rollbackCalls++;
		return true;
	}

	public function close(): bool {
		return true;
	}

	public function prepare(string $sql) {
		$this->prepared[] = $sql;

		return new FakeStatement();
	}
}

class FakeStatement {
	public $insert_id = 1;
	public $affected_rows = 1;

	public function bind_param(...$params): bool {
		return true;
	}

	public function execute(): bool {
		return true;
	}

	public function close(): bool {
		return true;
	}

	public function store_result(): bool {
		return true;
	}

	public function result_metadata() {
		return false;
	}

	public function fetch(): bool {
		return false;
	}
}