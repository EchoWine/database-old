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

		$this -> schema = new SchemaColumn(['name' => strtolower($v),'table' => $this -> getTable()]);

		if($type !== null)
			$this -> type($type,$length);
		

		return $this;
	}

	/**
	 * Defines the column id
	 *
	 * @return object $this
	 */
	public function id(){

		$this -> column('id','bigint',11);
		$this -> primary();
		$this -> auto_increment();
		return $this;
	}
	

	/**
	 * Defines the column timestamp
	 *
	 * @param string $name
	 * @return object $this
	 */
	public function timestamp($name){
		$this -> column($name,'int',10);
		return $this;
	}

	/**
	 * Defines the column md5
	 *
	 * @param string $name
	 * @return object $this
	 */
	public function md5($name){
		$this -> column($name,'int',10);
		return $this;
	}

	/**
	 * Defines the column string
	 *
	 * @param string $name
	 * @param string $length
	 * @return object $this
	 */
	public function string($name,$length = 80){
		$this -> column($name,'varchar',$length);
		return $this;
	}

	/**
	 * Defines the column string
	 *
	 * @param string $name
	 * @return object $this
	 */
	public function bigint($name){
		$this -> column($name,'bigint',11);
		return $this;
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
		$this -> schema -> setIndex($this -> schema -> getName());
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

		$new = false;

		# Check if table doesn't exists
		if(!Schema::hasTable($this -> getTable())){

			# Alter database
			$this -> query($this -> SQL_createTable());

			# Update table schema
			Schema::addTable($this -> getTable());
			Schema::getTable($this -> getTable()) -> addColumn(clone $this -> schema);
			$new = true;
		}



		# Check if column doesn't exists
		if(!$this -> hasColumn($this -> schema -> getName())){
			$this -> query($this -> SQL_addColumn());

			# Update Schema
			Schema::getTable($this -> getTable()) -> addColumn(clone $this -> schema);

			$new = true;
		}

		# Actual schema
		$a = Schema::getTable($this -> getTable()) -> getColumn($this -> schema -> getName());

		if($new){
			$a -> setIndex(null);
			$a -> setForeign('','');
		}


		# New schema
		$n = $this -> schema;
			
		# Update schema
		self::$tables[$this -> getTable()] -> setColumn($n);

		/*
		print_r("DB:");
		print_r($a);
		print_r("PHP:");
		print_r($n);
		*/
		

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
			if(!$n -> equalsForeign($a) && $a -> getForeign()){
				$this -> query("ALTER TABLE {$this -> table} DROP FOREIGN KEY {$a -> getConstraint()}");

				# Update Schema actual
				$a -> setConstraint(null);
				$a -> setForeign(null,null);
				$a -> setForeignDelete(null);
				$a -> setForeignUpdate(null);
			}

			
			if($n -> getForeign()){
				$this -> query($this -> SQL_addColumnKey('foreign'));

				# Get new name of constraint
				$constraint = DB::fetch("select CONSTRAINT_NAME from information_schema.key_column_usage WHERE CONSTRAINT_SCHEMA = '".DB::getName()."' AND TABLE_NAME = '{$this -> getTable()}' AND COLUMN_NAME = '{$a -> getName()}'")[0]['CONSTRAINT_NAME'];

				# Update Schema actual
				$a -> setConstraint($constraint);
				$a -> setForeign($this -> schema -> getForeignTable(),$this -> schema -> getForeignColumn());
				$a -> setForeignDelete($this -> SQL_columnKeyForeinDelete());
				$a -> setForeignUpdate($this -> SQL_columnKeyForeinDelete());
			}
			

			# Update index column
			if($a -> hasIndex() && !$n -> hasIndex()){
				$this -> query("ALTER TABLE {$this -> table} DROP INDEX {$a -> getIndex()}");

				# Update Schema actual
				$a -> setIndex(null);
			}

			if(!$a -> hasIndex() && $n -> hasIndex()){
				$this -> query($this -> SQL_addColumnKey('index'));

				# Update Schema actual
				$a -> setIndex($a -> getName());
			}


		}

		if(!$n -> equals($a)){

			# Update column
			$this -> query($this -> SQL_editColumn());

			# Update Schema actual
			Schema::getTable($this -> getTable()) -> setColumn(clone $n);

			return true;
		}

		return false;
	}



	/**
	 * Drop the table
	 *
	 * @return result query
	 */
	public function drop(){
		if(isset(Schema::$tables[$this -> getTable()])){

			foreach(Schema::getAllForeignKeyTo($this -> getTable()) as $k){
				DB::schema($k -> getTable()) -> dropForeignKey($k -> getName());
				$this -> query($this -> SQL_dropForeignKey($k));
			}

			unset(Schema::$tables[$this -> getTable()]);
			unset(self::$tables[$this -> getTable()]);

			return $this -> query($this -> SQL_dropTable());
		}
	}

	/**
	 * Drop the column
	 *
	 * @return result query
	 */
	public function dropColumn($column){


		if(($table = Schema::getTable($this -> getTable())) == null)return;
		$c = $table -> getColumn($column);

		if($c == null)return;

		if($c -> getForeign()){

			$this -> query($this -> SQL_dropForeignKey($c));
		}

		if($c -> getPrimary()){

			$a = Schema::getTable($this -> getTable());

			foreach(Schema::getAllForeignKeyToColumn($this -> getTable(),$c -> getName()) as $k){
				$this -> query($this -> SQL_dropForeignKey($k));
				self::$tables[$k -> getTable()] -> getColumn($k -> getName()) -> resetForeign($k);
			}

			DB::query($this -> SQL_resetSchemaColumn($c));
			DB::query("ALTER TABLE {$this -> getTable()} DROP PRIMARY KEY");
		}else if($c -> hasIndex())
			DB::query("ALTER TABLE {$this -> getTable()} DROP INDEX {$c -> getIndex()}");	
		

		if($table -> countColumns() == 1){
			unset(Schema::$tables[$this -> getTable()]);
			unset(self::$tables[$this -> getTable()]);
			return $this -> drop();

		}else{
			Schema::$tables[$this -> getTable()] -> dropColumn($c -> getName());
			self::$tables[$this -> getTable()] -> dropColumn($c -> getName());
			return $this -> query($this -> SQL_dropColumn($c -> getName()));
		}
	}

	public function SQL_dropTable(){
		return "DROP TABLE {$this -> getTable()}";
	}

	public function SQL_dropColumn($column){
		return "ALTER TABLE {$this -> getTable()} DROP COLUMN {$column}";
	}

	public function SQL_dropForeignKey($column){
		return "ALTER TABLE {$column -> getTable()} DROP FOREIGN KEY {$column -> getConstraint()}";
	}

	public function SQL_resetSchemaColumn($column){
		return "ALTER TABLE {$this -> getTable()} MODIFY {$column -> getName()} tinyint(1)";
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