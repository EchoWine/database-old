<?php

/**
 * Query Builder
 */
class QueryBuilder{

	/**
	 * Infomation about the creation of the query
	 */
	public $builder;

	/**
	 * Information about the alteration of the DB pattern
	 */
	public $schema;


	/**
	 * Initializes the object, the call is made from the method table of the class Database
	 * 
	 * @param string|array|closure $v
	 * @return string name alias of the table
	 */
	public function __construct($table,$alias = null){

		$this -> builder = new Builder();

		/* Annidate table */
		if($table instanceof Closure){
			$table = $table();

			if($alias !== null)
				$alias = self::getTableAs();

			// Jesk
			// $v = "(".$table -> getUnionSQL().") as $as";

			$this -> builder -> setPrepare($t -> builder -> getPrepare());
		}

		$this -> builder -> addTable($table,$alias);

		return $this;
	}
	
	/**
	 * Clone  the attribute builder
	 */
	public function __clone(){
		$this -> builder = clone $this -> builder;
	}

	/**
	 * Execute the query
	 *
	 * @param string $q query sql
	 * @param array $p array of binding values
	 * @return object result of the query
	 */
	public function query(string $q,array $p = NULL){
		if($p == null)$p = $this -> builder -> getPrepare();

		return empty($p) ? DB::query($q) : DB::execute($q,$p);
		
	}

	/**
	 * Execute the query and convert the result in an array
	 * 
	 * @param string $q query to execute
	 * @param array $p array of binding values
	 * @return array result of the query
	 */
	public function assoc(string $q,array $p = NULL){
		return DB::fetch($this -> query($q,$p));
	}

	/**
	 * Prepare a value to be insert in the SQL code. Used in the PDO calls
	 *
	 * @param string $v value
	 * @return string name of the value
	 */
	public function setPrepare($v){
		$l = ":p".$this -> builder -> getCount()."_".count($this -> builder -> getPrepare());
		$this -> builder -> addPrepare($l,$v);
		return $l;
	}

	/**
	 * Execute the query and return if the record exists or not
	 *
	 * @param string $v name of the column
	 * @param mixed $a value or array of value that identified the column
	 * @return mixed bool if is only a value or array of bool if is an array of records
	 */
	public function exists(string $v,$a){
		$r = is_array($a) ? $this -> whereIn($v,$a) : $this -> where($v,$a);
		$r = $r -> select($v);
		$r = is_array($a) ? $r -> setIndexResult($v) -> lists() : $r -> get();

		if(is_array($a)){

			$r = array_keys($r);
			$t = array();
			foreach($a as $k){
				$t[$k] = in_array($k,$r) ? 1 : 0;
			}

			$r = $t;
		}	
		return is_array($a) ? $r : $r[$v];
	}

	/**
	 * Execute the query and return the number of record in the table, if the column is specified
	 * return the number of record with the column's value not null
	 *
	 * @param string $v name of the column
	 * @return int number of records
	 */
	public function count(string $v = '*'){
		return $this -> selectFunction($v,DB::SQL()::COUNT);
	}

	
	/**
	 * Execute the query and return the lower value in a specific column
	 *
	 * @param string $v name of the column
	 * @return mixed lower value of all values in a column
	 */
	public function min(string $v){
		return $this -> selectFunction($v,DB::SQL()::MIN);
	}

	/**
	 * Execute the query and return the max values of a specific column
	 *
	 * @param string $v name of the column
	 * @return mixed max value of all values in a column
	 */
	public function max(string $v){
		return $this -> selectFunction($v,DB::SQL()::MAX);
	}

	/**
	 * Execute the query and return the average value in a specific column
	 *
	 * @param string $v name of the column
	 * @return float average value of all values in a column
	 */
	public function avg(string $v){
		return $this -> selectFunction($v,DB::SQL()::AVG);
	}

	/**
	 * Execute the query and return the summation of the values in a specific column
	 *
	 * @param string $v name of the column
	 * @return float sum of the values in a column
	 */
	public function sum(string $v){
		return $this -> selectFunction($v,DB::SQL()::SUM);
	}
	
	/**
	 * Execute the query and return the result of a method applied in some values of a specific column
	 *
	 * @param string $v name of the function
	 * @param string $f function
	 * @return object $this
	 */
	public function selectFunction(string $v,string $f){
		$c = clone $this;
		$sql = DB::SQL()::AGGREGATE($f,$v);

		$c -> builder -> addSelect($sql);
		$r = $c -> get();

		return isset($r[$sql]) ? $r[$sql] : 0;

	}

