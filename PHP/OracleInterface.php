<?php

/**
 * This is a basic oracle interface class.
 *
 * Class OracleInterface
 */
class OracleInterface{

	/**
	 * @var
	 */
	private $connectionHandle;

	/**
	 * @var array
	 */
	private $statements = array();

	/**
	 * @var bool
	 */
	private $autocommit = true;

	/**
	 * @var int
	 */
	private $fetchMode = OCI_ASSOC;

	/**
	 * @var
	 */
	private $lastQuery;

	/**
	 * @var int
	 */
	private $varMaxSize = 1000;

	/**
	 * @var bool
	 */
	private $executeStatus = false;

	/**
	 * @var
	 */
	private $executeError;

	/*
	 * Set on|off auto commit option
	 *
	 * @param bool $mode
	 */
	public function setAutoCommit($mode = true)
	{
		$this->autocommit = $mode;
	}

	/*
	 * Set variable max size for binding
	 *
	 * @param int $size
	 */
	public function setVarMaxSize($size)
	{
		$this->varMaxSize = $size;
	}

	/*
	 * Returns the last error found.
	 */
	public function getError()
	{
		return oci_error($this->connectionHandle);
	}

	/*
	 * Constructor
	 */
	public function __construct()
	{
		$this->setFetchMode(OCI_ASSOC);
		$this->setAutoCommit(true);
	}

	public function __destruct()
	{
		if (is_resource($this->connectionHandle)) {
			oci_close($this->connectionHandle);
		}
	}

	public function connect($username,$password,$connectionString)
	{
		// if already connected return true
		if( is_resource($this->connectionHandle) ) return true;

		// attempt connection
		$this->connectionHandle = oci_connect( $username, $password, $connectionString );

		return is_resource($this->connectionHandle) ? true : false;
	}

	/**
	 * Returns bool value based if last command was successful
	 *
	 * @return bool
	 */
	public function getExecuteStatus()
	{
		return $this->executeStatus;
	}

	/**
	 * Get values from the database
	 *
	 * @param   string          $sql    select column from schema.table where column = value || column = :bindValue
	 * @param   bool|array      $bind   [:bindValue=>value]
	 * @return  resource|false
	 */
	public function select($sql, $bind = false)
	{
		return $this->execute($sql, $bind);
	}

	/**
	 * Set array fetching mode for oci_fetch_array
	 *
	 * @param mixed $mode
	 */
	public function setFetchMode($mode = OCI_ASSOC)
	{
		$this->fetchMode = $mode;
	}

	/**
	 * @param $statement
	 *
	 * @return array
	 */
	public function fetchArray($statement)
	{
		return oci_fetch_array($statement, $this->fetchMode);
	}

	public function fetchAssoc($statement)
	{
		return oci_fetch_assoc($statement);
	}

	public function fetchObject($statement)
	{
		return oci_fetch_object($statement);
	}

	/**
	 *
	 *
	 * @param string    $statement
	 * @param int       $skip
	 * @param int       $maxrows
	 *
	 * @return array
	 */
	public function fetchAll($statement, $skip = 0, $maxrows = -1)
	{
		$rows = array();
		oci_fetch_all($statement, $rows, $skip, $maxrows, OCI_FETCHSTATEMENT_BY_ROW );
		return $rows;
	}

	/**
	 * Insert data into any table/schema, $table needs to be in schema.table format
	 *
	 * @param string        $table              schema.table'
	 * @param array         $columnValuesArray  [column=>value] or [column => :bindValue]
	 * @param bool|array    $bind               [:bindValue => value]
	 *
	 * @return bool|resource
	 */
	public function insert($table, $columnValuesArray, $bind = false)
	{
		if (empty($columnValuesArray)) return false;
		$fields = array();
		$values = array();
		foreach($columnValuesArray as $f=>$v){
			$fields[] = $f;
			$values[] = $v;
		}
		$fields = implode(",", $fields);
		$values = implode(",", $values);
		$sql = "insert into $table ($fields) values ($values)";
		$result = $this->execute($sql, $bind);
		if ($result === false) return false;
		else return $result;
	}

