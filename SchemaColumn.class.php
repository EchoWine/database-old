<?php

/**
 * Schema
 */
class SchemaColumn{

	public 	$table,
			$name,
			$type;

	public	$length = null,
	 		$null = null,
			$default = null,
			$primary = false,
			$unique = false,
			$auto_increment = false,
			$index = false,
			$constraint = '';

	public $foreignColumn,$foreignTable,$foreignDelete,$foreignUpdate;

	public function __construct($params){

		foreach($params as $n => $param){
			$this -> {$n} = $param;
		}

		$this -> foreign = new stdClass();

	}

	public function getTable(){
		return $this -> table;
	}

	public function getName(){
		return $this -> name;
	}

	public function getIndex(){
		return $this -> index;
	}

	public function hasIndex(){
		return !empty($this -> index);
	}

	public function getAutoIncrement(){
		return $this -> auto_increment;
	}

	public function getPrimary(){
		return $this -> primary;
	}

	public function getUnique(){
		return $this -> unique;
	}

	public function getNull(){
		return $this -> null;
	}

	public function getDefault(){
		return $this -> default;
	}

	public function getLength(){
		return $this -> length;
	}

	public function getType(){
		return $this -> type;
	}

	public function setIndex(String $index = null){
		$this -> index = $index;
	}

	public function setForeign(string $table = null,string $column = null){
		$this -> foreignTable = $table;
		$this -> foreignColumn = $column;
	}

	public function setForeignDelete($v){
		$this -> foreignDelete = $v;
	}

	public function setForeignUpdate($v){
		$this -> foreignUpdate = $v;
	}

	public function setConstraint($v){
		$this -> constraint = $v;
	}

	public function getForeign(){
		return !empty($this -> foreignTable) && !empty($this -> foreignColumn);
	}

	public function getConstraint(){
		return $this -> constraint;
	}

	public function getForeignTable(){
		return $this -> foreignTable;
	}

	public function getForeignColumn(){
		return $this -> foreignColumn;
	}

	public function getForeignDelete(){
		return $this -> foreignDelete;
	}

	public function getForeignUpdate(){
		return $this -> foreignUpdate;
	}

	public function setAutoIncrement(bool $auto_increment){
		$this -> auto_increment = $auto_increment;
	}

	public function setPrimary(bool $primary){
		$this -> primary = $primary;
	}

	public function setUnique(bool $unique){
		$this -> unique = $unique;
	}

	public function setNull(bool $null){
		$this -> null = $null;
	}

	public function setDefault(string $default){
		$this -> default = $default;
	}

	public function setLength(int $length = null){
		$this -> length = $length;
	}

	public function setType(string $type){
		$this -> type = $type;
	}

	public function get(){
		return $this;
	}

	public function equals(SchemaColumn $c){
		return $this -> getType() == $c -> getType()
		&& $this -> getLength() == $c -> getLength()
		&& $this -> getAutoIncrement() == $c -> getAutoIncrement()
		&& $this -> getPrimary() == $c -> getPrimary()
		&& $this -> getIndex() == $c -> getIndex()
		&& $this -> getNull() == $c -> getNull()
		&& $this -> getName() == $c -> getName()
		&& $this -> equalsForeign($c);
	}

	public function equalsForeign(SchemaColumn $c){
		return
		$this -> getForeignTable() == $c -> getForeignTable()
		&& $this -> getForeignColumn() == $c -> getForeignColumn();
		/*
		&& $this -> getForeignDelete() == $c -> getForeignDelete()
		&& $this -> getForeignUpdate() == $c -> getForeignUpdate();
		*/
	}

	public function getKeys(){

		$r = [];

		if($this -> getIndex())$r[] = 'index';
		if($this -> getPrimary())$r[] = 'primary';

		return $r;
	}

	public function resetForeign(){
		$this -> setConstraint(null);
		$this -> setForeign(null,null);
		$this -> setForeignDelete(null);
		$this -> setForeignUpdate(null);
	}

}