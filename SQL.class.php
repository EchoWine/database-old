<?php


class SQL{

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
}