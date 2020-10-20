<?php

namespace QueryBuilder;

use Closure;
use Exception;
use mysqli;
use QueryBuilder\QueryBuilder\Delete;
use QueryBuilder\QueryBuilder\Func;
use QueryBuilder\QueryBuilder\Insert;
use QueryBuilder\QueryBuilder\Raw;
use QueryBuilder\QueryBuilder\Select;
use QueryBuilder\QueryBuilder\Update;

/**
 * DB Class
 *
 * @author by felixmosh
 * @copyright Copyright (c) 2012
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 **/
class DB {
	/**
	 * Static instance of self
	 *
	 * @var object
	 */
	protected static $instance;
	/**
	 * MySQLi instance
	 *
	 * @var object
	 */
	protected $mysqli;
	/**
	 * The SQL query to be prepared and executed
	 *
	 * @var object
	 */

	/**
	 * The type of the environment
	 *
	 * @var boolean
	 */
	private $isDevelopment;
	/** The number of executed queries.
	 */
	private $nbQueries;
	private $logger;

	public function __construct($connection_str, $logger = null) {
		$this->isDevelopment = $_ENV['APP_ENV'] === 'development';
		$this->nbQueries = 0;
		$this->logger = $logger;

		if (!isset($connection_str)) {
			die('There was a problem connecting to the database - no db connection info.');
		}

		if (!$this->isDevelopment) {
			mysqli_report(MYSQLI_REPORT_OFF);
		}

		$connection = parse_url($connection_str);
		parse_str($connection['query'], $query);
		$connection['path'] = substr($connection['path'], 1);

		($this->mysqli = new mysqli(
			$connection['host'],
			$connection['user'],
			$connection['pass'],
			$connection['path']
		)) or die('There was a problem connecting to the database');

		if (isset($query['charset'])) {
			$this->mysqli->set_charset($query['charset']);
		}

		self::$instance = $this;
	}

	/**
	 * A method of returning the static instance to allow access to the
	 * instantiated object from within another class.
	 * Inheriting this class would require reloading connection info.
	 *
	 * @return object Returns the current instance.
	 * @uses $db = MySqliDb::getInstance();
	 *
	 */
	public static function getInstance() {
		return self::$instance;
	}

	/** Get the result of the query as value. The query should return single row.
	 * Note: no need to add "LIMIT 1" at the end of your query because
	 * the method will add that (for optimisation purpose).
	 *
	 * @param $tableName string The name of the database table to work with.
	 * @param null $postProcess
	 * @return Select A value representing a data cell (or NULL if result is empty).
	 */
	public function selectSingleRow($tableName, $postProcess = null) {
		$qb = $this->select($tableName, function ($results) use (&$postProcess) {
			if (is_array($results) && !empty($results)) {
				if (is_callable($postProcess) && $postProcess instanceof Closure) {
					return call_user_func($postProcess, $results[0]);
				}

				return $results[0];
			}

			return null;
		});

		return $qb->limit(1);
	}

	/** Get the result of the query as value. The query should return a unique cell.
	 * Note: no need to add "LIMIT 1" at the end of your query because
	 * the method will add that (for optimization purpose).
	 *
	 * @param $tableName string The name of the database table to work with.
	 * @param $column string The column name to fetch
	 * @return Select A value representing a data cell (or NULL if result is empty).
	 */
	public function selectUniqueValue($tableName, $column = null) {
		$qb = $this->selectSingleRow($tableName, function ($result) {
			$keys = array_keys($result);
			return $result[$keys[0]];
		});

		return $qb->column($column);
	}

	/**
	 * Select query.
	 *
	 * @param $tableName string The name of the database table to work with.
	 * @param null $postProcess
	 * @return Select|array
	 */
	public function select($tableName, $postProcess = null) {
		if ($this->mysqli === null) {
			return array();
		}

		$queryBuilder = new Select($tableName);
		$queryBuilder->setCallback($this->handleResults($queryBuilder, $postProcess));

		return $queryBuilder;
	}

	/**
	 *
	 * @param string $tableName The name of the table.
	 * @return Insert of inserted ids.
	 */
	public function insert($tableName) {
		if ($this->mysqli === null) {
			return null;
		}

		$queryBuilder = new Insert($tableName);
		return $queryBuilder->setCallback($this->handleResults($queryBuilder));
	}

