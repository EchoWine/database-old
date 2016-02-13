<?php

/**
 * Schema Builder
 */
class SchemaBuilder{


	/**
	 * Information about the alteration of the DB pattern
	 */
	public $schema;

	public $table;

	public static $tables;

	/**
	 * Initializes the object, the call is made from the method table of the class Database
	 * 
	 * @param string $v
	 */
	public function __construct($v){

		$this -> table = $v;

		if(!isset(self::$tables[$v])){
			$s = new SchemaTable($v);
			self::$tables[$v] = $s;
		}

		return $this;
	}
	
	public function query($q){

		return DB::query($q);
	}

	/**
	 * Get builder name table
	 *
	 * @return string name table
	 */
	public function getTable(){
		return $this -> table;
	}


	/**
	 * Execute a reserch query of the number of the columns
	 *
	 * @return int number of the columns
	 */
	public function countColumns(){
		return Schema::tableCountColumns($this -> getTable());
	}

	/**
	 * Execute a reserch query that search if a column exist
	 *
	 * @param string $v predetermined column type or SQL code that defined the type of the column
	 * @return object $this
	 */
	public function hasColumn(string $v){
		return Schema::getTable($this -> getTable()) -> hasColumn($v);
	}

	/**
	 * Defines the column to use
	 *
	 * @param string $v name of the column
	 * @param string $type type of the column
	 * @param int $length length of the column
	 * @return object $this
	 */
	public function column(string $v,string $type = null,int $length = null){

		$this -> schema = new SchemaColumn(['name' => strtolower($v)]);

		$tty = $type == null ? $v : $type;
		if(!$this -> getTypeFromModel($tty,$length) && $type !== null){

			$this -> type($type,$length);
		}

		return $this;
	}

	public function getTypeFromModel($type,$length){

		switch($type){
			case 'timestamp': 
				$this -> type('int',10);
			break;

			case 'md5': 
				$this -> type('char',32);
			break;

			case 'id': 
				$this -> type('bigint',11);
				$this -> primary();
				$this -> index();
				$this -> auto_increment();
			break;

			case 'string':
				$length == null 
					? $this -> type('varchar',80) 
					: $this -> type('varchar',$length);
			break;

			case 'f_id': 
				$this -> type('bigint',11);
			break;
			default: 
				return false;
			break;
		}

		return true;

	}

	/**
	 * Return the SQL code for the selection
	 *
	 * @param string $t predetermined column type or SQL code that defined the type of the column
	 * @return object $this
	 */
	public function type(string $type,int $length = null){
		$this -> schema -> setType($type);
		$this -> schema -> setLength($length);

		return $this;
	}

	/**
	 * Makes the column a primary key
	 *
	 * @return object $this
	 */
	public function primary(){

		if(self::$tables[$this -> getTable()] -> hasPrimary())
			die('['.$this -> getTable().'] There can be only one primary key');
		

		$this -> schema -> setPrimary(true);
		$this -> index();
		return $this;
	}

	/**
	 * Makes the column auto_increment
	 *
	 * @return object $this
	 */
	public function auto_increment(){

		if(self::$tables[$this -> getTable()] -> hasAutoIncrement())
			die('['.$this -> getTable().'] There can be only one auto_increment field');
		

		$this -> schema -> setAutoIncrement(true);
		return $this;
	}

	/**
	 * Makes the column a unique key
	 *
	 * @return object $this
	 */
	public function unique(){
		$this -> schema -> setUnique(true);
		return $this;

	}

	/**
	 * Makes the column an index
	 *
	 * @return object $this
	 */
	public function index(){
		$this -> schema -> setIndex(true);
		return $this;
	}

	/**
	 * Makes the column a foreign key
	 *
	 * @param string $t name of the table referenced
	 * @param string $v name of the column referenced
	 * @return object $this
	 */
	public function foreign(string $t,string $v){
		
		$this -> schema -> setForeign($t,$v);
		$this -> index();
		return $this;
	}

	/**
	 * Add a SQL code everytime there is a delete
	 *
	 * @param string $c SQL code
	 */
	public function onDelete(string $c){
		$this -> schema -> setForeignDelete("{$c}");
	}
	
	/**
	 * Add a SQL code everytime there is an update
	 *
	 * @param string $c SQL code
	 */
	public function onUpdate(string $c){
		$this -> schema -> setForeignUpdate("{$c}");
	}