	/**
	 * Arrange the results in ascending order
	 *
	 * @param string $v name of the column
	 * @return object $this
	 */
	public function orderBy(string $c){
		$this -> builder -> orderby[] = DB::SQL()::ASC($c);
		return $this;
	}

	/**
	 * Arrange the results in descending order
	 *
	 * @param string $v name of the column
	 * @return object $this
	 */
	public function orderByDesc(string $c){
		$this -> builder -> orderby[] = DB::SQL()::DESC($c);
		return $this;
	}

	/**
	 * Return the SQL code for sorting
	 *
	 * @return string SQL code
	 */
	public function SQL_ORDER_BY(){
		return DB::SQL()::ORDER_BY($this -> builder -> orderby);
	}

	/**
	 * Add a column to select on the query
	 *
	 * @param mixed $a contains the list of all the column to add or a single column
	 * @return object $this
	 */
	public function select($a){
		if(is_array($a)){
			foreach($a as $k => $v){
				$this -> builder -> select[] = $v;
			}
		}else{
			$this -> builder -> select[] = $a;
		}
		return $this;
	}

	/**
	 * Jump a number of results of the query defined by the parameter
	 *
	 * @param int $v number of results to jump
	 * @return object $this
	 */
	public function skip(int $v){
		$this -> builder -> skip = $v;
		return $this;
	}
	
	/**
	 * Take a specific number of results of the query defined by the parameter
	 *
	 * @param int $v number of results to take
	 * @return object $this
	 */
	public function take(int $v){
		$this -> builder -> take = $v;
		return $this;

	}
	
	/**
	 * Return the SQL code for select a range of results defined by skip and take
	 *
	 * @return string SQL code
	 */
	public function SQL_LIMIT(){
		return DB::SQL()::LIMIT($this -> builder -> skip,$this -> builder -> take);
	}

	/**
	 * Add a condition WHERE AND to the query where the results must have a specific value of a column.
	 * The result may change with the change of the parameters
	 *
	 * @param mixed $fun_col_value Indicate the name of the column || a closure execute by advanced where methods. 
	 * @param string $value_op if $value is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $value optional value of the column
	 * @return object $this
	 */
	public function where($fun_col_value,string $value_op = null,string $value = null){
		return $this -> location($fun_col_value,$value_op,$value,'andWhere');
	}

	/**
	 * Add a WHERE OR condition to the query where the results must have a value of a specific column.
	 * The result may change with the change of the parameters
	 *
	 * @param mixed $fun_col_value Indicate the name of the column || a closure execute by advanced where methods. 
	 * @param string $value_op if $value is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $value optional value of the column
	 * @return object $this
	 */
	public function orWhere($fun_col_value,string $value_op = null,string $value = null){
		return $this -> location($fun_col_value,$value_op,$value,'orWhere');
	}

	
	/**
	 * Add a condition WHERE IN to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v name of the column
	 * @param array $a array of accepted values
	 * @return object clone of $this
	 */
	public function whereIn(string $v,array $a){
		return $this -> locationIn($v,$a,'andWhere');
	}

	/**
	 * Add a condition OR WHERE IN to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v name of the column
	 * @param array $a array of accepted values
	 * @return object clone of $this
	 */
	public function orWhereIn(string $v,array $a){
		return $this -> locationIn($v,$a,'orWhere');
	}

	/**
	 * Add a condition WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v1 name of the column
	 * @param string $v2 reserched value
	 * @return object clone of $this
	 */
	public function whereLike(string $v1,string $v2){
		return $this -> locationLike($v1,$v2,'andWhere');
	}

	/**
	 * Add a condition OR WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v1 name of the column
	 * @param string $v2 reserched value
	 * @return object clone of $this
	 */
	public function orWhereLike(string $v1,string $v2){
		return $this -> locationLike($v1,$v2,'orWhere');
	}

	/**
	 * Add a condition WHERE NULL to the query where the results must have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function whereNull(string $v){
		return $this -> locationWhereNull($v,'andWhere');
	}
	
    /**
	 * Add a condition OR WHERE IS NULL to the query where the results must have a null value in the column
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function orWhereNull(string $v){
		return $this -> locationWhereNull($v,'orWhere');
	}

	/**
	 * Add a condition WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
     */
	public function whereNotNull(string $v){
		return $this -> locationWhereNotNull($v,'andWhere');
	}
	
