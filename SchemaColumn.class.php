<?php

/**
 * Schema
 */
class SchemaColumn{

	public 	$name,
			$type;

	public	$length = null,
	 		$null = null,
			$default = null,
			$primary = false,
			$unique = false,
			$auto_increment = false,
			$index = false,
			$foreign = null;

	public function __construct($params){

		foreach($params as $n => $param){
			$this -> {$n} = $param;
		}

		$this -> foreign = new stdClass();

	}

	public function getName(){
		return $this -> name;
	}

	public function getIndex(){
		return $this -> index;
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

	public function setIndex(bool $index){
		$this -> index = $index;
	}

	public function setForeign(string $table,string $column){
		$this -> foreign -> table = $table;
		$this -> foreign -> column = $column;
	}

	public function setForeignDelete($v){
		$this -> foreign -> delete = $v;
	}

	public function setForeignUpdate($v){
		$this -> foreign -> update = $v;
	}

	public function getForeign(){
		return $this -> foreign;
	}


	public function getForeignTable(){
		return $this -> foreign -> table;
	}

	public function getForeignColumn(){
		return $this -> foreign -> column;
	}

	public function getForeignDelete(){
		return $this -> foreign -> delete;
	}

	public function getForeignUpdate(){
		return $this -> foreign -> update;
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
		&& $this -> getNull() == $c -> getNull();
	}

	public function getKeys(){

		$r = [];

		if($this -> getIndex())$r[] = 'index';
		if($this -> getPrimary())$r[] = 'primary';

		return $r;
	}
}