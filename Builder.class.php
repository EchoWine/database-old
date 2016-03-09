<?php

/**
 * Builder
 */
class Builder{


	/**
	 * Counter of all istance of this class
	 */
	public static $counter = 0;

	/**
	 * Count of actual istance
	 */
	public $count;

	public $table;

	public $agg = [];

	public $select = [];

	public $update = [];

	public $orderby = [];
			
	public $skip = NULL;
			
	public $take = NULL;
			
	public $groupBy = [];
			
	public $andWhere = [];
			
	public $orWhere = [];
			
	public $join = [];
			
	public $andOn = [];
			
	public $orOn = [];
			
	public $union = [];
			
	public $is_table = false;
			
	public $indexResult = "";
			
	public $tmp_prepare = [];
			
	public $prepare = [];
			
	public $lastJoinTable = null;

	/**
	 * List of all the alias made automatically for the nested selection query
	 */
	public static $tableAs = array();

	public function __construct(){

	}

	public function setPrepare($v){
		$this -> prepare = $v;
	}

	public function getPrepare(){
		return $this -> prepare;
	}

	public function addPrepare($n,$v){
		$this -> prepare[$n] = $v;
	}

	/**
	 * Clone 
	 */
	public function __clone(){
		$this -> incCount();
	}

	/**
	 * Increment the counter
	 */
	public function incCount(){
		$this -> count = self::$counter++;
	}

	/**
	 * Get counter
	 */
	public function getCount(){
		return $this -> count;
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


	public function addTable($table,$alias = ''){

		if($table instanceof QueryBuilder){
			if($alias === null){
				$alias = slef::getTableAs();
			}
		}

		if($alias !== null)
			$this -> table[$alias] = $table;
		else
			$this -> table[] = $table;


		$this -> setLastJoinTable($table);
	}


	public function getLastJoinTable(){
		return $this -> lastJoinTable;
	}

	public function setLastJoinTable($table){
		$this -> lastJoinTable = $table;
	}

}