	/**
	 * Add a condition OR WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function orWhereNotNull(string $v){
		return $this -> locationWhereNotNull($v,'orWhere');
	}


	/**
	 * Inject a SQL code for obtain a condition AND WHERE to the query 
	 *
	 * @param string $v SQL code
	 * @return object clone of $this
	 */
	public function whereRaw(string $v){
		return $this -> locationRaw($v,'andWhere');
	}
	
	/**
	 * Inject a SQL code for obtain a condition OR WHERE to the query 
	 *
	 * @param string $v SQL code
	 * @return object clone of $this
	 */
	public function orWhereRaw(string $v){
		return $this -> locationRaw($v,'orWhere');
	}

	/**
	 * Return the SQL code for the condition WHERE
	 *
	 * @param bool $where indicates if is necessary add a WHERE comand (true, by default) or not (false)
	 * @return string SQL code
	 */
	private function SQL_WHERE(bool $where = true){

		$r = [];
		if(!empty($this -> builder -> andWhere))
			$r[] = DB::SQL()::AND($this -> builder -> andWhere);

		if(!empty($this -> builder -> orWhere))
			$r[] = DB::SQL()::OR($this -> builder -> orWhere);

		$r = DB::SQL()::AND($r);
		return $where ? DB::SQL()::WHERE($r) : $r;
	}

	/**
	 * Add a condition $buider to the query where the results must have a specific value of a column.
	 * The result may change with the change of the parameters
	 *
	 * @param mixed $fun_col_value Indicate the name of the column || a closure execute by advanced where methods. 
	 * @param string $value_op if $value is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $value optional value of the column
	 * @param string $builder
	 * @return object $this
	 */
	public function location($fun_col_value,string $value_op = null,string $value = null,$builder){

		// Se si tratta di un where avanzato
		if(($r = $this -> locationClosure($fun_col_value,$builder)) !== null)return $r;

		# If only a parameter is defined, get primary key column
		if($value_op == null)
			$value_op = Schema::getTable($this -> getBuilderTable()) -> getPrimary() -> getName();

		return $this -> _location($fun_col_value,$value !== null ? $value_op : '=',$value !== null ? $value : $value_op,$builder);
	}

	/**
	 * Elaborate content of advanced where 
	 *
	 * @param Closure $fun function that contains advanced where
	 * @param string $builder name of part builder that will be used to store result
	 * @return object $this
	 */
	public function locationClosure($fun,$builder){
		if($fun instanceof Closure){
			$n = DB::table($this -> getBuilderTable());
			$t = clone $this;
			$n -> builder -> prepare = $t -> builder -> prepare;
			$n = $fun($n);
			$sql = $n -> SQL_WHERE(false);

			if(!empty($sql)){
				$t -> builder -> {$builder}[] = $sql;
				$t -> builder -> prepare = $n -> builder -> prepare;
			}

			return $t;
		}

		return null;
	}

	/**
	 * Add a $builder condition to the query where the results must have a value of specific column.
	 * The result may change with the change of the parameters
	 *
	 * @param string $column
	 * @param string $op
	 * @param string $value
	 * @return object clone of $this
	 */
	public function _location(string $column,string $op,string $value,string $builder){
		$t = clone $this;
		return $t -> locationRaw(DB::SQL()::COL_OP_VAL($column,$op,$t -> setPrepare($value)),$builder);
	}

	/**
	 * Add a condition $builder IN
	 *
	 * @param string $v name of the column
	 * @param array $a array of value
	 * @param string $builder
	 * @return object clone of $this
     */
	public function locationIn(string $v,array $a,$builder){
		$t = clone $this;
		foreach($a as &$k)$k = $t -> setPrepare($k);
		return $t -> locationRaw(DB::SQL()::IN($v,$a),$builder);

	}
	
	/**
	 * Add a condition $builder LIKE
	 *
	 * @param string $v1 name of the column
	 * @param string $v2 value
	 * @param string $builder
	 * @return object clone of $this
     */
	public function locationLike($v1,$v2,$builder){
		$t = clone $this;
		return $t -> locationRaw(DB::SQL()::LIKE($v1,$t -> setPrepare($v2)),$builder);
	}
	
