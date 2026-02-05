<?php
/**
 *  tools.php 111 2021-01-08
 */

define('TPASSWORD', MD5('000000')); // 默认密码

/*************************************以下部分为tools工具箱的核心代码，请不要随意修改 Tuesday **************************************/
define('PHPS_CHARSET', 'UTF-8');
error_reporting(0);
date_default_timezone_set('PRC');
define('TMAGIC_QUOTES_GPC', function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc());
define('TOOLS_ROOT', rtrim(dirname(__FILE__),'/\\').DIRECTORY_SEPARATOR);


$reg[1] = '';
define('DISCUZ_VERSION', $reg[1]);
define('DISCUZ_DOWN_VERSION', str_ireplace('x','',DISCUZ_VERSION));
define('TOOLS_DISCUZ_VERSION', '智简魔方财务系统 '.DISCUZ_VERSION);
define('TOOLS_VERSION', 'Tools '.DISCUZ_VERSION);

$tools_versions = TOOLS_VERSION;
$tools_discuz_version = TOOLS_DISCUZ_VERSION;

if(!TMAGIC_QUOTES_GPC) {
    $_GET = taddslashes($_GET);
    $_POST = taddslashes($_POST);
    $_COOKIE = taddslashes($_COOKIE);
}

if (isset($_GET['GLOBALS']) || isset($_POST['GLOBALS']) ||  isset($_COOKIE['GLOBALS']) || isset($_FILES['GLOBALS'])) {
    show_msg('您当前的访问请求当中含有非法字符，已经被系统拒绝');
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST)) {
    $_GET = array_merge($_GET, $_POST);
}

$actionarray = array('index', 'setadmin', 'closesite', 'closeplugin', 'repairdb', 'reinstall' , 'restoredb', 'updatecache', 'login', 'logout','editpass','serverinfo','happy');
$_GET['action'] = htmlspecialchars($_GET['action']);
$action = in_array($_GET['action'], $actionarray) ? $_GET['action'] : 'index';

$database = include "../app/config/database.php";
$config = $database;
!$config['charset'] && $config['charset'] = PHPS_CHARSET;
define('PHP_CHARSET','utf-8');
define('DBNAME', $config['database']);
header('Content-type: text/html; charset='.PHP_CHARSET);
if(!is_login()) {
    login_page();
    exit;
}
define('DB_PRE',$config['prefix']);

