# YuanDB

YuanDB 是我自己使用的一个mysql数据库查询的类。因为经常需要在一些脚本中使用，所以设计的目标就是代码精简，易用，无依赖。

### 特点

- 代码精简。
- 易用。
- 无依赖。

### 用法

- 配置数据库

可以配置多个数据库连接，默认使用default。

```php
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
```

- 引入 YuanDB 或者直接复制代码

```php
include 'YuanDB.php'
```



- 建立连接

```php
YuanDB::conn();   //默认default连接
YuanDB::conn('test'); //使用test 连接
YuanDB::conn(['host'=>'127.0.0.1','...']); //直接传一个连接配置
```

- 查询

```php
$info = YuanDB::conn()->table('test_table')->where('id',1)->select('id,name')->first();
$list = YuanDB::conn('test')->table('test_table')
							->where('id',1)
    						->where('id=3')   //原生where 条件
							->where('id','!=',5)
							->where('id',[1,2,3]) // in 查询条件
							->orWhere('id',2) // or 查询
							->orderBy('id','desc')
							->limit(10)
							->get();
$list = YuanDB::conn()->query("select * from t where id=?",[1]); //原生sql
$count = YuanDB::conn()->table('test_table')->count(); //查询数量
```

- 更新

```php
$rowCount = YuanDB::conn()->table('test_table')->where('id',1)->update(['name'=>'123']);
$rowCount = YuanDB::conn()->table('test_table')->update(['name'=>'123'],1);
```

- 删除

```php
$rowCount = YuanDB::conn()->table('test_table')->where('id',1)->delete(); 
$rowCount = YuanDB::conn()->table('test_table')->delete(12);
```

- 插入

```php
$insertId = YuanDB::conn()->table('test_table')->insert(['name'=>'abc','age'=>15]);
```

- 获取上次执行的sql

```php
echo YuanDB::conn()->getFullSql();
```

