<?php   defined('IN_SYS') or exit('Request Error!');

/*
**************************
(C)2010-2014 upall.cn
update: 2014-5-31 21:57:47
person: Feng
**************************
*/


/*
 * 数据库类
 * 
 * 调用这个类前,请先设定这些外部变量
 * $GLOBALS['db_host'];
 * $GLOBALS['db_user'];
 * $GLOBALS['db_pwd'];
 * $GLOBALS['db_name'];
 * $GLOBALS['db_tablepre'];
 *
 * 在系统所有文件中不需要单独初始化本类
 * 可直接用 $dosql 或 $db 进行操作
 * 为了防止错误，操作完后不必关闭数据库
 */

//不限制响应时间
set_time_limit(0);

//初始化类
$dosql = new MySql();

class MySql
{
	public $db_host;
	public $db_user;
	public $db_pwd;
	public $db_name;
	public $db_tablepre;

	public $linkid;
	public $result;
	public $querystring;
	public $isclose;
	public $safecheck;


	//用外部定义的变量初始类，并连接数据库
	function __construct()
	{
		$this->isclose   = false;
		$this->safecheck = true;
		$this->Init();

	}


	//兼容PHP4
	function MySql()
	{
		$this->__construct();
	}


	//初始化变量
	function Init()
	{
		$this->db_host	  = $GLOBALS['db_host'];
		$this->db_user	  = $GLOBALS['db_user'];
		$this->db_pwd	   = $GLOBALS['db_pwd'];
		$this->db_name	  = $GLOBALS['db_name'];
		$this->db_tablepre  = $GLOBALS['db_tablepre'];
		$this->linkid	   = 0;
		$this->result['me'] = 0;
		$this->querystring  = '';
		$this->Open();
	}


	//打开数据库
	function Open()
	{
		global $dosql;

		//连接数据库
		if($dosql && !$dosql->isclose)
		{
			$this->linkid = $dosql->linkid;
		}
		else
		{
			list($dbhost, $dbport) = explode(':', $this->db_host);
			!$dbport && $dbport = 3306;

			$this->linkid = mysqli_init();
			mysqli_real_connect($this->linkid, $dbhost, $this->db_user, $this->db_pwd, false, $dbport);
			if(mysqli_connect_errno())
			{
				$this->DisplayError('连接数据库失败，可能数据库密码不对或数据库服务器出错！');
				exit();
			}
		}

		if($this->db_name && !mysqli_select_db($this->linkid, $this->db_name))
		{
			$this->DisplayError('无法使用数据库！');
			exit();
		}

		$db_info = mysqli_get_server_info($this->linkid);

		if($db_info > '4.1' && $GLOBALS['db_charset'])
		{
			mysqli_query($this->linkid, 'SET character_set_connection='.$GLOBALS['db_charset'].',character_set_results='.$GLOBALS['db_charset'].',character_set_client=binary');
		}

		if($db_info >= '5.0')
		{
			mysqli_query($this->linkid, "SET sql_mode=''");
		}

		return true;
	}


	//设置SQL语句
	function SetQuery($sql)
	{
		// 替换前缀
		$prefix = '#@__';
		$sql = str_replace($prefix, $this->db_tablepre, $sql);

		$this->querystring = $sql;
	}
	
	
	//执行一个带返回结果的SQL语句，如SELECT，SHOW等
	function Query($sql='',$id='me')
	{
		$this->Execute($sql,$id);
	}
	
	
	//执行一个不返回结果的SQL语句，如update,delete,insert等
	function QueryNone($sql='')
	{
		$this->ExecNoneQuery($sql);
	}