	/**
	 * Update query. Be sure to first call the "where" method.
	 *
	 * @param $tableName string The name of the database table to work with.
	 * @return Update of affected rows
	 */
	public function update($tableName) {
		if ($this->mysqli === null) {
			return null;
		}

		$queryBuilder = new Update($tableName);

		$queryBuilder->setCallback($this->handleResults($queryBuilder));

		return $queryBuilder;
	}

	/**
	 * Delete query. Call the "where" method first.
	 *
	 * @param $tableName string The name of the database table to work with.
	 * @return Delete|null of effected rows
	 */
	public function delete($tableName) {
		if ($this->mysqli === null) {
			return null;
		}

		$queryBuilder = new Delete($tableName);
		$queryBuilder->setCallback($this->handleResults($queryBuilder));
		return $queryBuilder;
	}

	/**
	 * @param $str string
	 * @param $params array
	 * @return Raw
	 */
	public function raw($str, $params = array()) {
		return new Raw($str, $params);
	}

	public function func() {
		return new Func(...func_get_args());
	}

	/** Get how many time the script took from the begin of this object.
	 *
	 * @param $startTime
	 * @return number The script execution time in seconds since the
	 * creation of this object.
	 */
	public function getExecTime($startTime) {
		return round(($this->getMicroTime() - $startTime) * 1000) / 1000;
	}

	/** Get the number of queries executed from the begin of this object.
	 *
	 * @return number The number of queries executed on the database server since the
	 * creation of this object.
	 */
	public function getQueriesCount() {
		return $this->nbQueries;
	}

	public function __destruct() {
		if ($this->mysqli) {
			/* Kill connection */
			$this->mysqli->close();
			$this->mysqli = null;
		}
	}

	/**
	 * This method is needed for prepared statements. They require
	 * the data type of the field to be bound with "i" s", etc.
	 * This function takes the input, determines what type it is,
	 * and then updates the param_type.
	 *
	 * @param mixed $item Input to determine the type.
	 * @return string The joined parameter types.
	 */
	protected function determineType($item) {
		switch (gettype($item)) {
			case 'NULL':
			case 'string':
				return 's';

			case 'integer':
				return 'i';

			case 'blob':
				return 'b';

			case 'double':
				return 'd';
			default:
				return '';
		}
	}

	protected function bindParams($bindParams, $stmt) {
		if (!is_array($bindParams)) {
			return;
		}
		$stmtParams = array('');

		foreach ($bindParams as $prop => $val) {
			$stmtParams[0] .= $this->determineType($val);
			$stmtParams[] = &$bindParams[$prop];
		}

		if (!empty($stmtParams)) {
			call_user_func_array(array($stmt, 'bind_param'), $stmtParams);
		}
	}

	/**
	 * This helper method takes care of prepared statements' "bind_result method
	 * , when the number of variables to pass is unknown.
	 *
	 * @param object $stmt Equal to the prepared statement object.
	 * @return array The results of the SQL fetch.
	 */
	protected function dynamicBindResults($stmt) {
		//Update the num rows
		$stmt->store_result();

		$parameters = array();
		$results = array();

		$meta = $stmt->result_metadata();
		$row = array();
		while ($field = $meta->fetch_field()) {
			$parameters[] = &$row[$field->name];
		}

		call_user_func_array(array($stmt, 'bind_result'), $parameters);

		while ($stmt->fetch()) {
			$x = array();
			foreach ($row as $key => $val) {
				$x[$key] = $val;
			}
			array_push($results, $x);
		}

		return $results;
	}

	/** Internal method to get the current time.
	 *
	 * @return number The current time in seconds with microseconds (in float format).
	 */
	protected function getMicroTime() {
		list($msec, $sec) = explode(' ', microtime());
		return floor($sec / 1000) + $msec;
	}

	/** Internal protected function to debug when MySQL encountered an error,
	 * even if debug is set to Off.
	 * @param $query
	 * @throws Exception
	 */
	protected function debugAndDie($query) {
		if ($this->isDevelopment) {
			$last_errors = error_get_last();
			$error = $this->mysqli->error ? $this->mysqli->error : implode('<br />', $last_errors);
			$this->debug($query, $error, 'error');

			die();
		}
		else {
			$this->logError($query);
			throw new Exception('ERROR.SERVER_ERROR');
		}
	}

