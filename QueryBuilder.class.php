<?php

/**
 * Query Builder
 */
class QueryBuilder{

	/**
	 * Counter of all istance of this class
	 */
	public static $counter = 0;

	/**
	 * Count of actual istance
	 */
	public $count;

	/**
	 * Infomation about the creation of the query
	 */
	public $builder;

	/**
	 * Information about the alteration of the DB pattern
	 */
	public $schema;

	/**
	 * List of all the alias made automatically for the nested selection query
	 */
	public static $tableAs = array();

	/**
	 * Initializes the object, the call is made from the method table of the class Database
	 * 
	 * @param string|array|closure $v
	 * @return string name alias of the table
	 */
	public function __construct($v){

		$this -> builder = new stdClass();
		$this -> builder -> prepare = array();

		/* Controllo che si tratti di una select annidata */
		if($v instanceof Closure){
			$t = $v();
			$as = self::getTableAs();
			$v = "(".$t -> getUnionSQL().") as $as";
			$this -> builder -> prepare = $t -> builder -> prepare;
		}


		if(!is_array($v))$v = [$v];

		$this -> builder -> table = $v;
		$this -> builder -> agg = [];
		$this -> builder -> select = [];
		$this -> builder -> update = [];
		$this -> builder -> orderby = array();[];
		$this -> builder -> skip = NULL;
		$this -> builder -> take = NULL;
		$this -> builder -> groupBy = [];
		$this -> builder -> andWhere = [];
		$this -> builder -> orWhere = [];
		$this -> builder -> join = [];
		$this -> builder -> union = [];
		$this -> builder -> is_table = false;
		$this -> builder -> indexResult = "";
		$this -> builder -> tmp_prepare = [];

		$this -> setCounter();

		return $this;
	}
	
	/**
	 * Increment the counter
	 */
	public function setCounter(){
		$this -> count = self::$counter++;
	}

	/**
	 * Return a random name (unused) to use as alias for the query
	 *
	 * @return string alias name of the table
	 */
	public static function getTableAs(){
		$c = "t".count(self::$tableAs);
		self::$tableAs[] = $c;
		return $c;
	}

	/**
	 * Clone  the attribute builder
	 */
	public function __clone(){
		$this -> builder = clone $this -> builder;
		$this -> setCounter();
	}

	/**
	 * Execute the query
	 *
	 * @param string $q query sql
	 * @param array $p array of binding values
	 * @return object result of the query
	 */
	public function query(string $q,array $p = NULL){
		if($p == null)$p = $this -> builder -> prepare;

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
		$l = ":p".$this -> count."_".count($this -> builder -> prepare);
		$this -> builder -> prepare[$l] = $v;
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
		return $this -> selectFunction($v,'COUNT');
	}

	
	/**
	 * Execute the query and return the lower value in a specific column
	 *
	 * @param string $v name of the column
	 * @return mixed lower value of all values in a column
	 */
	public function min(string $v){
		return $this -> selectFunction($v,'MIN');
	}

	/**
	 * Execute the query and return the max values of a specific column
	 *
	 * @param string $v name of the column
	 * @return mixed max value of all values in a column
	 */
	public function max(string $v){
		return $this -> selectFunction($v,'MAX');
	}

	/**
	 * Execute the query and return the average value in a specific column
	 *
	 * @param string $v name of the column
	 * @return float average value of all values in a column
	 */
	public function avg(string $v){
		return $this -> selectFunction($v,'AVG');
	}

	/**
	 * Execute the query and return the summation of the values in a specific column
	 *
	 * @param string $v name of the column
	 * @return float sum of the values in a column
	 */
	public function sum(string $v){
		return $this -> selectFunction($v,'SUM');
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
		$c -> builder -> select[] = "{$f}({$v})";
		$r = $c -> get();

		return isset($r["{$f}({$v})"]) ? $r["{$f}({$v})"] : 0;

	}