	/**
	 * Add a condition $builder NULL
	 *
	 * @param string $v name of the column
	 * @param string $builder
	 * @return object clone of $this
     */
	public function locationWhereNull($v,$builder){
		return $this -> locationRaw(DB::SQL()::IS_NULL($v),$builder);
	}

	/**
	 * Add a condition $builder NOT NULL
	 *
	 * @param string $v name of the column
	 * @param string $builder
	 * @return object clone of $this
     */
	public function locationWhereNotNull($v,$builder){
		return $this -> locationRaw(DB::SQL()::IS_NOT_NULL($v),$builder);
	}

	/**
	 * Add a condition $builder IN
	 *
	 * @param string $sql SQL code
	 * @param string $builder
	 * @return object clone of $this
     */
	public function locationRaw($sql,$builder){
		$t = clone $this;
		$t -> builder -> {$builder}[] = $sql;
		return $t;
	}

	/**
	 * Incements the value of the column
	 *
	 * @param string $c name of the column
	 * @param float $v value of increment
	 * @return object clone of $this
	 */
	public function increment(string $c,float $v = 1){
		$t = clone $this;
		$t -> builder -> update[] = "{$c} = {$c} + ".$t -> setPrepare($v);
		return $t;
	}
	
	/**
	 * Decrease the value of the column
	 *
	 * @param string $c name of the column
	 * @param float $v value of decrease
	 * @return object clone of $this
	 */
	public function decrement(string $c,float $v = 1){
		$t = clone $this;
		$t -> builder -> update[] = "{$c} = {$c} - ".$t -> setPrepare($v);
		return $t;
	}

	/**
	 * Get builder name table
	 *
	 * @return string name table
	 */
	public function getBuilderTable(){
		return implode($this -> builder -> table,",");
	}

	/**
	 * Effect a LEFT JOIN with an other table
	 *
	 * @param string $t name of the second table
	 * @param string $v1 name of the column of the primary table
	 * @param string $v2 if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param string $v3 optional name of the column of the second table
	 * @return object $this
	 */
	public function leftJoin($table,$col1 = null,string $op_col2 = null,string $col2 = NULL){

		if($op_col2 !== null){
			$this -> on($table2_col1,$op_col2,$col2);
			$table2_col1 = null;
		}

		return $this -> _join('LEFT JOIN',$table,$table2_col1);
	}

	/**
	 * Effect a RIGHT JOIN with an other table
	 *
	 * @param string $t name of the second table
	 * @param string $v1 name of the column of the primary table
	 * @param string $v2 if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param string $v3 optional name of the column of the second table
	 * @return object $this
	 */
	public function rightJoin($table,$table2_col1 = null,string $op_col2 = null,string $col2 = NULL){

		if($op_col2 !== null){
			$this -> on($table2_col1,$op_col2,$col2);
			$table2_col1 = null;
		}

		return $this -> _join('RIGHT JOIN',$table,$table2_col1);
	}

	/**
	 * Effect a JOIN with an other table
	 *
	 * @param string $table name of the second table
	 * @param string $v1 name of the column of the primary table
	 * @param string $v2 if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param string $v3 optional name of the column of the second table
	 * @return object $this
	 */
	public function join($table,$table2_col1 = NULL,string $op_col2 = NULL,string $col2 = NULL){
		
		if($op_col2 !== null){
			$this -> on($table2_col1,$op_col2,$col2);
			$table2_col1 = null;
		}

		return $this -> _join('JOIN',$table,$table2_col1);
	}