	/** Internal public function to debug a MySQL query.\n
	 * Show the query and output the resulting table if not NULL.
	 *
	 * @param $query string
	 * @param $results Object The result set.
	 * @param $type string The location called from.
	 * @param int $totalTime
	 */
	protected function debug($query, $results = null, $type = 'select', $totalTime = 0) {
		if (!$this->isDevelopment) {
			return;
		}

		$color = $type === 'error' ? 'red' : 'orange';
		$printType = $type === 'error' ? 'Error' : 'Debug';

		header('Content-Type: text/html; charset=utf-8');
		echo "<style type='text/css'>
.container {
border: solid {$color} 1px; margin: 1rem;direction:ltr;text-align:left;font-size: 16px;font-family: sans-serif
}
.container > strong {
float:left; padding: 0.5em 1em; margin-right: 1em; background-color:{$color}; color: white;
}
.container > div {
padding: 0.5rem 1rem; margin: 0;
}
.container > code {
padding: 0.5em 1em; background-color: #DDF; display: block
}
.container table {
font-size: 0.833rem
}
.container table tr:nth-child(2n) {
background-color: rgba(0,0,0,.05);
}
.container table td, 
.container table th {
padding: 0.25em 0.5em;
}
.container small {
display: block;
margin-top: 1.5rem;
}</style>
        <section class='container'><strong>{$printType}:</strong>";

		echo '<code>' . htmlentities($query) . '</code>';

		echo '<div>';
		switch ($type) {
			case 'select':
				$this->printResults($results);
				break;
			case 'insert':
				echo 'Inserted Ids: ' . (is_array($results) ? implode(', ', $results) : $results);
				break;
			case 'update':
			case 'delete':
				echo 'Number of affected rows: ' . $results;
				break;
			case 'error':
				echo $results;
				break;
		}

		echo '<small>Total exec time: ' . $totalTime . '</small></div></section>';
	}

	/** Internal protected function to output a table representing the result of a query, for debug purpose.\n
	 * Should be preceded by a call to debugQuery().
	 *
	 * @param $results Object The resulting table of the query.
	 */
	protected function printResults($results = null) {
		if (is_array($results) && count($results)) {
			$columns = array_keys($results[0]);

			echo '<table>';
			echo '<thead><tr>';

			for ($i = 0; $i < count($columns); $i++) {
				echo '<th>' . $columns[$i] . '</th>';
			}

			echo '</tr></thead>';
			//END HEADER
			foreach ($results as $row) {
				echo '<tr>';
				for ($i = 0; $i < count($columns); $i++) {
					echo '<td>' . htmlspecialchars($row[$columns[$i]]) . '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}

	/** Write the SQL error to file
	 * @param $query
	 */
	protected function logError($query) {
		if (!$this->logger) {
			return;
		}

		$stack_trace = debug_backtrace();

		//find the depth
		$className = get_class($this);
		for ($i = 0; $i < count($stack_trace); $i++) {
			if ($stack_trace[$i]['class'] != $className) {
				break;
			}
		}

		$err = array(
			'dateTime' => date('H:i:s d-m-Y  (T)'),
			'query' => $query,
			'error' => array('type' => 'SQLError', 'msg' => $this->mysqli->error),
			'script' => array('name' => $stack_trace[$i - 1]['file'], 'line' => $stack_trace[$i - 1]['line']),
			'ip' => $_SERVER['REMOTE_ADDR'],
		);

		$this->logger->error(json_encode($err));
	}

	private function handleResults($queryBuilder, $postProcess = null) {
		$startTime = $this->getMicroTime();
		$this->nbQueries++;

		return function ($sql, $params, $debug) use ($queryBuilder, &$postProcess, $startTime) {
			($stmt = $this->mysqli->prepare($sql)) or $this->debugAndDie($queryBuilder->toString());
			$this->bindParams($params, $stmt);

			$stmt->execute() or $this->debugAndDie($queryBuilder->toString());

			if ($queryBuilder instanceof Select) {
				$results = $this->dynamicBindResults($stmt);
			}
			else {
				$results = $stmt->insert_id ? $stmt->insert_id : $stmt->affected_rows;
			}

			//Close the statement
			$stmt->close();

			$type = explode('\\', get_class($queryBuilder));
			$type = strtolower(array_pop($type));

			$debug and $this->debug($queryBuilder->toString(), $results, $type, $this->getExecTime($startTime));

			if (is_callable($postProcess) && $postProcess instanceof Closure) {
				return call_user_func($postProcess, $results);
			}

			return $results;
		};
	}
}
