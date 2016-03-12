<?php
	
	include dirname(__FILE__).'/main.php';


	DB::connect([
		'driver' => 'mysql',
		'hostname' => '127.0.0.1',
		'database' => 'wine',
		'username' => 'root',
		'password' => '',
		'charset' => 'utf8',
		'restore' => 0,
		'alter_schema' => true,

		# 0: Do nothing, 1: Log, 2: Log + Backup
		'alter_schema_sec' => 0,

	]);

	/*
	DB::schema('tab1') -> id() -> alter();
	DB::schema('tab1') -> string('username',30) -> alter();


	DB::schema('tab2') -> id() -> alter();
	DB::schema('tab2') -> bigint('tab1_id') -> foreign('tab1','id')  -> alter();
	*/

	//DB::table('tab1') -> insert(['username' => 'mario']);

	// $q = DB::table('tab1') -> join('tab2') -> get();



	DB::startLog();

	DB::schema('tab1',function($tab){
		$tab -> id();
		$tab -> string('name') -> unique();
		$tab -> string('foo') -> unique() -> null();
		$tab -> string('fo1o') -> unique() -> null();
		$tab -> int('fo1os');
	});


	DB::schema('tab2') -> id() -> alter();
	DB::schema('tab2') -> bigint('tab1_id') -> foreign('tab1','id') -> alter();

	DB::schema('tab3') -> id() -> alter();
	DB::schema('tab3') -> bigint('tab1_id') -> foreign('tab1','id') -> alter();
	DB::schema('tab3') -> string('username') -> unique() -> alter();

 	DB::schema('tab3_tab2') -> bigint('tab3_id') -> foreign('tab3','id') -> alter();
 	DB::schema('tab3_tab2') -> bigint('tab2_id') -> foreign('tab2','id') -> alter();
 	DB::schema('tab3_tab2') -> bigint('taxi') -> alter();

 	$tab1_id = DB::table('tab1') -> insert([
		['name' => md5(microtime()),'foo' => null],
		['name' => md5(microtime()),'foo' => null],
		['name' => md5(microtime()),'foo' => null]
	]);
 	
 	$tab2_id = DB::table('tab2') -> insert(['tab1_id' => $tab1_id[0]]);
 	$tab3_id = DB::table('tab3') -> insert(['tab1_id' => $tab1_id[1],'username' => md5(microtime())]);
 	DB::table('tab3_tab2') -> insert(['tab2_id' => $tab2_id[0],'tab3_id' => $tab3_id[0],'taxi' => 5]);


 	DB::table('tab1') -> insert(['name' => md5(microtime())]);


 	/* --------------------------------------

 			JOIN

	--------------------------------------- */

 	DB::table('tab2') -> join('tab3_tab2','tab3_tab2.tab2_id','=','tab2.id') -> join('tab3','tab3_tab2.tab3_id','=','tab3.id') -> get();


 	DB::table('tab3_tab2') -> join(['tab3','tab2']) -> get();


 	DB::table('tab2') -> join('tab3_tab2') -> join('tab3') -> get();



 	DB::table('tab2')
 	-> join('tab3_tab2',function($q){
 		$q = $q -> where('tab3_tab2.taxi','=',5);
 		return $q;

 	}) -> join('tab3') -> get();

 	
	DB::table('tab2')
 	-> join('tab3_tab2',function($q){
 		$q = $q -> on('tab3_tab2.tab2_id','=','tab2.id');
 		$q = $q -> where('tab3_tab2.taxi','=',5);
 		return $q;

 	}) -> join('tab3') -> get();

 	

 	DB::table('tab3_tab2') -> join(['tab3' => function($q){
 		$q = $q -> where('tab3_tab2.taxi','=',5);
 		return $q;
 	},'tab2']) -> get();


	DB::table('tab2')
 	-> join('tab3_tab2',function($q){
 		$q = $q -> on(function($q){
 			return $q -> orOn('tab3_tab2.tab2_id','=','tab2.id') -> orOn('tab3_tab2.tab2_id','=','tab2.id');
 		});
 		$q = $q -> on(function($q){
 			return $q -> orOn('tab3_tab2.tab2_id','=','tab2.id') -> orOn('tab3_tab2.tab2_id','=','tab2.id');
 		});
 		$q = $q -> where('tab3_tab2.taxi','=',5);
 		return $q;

 	}) -> join('tab3') -> get();




 	
 
 	/*
 	DB::table('tab1') -> insert(function(){
 		return DB::table('tab1') -> select('name');
 	});
 	*/


	/*
	DB::schema('tab3') -> dropColumn('username');
	DB::schema('tab3') -> drop();
	*/

	DB::schema('tab3') -> drop();
	DB::schema('tab1') -> drop();
	DB::schema('tab2') -> drop();
	DB::schema('tab3_tab2') -> drop();

	// End all schema
	DB::dropMissing();
	DB::printLog();


?>