	/**
	 * Apply updates to a row or rows based on conditions set
	 *
	 * @param string        $table              schema.table
	 * @param array         $columnValuesArray  [column=>value] or [column => :bindValue]
	 * @param bool|array    $whereArray         [column=>value] or [column => :bindValue]
	 * @param bool|array    $bind               [:bindValue => value]
	 *
	 * @return bool|resource
	 */
	public function update($table, $columnValuesArray, $whereArray = false, $bind = false)
	{
		if (empty($columnValuesArray)) return false;
		$fields = array();
		foreach($columnValuesArray as $f=>$v){
			$fields[] = "$f = $v";
		}
		$fields = implode(",", $fields);

		if ($whereArray !== false) {
			foreach($whereArray as $f=>$v){
				$evaluator = ( ($v == 'null')||($v == 'NULL') )? 'is':'=';
				$where[] = "$f $evaluator $v";
			}
			$where = "where " . implode(" and ", $where);
			;
		}
		$sql = "update $table set $fields $where";
		$result = $this->execute($sql, $bind);
		if ($result === false) return false;
		else return $result;
	}

	/**
	 * @param string     $table         schema.table
	 * @param bool|array $whereArray    [column=>value] or [column => :bindValue]
	 * @param bool|array $bind          [bindValue=>value]
	 *
	 * @return bool|resource
	 */
	public function delete($table, $whereArray = false, $bind = false)
	{
		if ($whereArray !== false) {
			$fields = [];
			foreach($whereArray as $f=>$v){
				$fields[] = "$f = $v";
			}
			$where = "where " . implode(" and ", $fields);
		}
		$sql = "delete from $table $where";
		$result = $this->execute($sql, $bind);
		if ($result === false) return false;
		else return $result;
	}

	/**
	 * Executes a sql statement and binds any values sent
	 *
	 * @param string        $sql
	 * @param bool|array    $bind
	 *
	 * @return bool|resource
	 */
	private function execute($sql, $bind = false)
	{

		if (!is_resource($this->connectionHandle)) return false; //if connection failed, return
		$this->lastQuery = $sql; // setting the last query

		$stid = oci_parse($this->connectionHandle, $sql);
		$now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));

		$key  = $now->format("H:i:s.u"); // ensure unique key. todo: maybe use a timestamp instead.

		$this->statements[$key]['text'] = $sql;
		$this->statements[$key]['bind'] = $bind;

		if ($bind && is_array($bind)) {
			foreach($bind as $k=>$v){
				oci_bind_by_name($stid, $k, $bind[$k], $this->varMaxSize, $this->getBindingType($bind[$k]));
			}
		}
		$commitMode          = ($this->autocommit) ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT;
		$this->executeStatus = oci_execute($stid, $commitMode);
		if( $this->executeStatus == false) { $this->executeError = oci_error($stid); }
		return $this->executeStatus ? $stid : false;
	}

	private function getBindingType($var){
		if (is_a($var, "OCI-Collection")) {
			$bind_type = SQLT_NTY;
			$this->setVarMaxSize(-1);
		} elseif (is_a($var, "OCI-Lob")) {
			$bind_type = SQLT_CLOB;
			$this->setVarMaxSize(-1);
		} else {
			$bind_type = SQLT_CHR;
		}
		return $bind_type;
	}


	/**
	 * Commits any pending changes on the current connection resource
	 *
	 * @return bool - commits changes or returns false if connection is closed
	 */
	public function commit()
	{
		if (is_resource($this->connectionHandle))
			return oci_commit($this->connectionHandle);
		else
			return false;
	}

	/**
	 * Rollback
	 *
	 * @return bool
	 */
	public function rollback()
	{
		if (is_resource($this->connectionHandle))
			return oci_rollback($this->connectionHandle);
		else
			return false;
	}

	/**
	 * Closes the connection
	 */
	public function bye()
	{
		$this->__destruct();
	}

	/**
	 * Returns the current connection resource
	 *
	 * @return mixed
	 */
	public function getHandle()
	{
		return $this->connectionHandle;
	}

	/**
	 * dumps our the the past queries
	 */
	public function dumpQueriesStack()
	{
		var_dump($this->statements);
	}

	public function returnQueriesStack()
	{
		return $this->statements;
	}

	public function getExecuteError()
	{
		return $this->executeError;
	}

}