	/**
	 * Add a SQL code for a JOIN|LEFT JOIN|RIGHT JOIN
	 *
	 * @param string $ACT type of JOIN
	 * @param string $table name of the secondary table
	 * @return object clone of $this
	 */
	public function _join(string $ACT,$table,$table_fun = null,$last = null){

		$t = clone $this;

		if($table_fun == null)
			$last = $table_fun;

		$s_last = $last == null ? $t -> builder -> getLastJoinTable() : $last;

		if(is_array($table)){


			foreach($table as $tab_n => $tab_val){
				$t = $t -> _join($ACT,is_int($tab_n) ? $tab_val : $tab_n,is_int($tab_n) ? null : $tab_val,$s_last);
			}

			return $t;
		}

		$and = [];
		$or = [];
		if(is_object($table_fun) && ($table_fun instanceof Closure)){
			$n = DB::table($t -> getBuilderTable());
			$n -> builder -> prepare = $t -> builder -> prepare;
			$n -> builder -> orOn = $t -> builder -> orOn;
			$n -> builder -> andOn = $t -> builder -> andOn;
			$n = $table_fun($n);
			$t -> builder -> orOn = $n -> builder -> orOn;
			$t -> builder -> andOn = $n -> builder -> andOn;
			$t -> builder -> prepare = $n -> builder -> prepare;

			$and = $n -> builder -> andWhere;
			$or = $n -> builder -> orWhere;
			if($last == null)$last = $table;

		}else if(!Schema::hasTable($table)){
			die("Schema: $table doesn't exists");
		}

		if(empty($t -> builder -> orOn) && empty($t -> builder -> andOn)){

			$k1 = Schema::getTable($table) -> getForeignKeyTo($s_last);
			$k2 = Schema::getTable($s_last) -> getForeignKeyTo($table);


			if($k1 !== null)
				$k = $k1;
			else if($k2 !== null)
				$k = $k2;
			else{
				die("<br>\nCannot relate $last with $table: Error with foreign key\n<br>");
			}
			
			$c1 = $k -> getForeignTable().".".$k -> getForeignColumn();
			$c2 = $k -> getTable().".".$k -> getName();

			$t = $t -> on($c1,'=',$c2);

			$last = $k -> getTable();

		}

		$t -> builder -> setLastJoinTable($last);

		$t -> builder -> join[] = "{$ACT} {$table} ON ".$t -> getOnSQL($and,$or)."";

		$t -> builder -> andOn = [];
		$t -> builder -> orOn = [];

		return $t;

	}

	public function getOnSQL($and = [],$or = []){

		$a_and = [];
		$a_or = [];

		if(!empty($this -> builder -> andOn))
			$a_and[] = $this -> getOnPartSQL($this -> builder -> andOn,'AND');

		if(!empty($and))
			$a_and[] = DB::SQL()::AND($and);

		if(!empty($this -> builder -> orOn))
			$a_or[] = $this -> getOnPartSQL($this -> builder -> orOn,'OR');

		if(!empty($or))
			$a_or[] = DB::SQL()::AND($or);

		$s = [];
		if(!empty($a_or))
			$s[] = DB::SQL()::OR($a_or);

		if(!empty($a_and))
			$s[] = DB::SQL()::AND($a_and);

		return DB::SQL()::AND($s);
	}


	public function getOnPartSQL($on,$op){
		$s = [];

		foreach($on as $k)
			$s[] = is_array($k) ? "(".$this -> getOnPartSQL($k,$op).")" : $k -> col1.$k -> op.$k -> col2;

		return implode($s," $op ");
	}

	/**
	 * Add a condition ON JOIN
	 *
	 */
	public function on($col1_fun,string $op_col2 = null,string $col2 = null){

		if(($c = $this -> onFun($col1_fun,"AND")) != null)
			return $c;

		return $this -> _on($col1_fun,$col2 != null ? $op_col2 : '=',$col2 == null ? $op_col2 : $col2);
	}

	public function onFun($fun,$op){

		if(is_object($fun) && ($fun instanceof Closure)){
			$t = clone $this;
			$n = DB::table($t -> getBuilderTable());
			$n -> builder -> prepare = $t -> builder -> prepare;
			$n = $fun($n);

			$m = array_merge($n -> builder -> orOn,$n -> builder -> andOn);

			switch($op){
				case 'AND': $t -> builder -> andOn[] = $m; break;
				case 'OR': $t -> builder -> orOn[] = $m; break;
			}
			
			$t -> builder -> prepare = $n -> builder -> prepare;

			return $t;
		}

		return null;
	}

	public function orOn($col1_fun,string $op_col2 = null,string $col2 = null){
		if(($c = $this -> onFun($col1_fun,"OR")) != null)
			return $c;

		return $this -> _on($col1_fun,$col2 != null ? $op_col2 : '=',$col2 == null ? $op_col2 : $col2,'OR');
	}