if($action == 'index') {
    // TODO: 找回管理员
    $mysqli = new \mysqli($config['hostname'],$config['username'],$config['password'],$config['database']);
/*     $sql = "SELECT id,user_login FROM " . DB_PRE . "user where user_login= ? ";
    $pre_sql = $mysqli->prepare($sql);
    $user_login = 'admin';
    $pre_sql->bind_param('s',$user_login);
    $pre_sql->bind_result($result1,$result2);
    $pre_sql->execute();
    $res=$pre_sql->fetch();
    if($pre_sql->fetch()){ //没有内容
        $foundernames=$result2;
    } */

    $sql = "SELECT id,user_login FROM " . DB_PRE . "user order by id asc";
    $result = $mysqli->query($sql);
    $adminnames=$result->fetch_all(MYSQLI_ASSOC);

    if (!empty($_POST['setadminsubmit'])) {
        if ($_GET['username'] == NULL) {
            show_msg('请输入用户名', 'tools.php?action=' . $action, 2000);
        }
        if ($_GET['pass'] == NULL) {
            show_msg('请输入密码', 'tools.php?action=' . $action, 2000);
        }
        $_GET['username'] = strim($_GET['username']);
        $_GET['pass']= strim($_GET['pass']);
       
        $sql = "SELECT id FROM " . DB_PRE . "user WHERE `user_login`= ? ";
        $pre_sql = $mysqli->prepare($sql);
        $user_login =  $_GET['username'];
        $pre_sql->bind_param('s',$user_login);
        $pre_sql->bind_result($id);
        $pre_sql->execute();
        if(!$pre_sql->fetch()){ //没有内容
            show_msg('没有此用户', 'tools.php?action=' . $action, 2000);
        }
        //关闭预编译语句
        $pre_sql->close();
        $sql = "update " . DB_PRE . "user set `user_pass`='".cmf_password($_GET['pass'],'',$database)."'  where user_login='". $_GET['username']."'";

        $result = $mysqli->query($sql);       

        if($result){
			$mysqli->query("DELETE FROM shd_blacklist where `username`='{$_GET['username']}'");
            echo "数据更新成功!</br>";
            $result=$mysqli->query($sql);
            foreach($result as $row){
                echo $row["student_id"].'&nbsp;&nbsp';
                echo $row["student_no"].'&nbsp;&nbsp';
                echo $row["student_name"]."</br>";
            }
        }
        else{
            echo "数据更新失败!</br>".mysqli_error($mysqli)."</br>";
        }
        //关闭连接
        $mysqli->close();


        show_msg('管理员找回成功！', 'tools.php?action=' . $action, 2000);

    } else {
		
        show_header();
        echo "<p>当前超级管理员：$result2</p>";                            
       
		echo'<form action="?action='.$action.'" method="post">';
		echo'<h5>更改管理员密码</h5>';
		echo'<table id="setadmin">';
		    echo'<tr><th width="30%">用户名</th><td width="70%"><select name="username">';
		     foreach($adminnames as $v){
                echo'<option value="'.$v['user_login'].'">'.$v['user_login'].'</option>';
            }
		    echo'</select></td></tr>';
	
			echo'<tr><th width="30%">请输入密码</th><td width="70%"><input class="textinput" type="text" name="pass" size="25"></td></tr>';
		echo'</table>';
			echo'<input type="submit" name="setadminsubmit" value="提 &nbsp; 交">';
		echo'</form>';

        print<<<END
		<br/>
		恢复步骤: 
		重置管理员密码<br/>
		<ul>
		<li>选择管理员账号</li>
		<li>输入重置后的密码</li>
		<li>点击提交</li>

		</ul>
		<br/>
	
END;
        show_footer();
    }


}elseif($action == 'logout') {

    setcookie('toolsauth', '');
    @header('Location:'.basename(__FILE__));
    die;
    // TODO: 退出系统.
    tsetcookie('toolsauth', '', -1);
    @header('Location:'.basename(__FILE__));
    exit();
}
//大的分支 结束

/**********************************************************************************
 *
 *	tools.php 通用函数部分
 *
 *
 **********************************************************************************/
function cmf_password($pw, $authCode = '',$database)
{
    if (empty($authCode)) {
        $authCode =$database['authcode'];
    }
    $result = "###" . md5(md5($authCode . $pw));
    return $result;
}


function strim($str)
{
    return quotes(htmlspecialchars(trim($str)));
}
//防sql注入
function quotes($content)
{
 //if $content is an array
 if (is_array($content))
 {
  foreach ($content as $key=>$value)
  {
   $content[$key] = addslashes($value);
  }
 }else {
 
  $content=addslashes($content);
 }
 return $content;
}
 
/*
	checkpassword 函数
	判断密码强度，大小写字母加数字，长度大于6位。
	return flase 或者 errormsg
 */
function checkpassword($password){
    return false;
}

//去掉slassh
function tstripslashes($string) {
    if(empty($string)) return $string;
    if(is_array($string)) {
        foreach($string as $key => $val) {
            $string[$key] = tstripslashes($val);
        }
    } else {
        $string = stripslashes($string);
    }
    return $string;
}

function thash() {
    return substr(md5(substr(time(), 0, -4).TOOLS_ROOT), 16);
}

function taddslashes($string, $force = 1) {
    if(is_array($string)) {
        foreach($string as $key => $val) {
            $string[$key] = taddslashes($val, $force);
        }
    } else {
        $string = addslashes($string);
    }
    return $string;
}

