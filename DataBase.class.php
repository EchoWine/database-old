<?php

/**
 * Database, permits to handle connections and calls to DataBase with PDO
 */
class DB{


	/**
	 * Configuration
	 */
	private static $config;
	
	/**
	 * Connection
	 */
	public static $con;
	
	/**
	 * Log
	 */
	protected static $log;

	/**
	 * Contains the last ID of the table and it's used for rollback
	 */
	public static $rollbackLastID;

	/**
	 * Contains the last name of the table and it's used for rollback
	 */
	public static $rollbackLastTable;
	
	/**
	 * Create a new connection
	 */
	public static function connect($cfg){

		self::$config = $cfg;
		
		try{

			self::$con = new PDO(
				$cfg['driver'].":host=".$cfg['hostname'].";charset=".$cfg['charset'],
				$cfg['username'],
				$cfg['password'],
				array(
					PDO::MYSQL_ATTR_LOCAL_INFILE => true,
					PDO::ATTR_TIMEOUT => 60,
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
				)
			);
			
		}catch(PDOException $e){
			self::printError("<b>You can't connect to the host</b><br>".$e->getMessage());
		}

		self::select($cfg['database']);
		self::iniRollback();

	}
	
	/**
	 * Select the database
	 *
	 * @param string $db name of database
	 */
	public static function select($db){
		if(self::getAlterSchema())
			self::query("CREATE DATABASE IF NOT EXISTS $db");
		
		
		self::query("SET GLOBAL connect_timeout=500;");
		self::query("USE $db");
		self::query("set names utf8");

	}
	
	/**
	 * Close the connection of the database
	 */
	public static function close(){
		self::$con = NULL;
	}
	
	/**
	 * Return the name of the database
	 *
	 * @return string name database
	 */
	public static function getName(){
		return self::$config['database'];
	}

	/**
	 * Return the value of alter_schema
	 *
	 * @return string name database
	 */
	public static function getAlterSchema(){
		return self::$config['alter_schema'];
	}
	