	//执行一个带返回结果的SQL语句，如SELECT，SHOW等
	function Execute($sql='',$id='me')
	{
		global $dosql;

		if($dosql->isclose)
		{
			$this->Open();
			$dosql->isclose = false;
		}

		if(!empty($sql))
		{
			$this->SetQuery($sql);
		}
		else
		{
			return false;
		}

		//SQL语句安全检查
		if($this->safecheck)
		{
			$this->CheckSql($this->querystring);
		}

		$this->result[$id] = mysqli_query($this->linkid, $this->querystring);


		//查询性能测试
		//$t1 = ExecTime();
		//$queryTime = ExecTime() - $t1;
		//if($queryTime > 0.05) {
		   // echo $this->querystring."--{$queryTime}<hr />\r\n"; 
		//}
		// var_dump($this->result[$id]);exit(__FILE__.':'.__LINE__);

		if($this->result[$id]===false)
		{
			$this->DisplayError(mysqli_error($this->linkid).'<br /><strong>ErrorSQL</strong>：<pre>'.$this->querystring.'</pre>');
		}
	}
	

	//执行一个不返回结果的SQL语句，如update,delete,insert等
	function ExecNoneQuery($sql='')
	{
	   global $dosql;

		if($dosql->isclose)
		{
			$this->Open();
			$dosql->isclose = false;
		}

		if(!empty($sql))
		{
			$this->SetQuery($sql);
		}
		else
		{
			return false;
		}

		//SQL语句安全检查
		if($this->safecheck)
		{
			$this->CheckSql($this->querystring,'update');
		}

		if(mysqli_query($this->linkid, $this->querystring))
		{
			return true;
		}
		else
		{
			$this->DisplayError(mysqli_error($this->linkid).'<br /><strong>ErrorSQL</strong>：<pre>'.$this->querystring.'</pre>');
			exit();
		}
	}


	//执行一个不与任何表名有关的SQL语句,Create等
	function ExecuteSafeQuery($sql,$id='me')
	{
		global $dosql;

		if($dosql->isclose)
		{
			$this->Open();
			$dosql->isclose = false;
		}

		$this->result[$id] = mysqli_query($this->linkid,$sql);
	}


	//执行一个SQL语句,返回前一条记录或仅返回一条记录
	function GetOne($sql='',$acctype=MYSQLI_ASSOC)
	{
		global $dosql;

		if($dosql->isclose)
		{
			$this->Open();
			$dosql->isclose = false;
		}

		if(!empty($sql))
		{
			if(!preg_match("/LIMIT/i", $sql)) $this->SetQuery(preg_replace("/[,;]$/i", '', trim($sql))." LIMIT 0,1;");
			else $this->SetQuery($sql);
		}
		else
		{
			return false;
		}

		$this->Execute($sql, 'one');
		$res = $this->GetArray('one', $acctype);
		if(!is_array($res))
		{
			return '';
		}
		else
		{
			$this->FreeResult('one');
			return($res);
		}
	}


	//返回当前的一条记录并把游标移向下一记录
	//MYSQLI_ASSOC、MYSQLI_NUM、MYSQLI_BOTH
	function GetArray($id='me',$acctype=MYSQLI_ASSOC)
	{
		// var_dump($this->result[$id]);exit();
		if($this->result[$id]===0) {
			return false;
		} else {
			return mysqli_fetch_array($this->result[$id], $acctype);
		}
	}


	//以对象的形式放回当前的一条记录并把游标移向下一记录
	function GetObject($id='me')
	{
		if($this->result[$id]===0)
		{
			return false;
		}
		else
		{
			return mysqli_fetch_object($this->result[$id]);
		}
	}


