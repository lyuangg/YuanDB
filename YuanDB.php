<?php
/*
$db_config = [
	"default" => [
					"host" => "127.0.0.1",
					"db" => "test",
					"user" => "root",
					"password" => "123456"
				 ],
	"test" => [
					"host" => "127.0.0.1",
					"db" => "test",
					"user" => "root",
					"password" => "123456"
				 ],
];
$info = YuanDB::conn()->table('test_table')->where('id',1)->select('id,name')->first();
$list = YuanDB::conn('test')->table('test_table')
							->where('id',1)
							->where('id','!=',5)
							->where('id',[1,2,3])
							->orWhere('id',2)
							->orderBy('id','desc')
							->limit(10)
							->get();
$list = YuanDB::conn()->query("select * from t where id=?",[1]);
$count = YuanDB::conn()->table('test_table')->count();
$rowCount = YuanDB::conn()->table('test_table')->where('id',1)->update(['name'=>'123']);
$rowCount = YuanDB::conn()->table('test_table')->where('id',1)->delete();
$rowCount = YuanDB::conn()->table('test_table')->delete(12);
$insertId = YuanDB::conn()->table('test_table')->insert(['name'=>'abc','age'=>15]);
echo YuanDB::conn()->getFullSql();
*/
class YuanDB {
	private static $instances = [];
	private $db, $table, $columns, $sql, $bindValues, $updateBindValues, $rowCount=0, $limit, $orderBy, $lastInsertId = 0, $conditions = [];
	private function __construct($config) {
		try {
			$port = isset($config['port']) ? trim($config['port']) : '3306';
			$this->db = new \PDO("mysql:host=".$config['host'].";dbname=".$config['db'].";charset=utf8;port=$port", $config['user'], $config['password'] );
			$this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
		} catch (Exception $e) {
			die("connection db error: ".$e->getMessage());
		}
	}
	public static function conn($db = null) {
		if(empty($db) || is_array($db)) {
			$connName = 'default';
		} else {
			$connName = $db;
		}
		if(is_array($db)) {
			$db_config = $db;
		} else {
			global $db_config;
		}
		$config = isset($db_config[$connName]) ? $db_config[$connName] : $db_config;
		if (!isset(static::$instances[$connName])) {
			static::$instances[$connName] = new YuanDB($config);
		}
		return static::$instances[$connName];
	}
	public function query($query='', $args = []) {
		$query = trim($query);
		if($query) {
			$this->resetQuery();
			$this->sql = $query;
			$this->bindValues = $args;
		} else {
			$this->bindValues = $args ? $args : $this->bindValues;
		}
		if (strpos( strtoupper($this->sql), "SELECT" ) === 0 ) {
			$stmt = $this->db->prepare($this->sql);
			$stmt->execute($this->bindValues);
			$this->rowCount = $stmt->rowCount();
			return $stmt->fetchAll();
		}else{
			$stmt = $this->db->prepare($this->sql);
			$stmt->execute($this->bindValues);
			$this->rowCount = $stmt->rowCount();
			return $this->rowCount;
		}
	}
	public function table($table_name) {
		$this->table = $table_name;
		$this->resetQuery();
		return $this;
	}
	public function resetQuery() {
		$this->limit = '';
		$this->orderBy = '';
		$this->lastInsertId = 0;
		$this->bindValues = [];
		$this->updateBindValues = [];
		$this->columns = '';
		$this->conditions = [];
	}
	public function insert($fields = []) {
        $table_name = $this->table;
		if(isset($fields[0]) && is_array($fields[0])) {
			$keys = implode('`, `', array_keys($fields[0]));
			$values = '';
			foreach($fields as $row) {
				asort($row);
				$values .= '('.rtrim(str_repeat('?,',count($row)),',').'),';
				$this->bindValues = array_merge($this->bindValues,array_values($row));
			}
			$values = trim($values,',');
			$this->sql = "INSERT INTO `{$table_name}` (`{$keys}`) VALUES {$values}";
			$this->query();
			return $this->rowCount();
		} else {
			$keys = implode('`, `', array_keys($fields));
			$values = rtrim(str_repeat('?,',count($fields)),',');
			$this->bindValues = array_values($fields);
			$this->sql = "INSERT INTO `{$table_name}` (`{$keys}`) VALUES ({$values})";
			$this->query();
			$this->lastInsertId = $this->db->lastInsertId();
			return $this->lastInsertId;
		}
	}
	public function delete($id=null) {
		$table_name = $this->table;
		$this->sql = "DELETE FROM `{$table_name}`";
		if (isset($id)) {
			$this->sql .= $this->buildIdWhere($id);
		} else {
			if($this->conditions) {
				$this->sql .= ' WHERE 1 '.$this->buildConditions($this->conditions);
			}
		}
		if(!empty($this->limit)) {
			$this->sql .= $this->limit;
		}
		return $this->query();
	}
	public function buildIdWhere($id) {
		$where = '';
		if (is_numeric($id)) {
			$where = " WHERE `id` = ? ";
			$this->bindValues[] = $id;
		} else if (is_array($id)) {
			$keys = array_keys($id);
			if(isset($keys[0]) && is_numeric($keys[0])) {
				$where = " WHERE `id` IN (".rtrim(str_repeat('?,',count($keys)),',').')';
				$this->bindValues = array_values($id);
			}
		} else {
			$where = ' WHERE '. $id;
		}
		return $where;
	}
	public function buildConditions($conditions) {
		$where = '';
		if($conditions) {
			if(is_array($conditions)) {
				foreach($conditions as $condition) {
					$logic = $condition[0];
					if(count($condition) == 2) {
						if (is_string($condition[1])) {
							$where .= " $logic ".$condition[1];
						}
					} else if(count($condition) == 3) {
						$conn = '=';
						if(is_array($condition[2])) {
							$where .= " $logic ".$condition[1]." $conn (".rtrim(str_repeat('?,',count($condition[2])),',').')';
							$this->bindValues = array_merge($this->bindValues,$condition[2]);
						} else {
							$where .= " $logic ".$condition[1]." $conn ?";
							$this->bindValues[] = $condition[2];
						}
					} else if(count($condition) == 4) {
						$conn = $condition[2];
						if(is_array($condition[3])) {
							$where .= " $logic ".$condition[1]." $conn (".rtrim(str_repeat('?,',count($condition[3])),',').')';
							$this->bindValues = array_merge($this->bindValues,$condition[3]);
						} else {
							$where .= " $logic ".$condition[1]." $conn ?";
							$this->bindValues[] = $condition[3];
						}
					}
				}
			} else {
				$where = $conditions;
			}
		}
		return $where;
	}
	public function update($fields = [], $id=null) {
		$table_name = $this->table;
        $set = '';
		foreach ($fields as $column => $field) {
			$set .= "`$column` = ?, ";
			$this->updateBindValues[] = $field;
		}
		$set = rtrim(trim($set),',');
		$this->sql = "UPDATE `{$table_name}` SET $set";
		if (isset($id)) {
			$this->sql .= $this->buildIdWhere($id);
		} else {
			if($this->conditions) {
				$this->sql .= ' WHERE 1 '.$this->buildConditions($this->conditions);
			}
		}
		if(!empty($this->limit)) {
			$this->sql .= $this->limit;
		}
		$this->bindValues = array_merge($this->updateBindValues, $this->bindValues);
		return $this->query();
	}
	public function lastId() {
		return $this->lastInsertId;
	}
	public function select($columns) {
		$columns = explode(',', $columns);
        $columns = array_map(function($val) { return trim($val);}, $columns);
		$columns = implode('`, `', $columns);
		$this->columns = "`{$columns}`";
		return $this;
	}
	public function where() {
		$args = func_get_args();
		$logic = 'AND';
		return $this->insertWhere($logic, $args);
	}
	public function orWhere() {
		$args = func_get_args();
		$logic = 'OR';
		return $this->insertWhere($logic, $args);
	}
	public function insertWhere($logic, $args) {
		$num_args = count($args);
		if($num_args == 1) {
			$this->conditions[] = [$logic, $args[0]];
		} else if ($num_args == 2) {
			if(is_array($args[1])) {
				$conn = 'IN';
			} else {
				$conn = '=';
			}
			$this->conditions[] = [$logic, $args[0], $conn, $args[1]];
		} else if ($num_args == 3) {
			$this->conditions[] = [$logic, $args[0], $args[1], $args[2]];
		}
		return $this;
	}
	public function get($num=0) {
		$this->sql = "SELECT ";
		if($num > 0) {
			$this->limit($num);
		}
		if(!$this->columns) {
			$this->columns = '*';
		}
		$this->sql .= $this->columns. ' FROM ';
		$this->sql .= $this->table;
		if($this->conditions) {
			$this->sql .= ' WHERE 1 '.$this->buildConditions($this->conditions);
		}
		if($this->orderBy) {
			$this->sql .= $this->orderBy;
		}
		if($this->limit) {
			$this->sql .= $this->limit;
		}
		return $this->query();
	}
	public function first() {
		$res = $this->get(1);
		return isset($res[0]) ? $res[0] : null;
	}
    public function lists($name) {
        $res = [];
        $list = $this->select($name)->get();
        if($list) {
            foreach($list as $row) {
                if(isset($row->$name)) {
                    $res[] = $row->$name;
                }
            }
        }
        return $res;
    }
	public function limit($limit, $offset=null) {
		if ($offset ==null ) {
			$this->limit = " LIMIT {$limit}";
		}else{
			$this->limit = " LIMIT {$offset}, {$limit}";
		}
		return $this;
	}
    public function take($size) {
        return $this->limit($size);
    }
	public function orderBy($field_name, $order = 'ASC') {
		$field_name = trim($field_name);
		$order =  trim(strtoupper($order));
		if ($field_name !== null && ($order == 'ASC' || $order == 'DESC')) {
			if ($this->orderBy == null ) {
				$this->orderBy = " ORDER BY $field_name $order";
			}else{
				$this->orderBy .= ", $field_name $order";
			}
		}
		return $this;
	}
	public function count() {
		$this->sql = "SELECT COUNT(*) as num FROM `$this->table`";
		if($this->conditions) {
			$this->sql .= ' WHERE 1 '.$this->buildConditions($this->conditions);
		}
		if($this->limit) {
			$this->sql .= $this->limit;
		}
		$arr = $this->query();
		return isset($arr[0]) ? $arr[0]->num : 0;
	}
	public function sql() {
		return $this->sql;
	}
	public function getFullSql() {
		$fullSql = "";
		if($this->sql) {
			$replaceCount = 0;
			$fullSql = preg_replace_callback('/\?/',function($m) use (&$replaceCount) {
				$replace = isset($this->bindValues[$replaceCount]) ? "'".$this->bindValues[$replaceCount]."'" : '';
				$replaceCount ++;
				return $replace;
			},$this->sql);
		}
		return $fullSql;
	}
	public function rowCount() {
		return $this->rowCount;
	}
}