	/**
	 * Execute an alteration query of the pattern of the database according to setted parameters
	 *
	 * @return object result of the query
	 */
	public function alter(){
		if(!DB::getAlterSchema()) return;


		# Check if table doesn't exists
		if(!Schema::hasTable($this -> getTable())){

			# Alter database
			$this -> query($this -> SQL_createTable());

			# Update table schema
			Schema::addTable($this -> getTable());
			Schema::getTable($this -> getTable()) -> addColumn($this -> schema);
		}



		# Check if column doesn't exists
		if(!$this -> hasColumn($this -> schema -> getName())){
			$this -> query($this -> SQL_addColumn());

			# Update Schema
			Schema::getTable($this -> getTable()) -> addColumn($this -> schema);
		}

		# Actual schema
		$a = Schema::getTable($this -> getTable()) -> getColumn($this -> schema -> getName());

		# New schema
		$n = $this -> schema;
			
		# Update schema
		self::$tables[$this -> getTable()] -> setColumn($n);


		# Check if there is any difference between actual schema and new
		if(!$n -> equals($a)){


			# Drop X Primary key if
				# Actual: primary, New: Not primary
				# There is a primary key that isn't new. (There can be only one primary key)
			$p = Schema::getTable($this -> getTable()) -> getPrimary();

			if(($a -> getPrimary() && !$n -> getPrimary()) || $n -> getPrimary() && $p != null && !$p -> equals($n)){

				$primary = $p !== null ? $p : $n;

				$this -> query($this -> SQL_editColumnBasics($this -> table,$primary));

				$this -> query($this -> SQL_dropPrimaryKey($this -> table));
				Schema::getTable($this -> getTable()) -> dropPrimary();
			}

			

			# Update foreign column
			if(!$n -> equalsForeign($a) && $a -> getForeign())
				$this -> query("ALTER TABLE {$this -> table} DROP FOREIGN KEY {$a -> getConstraint()}");

			
			if($n -> getForeign())
				$this -> query($this -> SQL_addColumnKey('foreign'));
			

			print_r($n);
			print_r($a);
			# Update index column
			if($a -> getIndex() && !$n -> getIndex())
				$this -> query("ALTER TABLE {$this -> table} DROP INDEX {$a -> getName()}");

			if(!$a -> getIndex() && $n -> getIndex())
				$this -> query($this -> SQL_addColumnKey('index'));


			# Update column
			$this -> query($this -> SQL_editColumn());

			Schema::getTable($this -> getTable()) -> setColumn($n);
		}

		return false;
	}



	/**
	 * Drop the table
	 *
	 * @return result query
	 */
	public function drop(){
		return $this -> query($this -> SQL_dropTable());
	}

	public function SQL_dropTable(){
		return "DROP TABLE {$this -> getTable()}";
	}

	public function SQL_createTable(){
		return "CREATE TABLE IF NOT EXISTS {$this -> getTable()} ({$this -> SQL_column()})";
	}

	public function SQL_addColumn(){
		return "ALTER TABLE {$this -> getTable()} ADD {$this -> SQL_column()}";
	}

	public function SQL_editColumn(){
		return "ALTER TABLE {$this -> getTable()} CHANGE COLUMN {$this -> schema -> getName()} {$this -> SQL_column()}";
	}

	public function SQL_editColumnBasics($table,$schema){
		return "ALTER TABLE {$table} MODIFY {$schema -> getName()} 
		{$this -> SQL_columnType($schema -> getType(),$schema -> getLength())}";
	}


	
	public function SQL_addColumnKey($k){
		return "ALTER TABLE {$this -> getTable()} ADD {$this -> SQL_columnKey($k)}";
	}

	public function SQL_editColumnKey($k){
		// return "ALTER TABLE {$this -> getTable()} ADD {$this -> SQL_column()}";
	}

	public function SQL_dropPrimaryKey($table){
		return "ALTER TABLE {$table} DROP PRIMARY KEY";
	}

	public function SQL_column(){

		$s = $this -> schema;
		$unique = $s -> getUnique() ? 'UNIQUE' : '';
		$primary = $s -> getPrimary() ? 'PRIMARY KEY' : '';
		$auto_increment = $s -> getAutoIncrement() ? 'AUTO_INCREMENT' : '';
		$null = $s -> getNull() ? 'NULL' : 'NOT NULL';

		return $s -> getName()." ".$this -> SQL_columnType($s -> getType(),$s -> getLength())." ".$primary." ".$auto_increment." ".$unique." ".$null;

	}

	public function SQL_columnKey($type){
		$name = $this -> schema -> getName();
		switch($type){
			case 'index':
				return "INDEX ($name)";

			case 'foreign':
				return "
					FOREIGN KEY ($name) 
					REFERENCES {$this -> schema -> getForeignTable()}({$this -> schema -> getForeignColumn()})
					{$this -> SQL_columnKeyForeinDelete()}
					{$this -> SQL_columnKeyForeinUpdate()}
			";

		}

		return '';
	}

	public function SQL_columnKeyForeinDelete(){
		return $c = $this -> schema -> getForeignDelete() !== null ? ' ON DELETE '.$c : '';
	}

	public function SQL_columnKeyForeinUpdate(){
		return $c = $this -> schema -> getForeignUpdate() !== null ? ' ON UPDATE '.$c : '';
	}

	public function SQL_columnType($type,$length){

		switch($type){

			case 'varchar':
				return "VARCHAR($length)";

			case 'big_int':
				return "BIGINT($length)";

			case 'tiny_int': 
				return "TINYINT($length)";

			case 'text': 
				return 'TEXT';

			case 'float': 
				return "DOUBLE";

			default: 
				return $length != null ? $type."(".$length.")" : $type;

		}

	}
	

}