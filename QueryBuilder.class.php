<?php

/**
 * @class QueryBuilder
 * Class that permits the handle of the query in a defined and semplified way
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
	 * List of all the names of the tables which existence is known
	 */
	public static $cacheAlter = array();

	/**
	 * List of all the alias made automatically for the nested selection query
	 */
	public static $tableAs = array();

	/**
	 * Initializes the object, the call is made from the method table of the class Database
	 * @param $v (string) name of the table
	 * @param $as (string) optional alias of the table
	 * @return (string) name alias of the table
	 */
	public function __construct($v,$as = ''){

		$this -> builder = new stdClass();
		$this -> builder -> prepare = array();

		/* Controllo che si tratti di una select annidata */
		if(is_object($v) && ($v instanceof Closure)){
			$t = $v();
			$v = "(".$t -> getSelectSQL().")";
			if(empty($as)) $as = self::getTableAsRandom();
			$this -> builder -> prepare = $t -> builder -> prepare;
		}

		$this -> builder -> table = $v;
		$this -> builder -> table_as = $as;
		$this -> builder -> agg = array();
		$this -> builder -> select = array();
		$this -> builder -> update = array();
		$this -> builder -> orderby = array();
		$this -> builder -> skip = NULL;
		$this -> builder -> take = NULL;
		$this -> builder -> groupBy = array();;
		$this -> builder -> andWhere = array();
		$this -> builder -> orWhere = array();
		$this -> builder -> join = array();
		$this -> builder -> is_table = false;
		$this -> builder -> indexResult = "";
		$this -> builder -> tmp_prepare = array();

		return $this;
	}
	
	/**
	 * Return a random name (unused) to use as alias for the query
	 * @return (string) alias name of the table
	 */
	public static function getTableAsRandom(){
		$c = "t".count(self::$tableAs);
		self::$tableAs[] = $c;
		return $c;
	}

	/**
	 * Clone  the attribute builder
	 */
	public function __clone(){
		$this -> builder = clone $this -> builder;
	}

	/**
	 * Execute the query
	 * @return (object) result of the query
	 */
	public function query($q,$p = NULL){
		if(!isset($p))$p = $this -> builder -> prepare;

		return empty($p) ? DB::query($q) : DB::execute($q,$p);
		
	}

	/**
	 * Execute the query and convert the result in an array
	 * @param $q (string) query to execute
	 * @param $p (array) array of values to preparare
	 * @return (array) result of the query
	 */
	public function assoc($q,$p = NULL){
		return DB::fetch($this -> query($q,$p));
	}

	/**
	 * Prepare a value to be insert in the SQL code. Used in the PDO calls
	 * @param $v (string) value
	 * @return (string) name of the value
	 */
	public function setPrepare($v){
		$l = ":p".count($this -> builder -> prepare);
		$this -> builder -> prepare[$l] = $v;
		return $l;
	}

	/**
	 * Execute the query and return if the record exists or not
	 * @param $v (string) name of the column
	 * @param $a (mixed) value or array of value that identified the column
	 * @return (mixed) bool if is only a value or array of bool if is an array of records
	 */
	public function exists($v,$a){
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
	 * @param $v (string) name of the column
	 * @return (int) number of records
	 */
	public function count($v = '*'){
		return $this -> selectFunction($v,'COUNT');
	}

	
	/**
	 * Execute the query and return the lower value in a specific column
	 * @param $v (string) name of the column
	 * @return (mixed) lower value of all values in a column
	 */
	public function min($v){
		return $this -> selectFunction($v,'MIN');
	}

	/**
	 * Execute the query and return the max values of a specific column
	 * @param $v (string) name of the column
	 * @return (mixed) max value of all values in a column
	 */
	public function max($v){
		return $this -> selectFunction($v,'MAX');
	}

	/**
	 * Execute the query and return the average value in a specific column
	 * @param $v (string) name of the column
	 * @return (float) average value of all values in a column
	 */
	public function avg($v){
		return $this -> selectFunction($v,'AVG');
	}

	/**
	 * Execute the query and return the summation of the values in a specific column
	 * @param $v (string) name of the column
	 * @return (float) sum of the values in a column
	 */
	public function sum($v){
		return $this -> selectFunction($v,'SUM');
	}
	
	/**
	 * Execute the query and return the result of a method applied in some values of a specific column
	 * @param $v (string) name of the function
	 * @param $f (string) function
	 * @return (object) $this
	 */
	public function selectFunction($v,$f){
		$c = clone $this;
		$c -> builder -> select[] = "{$f}({$v})";
		$r = $c -> get();

		return isset($r["{$f}({$v})"]) ? $r["{$f}({$v})"] : 0;

	}

	/**
	 * Arrange the results in ascending order
	 * @param $v (string) name of the column
	 * @return (object) $this
	 */
	public function orderByAsc($c){
		$this -> builder -> orderby[] = "$c ASC";
		return $this;
	}

	/**
	 * Arrange the results in descending order
	 * @param $v (string) name of the column
	 * @return (object) $this
	 */
	public function orderByDesc($c){
		$this -> builder -> orderby[] = "$c DESC";
		return $this;
	}

	/**
	 * Return the SQL code for sorting
	 * @return (string) SQL code
	 */
	public function getOrderBySQL(){
		$o = $this -> builder -> orderby;
		return empty($o) ? '' : ' ORDER BY '.implode(' , ',$o);
	}

	/**
	 * Add a column to select on the query
	 * @param $a (mixed) contains the list of all the column to add or a single column
	 * @return (object) $this
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
	 * @param $v (int) number of results to jump
	 * @return (object) $this
	 */
	public function skip($v){
		$this -> builder -> skip = (int)$v;
		return $this;
	}
	
	/**
	 * Take a specific number of results of the query defined by the parameter
	 * @param $v (int) number of results to take
	 * @return (object) $this
	 */
	public function take($v){
		$this -> builder -> take = (int)$v;
		return $this;

	}
	
	/**
	 * Return the SQL code for select a range of results defined by skip and take
	 * @return (string) SQL code
	 */
	public function getLimitSQL(){
		$s = isset($this -> builder -> skip) ? $this -> builder -> skip."," : "";
		$t = isset($this -> builder -> take) ? $this -> builder -> take : "";
		return empty($s) && empty($t) ? "" : "LIMIT {$s}{$t}";
	}

	/**
	 * Add a condition WHERE AND to the query where the results must have a specific value of a column.
	 * The result may change with the change of the parameters
	 * @param $v1 (mixed) Indicate the name of the column or a closure execute by advanced where methods. 
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param $v3 (string) optional value of the column
	 * @param $v4 (bool) ?? DA RIMUOVERE FORSE ??
	 * @return (object) $this
	 */
	public function where($v1,$v2 = NULL,$v3 = NULL,$v4 = true){

		// Se si tratta di un where avanzato
		if(is_object($v1) && ($v1 instanceof Closure)){
			$n = DB::table($this -> builder -> table);
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
	 * @param $v1 (mixed) Indicate the name of the column or a closure execute by advanced where methods. 
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param $v3 (string) optional value of the column
	 * @param $v4 (bool) ?? DA RIMUOVERE FORSE ??
	 * @return (object) $this
	 */
	public function orWhere($v1,$v2 = NULL,$v3 = NULL,$v4 = true){

		// Se si tratta di un where avanzato
		if(is_object($v1) && ($v1 instanceof Closure)){
			$n = DB::table($this -> builder -> table);
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
	 * @param $v1 (string) if $v2 is defined indicates the name of the column, otherwise the value of the primary column
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent, otherwise the value of the column
	 * @param $v3 (string) optional value of the column
	 * @param $v4 (bool) ?? DA RIMUOVERE FORSE ??
	 * @param $v5 (string) type of where AND|OR
	 * @return (object) clone of $this
	 */
	public function _where($v1,$v2 = NULL,$v3 = NULL,$v4 = true,$ao){
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
					AND t.constraint_schema='".DB::$config['database']."'
					AND t.table_name='{$this -> builder -> table}')

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
	 * @param $v (string) name of the column
	 * @param $a (array) array of accepted values
	 * @return (object) clone of $this
	 */
	public function whereIn($v,$a){
		$t = clone $this;
		foreach($a as &$k)$k = $t -> setPrepare($k);
		$a = implode($a,",");
		$t -> builder -> andWhere[] = "({$v} IN ($a))";
		return $t;
	}

	/**
	 * Add a condition OR WHERE IN to the query where the results must have a value of the specific column present on the list of elements
	 * @param $v (string) name of the column
	 * @param $a (array) array of accepted values
	 * @return (object) clone of $this
	 */
	public function orWhereIn($v,$a){
		$t = clone $this;
		foreach($a as &$k)$k = $t -> setPrepare($k);
		$a = implode($a,",");
		$t -> builder -> orWhere[] = "({$v} IN ($a))";
		return $t;
	}

	/**
	 * Add a condition WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 * @param $v1 (string) name of the column
	 * @param $v2 (string) reserched value
	 * @return (object) clone of $this
	 */
	public function whereLike($v1,$v2){

		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v1} LIKE {$t -> setPrepare($v2)})";
		return $t;
	}

	/**
	 * Add a condition OR WHERE LIKE to the query where the results must have a value of the specific column present on the list of elements
	 * @param $v1 (string) name of the column
	 * @param $v2 (string) reserched value
	 * @return (object) clone of $this
	 */
	public function orWhereLike($v1,$v2){

		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v1} LIKE {$t -> setPrepare($v2)})";
		return $t;
	}

	/**
	 * Add a condition WHERE IS NULL to the query where the results must have a null value in the column
	 * @param $v (string) name of the column
	 * @return (object) clone of $this
	 */
	public function whereIsNull($v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v} IS NULL)";
		return $t;
	}
	
    /**
	 * Add a condition OR WHERE IS NULL to the query where the results must have a null value in the column
	 * @param $v (string) name of the column
	 * @return (object) clone of $this
	 */
	public function orWhereIsNull($v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v} IS NULL)";
		return $t;
	}
	
	/**
	 * Add a condition WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 * @param $v (string) name of the column
	 * @return (object) clone of $this
     */
	public function whereIsNotNull($v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "({$v} IS NOT NULL)";
		return $t;
	}
	
	/**
	 * Add a condition OR WHERE IS NOT NULL to the query where the results must not have a null value in the column
	 * @param $v (string) name of the column
	 * @return (object) clone of $this
	 */
	public function orWhereIsNotNull($v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "({$v} IS NOT NULL)";
		return $t;
	}

	/**
	 * Inject a SQL code for obtain a condition AND WHERE to the query 
	 * @param $v (string) SQL code
	 * @return (object) clone of $this
	 */
	public function whereRaw($v){
		$t = clone $this;
		$t -> builder -> andWhere[] = "(".$t -> setPrepare($v).")";
		return $t;
	}
	
	/**
	 * Inject a SQL code for obtain a condition OR WHERE to the query 
	 * @param $v (string) SQL code
	 * @return (object) clone of $this
	 */
	public function orWhereRaw($v){
		$t = clone $this;
		$t -> builder -> orWhere[] = "(".$t -> setPrepare($v).")";
		return $t;
	}

	/**
	 * Return the SQL code for the condition WHERE
	 * @param $where (bool) indicates if is necessary add a WHERE comand (true, by default) or not (false)
	 * @return (string) SQL code
	 */
	private function getWhereSQL($where = true){
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
	 * @param $c (string) name of the column
	 * @param $v (array) value of increment
	 * @return (object) clone of $this
	 */
	public function increment($c,$v = 1){
		$t = clone $this;
		$t -> builder -> update[] = "{$c} = {$c} + ".$t -> setPrepare($v);
		return $t;
	}
	
	/**
	 * Decrease the value of the column
	 * @param $c (string) name of the column
	 * @param $v (array) value of decrease
	 * @return (object) clone of $this
	 */
	public function decrement($c,$v = 1){
		$t = clone $this;
		$t -> builder -> update[] = "{$c} = {$c} - ".$t -> setPrepare($v);
		return $t;
	}



	public function getTableOperation(){
		$r = !empty($this -> builder -> table_as) ? " AS {$this -> builder -> table_as} " : '';
		return "{$this -> builder -> table} {$r}";
	}

	/**
	 * Effect a LEFT JOIN with an other table
	 * @param $t (string) name of the second table
	 * @param $v1 (string) name of the column of the primary table
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param $v3 (string) optional name of the column of the second table
	 * @param $v4 (bool) optional indicates if automatically assign the table to the column (true) or not (false)
	 * @return (object) $this
	 */
	public function leftJoin($t,$v1,$v2,$v3 = NULL,$v4 = true){
		return $this -> _join('LEFT JOIN',$t,$v1,$v2,$v3,$v4);
	}

	/**
	 * Effect a RIGHT JOIN with an other table
	 * @param $t (string) name of the second table
	 * @param $v1 (string) name of the column of the primary table
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param $v3 (string) optional name of the column of the second table
	 * @param $v4 (bool) optional indicates if automatically assign the table to the column (true) or not (false)
	 * @return (object) $this
	 */
	public function rightJoin($t,$v1,$v2,$v3 = NULL,$v4 = true){
		return $this -> _join('RIGHT JOIN',$t,$v1,$v2,$v3,$v4);
	}

	/**
	 * Effect a JOIN with an other table
	 * @param $t (string) name of the second table
	 * @param $v1 (string) name of the column of the primary table
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param $v3 (string) optional name of the column of the second table
	 * @param $v4 (bool) optional indicates if automatically assign the table to the column (true) or not (false)
	 * @return (object) $this
	 */
	public function join($t,$v1,$v2,$v3 = NULL,$v4 = true){
		return $this -> _join('JOIN',$t,$v1,$v2,$v3,$v4);
	}

	/**
	 * Add a SQL code for a JOIN|LEFT JOIN|RIGHT JOIN
	 * @param $ACT (string) type of JOIN
	 * @param $table (string) name of the secondary table
	 * @param $v1 (string) name of the column of the primary table
	 * @param $v2 (string) if $v3 is defined indicates the comparison agent between the columns, otherwise indicates the name of the column of the second table
	 * @param $v3 (string) optional name of the column of the second table
	 * @param $v4 (bool) optional indicates if automatically assign the table to the column (true) or not (false)
	 * @return (object) clone of $this
	 */
	public function _join($ACT,$table,$v1,$v2,$v3 = NULL,$v4 = true){

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

		$t -> builder -> join[] = ($v4)
			? "{$ACT} {$table} ON {$this -> builder -> table}.{$c1} {$op} {$table}.{$c2}"
			: "{$ACT} {$table} ON {$c1} {$op} {$c2}";

		return $t;

	}

	/**
	 * Execute the query and insert a record ignoring duplicates
	 * @param $v (string) array of elements to insert (name column => value column)
	 * @return (object) $this
	 */
	public function insertIgnore($v){
		return $this -> insert($v,true);
	}
	
	/**
	 * Execute the query and insert a record if isn't present any record, otherwise update
	 * @param $v (string) array of elements to insert|update (name column => value column)
	 * @param $ignore (bool) if set recall insertIgnore(true) or insert(false)
	 * @return (int) number of result affected from the query(update) or last ID insert(insert)
	 */
	public function insertUpdate($v,$ignore = false){
		return $this -> count() == 0
			? $ignore 
				? $this -> insertIgnore($v) 
				: $this -> insert($v)
			: $this -> update($v);
	}

	/**
	 * Execute the query and insert a record
	 * @param $a (array) array of elements to insert (name column => value column)
	 * @param $ignore (bool) ignore the duplicates(true) or reproduce an error(false)
	 * @return (int) last id insert
	 */
	public function insert($a,$ignore = false){

		if(empty($a))return 0;
		$t = clone $this;

		$kf = array();
		$vk = array();
		foreach($a as $k => $v){
			$kf[] = $k;
			$v = DB::quote($v);
			$vk[] = $t -> setPrepare($v);
		}

		$ignore = $ignore ? ' IGNORE ' : '';
		return $t -> query("
			INSERT {$ignore} INTO {$this -> getTableOperation()} 
			(".implode($kf,",").") 
			VALUES (".implode($vk,",").") 
		");
	}

	/**
	 * Execute the query and insert at least a record
	 * @param $nv (array) array made from the names of the column to insert
	 * @param $av (array) array made from an array of values for each row
	 * @param $ignore (bool) ignore the duplicates(true) or reproduce an error(false)
	 * @return (int) last ID insert
	 */
	public function insertMultiple($nv,$av,$ignore = false){
		
		if(empty($av) || empty($nv))return 0;

		$t = clone $this;
		$vkk = array();

		if(is_object($av) && ($av instanceof Closure)){
			$c = $av();
			$t -> builder -> prepare = array_merge($t -> builder -> prepare,$c -> builder -> prepare);
			$vkk = "(".$c -> getSelectSQL().")";

		}else{
			foreach($av as $k){
				$vk = array();
				foreach($k as $v){
					$v = DB::quote($v);
					$vk[] = $t -> setPrepare($v);
				}
				$vkk[] = "(".implode($vk,",").")";
			}
			$vkk = "VALUES ".implode($vkk,",");
		}

		$nv = "(".implode($nv,",").")";

		$ignore = $ignore ? ' IGNORE ' : '';
		
		return DB::count($t -> query("
			INSERT {$ignore} INTO {$this -> getTableOperation()} 
			$nv
			$vkk
		"));
	}

	/**
	 * Execute the query and update the record
	 * @param $v1 (mixed) if $v2 is defined indicates the name of the column to update, otherwise the array (name column => value columns)
	 * @param $v2 (string) optional value of the column to update
	 * @return (int) number of row involved in the update
	 */

	public function update($v1,$v2 = NULL){

		if(empty($v))return 0;

		$t = clone $this;

		if(!is_array($v1) && isset($v2)){
			$kf = array("{$this -> builder -> table}.{$v1} = ".$t -> setPrepare($v2));
		}else{
			$kf = empty($t -> builder -> update) ? array() : $t -> builder -> update;
			foreach($v1 as $k => $v){
				$kf[] = "{$this -> builder -> table}.$k = ".$t -> setPrepare($v1);
			}
		}

		$q = $t -> query("
			UPDATE {$this -> getTableOperation()} 
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
	 * @param $v1 (array) array of columns to update in base a specific condition
	 * @param $v2 (array) value of the column to update
	 * @return (int) number of row involved in the update
	 */
	public function updateMultiple($v1,$v2){
		if(empty($v1) || empty($v2))return false;

		$t = clone $this;
		$kf = empty($t -> builder -> update) ? array() : $t -> builder -> update;
		
		foreach($v1 as $k => $v){

			if(is_array($v2[$k])){
				$s = "{$this -> builder -> table}.{$v[1]} = CASE {$v[0]}";

				foreach($v2[$k] as $n1 => $k1){
					$s .= " WHEN ".$t -> setPrepare($n1)." THEN ".$t -> setPrepare($k1)." ";
					$where[] = $n1;
				}
				$s .= " ELSE {$v[1]} END";

				$kf[] = $s;
			}else{
				$kf[] = "{$this -> builder -> table}.{$v} = ".$t -> setPrepare($v2[$k]);
			}
		}


		$q = $t -> query("
			UPDATE {$this -> getTableOperation()} 
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
	 * @param $v (string) optional indicates the name of the table from which delete the records (used in the join)
	 * @return (int) number of rows involved in the elimination
	 */
	public function delete($v = ''){
		if(empty($v)) $v = $this -> getTableOperation();
		return $this -> query("
			DELETE {$v} FROM {$this -> getTableOperation()} 
			".implode($this -> builder -> join," ")."
			".$this -> getWhereSQL()."
		");
	}

	/**
	 * Execute the elimination query
	 * @param $v (string) optional indicates the name of the table from which delete the records (used in the join)
	 * @return (int) number of rows involved in the elimination
	 */
	public function truncate(){
		return $this -> query("
			TRUNCATE {$this -> builder -> table} 
		");
	}

	/**
	 * Regroup the same results from a specific column 
	 * @param $v (mixed) name or array of names of the column involved in the regroup
	 * @return (object) clone of $this
	 */
	public function groupBy($v){
		$t = clone $this;
		if(!is_array($v))$v = array($v);
		$t -> builder -> groupBy = array_merge($t -> builder -> groupBy,$v);
		return $t;
	}

	/**
	 * Return the SQL code to execute the regroup
	 * @return (string) SQL code
	 */
	public function getGroupBySQL(){
		$s = implode($this -> builder -> groupBy," , ");
		if(!empty($s))$s = " GROUP BY {$s} ";
		return $s;
	}
	
	/**
	 * Configure the column which will occupy the index of the array with the results
	 * @param $v (string) name of the column
	 * @return (string) name of the value
	 */
	public function setIndexResult($v){
		$this -> builder -> indexResult = $v;
		return $this;
	}

	/**
	 * Execute the query and return the selected records as result
	 * @return (array) result of the query
	 */
	public function lists(){
		$r = $this -> assoc($this -> getSelectSQL());
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
	 * @return (array) result of the query
	 */
	public function get(){
		$r = $this -> take(1) -> lists();

		return !empty($r) ? $r[0] : array();
	}

	/**
	 * Return the SQL code for selection
	 * @return (string) SQL code
	 */
	public function getSelectSQL(){

		if(empty($this -> builder -> select))$this -> builder -> select[] = "*";

		$t = "";
		$i = 0;

		$c = "
			SELECT ".implode($this -> builder -> select,",")." FROM {$this -> getTableOperation()} 
			".implode($this -> builder -> join," ")."
			".$this -> getWhereSQL()."
			".$this -> getGroupBySQL()."
			".$this -> getOrderBySQL()."
			".$this -> getLimitSQL()."
		";
		$t = empty($t) ? $c : "{$t}($c) as tmp".++$i;
		

		return $c;
	}

	// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
	
	/**
	 * Execute a reserch query of the number of the columns
	 * @return (int) number of the columns
	 */
	public function countColumns(){
		$c = DB::table('information_schema.COLUMNS');
		
		return $c -> where('TABLE_SCHEMA',DB::getName())
			-> where('TABLE_NAME',$this -> builder -> table)
			-> count();
	}

	/**
	 * Execute a reserch query that search if a column exist
	 * @param (string) predetermined column type or SQL code that defined the type of the column
	 * @return (object) $this
	 */
	public function hasColumn($v){
		$c = DB::table('information_schema.COLUMNS');
		return $c -> where('TABLE_SCHEMA',DB::getName())
			-> where('TABLE_NAME',$this -> builder -> table)
			-> where('COLUMN_NAME',$v)
			-> count() == 1;
	}

	/**
	 * Defines the column to use
	 * @param (string) name of the column
	 * @return (object) $this
	 */
	public function column($v){
		$this -> schema = new stdClass();
		$this -> schema -> column = strtolower($v);
		$this -> schema -> add = array();
		$this -> schema -> foreign = new stdClass();

		return $this;
	}

	/**
	 * Return the SQL code for the selection
	 * @param (string) predetermined column type or SQL code that defined the type of the column
	 * @return (object) $this
	 */
	public function type($t){

		switch($t){
			case 'timestamp': $t = "INT(10)"; break;
			case 'varchar': $t = "VARCHAR(55)"; break;
			case 'md5': $t = "CHAR(32)"; break;
			case 'id': $t = "BIGINT(11) AUTO_INCREMENT PRIMARY KEY"; break;
			case 'big_int': $t = "BIGINT(11) "; break;
			case 'tiny_int': $t = "TINYINT(1) "; break;
			case 'text': $t = 'TEXT'; break;
			case 'float': $t = "DOUBLE"; break;
			case 'cod': $t = "VARCHAR(11)"; break;
			case 'string': $t = "VARCHAR(80)"; break;
		}

		$this -> schema -> add[] = "{$this -> schema -> column} {$t}";
		return $this;
	}

	
	/**
	 * Makes the column a primary key
	 * @return (object) $this
	 */
	public function primary(){
		$this -> schema -> add[] = " PRIMARY KEY({$this -> schema -> column}) ";
		return $this;
	}

	/**
	 * Makes the column a unique key
	 * @return (object) $this
	 */
	public function unique(){
		$this -> schema -> add[] = " UNIQUE({$this -> schema -> column}) ";
		return $this;

	}

	/**
	 * Makes the column an index
	 * @return (object) $this
	 */
	public function index(){
		$this -> schema -> add[] = " INDEX({$this -> schema -> column}) ";
		return $this;
	}

	/**
	 * Makes the column a foreign key
	 * @param $t (string) name of the table referenced
	 * @param $v (string) name of the column referenced
	 * @return (object) $this
	 */
	public function foreign($t,$v){
		if(!empty($this -> schema -> foreign -> column ))
			$this -> updateForeign();

		$this -> schema -> foreign -> table = $t;
		$this -> schema -> foreign -> column = $v;
		return $this;
	}

	/**
	 * Add a SQL code everytime there is an elimination
	 * @param $c (string) SQL code
	 */
	public function onDelete($c){
		$this -> schema -> foreign -> onDelete = " ON DELETE {$c} ";
	}
	
	/**
	 * Add a SQL code everytime there is an update
	 * @param $c (string) SQL code
	 */
	public function onUpdate($c){
		$this -> schema -> foreign -> onDelete = " ON UPDATE {$c} ";
	}

	/**
	 * Return the SQL code that define the foreign keys
	 */
	private function updateForeign(){
		$this -> schema -> add[] = "
			ADD FOREIGN KEY ({$this -> schema -> column}) 
			REFERENCES {$this -> schema -> foreign -> table}({$this -> schema -> foreign -> column})
			{$this -> schema -> foreign -> onDelete}
			{$this -> schema -> foreign -> onUpdate}
		";

		$this -> schema -> foreign -> column = "";
		$this -> schema -> foreign -> table = "";
		$this -> schema -> foreign -> onDelete = "";
		$this -> schema -> foreign -> onUpdate = "";
	}

	/**
	 * Execute an alteration query of the pattern of the database according to setted parameters
	 * @return (object) result of the query
	 */
	public function alter(){
		if(!DB::getAlterSchema()) return;

		
		if(!$this -> getCacheNameTable($this -> builder -> table)){
			$this -> addCacheNameTable($this -> builder -> table);
			if(!$this -> builder -> is_table && !DB::hasTable($this -> builder -> table)){
				$this -> builder -> is_table = true;
				$this -> query("CREATE TABLE IF NOT EXISTS {$this -> builder -> table}( ".implode($this -> schema -> add,",").")");
			}
		}

		if(!$this -> hasColumn($this -> schema -> column)){
			return $this -> query("
				ALTER TABLE {$this -> builder -> table} ADD ".implode($this -> schema -> add,", ADD ")."
			");
		}

		return false;
	}

	/**
	 * Execute a reset query of the counter auto_increment
	 * @return (object) result of the query
	 */
	public function resetAutoIncrement(){
		return $this -> query("ALTER TABLE {$this -> builder -> table} AUTO_INCREMENT = 1");
	}
	
	/**
	 * Add the name of the table to the internal cache. That is used to avoid the request of existance of a table.
	 * @param (string) name of the table
	 */
	public function addCacheNameTable($r){
		self::$cacheAlter[] = $r;
	}

	/**
	 * Return the existance of a table in the cache list
	 * @return (bool) the table is already check(true) or not(false)
	 */
	public function getCacheNameTable($r){
		return in_array($r,self::$cacheAlter);
	}

}