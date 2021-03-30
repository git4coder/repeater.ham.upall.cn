<?php
define('SYS_INC', preg_replace("/[\/\\\\]{1,}/", '/', dirname(__FILE__)));
define('SYS_ROOT', preg_replace("/[\/\\\\]{1,}/", '/', substr(SYS_INC, 0, -8)));
define('SYS_DATA', SYS_ROOT.'/data');
define('SYS_UPLOAD', SYS_ROOT.'/file');
define('IN_SYS', TRUE);

if(isset($_SERVER['HTTP_HOST']) && isset($_SERVER['SERVER_ADDR']) && $_SERVER['HTTP_HOST'] == $_SERVER['SERVER_ADDR']){
	//header('HTTP/1.1 500 Internal Server Error');
	exit;
}

if(!isset($_SERVER['HTTPS'])){
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'upall.cn');
	$request = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
	header("HTTP/1.1 301 Moved Permanently");
	header('location: https://'.$host.$request);
}

$time  = time();
require_once(SYS_INC.'/mysqli.class.php');

//检查外部传递的值并转义
function _RunMagicQuotes(&$svar) {
	//PHP5.4已经将此函数移除
    if(function_exists('get_magic_quotes_gpc') && !get_magic_quotes_gpc()) {
        if(is_array($svar)) {
            foreach($svar as $_k => $_v) $svar[$_k] = _RunMagicQuotes($_v);
        } else {
            if(strlen($svar)>0 && preg_match('#^(cfg_|GLOBALS|_GET|_POST|_SESSION|_COOKIE)#',$svar)) {
					exit('不允许请求的变量值!');
            }
            $svar = trim($svar);
            $svar = addslashes($svar);
        }
    }
    return $svar;
}


//直接应用变量名称替代
foreach(array('_GET','_POST') as $_request) {
	foreach($$_request as $_k => $_v) {
		if(strlen($_k)>0 && preg_match('#^(GLOBALS|_GET|_POST|_SESSION|_COOKIE)#',$_k)) {
			exit('不允许请求的变量名!');
		}
		${$_k} = _RunMagicQuotes($_v);
	}
}

$HTTP_RAW_POST_DATA = '';
if(isset($GLOBALS["HTTP_RAW_POST_DATA"]) && !empty($GLOBALS["HTTP_RAW_POST_DATA"])){
	$HTTP_RAW_POST_DATA = $GLOBALS["HTTP_RAW_POST_DATA"];
}else{
	$HTTP_RAW_POST_DATA = @file_get_contents('php://input', 'r');
}
if (!empty($HTTP_RAW_POST_DATA)){
	$HTTP_RAW_POST_DATA = json_decode($HTTP_RAW_POST_DATA);
	if(is_object($HTTP_RAW_POST_DATA) || is_array($HTTP_RAW_POST_DATA)){
		foreach ($HTTP_RAW_POST_DATA as $_k => $_v) {
			${$_k} = _RunMagicQuotes($_v);
		}
	}
}
unset($HTTP_RAW_POST_DATA);

# End