	public function _on(string $col1,string $op,string $col2,$type = 'AND'){
		$t = clone $this;
		$o = (object)['col1' => $col1,'op' => $op,'col2' => $col2];

		switch($type){
			case 'AND': $t -> builder -> andOn[] = $o; break;
			case 'OR': $t -> builder -> orOn[] = $o; break;
		}

		return $t;
	}

	/**
	 * Add a union statment
	 *
	 * @param QueryBuilder $q
	 * @return object clone of $this
	 */
	public function union(QueryBuilder $q){
		$t = clone $this;
		$t -> builder -> union[] = $q;
		$t -> builder -> prepare = array_merge($t -> builder -> prepare,$q -> builder -> prepare);
		return $t;
	}

	/**
	 * Execute the query and insert a record ignoring duplicates
	 *
	 * @param array $v array of elements to insert (name column => value column)
	 * @return object $this
	 */
	public function insertIgnore(array $v){
		return $this -> insert($v,true);
	}
	
	/**
	 * Execute the query and insert a record if isn't present any record, otherwise update
	 *
	 * @param array $v array of elements to insert|update (name column => value column)
	 * @param bool $ignore if set recall insertIgnore(true) or insert(false)
	 * @return int number of result affected from the query(update) or last ID insert(insert)
	 */
	public function insertUpdate(array $v,bool $ignore = false){
		return $this -> count() == 0
			? $ignore 
				? $this -> insertIgnore($v) 
				: $this -> insert($v)
			: $this -> update($v);
	}