//显示
function show_msg($message, $url_forward='', $time = 2000, $noexit = 0) {
    show_header();
    !$url_forward && $url_forward = $_SERVER["HTTP_REFERER"];
    show_msg_body($message, $url_forward, $time, $noexit);
    show_footer();
    !$noexit && exit();
}

function show_msg_body($message, $url_forward='', $time = 1, $noexit = 0) {
    if($url_forward) {
        $url_forward = $_GET['from'] ? $url_forward.'&from='.rawurlencode($_GET['from']) : $url_forward;
        $message = "<a href=\"$url_forward\">$message (跳转中...)</a><script>setTimeout(\"window.location.href ='$url_forward';\", $time);</script>";
    }else{
        $message = "<a href=\"$url_forward\">$message </a>";
    }
    print<<<END
	<table>
	<tr><td>$message</td></tr>
	</table>
END;
}

function login_page() {

    show_header();
    $formhash = thash();
    $charset = PHPS_CHARSET;
    print<<<END
		<span>登录</span>
		<form action="tools.php?action=login" method="post">
			<table class="specialtable">
			<tr>
				<td width="20%"><input class="textinput" type="password" name="toolpassword"></input></td>
				<td><input class="specialsubmit" type="submit" value=" 登 录 "></input>
                </td>
			</tr>
            <tr>
           
            <td colspan="2" style="color: #FF8040;"></td>
            <tr>
			</table>
			<input type="hidden" name="action" value="login">
			<input type="hidden" name="formhash" value="{$formhash}">
		</form>
END;
    show_footer();
}