	/**
	 * Return information about the database
	 *
	 * @return string information about the database
	 */
	public static function getServerInfo(){
		return 
			self::$con -> getAttribute(PDO::ATTR_DRIVER_NAME)." ".
			self::$con -> getAttribute(PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Execute the escape function
	 *
	 * @param string $s string to filtrate
	 * @return string string to filtrate
	 */
	public static function quote($s){
		return $s;
	}
	
	/**
	 * Add characters of escape on the string for a query
	 *
	 * @return $s (string) string to filtrate
	 * @return string string to filtrate
	 */
	public static function escape($s){
		$s = str_replace("_","\_",$s);
		$s = str_replace("%","\%",$s);
		return $s;
	}

	/**
	 * Execute the query
	 *
	 * @param string $query SQL code
	 * @return object PDO object
	 */
	public static function query($query){

		try{
			$r = self::$con -> query($query);

		}catch(PDOException $e){
			self::printError("<b>Query</b>: <i>$query</i><br>".$e -> getMessage());
		}

		if(!$r)
			self::printError("<b>Query</b>: <i>$query</i><br>".self::$con -> errorInfo()[2]."");
		
		
		self::$log[] = "<i>".$query."</i>";

		return $r;
	}

	/**
	 * Execute the query with specific values to filtrate
	 *
	 * @param string $query SQL code
	 * @param array $a array of values
	 * @return object PDO object
	 */
	public static function execute($query,$a){
		
		// Converto la query in una stringa leggibile
		$r = array_reverse($a);
		$k = array_keys($r);
		$v = array_values($r);
		foreach($v as &$e)
			$e = "'{$e}'";

		$q = str_replace($k,$v,$query);

		try{

			$r = self::$con -> prepare($query);
			$r -> execute($a);

		}catch(PDOException $e){
			self::printError("<b>Query</b>: <i>$q</i><br>".$e -> getMessage());
		}

		if(!$r)
			self::printError("<b>Query</b>: <i>$q</i><br>".self::$con -> errorInfo()[2]."");
		

		self::$log[] = "<i>".$q."</i>";
		return $r;
	}
	
	/**
	 * Execute the query and return a result as array
	 *
	 * @param object $q PDO object
	 * @return array result
	 */
	public static function fetch($q){
		return $q -> fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Count the results of a query
	 *
	 * @param object $q PDO object
	 * @return int number of result
	 */
	public static function count($q){
		return $q -> rowCount();
	}
	
	/**
	 * Print the log
	 */
	public static function printLog(){
		echo "<h1>DataBase Log</h1>";
		echo implode(self::$log,"<br>");
	}
	
	/**
	 * Print the error
	 *
	 * @param string $error body of the error
	 */
	private static function printError($error){
		echo "<h1>DataBase Error</h1>";
		echo $error."<br>";
		// throw new Exception($error);
		die();
	}
		

	/**
	 * Get the value of the last field AUTO_INCREMENT insert
	 *
	 * @return int last value of the field AUTO_INCREMENT
	 */
	public static function getInsertID(){
		return self::$con -> lastInsertId();
	}


	/**
	 * Create the table that handle the rollback
	 */
	private static function iniRollback(){

		if(!self::$config['alter_schema'])return;
		
		self::query("
			CREATE TABLE IF NOT EXISTS database_rollback(
				id BIGINT(11) auto_increment,
				table_rollback varchar(55),
				table_from varchar(55),
				primary key (id)
			);
		");

		self::_delete();
	}

	/**
	 * Save the current status of the table
	 *
	 * @param string $table name of the table
	 */
	public static function save($table){
		self::$rollbackLastTable = self::_save($table);
	}

	/**
	 * Confirm the last operation
	 */
	public static function commit(){
		self::_delete();
	}

	/**
	 * Bring back the status of a table before the last save
	 */
	public static function undo(){
		$table = self::$rollbackLastTable;
		
		$q = self::query("SELECT * FROM database_rollback WHERE table_rollback = '{$table}'");
		$a = $q -> fetch();
		self::_restore($a['table_from'],$a['table_rollback']);

		self::query("DROP TABLE {$table}");
		self::query("DELETE FROM database_rollback WHERE table_rollback = '{$table}'");
	}

	/**
	 * Save a table
	 *
	 * @param string $table name of the table
	 */
	private static function _save($table){
		do{
			$name = md5(microtime());
			$name = "database_rollback_{$name}";
		}while(false);

		self::query("INSERT INTO database_rollback (table_rollback,table_from) VALUES ('{$name}','{$table}')");
		self::$rollbackLastID = self::getInsertID();
		self::query("CREATE TABLE {$name} LIKE {$table}");
		self::query("INSERT {$name} SELECT * FROM {$table}");

		return $name;

	}
	
	/**
	 * Delete the last saved operation
	 */
	private static function _delete(){

		// Cancellare l'ultima istanza
		$l = self::$config['rollback'] - 1;
		$q = self::query("SELECT table_rollback FROM database_rollback ORDER BY id ASC");
		if(self::count($q) > self::$config['rollback']){
			$a = $q -> fetch();
			self::query("DROP table {$a['table_rollback']}");
			self::query("DELETE FROM database_rollback WHERE table_rollback = '{$a['table_rollback']}'");
		}

	}

	/**
	 * Restore a table
	 *
	 * @param string $t1 name of table to restore
	 * @param stirng $t2 name of table to take data
	 * @return bool result of the query
	 */
	private static function _restore($t1,$t2){

		return self::query("TRUNCATE table {$t1}") && 
		self::query("INSERT {$t1} SELECT * FROM {$t2}");
	}

	/**
	 * Execute a rollback
	 *
	 * @param int $n number of operations to going back
	 * @param int $id ID of the operation from which start
	 * @param bool $overwrite overwrite the records during the rollback
	 * @return bool result of the operation
	 */
	public static function rollback($n = 1,$id = NULL,$overwrite = true){

		if($n < 1) $n = 1;
		$n--;

		$w = isset($id) && !empty($id) ? "WHERE id = {$id} ORDER BY id DESC " : " ORDER BY id DESC LIMIT {$n},1";

		$q = self::query("SELECT * FROM database_rollback {$w}");
		if(self::count($q) == 1){
			$a = $q -> fetch();
			
			self::_save($a['table_from']);
			
			if($overwrite){
				self::_restore($a['table_from'],$a['table_rollback']);
			}else{
				$q1 = true;
				$q2 = self::query("
					INSERT IGNORE {$a['table_from']} 
					SELECT * FROM {$a['table_rollback']} 
					ON DUPLICATE KEY UPDATE {$a['table_from']}.id = {$a['table_from']}.id
				");
			}

			self::_delete();
			return $q1 && $q2;
		}
		return false;
	}

	/**
	 * Check if a table exists
	 *
	 * @param string $v name of the table
	 * @return bool return if the table exists (true) or not (false)
	 */
	public static function hasTable($v){
		return self::count(self::query("SHOW TABLES LIKE '{$v}'")) == 1;
	}

	/**
	 * Create a new object QueryBuilder
	 *
	 * @param string $v name of the table
	 * @param string $as alias of the table
	 * @return object QueryBuilder object
	 */
	public static function table($v,$as = ''){
		return new QueryBuilder($v,$as);
	}

}
?>
