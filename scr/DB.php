<?php

namespace EduardoLanzini;

use \PDO;

Class DB
{
	private static $table, $pdo, $sql;

	public function __construct()
	{
		if(!self::$pdo){
			die("Conexão não definida");
		}
	}

	public static function connect($conn)
	{
		$conn = (object)$conn;

		try {

			if (!isset(self::$pdo)) {

				try {
					self::$pdo = new \PDO($conn->driver . ':host=' . $conn->host . ';'.'port='.$conn->port.';'.$conn->charset.'dbname=' . $conn->database, $conn->user, $conn->pass);
				} catch (\PDOException $e) {
					 //echo $e->getMessage();
					exit('Erro ao conectar com o banco de dados');
				}
				
				self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

			}

		} catch (\PDOException $e) {

			die($e->getMessage());
		}

		return true;
	}

	static function table($table)
	{
		self::$table = $table;

		self::$sql = preg_match("/SELECT/", self::$sql) ? self::$sql." FROM ".self::$table.' ' : self::$sql;

		return new self;
	}

	static function prefix()
	{
		$prefix = preg_match("/SELECT/", self::$sql) ? "" : "SELECT * FROM ".self::$table;
		self::$sql = $prefix.self::$sql;
		return new self;
	}

	static function exec()
	{
		self::get();
	}
	
	static function get()
	{
		self::prefix();

		try {

			$qry = self::$pdo->prepare(self::$sql);
			$qry->execute();

			self::$sql = null;

			return $qry->fetchAll(PDO::FETCH_OBJ);

		} catch (\PDOException $e) {

			die("get: ".$e->getMessage());
		}
	}


	static function find($value=null, $column=null) {

		if($value){
			$v = isset($column) ? $column : $value;
			$c = isset($column) ? $value  : 'id';
			self::prefix();
			self::where($c,$v);
			
		}else{
			self::prefix();
		}
		
		try {

			$qry = self::$pdo->prepare(self::$sql);
			$qry->execute();

			$count = self::$pdo->query(self::$sql)->rowCount();

			if ($count==1) {
				self::$sql = null;
				return $qry->fetch(PDO::FETCH_OBJ);
			}else{
				self::$sql = null;
				return false;
			}

		} catch (\PDOException $e) {

			die("get: ".$e->getMessage());
		}
	}

	static function pluck($column) {

		self::prefix();

		self::$sql = str_replace('SELECT *', 'SELECT '.$column, self::$sql);

		try {

			$qry = self::$pdo->prepare(self::$sql);
			$qry->execute();

			$dados = $qry->fetchAll(PDO::FETCH_OBJ);

			$column = strpos($column, '.') ? explode(".", $column)[1] : $column;

			foreach ($dados as $dado) {

				$collection[] = $dado->{$column};
			}

			self::$sql = null;

			return ($qry->rowCount()>0) ? $collection : null;

		} catch (PDOException $e) {

			die("pluck: ".$e->getMessage());
		}
	}

	static function count() {

		self::prefix();

		try {

			$qry = self::$pdo->prepare(self::$sql);
			$qry->execute();

			self::$sql = null;

			return $qry->rowCount();

		} catch (PDOException $e) {

			die("count: ".$e->getMessage());
		}
	}

	static function getSql(){
		return self::$sql;
	}

	static function toSql() {

		self::prefix();
		echo self::$sql;
		self::$sql = null;
		exit;
	}

	static function group($column) {

		$prefix = preg_match("/GROUP BY/", self::$sql) ? "," : " GROUP BY ";

		self::$sql .= $prefix." $column";
		return new self;

	}

	static function having($column, $value, $operator=null) {

		$o = isset($operator) ? $value : '=';
		$v = isset($operator) ? $operator : $value;

		self::$sql .= " HAVING $column $o '$v' ";
		return new self;
	}

	static function order($column, $sort='ASC') {

		$prefix = preg_match("/ORDER BY/", self::$sql) ? "," : " ORDER BY ";

		self::$sql .= $prefix." $column $sort ";
		return new self;
	}

	static function orderBy($column=null, $sort='ASC') {

		return self::order($column, $sort);
	}

	static function orderAsc($column) {

		return self::order($column, 'ASC');
	}

	static function orderDesc($column) {

		return self::order($column, 'DESC');
	}

	static function orderField($column, $array, $sort='ASC') {

		$column = isset($column) ? $column : ' ordem ';
		$prefix = preg_match("/ORDER BY/", self::$sql) ? "," : " ORDER BY ";

		self::$sql .= $prefix." FIELD($column , '".implode('\',\'', $array)."') $sort ";

		return new self;
	}

	static function orderByField($column, $array, $sort='ASC') {

		return self::orderField($column, $array, $sort);
	}

	static function orderRaw($statement) {

		$prefix = preg_match("/ORDER BY/", self::$sql) ? "," : " ORDER BY ";
		self::$sql .= $prefix." ".$statement;
		return new self;
	}

	static function orderByRaw($statement) {

		return self::orderRaw($statement);
	}

	static function rand() {

		self::$sql .= " ORDER BY RAND() ";
		return new self;
	}

	static function random() { /*pgsql*/

		self::$sql .= " ORDER BY RANDOM() ";
		return new self;
	}

	static function first($column=null) {

		self::$sql .= (isset($column) ? " ORDER BY ".$column. " ASC " : "")." LIMIT 1 OFFSET 0";
		return self::get()[0];
	}

	static function last($column=null) {

		self::$sql .= " ORDER BY ".($column ?: "id"). " DESC LIMIT 1 OFFSET 0";
		return self::get()[0];
	}

	static function limit($limit, $offset = 0) {

		$o = $offset !== 0 ? $limit : 0;
		$l = $offset !== 0 ? $offset : $limit;

		self::$sql .= " LIMIT $l OFFSET $o ";

		//dd(self::$sql);
		return new self;
	}

	static function offset($start) {

		if (preg_match("/OFFSET 0/", self::$sql)) {

			self::$sql = str_replace("OFFSET 0", "", self::$sql);
		}

		$prefix = preg_match("/LIMIT/", self::$sql)
		? self::$sql .= " OFFSET $start "
		: self::$sql .= " LIMIT 18446744073709551615 OFFSET $start ";

		return new self;
	}

	static function paginate($page = 1, $limit = 20){

		//$sql = self::$sql;

		//$total = self::count();

		//$pages = ceil($total / $limit);

		$offset = ($page - 1) * $limit;

		//dd($limit);

		//self::$sql = $sql;

		self::limit($limit,$offset);

		return new self;
	}

	static function select($columns='*') {

		if ($columns!='*') $columns = is_array($columns) ? implode(',', $columns) : $columns;

		//self::prefix();

		if (self::$table) {
			self::$sql = 'SELECT '.$columns.' FROM '. self::$table . ' ';
		}else{
			self::$sql = 'SELECT '.$columns.' ';
		}

		return new self;
	}

	static function cont($column=null, $alias='total') {

		$column = isset($column) ? $column : 'id';

		self::prefix();
		self::$sql = str_replace('SELECT *', 'SELECT COUNT('.$column.') AS '.$alias.' ', self::$sql);

		return self::get()[0]->$alias;
	}

	static function max($column, $alias='max') {

		self::prefix();
		self::$sql = str_replace('SELECT *', 'SELECT MAX('.$column.') AS '.$alias.' ', self::$sql);

		return self::get()[0]->$alias;
	}

	static function min($column, $alias='min') {

		self::prefix();
		self::$sql = str_replace('SELECT *', 'SELECT MIN('.$column.') AS '.$alias.' ', self::$sql);

		return self::get()[0]->$alias;
	}

	static function avg($column, $alias='avg') {

		self::prefix();
		self::$sql = str_replace('SELECT *', 'SELECT AVG('.$column.') AS '.$alias.' ', self::$sql);

		return self::get()[0]->$alias;
	}

	static function sum($column, $alias='sum') {

		self::prefix();
		self::$sql = str_replace('SELECT *', 'SELECT SUM('.$column.') AS '.$alias.' ', self::$sql);

		return self::get()[0]->$alias;
	}

	static function join($table, $column, $column2, $operator=null) {

		$o  = isset($operator) ? $column2 : '=';
		$c2 = isset($operator) ? $operator : $column2;

		self::$sql .= " INNER JOIN $table ON $column $o $c2 ";
		return new self;
	}

	static function leftJoin($table, $column, $column2, $operator=null) {

		$o  = ($operator) ? $column2 : '=';
		$c2 = ($operator) ?: $column2;

		self::$sql .= " LEFT JOIN $table ON $column $o $c2 ";
		return new self;
	}

	static function rightJoin($table, $column, $column2, $operator=null) {

		$o  = ($operator) ? $column2 : '=';
		$c2 = ($operator) ?: $column2;

		self::$sql .= " RIGHT JOIN $table ON $column $o $c2 ";
		return new self;
	}

	static function all() {

		return self::get();
	}

	static function actives($column=null, $value=1) {

		$column = isset($column) ? $column : 'status';
		$value  = is_int($value) ? (int)$value : "'".$value."'";

		self::prefix();

		self::statement();

		self::$sql .= " $column = $value ";

		return new self;
	}

	static function statement( $operator = " AND " ) {

		self::$sql .= preg_match("/WHERE/", self::$sql) ? $operator : " WHERE ";

		return new self;
	}


	static function and($column, $value) {

		$o = isset($operator) ? $value : '=';
		$v = isset($operator) ? $operator : $value;

		self::$sql .= " AND $column $o '$v' ";

		return new self;
	}

	static function where($column, $value, $operator=null) {

		$o = isset($operator) ? $value : '=';
		$v = isset($operator) ? $operator : $value;

		self::statement();

		self::$sql .= " $column $o '$v' ";

		return new self;
	}

	static function orWhere($column, $value, $operator=null) {

		$o = isset($operator) ? $value : '=';
		$v = isset($operator) ? $operator : $value;

		self::statement("OR");

		self::$sql .= " $column $o '$v' ";

		return new self;
	}

	static function whereIn($column, $array=null) {

		$c = isset($array) ? $column : 'id';
		$a = isset($array) ? $array : $column;

		self::statement();

		self::$sql .= " $c IN ('".implode('\',\'', $a)."') ";

		return new self;
	}

	static function whereNotIn($column, $array=null) {

		$c = isset($array) ? $column : 'id';
		$a = isset($array) ? $array : $column;

		self::statement();

		self::$sql .= " $c NOT IN ('".implode('\',\'', $a)."') ";

		return new self;
	}

	static function isNull($column, $operator="AND") {

		self::statement( $operator );

		self::$sql .= " $column IS NULL ";

		return new self;
	}

	static function isNotNull($column, $operator="AND") {

		self::statement( $operator );

		self::$sql .= " $column IS NOT NULL ";

		return new self;
	}

	static function match() {

		$args = func_get_args();

		self::statement();

		self::$sql .= " MATCH(" . implode(', ', $args) . ") ";

		return new self;
	}

	static function against($search_terms) {

		self::$sql .= " AGAINST('$search_terms' IN BOOLEAN MODE)";

		return new self;
	}

	static function like($column, $value) {

		self::statement();

		self::$sql .= " $column LIKE '%$value%' ";

		return new self;
	}

	static function startLike($column, $value) {

		self::statement();

		self::$sql .= " $column LIKE '$value%' ";

		return new self;
	}

	static function endLike($column, $value) {

		self::statement();

		self::$sql .= " $column LIKE '%$value' ";

		return new self;
	}

	static function between($column, $start, $end) {

		self::statement();

		self::$sql .= " $column BETWEEN '$start' AND '$end' ";

		return new self;
	}

	static function queryRaw($statement) {

		self::$sql = $statement;
		return new self;
	}

	static function raw($statement) {

		self::$sql .= $statement;
		return new self;
	}

	static function insert($request) {

		$values  = is_array($request) ? $request : (array)$request;
		$columns = array_keys($values);

		$sql  = "INSERT INTO ".self::$table." (".implode(',', $columns).") VALUES (:".implode(',:', $columns).")";

		try {

			$qry = self::$pdo->prepare($sql);

			$commit = $qry->execute($values);

			if ($commit) {

				$response['id'] 	= (int)self::$pdo->lastInsertId();
				$response['result'] = true;
			}
			else {

				$response['result'] = false;
			}

			self::$sql = null;

			return (object)$response;

		} catch (PDOException $e) {

			die("ins: " . $e->getMessage());
		}
	}

	static function update($request, $column=null, $operator=null, $value=null) {

		$values = is_array($request) ? $request : (array)$request;
		$fields = array_keys($values);

		$params = '';

		self::prefix();

		if (preg_match("/WHERE/", self::$sql)) {

			for ($i=0; $i<count($fields); $i++) {

				$params .= $fields[$i].'=:'.$fields[$i].',';
			}

			$statement = "UPDATE ".self::$table." SET ".substr($params, 0, -1);

			$sql = preg_match("/SELECT */", self::$sql) ? str_replace("SELECT * FROM ".self::$table, $statement, self::$sql) : "";

		}
		elseif (isset($column) && isset($column)) {

			for ($i=0; $i<count($fields); $i++) {

				$params .= $fields[$i].'=:'.$fields[$i].',';
			}

			$sql = "UPDATE ".self::$table." SET ".substr($params, 0, -1)." WHERE ".$column." ".$operator." '".$value."'";
		}
		else {

			for ($i=1; $i<count($fields); $i++) {

				$params .= $fields[$i].'=:'.$fields[$i].',';
			}

			$sql = "UPDATE ".self::$table." SET ".substr($params, 0, -1)." WHERE ".$fields[0].' = :'.$fields[0];
		}

		try {

			$qry = self::$pdo->prepare($sql);

			$commit = $qry->execute($values);

			self::$sql = null;

			return $commit ? true : false;

		} catch (PDOException $e) {

			die("up: " . $e->getMessage());
		}
	}

	static function delete($value=null, $operator=null, $column=null) {

		if ($value) {

			$v = isset($column) ? $column : ($operator ?: $value);
			$o = isset($column) ? $operator : '=';
			$c = isset($column) || isset($operator) ? $value : 'id';

			$sql = "DELETE FROM ".self::$table." WHERE $c $o $v ";
		}
		else {

			self::prefix();
			$sql = preg_match("/SELECT */", self::$sql) ? str_replace("SELECT *", "DELETE", self::$sql) : "";
		}

		try {

			$qry = self::$pdo->prepare($sql);

			$commit = $qry->execute();

			self::$sql = null;

			return $commit ? true : false;

		} catch (PDOException $e) {

			die("del: " . $e->getMessage());
		}
	}

	static function truncate() {

		$sql = "TRUNCATE ".self::$table;

		try {

			$qry = self::$pdo->prepare($sql);

			$commit = $qry->execute();

			return $commit ? true : false;

		} catch (PDOException $e) {

			die("truncate: " . $e->getMessage());
		}
	}

}
