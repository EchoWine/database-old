<?php

/**
 * Schema
 */
class Schema{


	public static $tables = [];

	public static function ini(){		

		# Get info about all tables
		foreach(DB::fetch("SHOW TABLES") as $k){

			$table = new SchemaTable($k[0]);
			
			# Get columns
			foreach(DB::fetch("describe {$table -> getName()}") as $k){
				
				preg_match('/\((.*)\)/',$k['Type'],$length);
				$type = preg_replace('/\((.*)\)/','',$k['Type']);

				$column = new SchemaColumn([
					'table' => $table -> getName(),
					'name' => $k['Field'],
					'type' => $type,
					'length' => isset($length[1]) ? $length[1]  : null,
					'null' => $k['Null'] == 'YES',
					'default' => $k['Default'],
					'primary' => $k['Key'] == 'PRI',
					'unique' => $k['Key'] == 'UNI',
					'auto_increment' => $k['Extra'] == 'auto_increment',
				]);

				$table -> addColumn($column);

			}

			# Get index
			foreach(DB::fetch("SHOW INDEX FROM {$table -> getName()}") as $k){
				if(!$table -> getColumn($k['Column_name']) -> getPrimary())
					$table -> getColumn($k['Column_name']) -> setIndex(true);
			}


			foreach(DB::fetch("select TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME from information_schema.key_column_usage WHERE CONSTRAINT_SCHEMA = '".DB::getName()."' AND TABLE_NAME = '{$table -> getName()}' AND REFERENCED_TABLE_NAME IS NOT NULL") as $k){

				$c = $table -> getColumn($k['COLUMN_NAME']);

				$c -> setConstraint($k['CONSTRAINT_NAME']);
				$c -> setForeign($k['REFERENCED_TABLE_NAME'],$k['REFERENCED_COLUMN_NAME']);

			}

			self::$tables[$table -> getName()] = $table;
		}

	}

	public static function dropMissing(){
		foreach(self::$tables as $n => $k){
			if(!isset(SchemaBuilder::$tables[$n]))
				DB::schema($n) -> drop();

			else{
				$table = SchemaBuilder::$tables[$n];

				foreach($k -> getColumns() as $name_column => $column){
					if($table -> getColumn($name_column) == null){
						DB::schema($n) -> dropColumn($name_column);
					}
				}

				
				
			}

		}
	}

	public static function hasTable($table){
		return isset(self::$tables[$table]);
	}

	public static function tableHasColumn($table,$column){
		return self::$tables[$table] -> hasColumn($column);
	}

	public static function tableCountColumns($table,$column){
		return self::hasTable($table) ? self::$table[$table] -> countColumns() : 0;
	}



	public static function getTables(){
		return self::$tables;
	}

	public static function getTable($table){
		return isset(self::$tables[$table]) ? self::$tables[$table] : null;
	}

	public static function addTable($table,$columns = []){
		$table = new SchemaTable($table,$columns);
		self::$tables[$table -> getName()] = $table;
	}

	public static function getAllForeignKeyTo($table){
		$r = [];
		foreach(self::$tables as $n => $k){
			$c = $k -> getForeignKeyTo($table);
			if($c !== null)$r[] = $c;
		}

		return $r;
	}

	public static function getAllForeignKeyToColumn($table,$column){
		$r = [];
		foreach(self::$tables as $n => $k){
			$c = $k -> getForeignKeyToColumn($table,$column);
			if($c !== null)$r[] = $c;
		}

		return $r;
	}



}