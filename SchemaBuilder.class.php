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

	public static $columns = [];
	public static $activeClosure = false;

	/**
	 * Initializes the object, the call is made from the method table of the class Database
	 * 
	 * @param string $table
	 * @param string $columns
	 */
	public function __construct($table,$columns = null){

		$this -> table = $table;

		if(!isset(self::$tables[$table])){
			$s = new SchemaTable($table);
			self::$tables[$table] = $s;
		}

		if($columns !== null && $columns instanceof Closure){
			self::$activeClosure = true;
			$columns($this);
			foreach((array)self::$columns[$table] as $column){
				$column -> alter();
			}

			self::$activeClosure = false;
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
		

		return $this -> updateThis();
	}

	/**
	 * Update this to all columns
	 */
	public function updateThis(){
		return self::$columns[$this -> getTable()][$this -> schema -> getName()] = clone $this;
	}

	/**
	 * Defines the column id
	 *
	 * @return object $this
	 */
	public function id(){

		$this -> column('id',DB::SQL()::BIGINT,11);
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
		$this -> column($name,DB::SQL()::BIGINT,10);
		return $this;
	}

	/**
	 * Defines the column md5
	 *
	 * @param string $name
	 * @return object $this
	 */
	public function md5($name){
		$this -> column($name,DB::SQL()::BIGINT,10);
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
		$this -> column($name,DB::SQL()::VARCHAR,$length);
		return $this;
	}

	/**
	 * Defines the column string
	 *
	 * @param string $name
	 * @return object $this
	 */
	public function bigint($name){
		$this -> column($name,DB::SQL()::BIGINT,11);
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
			self::printError('There can be only one primary key');
		

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
			self::printError('There can be only one auto_increment field');
		

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
		return $this -> index();
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
	 * Makes the column nullable
	 *
	 * @return object $this
	 */
	public function null(){
		$this -> schema -> setNull(true);
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

				$this -> query(DB::SQL()::DROP_PRIMARY_KEY($this -> getTable()));
				Schema::getTable($this -> getTable()) -> dropPrimary();
			}


			# Update foreign column
			if(!$n -> equalsForeign($a) && $a -> getForeign()){
				$this -> query(DB::SQL()::DROP_FOREIGN_KEY($this -> getTable(),$a -> getConstraint()));

				# Update Schema actual
				$a -> setConstraint(null);
				$a -> setForeign(null,null);
				$a -> setForeignDelete(null);
				$a -> setForeignUpdate(null);
			}

			
			if($n -> getForeign()){
				$this -> query($this -> SQL_addColumnForeign());

				# Get new name of constraint
				$constraint = DB::first(DB::SQL()::SELECT_CONSTRAINT(DB::getName(),$this -> getTable(),$a -> getName()))['CONSTRAINT_NAME'];

				# Update Schema actual
				$a -> setConstraint($constraint);
				$a -> setForeign($this -> schema -> getForeignTable(),$this -> schema -> getForeignColumn());
				$a -> setForeignDelete($this -> SQL_columnKeyForeinDelete());
				$a -> setForeignUpdate($this -> SQL_columnKeyForeinDelete());
			}
			

			# Update index column
			if($a -> hasIndex() && !$n -> hasIndex()){

				$this -> query(DB::SQL()::DROP_INDEX_KEY($this -> getTable(),$a -> getIndex()));

				# Update Schema actual
				$a -> setIndex(null);
			}

			if(!$a -> hasIndex() && $n -> hasIndex()){
				$this -> query($this -> SQL_addColumnIndex());

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
				$this -> query(DB::SQL()::DROP_FOREIGN_KEY($this -> getTable(),$k -> getConstraint()));
				Schema::getTable($k -> getTable()) -> getColumn($k -> getName()) -> resetForeign();
				self::$tables[$k -> getTable()] -> getColumn($k -> getName()) -> resetForeign();
			}

			unset(Schema::$tables[$this -> getTable()]);
			unset(self::$tables[$this -> getTable()]);

			return $this -> query(DB::SQL()::DROP_TABLE($this -> getTable()));
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

			$this -> query(DB::SQL()::DROP_FOREIGN_KEY($this -> getTable(),$c -> getConstraint()));
		}

		if($c -> getPrimary()){

			$a = Schema::getTable($this -> getTable());

			foreach(Schema::getAllForeignKeyToColumn($this -> getTable(),$c -> getName()) as $k){
				$this -> query(DB::SQL()::DROP_FOREIGN_KEY($this -> getTable(),$k -> getConstraint()));
				self::$tables[$k -> getTable()] -> getColumn($k -> getName()) -> resetForeign($k);
			}

			$this -> query(DB::SQL()::MODIFY_COLUMN_RESET($this -> getTable(),$c -> getName()));
			$this -> query(DB::SQL()::DROP_PRIMARY_KEY($this -> getTable()));
		}else if($c -> hasIndex())
			$this -> query(DB::SQL()::DROP_INDEX_KEY($this -> getTable(),$c -> getIndex()));	
		

		if($table -> countColumns() == 1){
			unset(Schema::$tables[$this -> getTable()]);
			unset(self::$tables[$this -> getTable()]);
			return $this -> drop();

		}else{
			Schema::$tables[$this -> getTable()] -> dropColumn($c -> getName());
			self::$tables[$this -> getTable()] -> dropColumn($c -> getName());
			return $this -> query(DB::SQL()::DROP_COLUMN($this -> getTable(),$c -> getName()));
		}
	}

	public function printError($mex){
		echo "<h1>Error in Schema</h1><br>";
		echo $mex;
		echo '<h2>Detail</h2>';
		echo json_encode($this);
		die();
	}

	public function SQL_createTable(){
		return DB::SQL()::CREATE_TABLE($this -> getTable(),[$this -> SQL_column()]);
	}

	public function SQL_addColumn(){
		return DB::SQL()::ADD_COLUMN($this -> getTable(),$this -> SQL_column());
	}

	public function SQL_editColumn(){
		return DB::SQL()::EDIT_COLUMN($this -> getTable(),$this -> SQL_column());
	}

	public function SQL_editColumnBasics($table,$schema){
		DB::SQL()::COLUMN($s -> getName(),$s -> getType(),$s -> getLength());
	}
	
	public function SQL_addColumnIndex(){
		return DB::SQL()::ADD_INDEX_KEY($this -> getTable(),$this -> schema -> getName());
	}

	public function SQL_addColumnForeign(){
		return DB::SQL()::ADD_FOREIGN_KEY(
			$this -> getTable(),
			$this -> schema -> getName(),
			$this -> schema -> getForeignTable(),
			$this -> schema -> getForeignColumn(),
			$this -> schema -> getForeignUpdate(),
			$this -> schema -> getForeignDelete()
		);
	}

	public function SQL_editColumnKey($k){
		// return "ALTER TABLE {$this -> getTable()} ADD {$this -> SQL_column()}";
	}

	public function SQL_column(){
		DB::SQL()::COLUMN($s -> getName(),$s -> getType(),$s -> getLength(),$s -> getPrimary(),$s -> getAutoIncrement(),$s -> getUnique(),$s -> getNull());
	}


}