	/**
	 * Arrange the results in ascending order
	 *
	 * @param string $v name of the column
	 * @return object $this
	 */
	public function orderByAsc(string $c){
		$this -> builder -> orderby[] = "$c ASC";
		return $this;
	}

	/**
	 * Arrange the results in descending order
	 *
	 * @param string $v name of the column
	 * @return object $this
	 */
	public function orderByDesc(string $c){
		$this -> builder -> orderby[] = "$c DESC";
		return $this;
	}

	/**
	 * Return the SQL code for sorting
	 *
	 * @return string SQL code
	 */
	public function getOrderBySQL(){
		$o = $this -> builder -> orderby;
		return empty($o) ? '' : ' ORDER BY '.implode(' , ',$o);
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
	public function getLimitSQL(){
		$s = isset($this -> builder -> skip) ? $this -> builder -> skip."," : "";
		$t = isset($this -> builder -> take) ? $this -> builder -> take : "";
		return empty($s) && empty($t) ? "" : "LIMIT {$s}{$t}";
	}

	/**
	 * Add a condition WHERE AND to the query where the results must have a specific value of a column.
	 * The result may change with the change of the parameters
	 *
	 * @param mixed $v1 Indicate the name of the column or a closure execute by advanced where methods. 
	 * @param string $v2 if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $v3 optional value of the column
	 * @param bool $v4 ?? DA RIMUOVERE FORSE ??
	 * @return object $this
	 */
	public function where($v1,string $v2 = NULL,string $v3 = NULL,bool $v4 = true){

		// Se si tratta di un where avanzato
		if($v1 instanceof Closure){
			$n = DB::table($this -> getBuilderTable());
			$t = clone $this;
			$n -> builder -> prepare = $t -> builder -> prepare;
			$n = $v1($n);
			$sql = $n -> getWhereSQL(false);

			if(!empty($sql)){
				$t -> builder -> andWhere[] = $sql;
				$t -> builder -> prepare = $n -> builder -> prepare;
			}

			return $t;
		}

		return $this -> _where($v1,$v2,$v3,$v4,'AND');
	}

	/**
	 * Add a WHERE OR condition to the query where the results must have a value of a specific column.
	 * The result may change with the change of the parameters
	 *
	 * @param mixed $v1 Indicate the name of the column or a closure execute by advanced where methods. 
	 * @param string $v2 if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $v3 optional value of the column
	 * @param bool $v4 ?? DA RIMUOVERE FORSE ??
	 * @return object $this
	 */
	public function orWhere($v1,string $v2 = NULL,string $v3 = NULL,$v4 = true){

		// Se si tratta di un where avanzato
		if($v1 instanceof Closure){
			$n = DB::table($this -> getBuilderTable());
			$t = clone $this;
			$n -> builder -> prepare = $t -> builder -> prepare;
			$n = $v1($n);
			$sql = $n -> getWhereSQL(false);

			if(!empty($sql)){
				$t -> builder -> orWhere[] = $sql;
				$t -> builder -> prepare = $n -> builder -> prepare;
			}

			return $t;
		}

		return $this -> _where($v1,$v2,$v3,$v4,'OR');
	}

	/**
	 * Add a WHERE condition to the query where the results must have a value of specific column.
	 * The result may change with the change of the parameters
	 *
	 * @param string $v1 if $v2 is defined indicates the name of the column, otherwise the value of the primary column
	 * @param string $v2 if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param string $v3 optional value of the column
	 * @param bool $v4 ?? DA RIMUOVERE FORSE ??
	 * @param string $v5 type of where AND|OR
	 * @return object clone of $this
	 */
	public function _where(string $v1,string $v2 = NULL,string $v3 = NULL,bool $v4 = true,string $ao){
		$t = clone $this;

		if(isset($v3)){
			$col = $v1;
			$op = $v2;
			$val = $v3;
		}else if(isset($v2)){
			$col = $v1;
			$op = '=';
			$val = $v2;
		}else{

			// Ottengo automaticamente la chiave primaria
			$col = "(SELECT k.column_name
				FROM information_schema.table_constraints t
				JOIN information_schema.key_column_usage k
				USING(constraint_name,table_schema,table_name)
				WHERE t.constraint_type='PRIMARY KEY'
					AND t.constraint_schema='".DB::getName()."'
					AND t.table_name='{$this -> getBuilderTable()}')

			";

			$op = '=';
			$val = $v1;
		}

		if($v4)$val = $t -> setPrepare($val);

		$r = "{$col} {$op} {$val}";

		switch($ao){
			case 'AND':
				$t -> builder -> andWhere[] = " ({$r}) ";
			break;
			case 'OR':
				$t -> builder -> orWhere[] = " ({$r}) ";
			break;
		}

		return $t;
	}
	