	/**
	 * Execute the query and insert a record
	 *
	 * @param array $data array of elements to insert (name column => value column)
	 * @param bool $ignore ignore the duplicates(true) or reproduce an error(false)
	 * @return int last id insert
	 */
	public function insert($data,bool $ignore = false){

		if(empty($data))return 0;

		$t = clone $this;

		if(is_object($data) && ($data instanceof Closure)){
			$c = $data();
			$t -> builder -> prepare = array_merge($t -> builder -> prepare,$c -> builder -> prepare);
			$values = "(".$c -> getUnionSQL().")";
		
			$columns = empty($c -> builder -> select) ? '' : "(".implode($c -> builder -> select,",").")";

		}else{

			if(!isset($data[0]))$data = [$data];

			$values = [];

			foreach($data as $k){
				$value = [];

				foreach($k as $v)
					$value[] = $t -> setPrepare($v);
				
				$values[] = "(".implode($value,",").")";
			}

			$values = "VALUES ".implode($values,",");
			$columns = "(".implode(array_keys($data[0]),",").")";
		}


		$ignore = $ignore ? ' IGNORE ' : '';
		
		$q = DB::count($t -> query("
			INSERT {$ignore} INTO {$this -> getBuilderTable()} 
			$columns
			$values
		"));

		# Get all ID from last Insert
		# Granted with InnoDB
		return range($i = DB::getInsertID(), $i + $q - 1);
	}

	/**
	 * Execute the query and update the record
	 *
	 * @param mixed $v1 if $v2 is defined indicates the name of the column to update, otherwise the array (name column => value columns)
	 * @param mixed $v2 optional value of the column to update
	 * @return int number of row involved in the update
	 */

	public function update($v1,$v2 = NULL){

		if(empty($v1))return 0;

		$t = clone $this;

		if(!is_array($v1) && isset($v2)){
			$kf = array("$v1 = ".$t -> setPrepare($v2));
		}else{
			$kf = empty($t -> builder -> update) ? array() : $t -> builder -> update;
			foreach($v1 as $k => $v){
				$kf[] = "$k = ".$t -> setPrepare($v);
			}
		}

		$q = $t -> query("
			UPDATE {$this -> getBuilderTable()} 
			".implode($t -> builder -> join," ")."
			SET
			".implode($kf,",")." 
			".$this -> SQL_WHERE()."
		");


		$r = DB::count($q);

		return ($r == 0 && $q) ? 1 : $r;

	}

	/**
	 * Execute the query and update the records
	 *
	 * @param array $v1 array of columns to update in base a specific condition
	 * @param array $v2 value of the column to update
	 * @return int number of row involved in the update
	 */
	public function updateMultiple(array $v1,array $v2){
		if(empty($v1) || empty($v2))return false;

		$t = clone $this;
		$kf = empty($t -> builder -> update) ? array() : $t -> builder -> update;
		
		foreach($v1 as $k => $v){

			if(is_array($v2[$k])){
				$s = "{$v[1]} = CASE {$v[0]}";

				foreach($v2[$k] as $n1 => $k1){
					$s .= " WHEN ".$t -> setPrepare($n1)." THEN ".$t -> setPrepare($k1)." ";
					$where[] = $n1;
				}
				$s .= " ELSE {$v[1]} END";

				$kf[] = $s;
			}else{
				$kf[] = "{$v} = ".$t -> setPrepare($v2[$k]);
			}
		}


		$q = $t -> query("
			UPDATE {$this -> getBuilderTable()} 
			".implode($t -> builder -> join," ")."
			SET
			".implode($kf,",")." 
			".$t -> SQL_WHERE()."
		");

		$r = DB::count($q);

		return ($r == 0 && $q) ? 1 : $r;

	}

	/**
	 * Execute the query and delete the selected records
	 *
	 * @param string $v optional indicates the name of the table from which delete the records (used in the join)
	 * @return int number of rows involved in the elimination
	 */
	public function delete(string $v = ''){
		return $this -> query("
			DELETE {$v} FROM {$this -> getBuilderTable()} 
			".implode($this -> builder -> join," ")."
			".$this -> SQL_WHERE()."
		");
	}

	/**
	 * Regroup the same results from a specific column 
	 *
	 * @param mixed $v name or array of names of the column involved in the regroup
	 * @return object clone of $this
	 */
	public function groupBy($v){
		$t = clone $this;
		if(!is_array($v))$v = array($v);
		$t -> builder -> groupBy = array_merge($t -> builder -> groupBy,$v);
		return $t;
	}

	/**
	 * Return the SQL code to execute the regroup
	 *
	 * @return string SQL code
	 */
	public function getGroupBySQL(){
		$s = implode($this -> builder -> groupBy," , ");
		if(!empty($s))$s = " GROUP BY {$s} ";
		return $s;
	}
	
	/**
	 * Configure the column which will occupy the index of the array with the results
	 *
	 * @param string $v name of the column
	 * @return string name of the value
	 */
	public function setIndexResult(string $v){
		$this -> builder -> indexResult = $v;
		return $this;
	}

	/**
	 * Execute the query and return the selected records as result
	 *
	 * @return array result of the query
	 */
	public function lists(){
		$r = $this -> assoc($this -> getUnionSQL());

		if(!empty($this -> builder -> indexResult)){
			$s = array();
			foreach($r as $n => $k){
				$s[$k[$this -> builder -> indexResult]] = $k;
			}

			$r = $s;
		}

		return $r;
	}

	/**
	 * Execute the query and return the selected record as results
	 *
	 * @return array result of the query
	 */
	public function get(){
		$r = $this -> take(1) -> lists();

		return !empty($r) ? $r[0] : array();
	}

	/**
	 * Return the SQL code for union
	 *
	 * @return string SQL code
	 */
	public function getUnionSQL(){

		$u = $this -> builder -> union;
		$u[] = $this;


		$r = [];
		foreach($u as $k){
			$r[] = $k -> getSelectSQL();
		}
		return implode($r," UNION ");
	}

	/**
	 * Return the SQL code for selection
	 *
	 * @return string SQL code
	 */
	public function getSelectSQL(){

		if(empty($this -> builder -> select))$this -> builder -> select[] = "*";

		$t = "";
		$i = 0;

		$c = "
			SELECT ".implode($this -> builder -> select,",")." FROM {$this -> getBuilderTable()} 
			".implode($this -> builder -> join," ")."
			".$this -> SQL_WHERE()."
			".$this -> getGroupBySQL()."
			".$this -> SQL_ORDER_BY()."
			".$this -> SQL_LIMIT()."
		";

		$t = empty($t) ? $c : "{$t}($c) as tmp".++$i;
		

		return $c;
	}


	/**
	 * Execute a reset query of the counter auto_increment
	 *
	 * @return object result of the query
	 */
	public function resetAutoIncrement(){
		return $this -> query("ALTER TABLE {$this -> getBuilderTable()} AUTO_INCREMENT = 1");
	}
	
	/**
	 * Eliminate all record in table
	 *
	 * @return result query
	 */
	public function truncate(){
		return $this -> delete() && $this -> resetAutoIncrement();
		/*
		Problem with foreign key
		return $this -> query("
			TRUNCATE {$this -> getBuilderTable()} 
		");
		*/
	}

}