<?php

/**
 * @class DB
 * Class Database, permits to handle connections and calls to DBMS with the PDO
 */
class DB{


	/**
	 * Configuration
	 */
	public static $config;
	
	/**
	 * Connection
	 */
	public static $con;
	
	/**
	 * Log
	 */
	protected static $log;

	/**
	 * Object that contains information for simplified query
	 */
	public static $exe;

	/**
	 * Oggetto per schema
	 * #Tradurre#
	 */
	public static $schema;

	/**
	 * Contains the last ID of the table and it's used for rollback
	 */
	public static $save_id;

	/**
	 * Contains the last name of the table and it's used for rollback
	 */
	public static $save_name;
	
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
			die();
		}

		self::selectDB($cfg['database']);
		self::iniRollback();

	}
	
	/**
	 * Select the database
	 * @param $db (string) nome database
	 */
	public static function selectDB($db){
		if(self::$config['alter_schema'])
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
	 * Execute the query
	 * @param $query (string) codice SQL
	 * @return (object) oggetto PDO
	 */
	public static function query($query){

		try{
			$r = self::$con -> query($query);

		}catch(PDOException $e){
			self::printError("<b>Query</b>: <i>$query</i><br>".$e -> getMessage());
			error_backtrace();
			die();
		}

		if(!$r){
			self::printError("<b>Query</b>: <i>$query</i><br>".self::$con -> errorInfo()[2]."");
			error_backtrace();
			die();
		}
		
		self::$log[] = "<i>".$query."</i>";

		return $r;
	}

	/**
	 * Execute the query with specific values to filtrate
	 * @param $query (string) codice SQL
	 * @param $a (array) array di valori
	 * @return (object) oggetto PDO
	 */
	public static function execute($query,$a){
		
		try{

			$r = self::$con -> prepare($query);
			$r -> execute($a);
			

		}catch(PDOException $e){
			self::printError("<b>Query</b>: <i>$query</i> <br><b>Value</b>: <i>".json_encode($a)."</i><br>".$e -> getMessage());
			error_backtrace();
			die();
		}

		if(!$r){
			self::printError("<b>Query</b>: <i>$query</i><br>".self::$con -> errorInfo()[2]."");
			error_backtrace();
			die();
		}


		self::$log[] = "<i>".$query." (".json_encode($a).")</i>";
		return $r;
	}
	
	/**
	 * Execute the query and return a result as array
	 * @param $q (object) PDO object
	 * @return (array) risultato
	 */
	public static function fetch($q){
		return $q -> fetchAll(PDO::FETCH_ASSOC);
	}

	/**
	 * Count the results of a query
	 * @param $q (object) PDO object
	 * @return (int) numero risultati
	 */
	public static function count($q){
		return $q -> rowCount();
	}
	
	/**
	 * Print the log
	 */
	public static function printLog(){
		$log = new log("DataBase Log");
		$log -> setLog(self::$log);
		$log -> print_();
	}
	
	/**
	 * Print the error
	 * @param $error (string) contenuto dell'errore
	 */
	private static function printError($error){
		echo "<h1>DataBase error</h1>";
		echo $error;
	}
	
	/**
	 * Check if a table exists
	 * @param $name (string) nome della tabella
	 * @return (bool) la tabella esiste (true) o meno (false)
	 */
	public static function if_table_exists($name){
		return (self::count(self::query("SHOW TABLES LIKE '$name'")) == 1);
	}
		
	/**
	 * Return the name of the database
	 * @return (string) nome database
	 */
	public static function getName(){
		return self::$config['database'];
	}
	
	/**
	 * Execute the escape function
	 * @param $s (string) stringa da filtrare
	 * @return (string) stringa filtrata
	 */
	public static function quote($s){
		return $s;
	}

	/**
	 * Get the value of the last field AUTO_INCREMENT insert
	 * @return (int) ultimo valore del campo AUTO_INCREMENT
	 */
	public static function insert_id(){
		return self::$con -> lastInsertId();
	}

	/**
	 * Return information about the database
	 * @return (string) informazioni sul database
	 */
	public static function get_server_info(){
		return 
			self::$con -> getAttribute(PDO::ATTR_DRIVER_NAME)." ".
			self::$con -> getAttribute(PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Add characters of escape on the string for a query
	 * @return $s (string) stringa da filtrare
	 * @return (string) stringa filtrata
	 */
	public static function escapeQuery($s){
		$s = str_replace("_","\_",$s);
		$s = str_replace("%","\%",$s);
		return $s;
	}

	/**
	 * Create the table that handle the rollback
	 */
	public static function iniRollback(){

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
	 * @param $table (string) nome tabella
	 */
	public static function save($table){
		self::$save_name = self::_save($table);
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
		$table = self::$save_name;
		self::_rollback();
		self::query("DROP TABLE {$table}");
		self::query("DELETE FROM database_rollback WHERE table_rollback = '{$table}'");
	}

	/**
	 * Save a table
	 * @param $table (string) nome tabella
	 */
	public static function _save($table){
		// Salvo i dati...	
		do{
			$name = md5(microtime());
			$name = "database_rollback_{$name}";
		}while(false);

		self::query("INSERT INTO database_rollback (table_rollback,table_from) VALUES ('{$name}','{$table}')");
		self::$save_id = self::insert_id();
		self::query("CREATE TABLE {$name} LIKE {$table}");
		self::query("INSERT {$name} SELECT * FROM {$table}");

		return $name;

	}
	
	/**
	 * Delete the last saved operation
	 */
	public static function _delete(){

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
	 * Execute a rollback
	 */
	public static function _rollback(){
		$table = self::$save_name;
		$q = self::query("SELECT * FROM database_rollback WHERE table_rollback = '{$table}'");
		$a = $q -> fetch();
		self::query("TRUNCATE table {$a['table_from']}");
		self::query("INSERT {$a['table_from']} SELECT * FROM {$a['table_rollback']}");
	}

	/**
	 * Predispose everything for a rollback
	 * @param $n (int) numero di operazioni da cui tornare indietro
	 * @param $id (int) identificatore dell'operazione da cui partire
	 * @param $overwrite (bool) sovrascrivi i record durante il rollback
	 * @return (bool) risultato dell'operazione
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
				$q1 = self::query("TRUNCATE table {$a['table_from']}");
				$q2 = self::query("INSERT {$a['table_from']} SELECT * FROM {$a['table_rollback']}");
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
	 * Check if a table or a column exists
	 * @param $w (string) indica se si tratta di una colonna o di una tabella
	 * @param $t (string) nome della tabella
	 * @param $n (string) optional nome della colonna
	 * @return (bool) l'oggetto ricercato esiste (true)
	 */
	public static function exists($w,$t,$n = ''){
		switch($w){
			case 'column':
				$q = self::query("
					SELECT * FROM information_schema.COLUMNS  WHERE 
					TABLE_SCHEMA = '{self::getName()}' AND 
					TABLE_NAME = '{$t}' AND
					COLUMN_NAME = '{$n}'
				");
				return self::count($q) == 1;
			break;
			case 'table':
				return self::if_table_exists($t);
			break;
		}				
	}

	/**
	 * Check if a table exists
	 * @param $v (string) nome della tabella
	 * @return (bool) restituisce se la tabella esiste (true) o meno (false)
	 */
	public static function hasTable($v){
		return self::count(self::query("SHOW TABLES LIKE '{$v}'")) == 1;
	}

	/**
	 * Check if a column exists
	 * @param $v1 (string) nome della tabella
	 * @param $v2 (string) nome della colonna
	 * @return (bool) restituisce se la colonna esiste (true) o meno (false)
	 */
	public static function hasColumn($v1,$v2){
		return self::table('information_schema.COLUMNS')
			-> where('TABLE_SCHEMA',self::getName())
			-> where('TABLE_NAME',$v1)
			-> where('COLUMN_NAME',$v2)
			-> count() == 1;
	}

	/**
	 * Create a new object queryBuilder
	 * @param $v (string) nome tabella
	 * @param $as (string) alias tabella
	 * @return (object) oggetto queryBuilder
	 */
	public static function table($v,$as = ''){
		return new queryBuilder($v,$as);
	}

}

?>