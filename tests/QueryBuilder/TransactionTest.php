<?php

namespace QueryBuilder\Tests\QueryBuilder;

use Exception;
use PHPUnit\Framework\TestCase;
use QueryBuilder\DB;
use QueryBuilder\QueryBuilder\Transaction;
use QueryBuilder\Tests\FakeConnection;
use ReflectionClass;
use ReflectionProperty;

final class TransactionTest extends TestCase {
	private function db(FakeConnection $connection): DB {
		$db = (new ReflectionClass(DB::class))->newInstanceWithoutConstructor();

		$property = new ReflectionProperty(DB::class, 'mysqli');
		$property->setAccessible(true);
		$property->setValue($db, $connection);

		return $db;
	}

	public function testStartTransactionBeginsIt() {
		$connection = new FakeConnection();

		$transaction = new Transaction($connection);

		$this->assertSame(1, $connection->beginTransactionCalls);
		$this->assertTrue($transaction->isOpen());
	}

	public function testStartTransactionWithoutConnectionThrows() {
		$this->expectException(Exception::class);

		new Transaction(null);
	}

	public function testCommitClosesTheTransaction() {
		$connection = new FakeConnection();

		$transaction = new Transaction($connection);

		$this->assertTrue($transaction->commit());
		$this->assertSame(1, $connection->commitCalls);
		$this->assertSame(0, $connection->rollbackCalls);
		$this->assertFalse($transaction->isOpen());
	}

	public function testRollbackClosesTheTransaction() {
		$connection = new FakeConnection();

		$transaction = new Transaction($connection);

		$this->assertTrue($transaction->rollback());
		$this->assertSame(1, $connection->rollbackCalls);
		$this->assertSame(0, $connection->commitCalls);
		$this->assertFalse($transaction->isOpen());
	}

	public function testCommitAndRollbackAreIgnoredOnceTheTransactionIsClosed() {
		$connection = new FakeConnection();

		$transaction = new Transaction($connection);
		$this->assertTrue($transaction->commit());

		$this->assertFalse($transaction->commit());
		$this->assertFalse($transaction->rollback());
		$this->assertSame(1, $connection->commitCalls);
		$this->assertSame(0, $connection->rollbackCalls);
	}

	public function testTransactionCommitsWhenNothingThrows() {
		$connection = new FakeConnection();
		$db = $this->db($connection);

		$result = $db->transaction(function ($db) {
			return $db
				->insert('users')
				->values(['name' => 'felixmosh'])
				->execute();
		});

		$this->assertTrue($result);
		$this->assertSame(1, $connection->commitCalls);
		$this->assertSame(0, $connection->rollbackCalls);
	}

	public function testTransactionRollsBackWhenTheCallbackThrows() {
		$connection = new FakeConnection();
		$db = $this->db($connection);

		$result = $db->transaction(function ($db) {
			$db->insert('users')
				->values(['name' => 'felixmosh'])
				->execute();

			throw new \RuntimeException('Something went wrong');
		});

		$this->assertFalse($result);
		$this->assertSame(1, $connection->rollbackCalls);
		$this->assertSame(0, $connection->commitCalls);
	}

	public function testTransactionRollsBackOnPhpErrors() {
		$connection = new FakeConnection();
		$db = $this->db($connection);

		$result = $db->transaction(function () {
			throw new \Error('Something went wrong');
		});

		$this->assertFalse($result);
		$this->assertSame(1, $connection->rollbackCalls);
		$this->assertSame(0, $connection->commitCalls);
	}

	public function testBareTransactionUsage() {
		$connection = new FakeConnection();
		$db = $this->db($connection);

		$transaction = $db->startTransaction();
		$db->insert('users')
			->values(['name' => 'bob'])
			->execute();

		$this->assertTrue($transaction->isOpen());

		$this->assertTrue($transaction->rollback());
		$this->assertSame(1, $connection->beginTransactionCalls);
		$this->assertSame(1, $connection->rollbackCalls);
		$this->assertSame(0, $connection->commitCalls);
	}
}