function show_header() {
    // TODO: 头部导航开始
    $_GET['action'] = htmlspecialchars($_GET['action']);
    $nowarr = array($_GET['action'] => ' class="current"');
    $charset = PHP_CHARSET;
	$logout='';
	if(is_login()){
		$logout='<table id="menu">
	<tr>

	<td'.$nowarr[logout].' style="text-align: right;"><a href="?action=logout">退出</a></td>
	</tr>
	</table>';
	}
	
    print<<<END
	<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset={$charset}" />
	<title>设置管理员密码</title>
	<style type="text/css">
     input{vertical-align: middle;}
    a:visited,a:link{color: #575757;}
	* {font-size:12px; color: #575757; font-family: Verdana, Arial, Helvetica, sans-serif; line-height: 1.5em; word-break: break-all; }
	body { text-align:center; margin: 0; padding: 0; background: #F5FBFF; }
	.bodydiv { margin: 40px auto 0; width:1000px; text-align:left; border: solid #86B9D6; border-width: 5px 1px 1px; background: #FFF; }
	h1 { font-size: 18px; margin: 1px 0 0; line-height: 50px; height: 50px; background: #E8F7FC; color: #5086A5; padding-left: 10px; }
	#menu {width: 100%; margin: 10px auto; text-align: center; }
	#menu td { height: 30px; line-height: 30px; color: #999; border-bottom: 3px solid #EEE; }
	.current { font-weight: bold; color: #090 !important; border-bottom-color: #F90 !important; }
	input { border: 1px solid #B2C9D3; padding: 5px; background: #F5FCFF; }
	#footer { font-size: 10px; line-height: 40px; background: #E8F7FC; text-align: center; height: 38px; overflow: hidden; color: #5086A5; margin-top: 20px; }
	table {width:100%;font-size:12px;margin-top:5px;}
		table.specialtable,table.specialtable td {border:0;}
		td,th {padding:5px;text-align:left;}
		caption {font-weight:bold;padding:8px 0;color:#3544FF;text-align:left;}
		th {background:#E8F7FC;font-weight:600;}
		td.specialtd {text-align:left;}
	#setadmin {margin: 0px;}
	.textarea {height: 80px;width: 400px;padding: 3px;margin: 5px;}
	</style>
	</head>
	<body>
	<div class="bodydiv">
	<h1>重置密码</h1><br/>
	<div style="width:90%;margin:0 auto;">
	{$logout}
	<br>
END;
}

//页面顶部
function show_footer() {
    global $tools_versions;
    print<<<END
	</div>
	<div id="footer"></div>
	</div>
	<br>
	</body>
	</html>
END;
}

//登录判断函数
function is_login() {
    $error = false;
    $errormsg = array();
    $tpassword = TPASSWORD;

    if(isset($_COOKIE['toolsauth'])) {
        if($_COOKIE['toolsauth'] === md5($tpassword.thash())) {
            return TRUE;
        }
    }

    if ($_GET['action'] === 'login') {
        $formhash = $_GET['formhash'];
        $_GET['toolpassword'] = md5($_GET['toolpassword']);
        if($formhash !== thash()) {
            show_msg('您的请求来路不正或者输入密码超时，请刷新页面后重新输入正确密码！');
        }
        $toolsmd5 = md5($tpassword.thash());
        if(md5($_GET['toolpassword'].thash()) == $toolsmd5) {
            tsetcookie('toolsauth', $toolsmd5, time()+3600, '', false, '','');
            show_msg('登陆成功！', 'tools.php?action=index', 2000);
        } else {
            show_msg( '您输入的密码不正确，请重新输入正确密码！', 'tools.php', 2000);
        }
    } else {
        return FALSE;
    }
}

//登录成功设置cookie
function tsetcookie($var, $value = '', $life = 0, $prefix = '', $httponly = false, $cookiepath, $cookiedomain) {
    $var = (empty($prefix) ? '' : $prefix).$var;
    $_COOKIE[$var] = $value;

    if($value == '' || $life < 0) {
        $value = '';
        $life = -1;
    }
    $path = $httponly && PHP_VERSION < '5.2.0' ? $cookiepath.'; HttpOnly' : $cookiepath;
    $secure = $_SERVER['SERVER_PORT'] == 443 ? 1 : 0;

    if(PHP_VERSION < '5.2.0') {
        $r = setcookie($var, $value, $life);
    } else {
        $r = setcookie($var, $value, $life);
    }
}

//T class 结束
/**
 * End of the tools.php
 */

class zip{
    public $total_files = 0;
    public $total_folders = 0;
    function Extract( $zn, $to, $index = Array(-1) ){
        $ok = 0; $zip = @fopen($zn,'rb');
        if(!$zip) return(-1);
        $cdir = $this->ReadCentralDir($zip,$zn);
        $pos_entry = $cdir['offset'];

        if(!is_array($index)){ $index = array($index);  }
        for($i=0; $index[$i];$i++){
            if(intval($index[$i])!=$index[$i]||$index[$i]>$cdir['entries'])
                return(-1);
        }
        for ($i=0; $i<$cdir['entries']; $i++)
        {
            @fseek($zip, $pos_entry);
            $header = $this->ReadCentralFileHeaders($zip);
            $header['index'] = $i; $pos_entry = ftell($zip);
            @rewind($zip); fseek($zip, $header['offset']);
            if(in_array("-1",$index)||in_array($i,$index))
                $stat[$header['filename']]=$this->ExtractFile($header, $to, $zip);
        }
        fclose($zip);
        return $stat;
    }

    function ReadFileHeader($zip){
        $binary_data = fread($zip, 30);
        $data = unpack('vchk/vid/vversion/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len', $binary_data);

        $header['filename'] = fread($zip, $data['filename_len']);
        if ($data['extra_len'] != 0) {
            $header['extra'] = fread($zip, $data['extra_len']);
        } else { $header['extra'] = ''; }

        $header['compression'] = $data['compression'];$header['size'] = $data['size'];
        $header['compressed_size'] = $data['compressed_size'];
        $header['crc'] = $data['crc']; $header['flag'] = $data['flag'];
        $header['mdate'] = $data['mdate'];$header['mtime'] = $data['mtime'];

        if ($header['mdate'] && $header['mtime']){
            $hour=($header['mtime']&0xF800)>>11;$minute=($header['mtime']&0x07E0)>>5;
            $seconde=($header['mtime']&0x001F)*2;$year=(($header['mdate']&0xFE00)>>9)+1980;
            $month=($header['mdate']&0x01E0)>>5;$day=$header['mdate']&0x001F;
            $header['mtime'] = mktime($hour, $minute, $seconde, $month, $day, $year);
        }else{$header['mtime'] = time();}

        $header['stored_filename'] = $header['filename'];
        $header['status'] = "ok";
        return $header;
    }

    function ReadCentralFileHeaders($zip){
        $binary_data = fread($zip, 46);
        $header = unpack('vchkid/vid/vversion/vversion_extracted/vflag/vcompression/vmtime/vmdate/Vcrc/Vcompressed_size/Vsize/vfilename_len/vextra_len/vcomment_len/vdisk/vinternal/Vexternal/Voffset', $binary_data);

        if ($header['filename_len'] != 0)
            $header['filename'] = fread($zip,$header['filename_len']);
        else $header['filename'] = '';

        if ($header['extra_len'] != 0)
            $header['extra'] = fread($zip, $header['extra_len']);
        else $header['extra'] = '';

        if ($header['comment_len'] != 0)
            $header['comment'] = fread($zip, $header['comment_len']);
        else $header['comment'] = '';

        if ($header['mdate'] && $header['mtime'])
        {
            $hour = ($header['mtime'] & 0xF800) >> 11;
            $minute = ($header['mtime'] & 0x07E0) >> 5;
            $seconde = ($header['mtime'] & 0x001F)*2;
            $year = (($header['mdate'] & 0xFE00) >> 9) + 1980;
            $month = ($header['mdate'] & 0x01E0) >> 5;
            $day = $header['mdate'] & 0x001F;
            $header['mtime'] = mktime($hour, $minute, $seconde, $month, $day, $year);
        } else {
            $header['mtime'] = time();
        }
        $header['stored_filename'] = $header['filename'];
        $header['status'] = 'ok';
        if (substr($header['filename'], -1) == '/')
            $header['external'] = 0x41FF0010;
        return $header;
    }

    function ReadCentralDir($zip,$zip_name){
        $size = filesize($zip_name);

        if ($size < 277) $maximum_size = $size;
        else $maximum_size=277;

        @fseek($zip, $size-$maximum_size);
        $pos = ftell($zip); $bytes = 0x00000000;

        while ($pos < $size){
            $byte = @fread($zip, 1); $bytes=($bytes << 8) | ord($byte);
            if ($bytes == 0x504b0506 or $bytes == 0x2e706870504b0506){ $pos++;break;} $pos++;
        }

        $fdata=fread($zip,18);

        $data=@unpack('vdisk/vdisk_start/vdisk_entries/ventries/Vsize/Voffset/vcomment_size',$fdata);

        if ($data['comment_size'] != 0) $centd['comment'] = fread($zip, $data['comment_size']);
        else $centd['comment'] = ''; $centd['entries'] = $data['entries'];
        $centd['disk_entries'] = $data['disk_entries'];
        $centd['offset'] = $data['offset'];$centd['disk_start'] = $data['disk_start'];
        $centd['size'] = $data['size'];  $centd['disk'] = $data['disk'];
        return $centd;
    }

    function ExtractFile($header,$to,$zip){
        $header = $this->readfileheader($zip);
        if(substr($to,-1)!="/") $to.="/";
        if($to=='./') $to = '';
        $header['filename'] = ltrim(strtr('./'.$header['filename'],array('./upload/'=>'./')),'./');
        $pth = explode("/",$to.$header['filename']);
        $mydir = './';

        if('./utility/' === './'.$pth[0].'/' || './readme/' === './'.$pth[0].'/')
            return true;

        for($i=0;$i<count($pth)-1;$i++){
            if(!$pth[$i]) continue;
            $mydir .= $pth[$i]."/";
            if((!is_dir($mydir) && @mkdir($mydir,0777)) || (($mydir==$to.$header['filename'] || ($mydir==$to && $this->total_folders==0)) && is_dir($mydir)) ){
                @chmod($mydir,0777);
                $this->total_folders ++;
            }
        }

        if(strrchr($header['filename'],'/')=='/') return;
        if (!($header['external']==0x41FF0010)&&!($header['external']==16)){
            if ($header['compression']==0){
                if($to.$header['filename'])
                    $fp = @sfopen($to.$header['filename'], 'wb');
                if(!$fp) return(-1);
                $size = $header['compressed_size'];
                while ($size != 0){
                    $read_size = ($size < 2048 ? $size : 2048);
                    $buffer = fread($zip, $read_size);
                    $binary_data = pack('a'.$read_size, $buffer);
                    @fwrite($fp, $binary_data, $read_size);
                    $size -= $read_size;
                }
                fclose($fp);
                touch($to.$header['filename'], $header['mtime']);
            }else{
                $fp = @fopen($to.$header['filename'].'.gz','wb');
                if(!$fp) return(-1);
                $binary_data = pack('va1a1Va1a1', 0x8b1f, Chr($header['compression']),
                    Chr(0x00), time(), Chr(0x00), Chr(3));
                fwrite($fp, $binary_data, 10);
                $size = $header['compressed_size'];
                while ($size != 0){
                    $read_size = ($size < 1024 ? $size : 1024);
                    $buffer = fread($zip, $read_size);
                    $binary_data = pack('a'.$read_size, $buffer);
                    @fwrite($fp, $binary_data, $read_size);
                    $size -= $read_size;
                }

                $binary_data = pack('VV', $header['crc'], $header['size']);
                fwrite($fp, $binary_data,8); fclose($fp);

                $fp = @sfopen($to.$header['filename'],'wb');
                if(!$fp) return(-1);
                $gzp = @gzopen($to.$header['filename'].'.gz','rb');
                if(!$gzp) return(-2);
                $size = $header['size'];

                while ($size != 0){
                    $read_size = ($size < 2048 ? $size : 2048);
                    $buffer = gzread($gzp, $read_size);
                    $binary_data = pack('a'.$read_size, $buffer);
                    @fwrite($fp, $binary_data, $read_size);
                    $size -= $read_size;
                }
                fclose($fp); gzclose($gzp);

                touch($to.$header['filename'], $header['mtime']);
                @unlink($to.$header['filename'].'.gz');
            }
        }

        $this->total_files ++;
        return true;
    }
}

function sfopen($path, $type='wb'){
    global $gzp;
    static $oldfile = array();
    if($path === true)
        return $oldfile;
    if(is_file($path) && !YESINSTALL){
        if(is_file($path.'.gz')){
            @gzclose($gzp);
            if(!@unlink($path.'.gz'))
                if(!@unlink($path.'.gz'))
                    if(!@unlink($path.'.gz'))
                        if(!@unlink($path.'.gz'))
                            if(!@unlink($path.'.gz'))
                                exit('unlink err:'. $path.'.gz');
        }
        $oldfile[0][] = $path;
        return false;
    }

    $oldfile[1][] = $path;
    return @fopen($path,$type);
}


// TODO: 函数开始

function fun_char($data){
    global $is_utf8;
    if(!$is_utf8){
        return mb_convert_encoding($data,HTML_CHARSET,THIS_CHARSET);
    }else{
        return $data;
    }
}

function fun_size($size) {
    $units = array(' BYT', ' KB', ' MB', ' GB', ' TB');
    for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
    return round($size, 2).$units[$i];
}