	/**
	 * Add a condition WHERE IN to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v name of the column
	 * @param array $a array of accepted values
	 * @return object clone of $this
	 */
	public function whereIn(string $v,array $a){
		$t = clone $this;
		foreach($a as &$k)$k = $t -> setPrepare($k);
		$a = implode($a,",");
		$t -> builder -> andWhere[] = "({$v} IN ($a))";
		return $t;
	}

	/**
	 * Add a condition OR WHERE IN to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v name of the column
	 * @param array $a array of accepted values
	 * @return object clone of $this
	 */
	public function orWhereIn(string $v,array $a){
		$t = clone $this;
		foreach($a as &$k)$k = $t -> setPrepare($k);
		$a = implode($a,",");
		$t -> builder -> orWhere[] = "({$v} IN ($a))";
		return $t;
	}

	/**
	 * Add a condition WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v1 name of the column
	 * @param string $v2 reserched value
	 * @return object clone of $this
	 */
	public function whereLike(string $v1,string $v2){

		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v1} LIKE {$t -> setPrepare($v2)})";
		return $t;
	}

	/**
	 * Add a condition OR WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 *
	 * @param string $v1 name of the column
	 * @param string $v2 reserched value
	 * @return object clone of $this
	 */
	public function orWhereLike(string $v1,string $v2){

		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v1} LIKE {$t -> setPrepare($v2)})";
		return $t;
	}

	/**
	 * Add a condition WHERE NULL to the query where the results must have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function whereNull(string $v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v} IS NULL)";
		return $t;
	}
	
    /**
	 * Add a condition OR WHERE IS NULL to the query where the results must have a null value in the column
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function orWhereNull(string $v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v} IS NULL)";
		return $t;
	}
	
	/**
	 * Add a condition WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
     */
	public function whereNotNull(string $v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v} IS NOT NULL)";
		return $t;
	}
	
	/**
	 * Add a condition OR WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 *
	 * @param string $v name of the column
	 * @return object clone of $this
	 */
	public function orWhereNotNull(string $v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v} IS NOT NULL)";
		return $t;
	}

	/**
	 * Inject a SQL code for obtain a condition AND WHERE to the query 
	 *
	 * @param string $v SQL code
	 * @return object clone of $this
	 */
	public function whereRaw(string $v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "($v)";
		return $t;
	}
	
	/**
	 * Inject a SQL code for obtain a condition OR WHERE to the query 
	 *
	 * @param string $v SQL code
	 * @return object clone of $this
	 */
	public function orWhereRaw(string $v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "($v)";
		return $t;
	}

	/**
	 * Return the SQL code for the condition WHERE
	 *
	 * @param bool $where indicates if is necessary add a WHERE comand (true, by default) or not (false)
	 * @return string SQL code
	 */
	private function getWhereSQL(bool $where = true){
		$s = $where ? ' WHERE ' : '';

		$r = array();

		if(!empty($this -> builder -> andWhere))
			$r[] = '('.implode($this -> builder -> andWhere," AND ").')';

		if(!empty($this -> builder -> orWhere))
			$r[] = '('.implode($this -> builder -> orWhere," OR ").')';

		$r = implode($r," AND ");

		return empty($r) ? "" : $s.$r;
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
	public function leftJoin(string $t,string $v1,string $v2,string $v3 = NULL){
		return $this -> _join('LEFT JOIN',$t,$v1,$v2,$v3);
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
	public function rightJoin(string $t,string $v1,string $v2,string $v3 = NULL){
		return $this -> _join('RIGHT JOIN',$t,$v1,$v2,$v3);
	}

	/**
	 * Effect a JOIN with an other table
	 *
	 * @param string $t name of the second table
	 * @param string $v1 name of the column of the primary table
	 * @param string $v2 if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param string $v3 optional name of the column of the second table
	 * @return object $this
	 */
	public function join(string $t,string $v1,string $v2,string $v3 = NULL){
		return $this -> _join('JOIN',$t,$v1,$v2,$v3);
	}

	/**
	 * Add a SQL code for a JOIN|LEFT JOIN|RIGHT JOIN
	 *
	 * @param string $ACT type of JOIN
	 * @param string $table name of the secondary table
	 * @param string $v1 name of the column of the primary table
	 * @param string $v2 if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param string $v3 optional name of the column of the second table
	 * @return object clone of $this
	 */
	public function _join(string $ACT,string $table,string $v1,string $v2,string $v3 = NULL){

		$t = clone $this;

		if(isset($v3)){
			$c1 = $v1;
			$op = $v2;
			$c2 = $v3;
		}else{
			$c1 = $v1;
			$op = " = ";
			$c2 = $v2;
		}

		$t -> builder -> join[] = "{$ACT} {$table} ON {$c1} {$op} {$c2}";

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
	 * @param array $a array of elements to insert (name column => value column)
	 * @param bool $ignore ignore the duplicates(true) or reproduce an error(false)
	 * @return int last id insert
	 */
	public function insert(array $a,bool $ignore = false){

		if(empty($a))return 0;
		$t = clone $this;

		$kf = array();
		$vk = array();
		foreach($a as $k => $v){
			$kf[] = $k;
			$vk[] = $t -> setPrepare($v);
		}

		$ignore = $ignore ? ' IGNORE ' : '';
		return $t -> query("
			INSERT {$ignore} INTO {$this -> getBuilderTable()} 
			(".implode($kf,",").") 
			VALUES (".implode($vk,",").") 
		");
	}

	/**
	 * Execute the query and insert at least a record
	 *
	 * @param array $nv array made from the names of the column to insert
	 * @param array $av array made from an array of values for each row
	 * @param bool $ignore ignore the duplicates(true) or reproduce an error(false)
	 * @return int last ID insert
	 */
	public function insertMultiple(array $nv,array $av,bool $ignore = false){
		
		if(empty($av) || empty($nv))return 0;

		$t = clone $this;
		$vkk = array();

		if(is_object($av) && ($av instanceof Closure)){
			$c = $av();
			$t -> builder -> prepare = array_merge($t -> builder -> prepare,$c -> builder -> prepare);
			$vkk = "(".$c -> getUnionSQL().")";

		}else{
			foreach($av as $k){
				$vk = array();
				foreach($k as $v){
					$vk[] = $t -> setPrepare($v);
				}
				$vkk[] = "(".implode($vk,",").")";
			}
			$vkk = "VALUES ".implode($vkk,",");
		}

		$nv = "(".implode($nv,",").")";

		$ignore = $ignore ? ' IGNORE ' : '';
		
		return DB::count($t -> query("
			INSERT {$ignore} INTO {$this -> getBuilderTable()} 
			$nv
			$vkk
		"));
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
			".$this -> getWhereSQL()."
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
			".$t -> getWhereSQL()."
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
			".$this -> getWhereSQL()."
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
			".$this -> getWhereSQL()."
			".$this -> getGroupBySQL()."
			".$this -> getOrderBySQL()."
			".$this -> getLimitSQL()."
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
		return $this -> query("
			TRUNCATE {$this -> getBuilderTable()} 
		");
	}

}