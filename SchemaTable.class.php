<?php

/**
 * Schema
 */
class SchemaTable{

	public 	$name,$columns = [];


	public function __construct($name,$column = []){

		$this -> name = $name;
		$this -> columns = $column;

	}	

	public function getName(){
		return $this -> name;
	}

	public function addColumn($column){
		$this -> columns[$column -> getName()] = $column;
	}

	public function setColumn($column){
		$this -> columns[$column -> getName()] = $column;
	}

	public function getColumn(string $nameColumn){
		return $this -> hasColumn($nameColumn) ? $this -> columns[$nameColumn] : null;
	}

	public function hasColumn(string $nameColumn){
		return isset($this -> columns[$nameColumn]);
	}

	public function countColumns(){
		return count($this -> columns);
	}

	public function hasPrimary(){
		foreach($this -> columns as $k){
			if($k -> getPrimary())
				return true;
		}

		return false;
	}

	public function hasAutoIncrement(){
		foreach($this -> columns as $k){
			if($k -> getAutoIncrement())
				return true;
		}

		return false;
	}

	public function dropPrimary(){
		foreach($this -> columns as $k){
			if($k -> getPrimary())
				$k -> setPrimary(false);
		}
	}

}