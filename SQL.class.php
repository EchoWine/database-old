<?php

/**
 * SQL
 */
class SQL{
	
	const TINYINT = 'tinyint';
	const BIGINT = 'bigint';
	const INT = 'int';
	const VARCHAR = 'varchar';
	const FLOAT = 'float';
	const DOUBLE = 'double';
	const TEXT = 'text';


	const INDEX = 'index';
	const FOREIGN = 'foreign';


	/**
	 * @return string get all the tables
	 */
	public static function SHOW_TABLES(){
		return "SHOW TABLES";
	}

	/**
	 * @param string $tableName
	 * @return string get information about a table
	 */
	public static function SHOW_TABLE($tableName){
		return "describe $tableName";
	}

	/**
	 * @param string $tableName
	 * @return string get all index in a table
	 */
	public static function SHOW_INDEX($tableName){
		return "SHOW INDEX FROM $tableName";
	}

	/**
	 * @param string $dbName
	 * @param string $tableName
	 * @return string get all constraint in a table
	 */
	public static function SHOW_CONSTRAINT($dbName,$tableName){
		return "
			select TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME 
			from information_schema.key_column_usage 
			WHERE CONSTRAINT_SCHEMA = '$dbName' AND TABLE_NAME = '$tableName' AND REFERENCED_TABLE_NAME IS NOT NULL
		";
	}

	public static function DROP_TABLE($tableName){
		return "DROP TABLE $tableName";
	}

	public static function DROP_COLUMN($tableName,$columnName){
		return "ALTER TABLE $tableName DROP COLUMN $columnName";
	}

	public static function DROP_FOREIGN_KEY($tableName,$constraintName){
		return "ALTER TABLE $tableName DROP FOREIGN KEY $constraintName";
	}

	public static function MODIFY_COLUMN_RESET($tableName,$columnName){
		return "ALTER TABLE $tableName MODIFY $columnName tinyint(1)";
	}

	public static function DROP_PRIMARY_KEY($tableName){
		return "ALTER TABLE $tableName DROP PRIMARY KEY";
	}

	public static function DROP_INDEX_KEY($tableName,$indexName){
		return "ALTER TABLE $tableName DROP INDEX $indexName";
	}

	public static function ADD_INDEX_KEY($tableName,$indexName){
		return "ALTER TABLE $tableName ADD INDEX($indexName)";
	}

	public static function ADD_FOREIGN_KEY($tableName,$column,$constraintName,$foreignTable,$foreignColumn,$onDelete,$onUpdate){
 		
 		$onDelete =  $onDelete ? ' ON DELETE '.$onDelete : '';
 		$onUpdate =  $onUpdate ? ' ON UPDATE '.$onUpdate : '';
 		$constraintName = $constraintName != null ? ' CONSTRAINT '.$constraintName : '';

		return "ALTER TABLE $tableName ADD 
			$constraintName 
			FOREIGN KEY ($column)
			REFERENCES $foreignTable($foreignColumn)
			$onDelete $onUpdate
		";

	}
	public static function SELECT_CONSTRAINT($dbName,$tableName,$columnName){
		return "
			select CONSTRAINT_NAME 
			from information_schema.key_column_usage 
			WHERE CONSTRAINT_SCHEMA = '$dbName' AND TABLE_NAME = '$tableName' AND COLUMN_NAME = '$columnName'
		";
	}

	public static function CREATE_TABLE($tableName,$columns){
		return "CREATE TABLE IF NOT EXISTS $tableName (".implode(",",$columns).")";
	}

	public static function EDIT_COLUMN($tableName,$columnName,$column){
		return "ALTER TABLE $tableName CHANGE COLUMN $columnName $column";
	}

	public static function ADD_COLUMN($tableName,$column){
		return "ALTER TABLE $tableName ADD $column";
	}

	public static function COLUMN($name,$type,$length = null,$primary = false,$unique = false,$auto_increment = false,$null = false){

		$unique = $unique ? 'UNIQUE' : '';
		$primary = $primary ? 'PRIMARY KEY' : '';
		$auto_increment = $auto_increment ? 'AUTO_INCREMENT' : '';
		$null = $null ? 'NULL' : 'NOT NULL';

		return $name." ".self::TYPE($type,$length)." ".$primary." ".$auto_increment." ".$unique." ".$null;
	}

	public static function TYPE($type,$length){

		switch($type){

			case self::TINYINT:
			case self::INT:
			case self::BIGINT:
			case self::VARCHAR:
			case self::FLOAT:
			case self::DOUBLE:
				return "$type($length)";


			case self::TEXT:
				return $type;

		}

		die('Error');
	}

	public static function ENABLE_CHECKS_FOREIGN(){
		return "SET FOREIGN_KEY_CHECKS = 1";
	}

	public static function DISABLE_CHECKS_FOREIGN(){
		return "SET FOREIGN_KEY_CHECKS = 0";
	}
	
}