	//检测是否存在某数据表
	function IsTable($tbname)
	{
		$prefix = '#@__';
		$tbname = str_replace($prefix, $this->db_tablepre, $tbname);
		$tbname = '`'.$tbname.'`';

		$sql = "SHOW TABLES LIKE '".$tbname."'";
		$sql = str_replace('`', '', $sql);
		// exit($sql);

		if(mysqli_num_rows(mysqli_query($this->linkid, $sql)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}


	//获得MySql的版本号
	function GetVersion($isformat=true)
	{
		global $dosql;

		if($dosql->isclose)
		{
			$this->Open();
			$dosql->isclose = false;
		}

		$rs = mysqli_query($this->linkid, "SELECT VERSION();");
		$row = mysqli_fetch_array($rs);
		$mysql_version = $row[0];
		mysqli_free_result($rs);

		if($isformat)
		{
			$mysql_versions = explode(".",trim($mysql_version));
			$mysql_version = number_format($mysql_versions[0].".".$mysql_versions[1],2);
		}

		return $mysql_version;
	}


	//获取特定表的信息
	function GetTableFields($tbname, $id='me')
	{
		$prefix = '#@__';
		$tbname = str_replace($prefix, $this->db_tablepre, $tbname);
		$query  = "SELECT * FROM {$tbname} LIMIT 0,1";
		$this->result[$id] = mysqli_query($this->linkid, $query);
	}


	//获取字段详细信息
	function GetFieldObject($id='me')
	{
		return mysqli_fetch_field($this->result[$id]);
	}


	//获得查询的总记录数
	function GetTotalRow($id='me')
	{
		if($this->result[$id]===0)
		{
			return -1;
		}
		else
		{
			return mysqli_num_rows($this->result[$id]);
		}
	}


	//获得指定表数据总记录数
	function GetTableRow($tbname='',$siteid='',$field='id')
	{
		if($tbname == '') return false;

		//是否区分站点
		if($siteid == '')
			$sql = "SELECT `$field` FROM `$tbname`";
		else
			$sql = "SELECT `$field` FROM `$tbname` WHERE siteid='$siteid'";

		$this->Execute($sql);
		return $this->GetTotalRow();
	}


	//获取上一步INSERT操作产生的ID
	function GetLastID()
	{
		//如果 AUTO_INCREMENT 的列的类型是 BIGINT，则 mysqli_insert_id() 返回的值将不正确。
		//可以在 SQL 查询中用 MySQL 内部的 SQL 函数 LAST_INSERT_ID() 来替代。
		//$rs = mysqli_query($this->linkid, "Select LAST_INSERT_ID() as lid");
		//$row = mysqli_fetch_array($rs);
		//return $row["lid"];
		return mysqli_insert_id($this->linkid);
	}


	//释放记录集占用的资源
	function FreeResult($id='me')
	{
		mysqli_free_result($this->result[$id]);
	}


	//释放全部记录集占用的资源
	function FreeResultAll()
	{
		if(!is_array($this->result))
		{
			return '';
		}
		foreach($this->result as $k=>$v)
		{
			if($v)
			{
				mysqli_free_result($v);
			}
		}
	}


	//关闭数据库
	//mysql能自动管理非持久连接的连接池
	//实际上关闭并无意义并且容易出错，所以取消这函数
	function Close($isok=false)
	{
		$this->FreeResultAll();

		if($isok)
		{
			mysqli_close($this->linkid);
			$this->isclose = true;
			$GLOBALS['dosql'] = NULL;
		}
	}


	//关闭指定的数据库连接
	function CloseLink($dblink)
	{
		mysqli_close($dblink);
	}


	/*
	 * 显示数据链接错误信息
	 *
	 * @param  string  $msg  错误信息
	 * @param  int	 $t	错误类型
	 */
	function DisplayError($msg,$t=0)
	{
		// 向浏览器输出错误
		switch($t)
		{
			case 0:
			$title = '数据库错误！';
			break;
			case 1:
			$title = '请检查您的SQL语句是否合法，您的操作将被强制停止！';
			break;
			default;
		}

		$str  = '<meta charset="UTF-8"><div style="font-family:\'微软雅黑\';font-size:12px;">';
		$str .= '<h3 style="margin:0;padding:0;line-height:30px;color:red;">'.$title.'</h3>';
		$str .= '<strong>错误信息</strong>：'.$msg.'';
		$str .= '</div>';

		// 输出错误提示
		echo $str;

		// 危险错误，强制停止
		if($t == 1) 
			exit();
	}


	//SQL语句过滤程序，由80sec提供，这里作了适当的修改
	function CheckSql($sql, $querytype='select')
	{
		$clean   = '';
		$error   = '';
		$pos	 = -1;
		$old_pos = 0;


		//如果是普通查询语句，直接过滤一些特殊语法
		if($querytype == 'select')
		{
			if(preg_match('/[^0-9a-z@\._-]{1,}(union|sleep|benchmark|load_file|outfile)[^0-9a-z@\.-]{1,}/', $sql))
			{
				$this->DisplayError("$sql||SelectBreak",1);
			}
		}

		//完整的SQL检查
		while(true)
		{
			$pos = strpos($sql, '\'', $pos + 1);
			if($pos === false)
			{
				break;
			}
			$clean .= substr($sql, $old_pos, $pos - $old_pos);

			while(true)
			{
				$pos1 = strpos($sql, '\'', $pos + 1);
				$pos2 = strpos($sql, '\\', $pos + 1);
				if($pos1 === false)
				{
					break;
				}
				else if($pos2 == false || $pos2 > $pos1)
				{
					$pos = $pos1;
					break;
				}
				$pos = $pos2 + 1;
			}

			$clean .= '$s$';
			$old_pos = $pos + 1;
		}

		$clean .= substr($sql, $old_pos);
		$clean  = trim(strtolower(preg_replace(array('~\s+~s' ), array(' '), $clean)));

		//老版本的Mysql并不支持union，常用的程序里也不使用union，但是一些黑客使用它，所以检查它
		if(strpos($clean, 'union') !== false && preg_match('~(^|[^a-z])union($|[^[a-z])~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'union detect';
		}

		//发布版本的程序可能比较少包括--,#这样的注释，但是黑客经常使用它们
		else if(strpos($clean, '/*') > 2 || strpos($clean, '--') !== false || strpos($clean, '#') !== false)
		{
			$fail  = true;
			$error = 'comment detect';
		}

		//这些函数不会被使用，但是黑客会用它来操作文件，down掉数据库
		else if(strpos($clean, 'sleep') !== false && preg_match('~(^|[^a-z])sleep($|[^[a-z])~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'slown down detect';
		}
		else if(strpos($clean, 'benchmark') !== false && preg_match('~(^|[^a-z])benchmark($|[^[a-z])~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'slown down detect';
		}
		else if(strpos($clean, 'load_file') !== false && preg_match('~(^|[^a-z])load_file($|[^[a-z])~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'file fun detect';
		}
		else if(strpos($clean, 'into outfile') !== false && preg_match('~(^|[^a-z])into\s+outfile($|[^[a-z])~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'file fun detect';
		}

		//老版本的MYSQL不支持子查询，我们的程序里可能也用得少，但是黑客可以使用它来查询数据库敏感信息
		else if(preg_match('~\([^)]*?select~s', $clean) != 0)
		{
			$fail  = true;
			$error = 'sub select detect';
		}

		if(!empty($fail))
		{
			$this->DisplayError("$sql,$error",1);
		}
		else
		{
			return $sql;
		}
	}
	
	// 自动事务开关
	function autocommit($bool){
		if ($bool){
			$this->ExecuteSafeQuery("SET AUTOCOMMIT=1");
		}else{
			$this->ExecuteSafeQuery("SET AUTOCOMMIT=0");
		}
	}

	// 开始事务
	function begin(){
		$this->autocommit(0);
		$this->ExecuteSafeQuery('START TRANSACTION');
	}

	// 回滚事务
	function rollback(){
		$this->ExecuteSafeQuery('ROLLBACK');
		$this->autocommit(1);
		// echo "rollback\r\n";
	}

	// 提交事务
	function commit(){
		$this->ExecuteSafeQuery('COMMIT');
		$this->autocommit(1);
	}
	// 错误信息
	function error(){
		if(!empty($this->error)) return $this->error;
		return mysqli_error($this->linkid);
	}
	// 错误号
	function errno(){
		return mysqli_errno($this->linkid);
	}

}
?>