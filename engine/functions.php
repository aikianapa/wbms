<?php

ini_set('display_errors', 0);
require_once __DIR__.'/lib/vendor/autoload.php';
require_once __DIR__."/lib/weprocessor/weprocessor.php";
require_once __DIR__."/lib/weprocessor/weparser.class";

//require_once __DIR__.'/wbapp.php';



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Adbar\Dot;
use Nahid\JsonQ\Jsonq;

//use soundintheory\PHPSQL;
// Rct567\DomQuery\DomQuery;

function wbInit()
{
    //error_reporting(error_reporting() & ~E_NOTICE);
    date_default_timezone_set('Europe/Moscow');
    wbTrigger('func', __FUNCTION__, 'before');
}

function wbInitEnviroment()
{
    wbTrigger('func', __FUNCTION__, 'before');
    $_ENV["wbattr"]="data-wb";
    if (!isset($_SESSION['order_id']) or '' == $_SESSION['order_id']) {
        $_SESSION['order_id'] = wbNewId();
        $new = true;
    } else {
        $new = false;
    }

    $dir=explode("/", __DIR__);
    array_pop($dir);
    $dir=implode("/", $dir);

    !isset($_ENV["driver"]) ? $_ENV["driver"] = "json" : null;
    !isset($_ENV["base"]) ? $_ENV['base'] = "/tpl/" : null;

    $_ENV['path_app'] = ($_SERVER['DOCUMENT_ROOT']>"") ? $_SERVER['DOCUMENT_ROOT'] : $dir ;
    $_ENV['path_engine'] = $_ENV['path_app'].'/engine';
    $_ENV['dir_engine'] = __DIR__;
    $_ENV['dir_app'] = dirname(__FILE__);
    $_ENV['path_tpl'] = $_ENV['path_app'].$_ENV['base'];
    $_ENV['dbe'] = $_ENV['path_engine'].'/database'; 			// Engine data
    $_ENV['dba'] = $_ENV['path_app'].'/database';	// App data
    $_ENV['dbec'] = $_ENV['path_engine'].'/database/_cache'; 			// Engine data
    $_ENV['dbac'] = $_ENV['path_app'].'/database/_cache';	// App data
    $_ENV['drve'] = $_ENV['path_engine'].'/drivers'; 			// Engine data
    $_ENV['drva'] = $_ENV['path_app'].'/drivers';	// App data

    $_ENV['error'] = array();
    $_ENV['last_error'] = null;
    $_ENV['env_id'] = $_ENV['new_id'] = wbNewId();
    $_ENV['datetime'] = date('Y-m-d H:i:s');
    $_ENV['thumb_width'] = 200;
    $_ENV['thumb_height'] = 160;
    $_ENV['intext_width'] = 320;
    $_ENV['intext_height'] = 240;
    $_ENV['page_size'] = 12;
    $_ENV['max_post'] = ini_get('post_max_size');
    $_ENV['max_file'] = ini_get('upload_max_filesize');
    $_ENV['hash_algorithm'] = PASSWORD_DEFAULT;

    $_ENV['data'] = new stdClass(); // for store some data

    $_ENV['forms'] = wbListForms(false);
    $_ENV['editors'] = [];

    //$_ENV['drivers'] = wbListDrivers();
    $_ENV['settings']['driver'] = 'json';
    // Load tags
    //$_ENV['tags'] = wbListTags();
    $_ENV['stop_func'] = explode(",", "exec,system,passthru,readfile,shell_exec,escapeshellarg,escapeshellcmd,proc_close,proc_open,ini_alter,dl,popen,parse_ini_file,show_source,curl_exec,file_get_contents,file_put_contents,file,eval,chmod,chown");
}

function wbIsApp(&$obj) {
    return strtolower(get_class($obj)) == 'wbapp' ? true : false;
}

function wbIsDom(&$obj) {
    return strtolower(get_class($obj)) == 'wbdom' ? true : false;
}

function wbInitSettings(&$app)
{
    $app->vars('_sess.events') ? null : $app->vars('_sess.events', []);
    // массив для передачи событий в браузер во время вызова wbapp.alive()

    if (isset($_COOKIE['user']) && !isset($_SESSION['user'])) {
        $_SESSION['user'] = $app->ItemRead("users", $_COOKIE['user']);
    }
    
    if ($app->vars('_sess.user') !== null AND $app->vars('_sess.user.active') == 'on') {
        $_ENV["user"] = &$_SESSION['user'];
        $_SESSION['user_role'] = $_SESSION['user']['role'];
        $app->user = (object)$_ENV["user"];
        unset($_COOKIE['user']);
        isset($app->user->id) ? $cookuser = $app->user->id : $cookuser = "";
        setcookie("user", $cookuser, time()+3600, "/"); // срок действия час
    } else {
        $_SESSION['user'] = $_ENV["user"] = null;
        setcookie("user", null, time()-3600, "/");
    }
    $variables = [];
    $settings = $app->ItemRead('_settings', 'settings');
    $app->vars('_sett', $settings);
    if (!$settings) {
        $settings = [];
    } else {
        if (!isset($settings['variables'])) $settings['variables'] = [];
        foreach ((array)$settings['variables'] as $v) {
            if (isset($v['var']) AND $v['var'] > '') $variables[$v['var']] = $v['value'];
        }
    }
    $_ENV['variables'] = array_merge((array)$_ENV['variables'], $variables);
    $settings = array_merge($settings, $variables);
    $_ENV['settings'] = &$settings;

    $app->vars('_sett.driver') > '' ? $app->settings->driver = $app->vars('_sett.driver') : null;
    $app->vars('_sett.lang') > '' ? $lang = $app->vars('_sett.lang') : null;

    if ($_SERVER['REQUEST_URI']=='/engine/') {
        unset($_ENV['lang']);
    }

    isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] : $lang = 'ru';

    $app->vars('_sett.lang') > '' ? $lang = $app->vars('_sett.lang') : null;
    $app->vars('_cook.lang') > '' ? $lang = $app->vars('_cook.lang') : null;
    $app->vars('_sess.lang') > '' ? $lang = $app->vars('_sess.lang') : null;
    $app->vars('_sess.user.lang') > '' ? $lang = $app->vars('_sess.user.lang') : null;
    $app->vars('_route.lang') > '' ? $lang = $app->vars('_route.lang') : null;

    $app->lang = $_ENV['settings']['locale'] = $_COOKIE['lang'] = $_SESSION['lang'] = $_ENV['lang'] = substr($lang, 0, 2);

    if ($app->vars('_sett.path_tpl') > '') {
        $_ENV['base'] = $app->vars('_sett.path_tpl');
        $_ENV['path_tpl'] = $_ENV['path_app'].$_ENV['base'];
    }
    
    $app->vars('_sett.thumb_width') > '0' ? $_ENV['thumb_width'] = $app->vars('_sett.thumb_width') : null;
    $app->vars('_sett.thumb_height') > '0' ? $_ENV['thumb_height'] = $app->vars('_sett.thumb_height') : null;
    $app->vars('_sett.intext_width') > '0' ? $_ENV['intext_width'] = $app->vars('_sett.intext_width') : null;
    $app->vars('_sett.intext_height') > '0' ? $_ENV['intext_height'] = $app->vars('_sett.intext_height') : null;

    if ($app->vars('_sett.page_size') > '') {
        $_ENV['page_size'] = $app->vars('_sett.page_size');
    } else {
        $settings['page_size'] =  $_ENV['page_size'];
    }

    if ($app->vars('_sett.base') > "") {
        $_ENV['base'] = $app->vars('_sett.base');
        $_ENV['path_tpl'] = str_replace("//", "/", $_ENV['path_app']."/".$_ENV['base']);
    }
    if ($app->vars("_env.settings.editor") == "") {
        $_ENV['settings']['editor'] = 'jodit';
    }
    $_ENV['settings']['max_upload_size'] = wbMaxUplSize();
    $_ENV['sysmsg'] = wbGetSysMsg();
    $_ENV['settings']['sysmsg'] = &$_ENV['sysmsg']; // для доступа из JS
    $_ENV['settings']['user'] = &$_SESSION['user'];
    if (isset($_ENV['settings']['user']['password'])) unset($_ENV['settings']['user']['password']);
    $app->vars('_sett', $_ENV['settings']);
    if ($app->route->controller !== 'ajax' && $app->vars('_sett.devmode') == 'on') {
        setcookie('devmode', time(), time()+3600, '/');
    } else {
        setcookie('devmode', null, time()+3600, '/');
    }
    if (!($app->vars('_sett.cache') > "")) $app->vars('_sett.cache',0);
    if (in_array($app->vars('_route.controller'),['thumbnails','file'])) {
          if ($app->vars('_sett.user') && $app->vars('_sett.user.group') == '') {
              $app->vars('_sett.user.group', $app->ItemRead('users', $app->vars('_sett.user.role')));
          }
          if (!$app->vars('_cookie.events')) {
              setcookie('events', base64_encode(json_encode([])), time()+3600, '/');
          } // срок действия час
    }
		$app->vars("_sess.token",$app->getToken());
        
}

function wbCheckAllow($allow = [],$disallow = [], $role = null) {
    $app = &$_ENV['app'];
    $res = true;
    $role == null ? $role = $app->vars('_sess.user.role') : null;
    $allow === (array)$allow ? null : $allow = wbAttrToArray($allow);
    $disallow === (array)$disallow ? null : $disallow = wbAttrToArray($disallow);
    $role > '' ? null : $res = false;
    if (count($allow) && !in_array($role,$allow)) $res = false;
    if (count($disallow) && in_array($role,$disallow)) $res = false;
    return $res;
}

    function wbApikey($mode = null)
    {
        $app = &$_ENV['app'];
        if ($app->vars('_sett.api_key_query') !== 'on') {
            return true;
        }
        $mode == null ? $mode = $app->vars('_route.mode') : null;
        $token = $app->vars("_sess.token");
        $access = $app->checkToken($app->vars('_req.__token'));

        if (!in_array($mode,['ajax','save']) && $app->vars('_sett.api_key_'.$mode) !== 'on') $access = true;

        if (!$access) {
            echo json_encode(['error'=>true,'msg'=>'Access denied']);
            die;
        }

        if ($app->vars('_req.__apikey')) {
            unset($_REQUEST['__apikey']);
            unset($_POST['__apikey']);
            unset($_GET['__apikey']);
            unset($app->route->query->__apikey);
        }
        if ($app->vars('_req.__token')) {
            unset($_REQUEST['__token']);
            unset($_POST['__token']);
            unset($_GET['__token']);
            unset($app->route->query->__token);
        }
        return true;
    }


function wbGetToken()
{
    $app = &$_ENV['app'];
    $apikey = $app->vars('_sett.api_key');
    $role = $app->vars('_sess.user.role');
    $user = $app->vars('_sess.user.id');
    !$user ? $user = microtime() : null;
    !$role ? $role = microtime() : null;
    $app->vars('_sett.api_allow') ? $allow = explode(',', $app->vars('_sett.api_allow')) : $allow = [];
    $app->vars('_sett.api_disallow') ? $disallow = explode(',', $app->vars('_sett.api_disallow')) : $disallow = [];
    $flag = true;
    (count($allow) && !in_array($role, $allow)) ? $flag = false : null;
    (count($disallow) && in_array($role, $disallow)) ? $flag = false : null;
    (!$flag) ? $role = microtime() : null;
    return password_hash($app->route->host.session_id().$apikey.$role.$user,$app->vars('_env.hash_algorithm'));
}


function wbCheckToken($token = null) {
    $app = &$_ENV['app'];
    $form = null;
    $app->vars('_route.form') > '' ? $form = $app->vars('_route.form') : null;
    $app->vars('_route.table') > '' ? $form = $app->vars('_route.table') : null;
    $test = debug_backtrace(2);
    $test = array_column($test,'function');

    if (!in_array('checkToken',$test)) {
        $frm = $app->formClass($form);
        if (method_exists($frm,'checkToken')) {
            $res = $frm->checkToken();
            if ($res === true OR $res === false) return $res;
        }
    }
    $apikey = $app->vars('_sett.api_key');
    $app->vars('_sess.user.id') == '' ? $user = microtime() : $user = $app->vars('_sess.user.id');
    $app->vars('_sess.user.role') == '' ? $role = microtime() : $role = $app->vars('_sess.user.role');
    $token = ($token == null) ? $app->vars('_req.__token') : $token;
    $res = password_verify($app->route->host.session_id().$apikey.$role.$user,$token);
    return $res;
}

function wbMaxUplSize()
{
    $qty = (int)(ini_get('upload_max_filesize'));
    $unit = strtolower(preg_replace("/[0-9]/", "", ini_get('upload_max_filesize')));
    switch ($unit) {
          case 'k':
              $qty *= 1024;
              break;
          case 'm':
              $qty *= 1048576;
              break;
          case 'g':
              $qty *= 1073741824;
              break;
      }
    return $qty;
}


function wbGetSysMsg()
{
    $locale = [];
    if (is_file($_ENV["path_app"]."/forms/common/system_messages.ini")) {
        $locale = (array)parse_ini_file($_ENV["path_app"]."/forms/common/system_messages.ini", true);
    } elseif (is_file($_ENV["path_engine"]."/forms/common/system_messages.ini")) {
        $locale = (array)parse_ini_file($_ENV["path_engine"]."/forms/common/system_messages.ini", true);
    }
    isset($locale[$_ENV["lang"]]) ? $locale = $locale[$_ENV["lang"]] : $locale = array_pop($locale);
    return $locale;
}


    function wbLoadDriver($drv = null)
    {
        // Load default DB driver //
        if ($drv !== null and is_file($_ENV['drva']."/{$drv}/{$drv}.php")) {
            require_once $_ENV['drva']."/{$drv}/{$drv}.php";
        } elseif ($drv !== null and is_file($_ENV['drve']."/{$drv}/{$drv}.php")) {
            require_once $_ENV['drve']."/{$drv}/{$drv}.php";
        } else {
            $drv = "default";
            if (is_file($_ENV['drve']."/{$drv}/{$drv}.php")) {
                require_once $_ENV['drve']."/{$drv}/{$drv}.php";
            }
        }
        $_ENV['driver'] = $drv;
    }

function wbFormExist($form = null) {
    $app = &$_ENV['app'];
    ($form == null) ? $form = $app->vars("_route.form") : null;
    $res = false;
    if (is_dir($app->vars("_env.path_app")."/forms/{$form}")) {
        $res = true;
    } elseif (is_dir($app->vars("_env.path_engine")."/forms/{$form}")) {
        $res = true;
    } elseif (is_dir($app->vars("_env.path_engine")."/modules/cms/forms/{$form}")) {
        $res = true;
    }
    return $res;
}


function wbModuleClass($module)
{
    $app = &$_ENV['app'];
    if (is_file($app->vars("_env.path_app")."/modules/{$module}/{$module}.php")) {
        require_once($app->vars("_env.path_app")."/modules/{$module}/{$module}.php");
    } elseif (is_file($app->vars("_env.path_engine")."/modules/{$module}/{$module}.php")) {
        require_once($app->vars("_env.path_engine")."/modules/{$module}/{$module}.php");
    }
    $module = ucwords($module);
    return new $module($app);
}



function wbFormClass($form = null) {
  $app = &$_ENV['app'];
  ($form == null) ? $form = $app->vars("_route.form") : null;
  if (is_file($app->vars("_env.path_app")."/forms/{$form}/_class.php")) {
      require_once($app->vars("_env.path_app")."/forms/{$form}/_class.php");
  } else if (is_file($app->vars("_env.path_engine")."/forms/{$form}/_class.php")) {
      require_once($app->vars("_env.path_engine")."/forms/{$form}/_class.php");
  } else if (is_file($app->vars("_env.path_engine")."/modules/cms/forms/{$form}/_class.php")) {
      require_once($app->vars("_env.path_engine")."/modules/cms/forms/{$form}/_class.php");
  }
  $class = $form."Class";
  if (class_exists($class)) {
      $form = new $class($app);
  } else {
      $form = new cmsFormsClass($app);
  }
  return $form;
}

function wbCorrelation($form,$id,$fld) {
    $item = wbItemRead($form,$id);
    $item = wbTrigger('form', __FUNCTION__, 'beforeItemShow', func_get_args(), $item);
    if ($item) {
        $data = new Dot($item);
        return $data->get($fld);
    } else {
        return null;
    }
}

function wbMime($path) {
    $mime = "text/plain";
    if (is_file($path)) {
        $info = new SplFileInfo($path);
        $ext  = $info->getExtension();
    } else {
        $ext = explode("?",$path);
        $ext = explode(".",$ext[0]);
        $ext = array_pop($ext);
    }
    $types = json_decode(file_get_contents(__DIR__.'/database/_mimetypes.json'),true);
    if (isset($types[$ext])) $mime = $types[$ext];
    return $mime;
}

function wbMimeExt($mime = "text/plain") {
    $types = json_decode(file_get_contents(__DIR__.'/database/_mimetypes.json'),true);
    $types = array_flip($types);
    isset($types[$mime]) ? $ext = $types[$mime] : $ext = 'txt';
    return $ext;
}

function wbMail(
    $from = null,
    $sent = null,
    $subject = null,
    $message = null,
    $attach = null
    ) {
    $app = $_ENV['app'];
    if ($from == null) {
        $from=$_ENV["settings"]["email"].";".$_ENV["settings"]["header"];
    } elseif (!is_array($from)) {
        if (strpos($from, ";")) {
            $from=explode(";", $from);
        } else {
            $from=array($from,strip_tags($_ENV['settings']['header']));
        }
    }
    $app->vars('_sett.modules.phpmailer.email') > '' ? $from[0] = $app->vars('_sett.modules.phpmailer.email') : null;
    $app->vars('_sett.modules.phpmailer.name') > '' ? $from[1] = $app->vars('_sett.modules.phpmailer.name') : null;
    if (is_string($sent) and strpos($sent, ',')) $sent = explode(',',$sent);
    if (is_string($sent) and strpos($sent, ";")) {
        $sent=array(explode(";", $sent));
    } elseif (!is_array($sent)) {
        $sent=array(array($sent,$sent));
    } elseif (is_array($sent) and !is_array($sent[0]) and strpos($sent[0], ";")) {
        foreach ($sent as $k => $s) {
            if (!is_array($s)) {
                $sent[$k]=explode(";", $s);
            }
        }
    } elseif (is_array($sent) and !is_array($sent[0]) and !strpos($sent[0], ";")) {
        foreach ($sent as $k => $s) {
            if (!is_array($s)) {
                $sent[$k]=explode(";", $s);
            }
        }
    }

        require_once __DIR__.'/modules/phpmailer/phpmailer.php';
        $app->vars('_sett.modules.phpmailer.smtp') == 'on' 
            ?  $sett = $app->vars('_sett.modules.phpmailer') : $sett = ['smtp'=>'','host'=>$app->vars('_route.domain')];

        isset($sett["func"]) ? null : $sett["func"] = 'mail';
        $mail = new PHPMailer(true);
        $mail->Port = 25;
        try {
        /*
            $mail->SMTPDebug = 2;                                 // Enable verbose debug output
            $mail->Host = 'smtp1.example.com;smtp2.example.com';  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = 'user@example.com';                 // SMTP username
            $mail->Password = 'secret';                           // SMTP password
            $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 587;                                    // TCP port to connect to
        */
        if ($app->vars('_sett.modules.phpmailer.smtp')=="on") {
            $mail->isSMTP();
            $mail->Host = $app->vars('_sett.modules.phpmailer.host');
            $mail->SMTPAuth = true;
            $mail->Username = $app->vars('_sett.modules.phpmailer.username');
            $mail->Password = $app->vars('_sett.modules.phpmailer.password');
            if ($app->vars('_sett.modules.phpmailer.secure') > '')
            $mail->SMTPSecure = $app->vars('_sett.modules.phpmailer.secure');
            $app->vars('_sett.modules.phpmailer.secure') == 'ssl' ? $mail->Port = 465 : null;
            $app->vars('_sett.modules.phpmailer.secure') == 'tls' ? $mail->Port = 587 : null;
            intval($app->vars('_sett.modules.phpmailer.secure')) > 0 ? $mail->Port = intval($sett["port"]) : null;
        } else if ($sett["func"]=="sendmail") {
            $mail->isSendmail();
        } else {
            $mail->isMail();
        }

        $mail->Timeout  =   20;
        $mail->setFrom($from[0], $from[1]);
        $mail->addReplyTo($from[0], $from[1]);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        foreach ($sent as &$s) {
            if (!isset($s[1]) OR $s[1] == '') $s[1] = $s[0];
            $mail->addAddress($s[0], $s[1]);
        }
        //Read an HTML message body from an external file, convert referenced images to embedded,
        //convert HTML into a basic plain-text alternative body
        $mail->msgHTML($message, dirname(__FILE__));
        $mail->CharSet = 'utf-8';
        //Replace the plain text body with one created manually
        $mail->AltBody = strip_tags($message);
        //Attach an image file
        if (!is_array($attach) and is_string($attach)) {
            $attach=array($attach);
        }
        if (is_array($attach)) {
            foreach ($attach as $a) {
                if (is_string($a) and substr($a, 0, 5) == "data:") {
                    preg_match('/^data:(.*);base64,/', substr($a, 0, 50), $matches, PREG_OFFSET_CAPTURE);
                    $mime = $matches[1][0];
                    $ext = explode("/", $mime);
                    $file = wbNewId().".".$ext[1];
                    $base64 = substr($a, strlen("data:{$mime};base64,"));
                    $mail->AddStringAttachment(base64_decode($base64), $file, 'base64', $mime);
                } else {
                    $mail->addAttachment($a);
                }
            }
        }

        if ($app->vars('_sett.modules.phpmailer.dkim') > '') {
            $mail->DKIM_domain = $app->route->domain;
            $mail->DKIM_selector = 'phpmailer';
            $mail->DKIM_private = $app->vars('_sett.modules.phpmailer.dkim');
            $mail->DKIM_passphrase = '';
            $mail->DKIM_identity = $mail->From;
        }

        //send the message, check for errors
            $res = $mail->send();
            $mail->SmtpClose();
            if ($res) {
				return ['error'=>false];
			} else {
				return ['error'=>true,'msg'=>$mail->ErrorInfo];
			}
        } catch (Exception $e) {
            return ['error'=>true,'msg'=>$mail->ErrorInfo];
        }
}

function wbPagination($c, $m, $dots = '...')
{
    ($c == '') ? $c = 1 : $c = intval($c);
    $current = $c;
    $last = $m;
    $delta = 4;

    $left = $current - $delta;
    $right = $current + $delta + 1;
    $range = array();
    $rangeWithDots = array();
    $l = -1;

    for ($i = 1; $i <= $last; $i++) {
        if ($i == 1 || $i == $last || $i >= $left && $i < $right) {
            array_push($range, $i);
        }
    }

    for ($i = 0; $i<count($range); $i++) {
        if ($l != -1) {
            if ($range[$i] - $l === 2) {
                array_push($rangeWithDots, $l + 1);
            } elseif ($range[$i] - $l !== 1) {
                array_push($rangeWithDots, $dots);
            }
        }

        array_push($rangeWithDots, $range[$i]);
        $l = $range[$i];
    }
    $range = [];
    $flag = false;
    $i=0;
    if ($current == 1) {
        $range[$i] = ["label"=>"prev","page"=>$m];
    } else {
        $range[$i] = ["label"=>"prev","page"=>$current-1];
    }
    foreach ($rangeWithDots as $key => $page) {
        $i++;
        if ($page == $dots and $flag) {
            $idx = ceil($rangeWithDots[$key-1] + ($rangeWithDots[$key+1] - $rangeWithDots[$key-1]) / 2);
            $range[$i] = ["label"=>$dots,"page"=>$idx];
        } elseif ($page == $dots and !$flag) {
            $idx = ceil($rangeWithDots[$key+1] / 2);
            $range[$i] = ["label"=>$dots,"page"=>$idx];
            $flag = true;
        } elseif ($page !== $dots) {
            $range[$i] = ["label"=>$page,"page"=>$page];
        }
    }
    $i++;
    $c == $m ? $range[$i] = ["label"=>"next","page"=>1] : $range[$i] = ["label"=>"next","page"=>$c+1];
    return $range;
}

function wbFormUploadPath()
{
    $path = '/uploads';
    if ('form' == $_ENV['route']['controller']) {
        if (isset($_ENV['route']['form']) and $_ENV['route']['form'] > '') {
            $path .= '/'.$_ENV['route']['form'];
        } else {
            $path .= '/undefined';
        }
        if (isset($_ENV['route']['item']) and $_ENV['route']['item'] > '') {
            $path .= '/'.$_ENV['route']['item'];
        } else {
            $path .= '/undefined';
        }
    } elseif ('ajax' == $_ENV['route']['controller'] and 'buildfields' == $_ENV['route']['mode'] and isset($_POST['data'])) {
        if (isset($_POST['data']['_form']) and $_POST['data']['_form'] > '') {
            $path .= '/'.$_POST['data']['_form'];
        } else {
            $path .= '/undefined';
        }
        if (isset($_POST['data']['_id']) and $_POST['data']['_id'] > '') {
            $path .= '/'.$_POST['data']['_id'];
        } else {
            $path .= '/undefined';
        }
    } else {
        $path .= '/undefined';
    }

    return $path;
}


function wbInitFunctions(&$app)
{
    wbTrigger('func', __FUNCTION__, 'before');
    if (is_file($_ENV['path_app'].'/functions.php')) {
        require_once $_ENV['path_app'].'/functions.php';
    }

    foreach ($_ENV['forms'] as $form) {
        $inc = array(
                   "{$_ENV['path_engine']}/forms/{$form}.php", "{$_ENV['path_engine']}/forms/{$form}/{$form}.php",
                   "{$_ENV['path_app']}/forms/{$form}.php", "{$_ENV['path_app']}/forms/{$form}/{$form}.php",
               );
        foreach ($inc as $k => $file) {
            if (is_file("{$file}")) {
                require_once "{$file}";
            }
        }
    }

    $_ENV['modules'] = wbListModules();
    $excmod = ["cms"];

    foreach ($_ENV['modules'] as $module) {
        if (in_array($module['id'], $excmod) or $app->vars("_sett.modcheck") !== "on" or
            ($app->vars("_sett.modcheck") == "on"
            and $app->vars("_sett.modules.{$module['name']}.active") == "on"
            )
          ) {
            try {
                include_once $module["module"];
            } catch (\Throwable $th) {
                //throw $th;
                error_log("Модуль не загружен:".$module["module"] , 0);
            }
        }
    }
}

function wbGetUserUi($details=false)
{
    $prop=wbGetUserUiConfig();
    if ($prop==null) {
        $conf=wbObjToArray($_SESSION["user"]->group);
        if (!$_ENV["last_error"] and isset($conf["roleprop"]) and $conf["roleprop"] !== "") {
            if ($details) {
                $prop=array();
                $prop["roleprop"]=wbItemToArray($conf["roleprop"]);
                $prop["_roleprop__dict_"]=wbItemToArray($conf["_roleprop__dict_"]);
            } else {
                $prop=wbItemToArray($conf["roleprop"]);
            }
        }
    }
    if ($prop==null) {
        $conf=wbItemRead("users:engine", "admin");
        if (!$_ENV["last_error"]) {
            if ($details) {
                $prop=array();
                $prop["roleprop"]=wbItemToArray($conf["roleprop"]);
                $prop["_roleprop__dict_"]=wbItemToArray($conf["_roleprop__dict_"]);
            } else {
                $prop=wbItemToArray($conf["roleprop"]);
            }
        } else {
            if ($details) {
                $prop=array();
                $prop["roleprop"]=array();
                $prop["_roleprop__dict_"]=array();
            } else {
                $prop=array();
            }
        }
    }
    return $prop;
}

function wbGetUserUiConfig($prop=null)
{
    if ($prop==null) {
        $prop=wbTreeRead("_config");
        $prop=wbItemToArray($prop["tree"]);
    }
    if (is_array($prop)) {
        foreach ($prop as $key => $item) {
            $item=wbItemToArray($item);
            if (is_array($item)) {
                $item=wbGetUserUiConfig($item);
                if (!isset($item["data"])) {
                    $item["data"]=array();
                }
                $item["data"]["visible"]="on";
                $prop[$key]=$item;
            }
        };
    }
    return $prop;
}

function wbProfData(&$xhprof_data)
{
    $tmp=$xhprof_data;
    $last=array_pop($tmp);
    $sec=$last["wt"];
    foreach ($xhprof_data as $key=> &$item) {
        $item["func"]=explode("==>", $key);
        $item["func"]=$item["func"][1];
    }
    $xhprof_data = wbArraySort($xhprof_data, "wt:d");
    $tpl=wbFromString('
	<script data-wb-src="jquery"></script>
	<script data-wb-src="bootstrap3"></script>
	<table class="table table-striped">
	<thead><tr>
	<th>Name</th><th>Calls</th><th>Time</th><th>MemUse</th><th>PeakMemUse</th><th>CPU</th>
	</tr></thead>
	<tbody data-wb-role="foreach" data-wb-from="data">
	<tr>
	<td class="text-right">{{func}}</td>
	<td class="text-center">{{ct}}</td>
	<td class="text-right">{{wt->round(@ / 1000000,4)}}</td>
	<td class="text-right">{{mu->number_format()}}</td>
	<td class="text-right">{{pmu->number_format()}}</td>
	<td class="text-center">{{cpu}}</td>
	</tr>
	</tbody>
	</table>');
    $tpl->wbSetData(array("data"=>$xhprof_data));
    return $tpl->outerHtml();
}

function wbPostToArray(&$array = [], $replace = false)
{
    foreach ($_POST as $key => $val) {
        if ($replace == true or !in_array($key, array_keys($array))) {
            $array[$key] = htmlspecialchars($val, ENT_QUOTES);
        }
    }
    return $array;
}

function wbObjToArray($obj)
{
    return json_decode(json_encode($obj), true);
}

function wbArrayToObj($arr)
{
    return json_decode(wbJsonEncode($arr));
}

function wbAttrToValue($atval)
{
    $prms = "";
    if (substr(trim($atval), 0, 1) == "{" && substr(trim($atval), -1, 1) == "}") {
        $atval = str_replace("'", '"', $atval);
        $prms = json_decode($atval, true);
        if (!$prms) {
            $prms = [];
        }
    } else {
        $prms = $atval;
        if (strpos($atval, "=")) {
            parse_str($atval, $prms);
        }
    }
    return $prms;
}

function wbItemBeforeShow(&$Item)
{
    if (!((array)$Item === $Item)) {
        return;
    }
    if (!isset($Item["_form"])) {
        return;
    }
    $form = $Item["_form"];
    $ecall = "_{$form}BeforeItemShow";
    $acall = "{$form}BeforeItemShow";
    is_callable($ecall) ? $Item = @$ecall($Item) : null;
    is_callable($acall) ? $Item = @$acall($Item) : null;
}


function wbItemToArray(&$Item = array(), $convid = true)
{
    is_object($Item) ? $Item=wbObjToArray($Item) : null;
    (isset($Item["_table"]) && $Item["_table"]=="admin" && $Item["id"]=="settings") ? $convid=false : null ;
    if ((array)$Item === $Item) {
        $tmpItem=array();
        foreach ($Item as $i => $item) {
            if (substr($i, 0, 1) !== "%" and $i !== "_parent") {
                if (!((array)$item === $item)) {
                    $tmp = json_decode($item, true);
                    (array)$tmp === $tmp ? $item = wbItemToArray($tmp, $convid) : null;
                }
                $item = wbItemToArray($item, $convid);
            }
            if ($convid == true and (array)$item === $item and isset($item['id'])) {
                $tmpItem[$item['id']] = $item;
            } else {
                $tmpItem[$i] = $item;
            }
        }
        $Item=$tmpItem;
    } else if (!(array($Item) === $Item)) {
        $tmp = json_decode($Item, true);
        (array)$tmp === $tmp ? $Item = wbItemToArray($tmp, $convid) : null;
    }
    if (isset($Item['__token'])) unset($Item['__token']);
    return $Item;
}

function wbDotFix($data = [])
{
    $app = &$_ENV['app'];
    $data = (array)$data;
    $dot = $app->dot();
    $dot->set($data);
    foreach ($dot as $key => $val) {
        if (strpos($key, '.')) {
            $dot->set($key, $val);
        } elseif ((array)$val === $val) {
            $dot->set($key, wbDotFix($val));
        }
    }
    return $dot->get();
}

function wbGetDomain($url, $deep = 2) {
    $parseUrl = parse_url(trim($url)); 
    $parseUrl['host'] == '' ? $parseUrl['host'] = $_SERVER['HTTP_HOST'] : null;
    $domain = trim($parseUrl['host'] ? $parseUrl['host'] : array_shift(explode('/', $parseUrl['path'], 2))); 
    $domain = array_slice(explode('.',$domain),-1 * $deep);
    $domain = implode('.',$domain);
    return $domain;
} 

function wbGetDataWbFrom($Item, $str)
{
    $str = trim($str);
    $str_1=json_decode(wbSetValuesStr("{{".$str."}}", $Item), true);
    if (is_array($str_1)) {
        return $str_1;
    }

    if (substr($str, 0, 1)=="_" and $str !== $str_1) {
        // если в атрибуте data-wb-from указанна общая переменная (типа _ENV, _SESS)
        $tmp=json_encode($str_1, true);
        if (is_array($tmp)) {
            return $tmp;
        } else {
            return $str_1;
        }
    }
    if (strpos($str, "}}")) {
        $str = wbSetValuesStr($str, $Item);
    }

    $pos = strpos($str, '[');
    if ($pos) {
        $fld = '['.substr($str, 0, $pos).']';
        $suf = substr($str, $pos);
        $fld .= $suf;
        $fld = str_replace('[', '["', $fld);
        $fld = str_replace(']', '"]', $fld);
        $fld = str_replace('""', '"', $fld);
        if (eval('return isset($Item'.$fld.');')) {
            eval('$res=$Item'.$fld.';');
        } else {
            $res="";
        }

        return $res;
    }
    if (isset($Item[$str])) {
        return $Item[$str];
    } else {
        return null;
    }
}

function wbMerchantList($type = 'both')
{
    $res = array();
    if ('both' == $type) {
        $res_e = wbMerchantList('engine');
        $res_a = wbMerchantList('app');

        return array_merge($res_e, $res_a);
    }
    $dir = $_ENV["path_{$type}"].'/modules';
    if (is_dir($dir)) {
        exec("find {$dir} -maxdepth 2 -name '*.php'", $list);
        foreach ($list as $val) {
            $file = $val;
            if (is_file($file) and !strpos($file, "_")) {
                $php = strtolower(trim(file_get_contents($file)));
                $form = array_pop(explode('/', $file));
                $form = explode('.php', $form);
                $form = $form[0];
                if ((strpos($php, "function {$form}_checkout") and strpos($php, "function {$form}_success"))
                        or (strpos($php, "function {$form}__checkout") and strpos($php, "function {$form}__success"))) {
                    $arr = array();
                    $arr['id'] = $form;
                    $arr['name'] = $form;
                    $arr['dir'] = $dir;
                    $arr['type'] = $type;
                    $res[] = $arr;
                }
            }
        }
    }
    unset($dir,$list,$val,$form,$php,$file,$arr);
    return $res;
}

function wbInitDatabase()
{
    wbTrigger('func', __FUNCTION__, 'before');
    if (!is_dir($_ENV['dbe'])) {
        @mkdir($_ENV['dbe'], 0766);
    }
    if (!is_dir($_ENV['dba'])) {
        @mkdir($_ENV['dba'], 0766);
    }
    if (!is_dir($_ENV['dbec'])) {
        @mkdir($_ENV['dbec'], 0766);
    }
    if (!is_dir($_ENV['dbac'])) {
        @mkdir($_ENV['dbac'], 0766);
    }
    $_ENV['tables'] = wbTableList();
}

function wbTreeToArray($tree, $data = false)
{
    $assoc=array();
    if (!is_array($tree)) {
        return $assoc;
    }
    foreach ($tree as $i => $item) {
        if (isset($item["children"])  and is_array($item["children"]) and count($item["children"])) {
            $item["children"]=wbTreeToArray($item["children"],$data);
        }
        if (isset($item["id"])) {
            $key=$item["id"];
        } else {
            $key=$i;
        }
        if (isset($item["children"]) and (!is_array($item["children"]) or !count($item["children"]))) {
            $item["children"]="";
        }
        if (isset($item['data']) and (!is_array($item["data"]) or !count($item["data"]))) {
            $item["data"]="";
        }

        if ($data == true AND $item["data"] === (array)$item["data"]) {
            $item = array_merge($item, $item["data"]);
            unset($item["data"]);
        }

        $assoc[$key]=$item;
    }
    return $assoc;
}

function wbTreeFindBranchById($Item, $id)
{
    //$Item=wbItemToArray($Item);
    $res = false;
    if (is_array($Item)) {
        foreach ($Item as $item) {
            if ($id == '' OR (isset($item['id']) AND $item['id'] === $id)) {
                return $item;
            }
            if (isset($item['children']) AND (array)$item['children'] === $item['children']) {
                $res = wbTreeFindBranchById($item['children'], $id);
                if ($res) {
                    return $res;
                }
            }
        }
    }
    return $res;
}

function wbTreeFindBranch($tree, $branch = '', $parent = 'true', $childrens = 'true')
{
    //$tree=wbItemToArray($tree);
    if (trim($branch) == '') {
        return $tree;
    }
    $branch = html_entity_decode($branch);
    $br = explode('->', $branch);
    foreach ($br as $b) {
        $tree = array(wbTreeFindBranchById($tree, rtrim(ltrim($b))));
    }

    if ('false' == $childrens) {
        unset($tree['children']);
    }
    if ('false' == $parent) {
        $tree = $tree[0]['children'];
    }

    return $tree;
}

function wbTreeWhere($tree, $id, $field, $inc = true)
{
    if (!is_array($tree)) {
        $tree = wbTreeRead($tree);
        $tree_id = $tree['id'];
        $tree = $tree['tree'];
    } else {
        $tree_id = $tree['id'];
    }
    if (strpos($id, '->')) {
        $tree = wbTreeFindBranch($tree, $id);
        $tree = $tree[0];
    } else {
        $tree = wbTreeFindBranchById($tree, $id);
    }
    $cache_id = md5($tree_id.$id.$field.$inc.$_ENV["lang"].$_ENV["lang"]);
    if (isset($_ENV['cache'][__FUNCTION__][$cache_id])) {
        return $_ENV['cache'][__FUNCTION__][$cache_id];
    }
    $list = wbTreeIdList($tree);
    $where = '';
    foreach ($list as $key => $val) {
        if (0 == $key) {
            $where .= '"'.$val.'"';
        } else {
            $where .= ',"'.$val.'"';
        }
    }
    $where = "in_array({$field},array({$where}))";
    $_ENV['cache'][__FUNCTION__][$cache_id] = $where;

    return $_ENV['cache'][__FUNCTION__][$cache_id];
}

function wbTreeIdList($tree, $list = array())
{
    if (isset($tree['id'])) {
        $list[] = $tree['id'];
    }
    //$tree = wbItemToArray($tree);
    if (isset($tree['children']) and is_array($tree['children'])) {
        foreach ($tree['children'] as $key => $child) {
            $list = wbTreeIdList($child, $list);
        }
    }

    return $list;
}

function wbWhereLike($ref, $val)
{
    if (is_array($ref)) {
        $ref = implode('|', $ref);
    } else {
        $val = trim($val);
        $val = str_replace(' ', '|', $val);
    }
    $res = preg_match("/{$val}/ui", $ref);

    return $res;
}

function wbWhereNotLike($ref, $val)
{
    if (is_array($ref)) {
        $ref = implode('|', $ref);
    } else {
        $val = trim($val);
        $val = str_replace(' ', '|', $val);
    }
    $res = preg_match("/{$val}/ui", $ref);
    if (1 == $res) {
        $res = 0;
    } else {
        $res = 1;
    }

    return $res;
}

function wb_json_encode($Item=[])
{
    return wbJsonEncode($Item);
}

function wbJsonFromFile($file) {
    // https://github.com/halaxa/json-machine
    try {
        return \JsonMachine\JsonMachine::fromFile($file);
    } catch(Exception $e) {
        return [];
    }

}

function wbJsonDecode($json = '') {
    $params = &$json;
    if ($params > '') {
        try {
            $params = json_decode($json,true);
        } catch (\Throwable $th) {
            $params = [];
        }
    } else {
        $params = [];
    }
    return $params;
}



function wbJsonEncode($Item = [])
{
    if (version_compare(phpversion(), "5.6")<0) {
        return stripcslashes(wbJsonEncodeAlt($Item));
    } else {
        return json_encode($Item, JSON_UNESCAPED_UNICODE | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_APOS | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_HEX_QUOT);
    }
}

function wbJsonEncodeAlt($a=false)
{
    if (is_null($a)) {
        return 'null';
    }
    if ($a === false) {
        return 'false';
    }
    if ($a === true) {
        return 'true';
    }
    if (is_scalar($a)) {
        if (is_float($a)) {
            // Always use "." for floats.
            $a = str_replace(",", ".", strval($a));
        }

        // All scalars are converted to strings to avoid indeterminism.
        // PHP's "1" and 1 are equal for all PHP operators, but
        // JS's "1" and 1 are not. So if we pass "1" or 1 from the PHP backend,
        // we should get the same result in the JS frontend (string).
        // Character replacements for JSON.
        static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
    array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
        return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
    }
    $isList = true;
    for ($i = 0, reset($a); $i < count($a); $i++, next($a)) {
        if (key($a) !== $i) {
            $isList = false;
            break;
        }
    }
    $result = array();
    if ($isList) {
        foreach ($a as $v) {
            $result[] = wbJsonEncodeAlt($v);
        }
        return '[ ' . join(', ', $result) . ' ]';
    } else {
        foreach ($a as $k => $v) {
            $result[] = wbJsonEncodeAlt($k).': '.wbJsonEncodeAlt($v);
        }
        return '{ ' . join(', ', $result) . ' }';
    }
}


function wbSetChmod($ext = '.json')
{
    foreach ($_ENV['tables'] as $table) {
        if (is_file($_ENV['dba'].'/'.$table.$ext)) {
            @chmod($_ENV['dba'].'/'.$table.$ext, 0766);
        }
    }
}

function wbItemInit($table, $item = null)
{
    $app = &$_ENV["app"];
    (!isset($item['_id']) && isset($item['id'])) ? $item['_id'] = $item['id'] : null;
    (!isset($item['_id']) or '_new' == $item['_id'] or $item['_id'] == "") ? $item['_id'] = wbNewId() : null;
    $item['id'] = $item['_id'];
    $item['_table'] = $item['_form'] = $table;
    $tmp = (in_array('wbItemRead',wbCallStack()) OR in_array('wbItemList',wbCallStack())) ? null : $tmp = wbItemRead($item["_form"], $item["_id"]);
    (!$tmp or !isset($tmp['_created']) or '' == $tmp['_created']) or !isset($item["_created"]) ? $item['_created'] = date('Y-m-d H:i:s') : null;
    (!$tmp or !isset($tmp['_creator']) or '' == $tmp['_creator']) and !isset($item["_creator"]) ? $item['_creator'] = $app->vars("_sess.user.id") : null;
    $item['_lastdate'] = date('Y-m-d H:i:s');
    $item['_lastuser'] = $app->vars("_sess.user.id");
    $item['_sort'] = isset($item['_sort']) ? wbSortIndex($item['_sort']) : wbSortIndex();
    if (isset($item['__token'])) unset($item['__token']);
    return $item;
}

function wbSortIndex($idx = 999999999) {
    return str_pad($idx, 9, '0', STR_PAD_LEFT);
}

function wbCallStack() {
    return array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),'function');
}

function wb_file_get_contents($file)
{
    $fp = fopen($file, 'rb');
    flock($fp, LOCK_SH);
    $contents = file_get_contents($file);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $contents;
}


function modLoginSignin($user) {

}

function modLoginSignup($user) {

}

function wbTrigger($type, $name, $trigger, $args = [], $data = null)
{

    //$env_error = $_ENV['error'];
    if (!isset($env_error) or (array)$env_error !== $env_error) {
        $_ENV['error'] = array();
    }
    if (!isset($env_error[$type])) {
        $_ENV['error'][$type] = array();
    }
    switch ($type) {
    case 'form':
        $form = $args[0];
        $class = wbFormClass($form);
        $call = '_'.$trigger;
        if (is_callable($call)) $data = $call($data, $args);
        if ($class && method_exists($class,$trigger)) {
            $class->$trigger($data);
        }
        return $data;
        break;
    case 'func':
        $call = $name.'_'.$trigger;
        if (is_callable($call)) {
            $data = $call($data, $args);
        } else {
            wbError($type, $name, null);
        }

        return $data;
        break;
    default:
        break;
    }

    return $data;
}

function wbFurlPut($item, $string, $flag = 'update')
{
    $res = false;
    $table = $item['_table'];
    $id = $item['id'];
    $furl = wbFurlGenerate($string);
    if (!in_array('furl_index', $_ENV['tables'], true)) {
        wbTableCreate('furl_index');
    }
    $item = wbItemRead('furl_index', $table);
    if (!isset($item)) {
        $item = array('id' => $table, 'furl' => array());
    }
    switch ($flag) {
    case 'update':
        foreach ($item['furl'] as $f => $fid) {
            if ($id == $fid) {
                unset($item['furl'][$f]);
            }
        }
        $item['furl'][$furl] = $id;
        $res = $furl;
        break;
    case 'remove':
        foreach ($item['furl'] as $f => $fid) {
            if ($id == $fid) {
                unset($item['furl'][$f]);
            }
        }
        break;
    }
    $res = wbItemSave('furl_index', $item);
    if ($res) {
        $res = $furl;
    }
    unset($item,$table,$id,$furl);

    return $res;
}

function wbFurlGet($table, $furl)
{
    $res = false;
    if (!in_array('furl_index', $_ENV['tables'], true)) {
        wbTableCreate('furl_index');
    }
    $item = wbItemRead('furl_index', $table);
    if (isset($item['furl'][$furl])) {
        return $item['furl'][$furl];
    }

    return $res;
}

function wbFurlGenerate($str)
{
    $str = mb_strtolower(wbTranslit($str));
    $str = mb_ereg_replace('[^A-Za-z0-9 ]', ' ', $str);
    $str = str_replace([' '], '-', trim($str));
    $str = str_replace('--', '-', trim($str));
    $str = str_replace('--', '-', trim($str));

    return $str;
}

function wbError($type, $name, $error = '__return__error__', $args = null)
{
    if (null == $error) {
        if (isset($_ENV['error'][$type][$name])) {
            unset($_ENV['error'][$type][$name]);
        }
        $_ENV["last_error"]=null;
    } else {
        if (isset($_ENV['errors'][$error])) {
            $errname = $_ENV['errors'][$error];
            foreach ((array)$args as $key => $arg) {
@                (object)$arg === $arg ? $arg = (array)$arg : null;
@                (array)$arg === $arg ? $arg = implode(',', $arg) : null;
                $errname = str_replace('{{'.$key.'}}', $arg, $errname);
            }
            $_ENV["last_error"] = $error = $errname;
        } else {
            if ('__return__error__' == $error) {
                $error = $_ENV['error'][$type][$name];
            } else {
                if (isset($_ENV['errors'])) {
                    $_ENV['error'][$type][$name] = array('errno' => $error, 'error' => $errname);
                } else {
                    $_ENV['error'][$type][$name] = array('errno' => $error, 'error' => 'unknown error');
                }
            }
            $_ENV["last_error"]=$error;
        }
    }
    return $error;
}

function wbErrorOut($error, $ret = false)
{
    if ($ret == false) {
        echo $_ENV['errors'][$error];
    } else {
        return $_ENV['errors'][$error];
    }
}


function wbOconv($value, $oconv)
{
    $oconv = htmlspecialchars_decode($oconv, ENT_QUOTES);
    $value = htmlspecialchars_decode($value, ENT_QUOTES);
    if (is_callable($oconv)) {
        $oconv = '$result = '.$oconv.'("'.$value.'");';
        eval($oconv);
        return $result;
    }
}

function wbEnvData($index, $value="__wb__null__data__")
{
    $loop=explode("->", $index);
    $index='$_ENV["data"]';
    $count=count($loop);
    $i=0;
    $res = false;
    foreach ($loop as $key) {
        $i++;
        if ($key=="") {
            $key="undefined";
        } else {
            $key=preg_replace('/[^ a-zа-яё\d]/ui', '_', $key);
        }
        $index.="->".$key;
        if (!eval('return isset( '.$index.' );')) {
            eval($index.' = new stdClass();');
        }
        if ($i==$count) {
            if ($value=="__wb__null__data__") {
                $res = eval('return '.$index.';');
            } else {
                $res = eval('return '.$index.' = $value;');
            }
        }
    }
    return $res;
}

function wbPage404(&$app=null)
{
    header("HTTP/1.0 404 Not Found");
    if ($app == null) {
        $app = new wbApp();
    }
    $_ENV["route"]["error"]="404";
    $dom = $app->getTpl("404.php");
    if (is_object($dom)) {
        $dom->fetch();
    }
    wbLog("func", __FUNCTION__, 404, $_ENV["route"]);
    return $dom;
}


function wbErrorList()
{
    $_ENV['errors'] = array(
                          100 => 'Login succeessful {{l}}',
                          101 => 'Login incorrect {{l}}',
                          404 => 'Page not found',
                          1001 => 'Table {{0}} not exists',
                          1002 => 'Table {{0}} already exixts',
                          1003 => 'Do not remove {{0}}',
                          1004 => 'Failed to remove file {{0}}',
                          1005 => 'Failed to remove table {{0}}',
                          1006 => 'Item {{1}} is not exists in table {{0}}',
                          1007 => 'Failed to save record to table {{0}}',
                          1008 => 'Delete item {{1}} in table {{0}}',
                          1009 => 'Flush data from cache table {{0}}',
                          1010 => 'Failed to create table {{0}}',
                          1010 => 'Create a table {{0}}',
                          1011 => 'Template {{0}} not found',
                          1012 => 'Form {{0}} not found',
                          1013 => 'PHP code not valid',
                          1016 => 'Item {{1}} already exists in table {{0}}',
                      );
}

function wbLog($type, $name, $error, $args)
{
    if (isset($_ENV['errors'][$error])) {
        $error = array('errno' => $error, 'error' => $_ENV['errors'][$error]);
    } else {
        $error = wbError($type, $name);
    }
    
    foreach ((array)$args as $key => $arg) {
        if ((array)$arg === $arg) {
            $arg = implode(',', $arg);
        }
        $error['error'] = str_replace('{{'.$key.'}}', $arg, $error['error']);
    }

//    if (isset($_ENV["settings"]["log"]) and $_ENV["settings"]["log"]=="on") {
        error_log("{$type} {$name} [{$error['errno']}]: {$error['error']} [{$_SERVER['REQUEST_URI']} from {$_SERVER['REMOTE_ADDR']}]");
//    }
}

function wbNewId($separator = '', $prefix = '')
{
    $mt = explode(' ', microtime());
    $md = substr(str_repeat('0', 2).dechex(ceil($mt[0] * 10000)), -4);
    $id = dechex(time() + rand(100, 999));
    if ($prefix > '') {
        $id = $prefix.$separator.$id.$md;
    } else {
        $id = "id".$id.$separator.$md;
    }
    return $id;
}

function wbGetItemImg($Item = null, $idx = 0, $noimg = '', $imgfld = 'images', $visible = true)
{
    $res = false;
    $count = 0;

    if (!is_file("{$_ENV['path_app']}/{$noimg}")) {
        if (is_file("{$_ENV['path_engine']}/uploads/__system/{$noimg}")) {
            $noimg = "/engine/uploads/__system/{$noimg}";
        } else {
            $noimg = '/engine/uploads/__system/image.jpg';
        }
    }
    $image = $noimg;

    if (isset($Item[$imgfld])) {
        if (!is_array($Item[$imgfld])) {
            $Item[$imgfld] = json_decode($Item[$imgfld], true);
        }
        if (!is_array($Item[$imgfld])) {
            $Item[$imgfld] = array();
        }
        foreach ($Item[$imgfld] as $key => $img) {
            if (!isset($img['visible'])) {
                $img['visible'] = 1;
            }

            if (false == $res and ((true == $visible and 1 == $img['visible']) or false == $visible) and is_file("{$_ENV['path_app']}/uploads/{$Item['_table']}/{$Item['id']}/{$img['img']}")) {
                if ($idx == $count) {
                    $image = "/uploads/{$Item['_table']}/{$Item['id']}/{$img['img']}";
                    $res = true;
                }
                ++$count;
            }
        }
        unset($img);
    }

    return urldecode($image);
}

function wbListFiles($dir)
{
    $list = array();
    if (is_dir($dir) and $dircont = scandir($dir)) {
        $i = 0;
        $idx = 0;
        while (isset($dircont[$i])) {
            if ('.' !== $dircont[$i] && '..' !== $dircont[$i]) {
                $current_file = "{$dir}/{$dircont[$i]}";
                if (is_file($current_file)) {
                    $list[] = "{$dircont[$i]}";
                }
            }
            ++$i;
        }
    }

    return $list;
}

function wbFileRemove($file)
{
    $res = false;
    if (is_file($file)) {
        unlink($file);
        if (is_file($file)) {
            $res = false;
        } else {
            $res = true;
        }
    }

    return $res;
}

function wbPutContents($dir, $contents, $flag = null)
{
    $parts = explode('/', $dir);
    $file = array_pop($parts);
    $dir = implode('/',$parts);
    $u=umask();
    is_dir($dir) ? null : mkdir($dir, 0755, true);
    umask($u);
    return file_put_contents("{$dir}/{$file}", $contents, $flag);
}

function wbRecurseDelete($src)
{
    $dir = opendir($src);
    if (is_resource($dir)) {
        while (false !== ($file = readdir($dir))) {
            if (('.' !== $file) && ('..' !== $file)) {
                if (!is_link($src.'/'.$file) && is_dir($src.'/'.$file)) {
                    wbRecurseDelete($src.'/'.$file);
                } else {
                    unlink($src.'/'.$file);
                }
            }
        }
        closedir($dir);
        if (is_dir($src)) {
            rmdir($src);
        }
    }
}

function wbRecurseCopy($src, $dst)
{
    $mask=umask();
    if (is_file($src)) {
        copy($src, $dst);
    } else {
        $dir = opendir($src);
        if (is_resource($dir)) {
            if (!is_dir($dst)) {
                mkdir($dst);
            }
            while (false !== ($file = readdir($dir))) {
                if (('.' !== $file) && ('..' !== $file)) {
                    if (is_dir($src.'/'.$file)) {
                        wbRecurseCopy($src.'/'.$file, $dst.'/'.$file);
                        @chmod($dst.'/'.$file, 0777);
                    } else {
                        copy($src.'/'.$file, $dst.'/'.$file);
                        @chmod($dst.'/'.$file, 0766);
                    }
                }
            }
            closedir($dir);
        }
    }
    umask($mask);
}

function wbQuery($sql)
{
    require_once $_ENV['path_engine'].'/lib/sql/PHPSQLParser.php';

    $parser = new PHPSQLParser();
    $p = $parser->parse($sql);
    $sid = md5($sql.$_ENV["lang"].$_ENV["lang"]);

    foreach ($p as $r => $a) {
        foreach ($a as $e) {
            wbt_route($sid, $e, $r);
        }
    }

    $table = array();
    $join = array();
    foreach ($_ENV['sql'][$sid]['FROM'] as $key => $t) {
        if (!isset($t['join'])) {
            // join пока не работает
            $table['name'] = $key;
            $table['table'] = $t['name'];
            $table['data'] = wbItemList(wbTable($t['name']));
        } else {
            $join[$key]['name'] = $key;
            $join[$key]['data'] = wbItemList(wbTable($t['name']));
            $join[$key]['join'] = $t['join'];
        }
    }
    if (isset($_ENV['sql'][$sid]['WHERE'])) {
        $where = wbt_where($table['name'], $_ENV['sql'][$sid]['WHERE']);
    }

    $object = new ArrayObject($table['data']);
    foreach ($object as $key => $item) {
        $call = $table['table'].'AfterReadItem';
        if (is_callable($call)) {
            $item = $call($item);
        }
        $object[$key] = $item;
    }
    $iterator = new wbt_filter($object->getIterator(), 'where', array('where' => $where, 'table' => $table['name'], 'join' => $join));
    $table['data'] = iterator_to_array($iterator);
    $iterator->rewind();

    unset($_ENV['sql'][$sid]);

    return $table['data'];
}

function wbArrayFilter($list,$options) {
    foreach((array)$list as $key => $item) {
        if (!wbItemFilter($item, (object)$options)) unset($list[$key]);
    }
    return $list;
}


function wbItemFilter($item, $options, $field = null)
{
    (isset($item['id']) && !isset($item['_id'])) ? $item['_id'] = $item['id'] : null ;
    isset($options->filter) ? $filter = $options->filter : $filter = $options;
    isset($options->context) ? $context = wbAttrToArray($options->context) : $context = ['_id','header','text','articul'];
    isset($item['_form']) ? $item = wbTrigger('form', __FUNCTION__, 'beforeItemFilter', [$item['_form']] , $item) : null;

    $fields = new Dot();
    $fields->setReference($item);
    if (count($context)) {
        $text = [];
        foreach ($context as $c) {
            $val = $fields->get($c);
            (array)$val === $val ? $fld = json_encode($val) : null;
            $text[] = $val;
        }
        $item['_context'] = @implode(' ', $text);
        strtolower(trim($item['_context']));
    }
    $result = true;
    foreach ((array)$filter as $fld => $expr) {
        if ((array)$expr !== $expr && substr($fld,0,1) !== '$') {
					if (is_bool($fields->get($fld)) AND !is_bool($expr)) {
							if ($expr == 'true') {
									$expr = true;
							} else if ($expr == 'false') {
									$expr = false;
							}
					}
            if ($fields->get($fld).'' !== ''.$expr) {
                $result = false;
            }
        } else {
            if ($fld == '$or') {
                $result = false;
                foreach($expr as $field => $orFilter) {
                    wbItemFilter($item, $orFilter, $field) == true ? $result = true : null;
                }
            } else if ($fld == '$and') {
                $result = true;
                foreach($expr as $field => $andFilter) {
                    if (wbItemFilter($item, $andFilter, $field) == false) {
                      $result = false;
                      break;
                    }
                }
            } else if ($fld == '$in') {
                $val = $expr[0];
                $arr = $expr[1];
                if (substr($arr,0,1) == '$') {
                    $arr = $fields->get(substr($arr,1));
                    (array)$arr === $arr ? null : $arr = json_decode($arr,true);
                    if (in_array($val,$arr)) {$result;} else {$result = false;}
                }
            } else if (in_array($fld,['$gte','$lte','$gt','$lt','='])) {
                    $field == null ? $fldname = array_key_first($expr) : $fldname = $field;
                    $result = wbItemFilter($item, [$fldname=>[$fld => $expr]]);
            } else {
                foreach ($expr as $cond => $val) {
                    $field = $fields->get($fld);
                    (array)$field === $field ?  $field = wbJsonEncode($field) : null;
                    if (is_numeric($field) && is_numeric($val)) {
                        $field = $field * 1;
                        $val = $val * 1;
                    }
                    switch($cond) {
                        case '$ne':
                            $field == $val ? $result = false : $result;
                            break;
                        case '$not':
                            $field === $val ? $result = false : $result;
                            break;
                        case '$like':
                            preg_match('/'.$val.'/ui', $field) ? $result : $result = false;
                            break;
                        case '$gte':
                            $field >= $val ? $result : $result = false;
                            break;
                        case '$lte':
							$field <= $val ? $result : $result = false;
                            break;
                        case '$gt':
							$field > $val ? $result : $result = false;
                            break;
                        case '$lt':
							$field < $val ? $result : $result = false;
                            break;
                        case '$nin':
                            !in_array($field,(array)$val) ? $result : $result = false;
                            break;
                        case '$in':
                            in_array($field,(array)$val) ? $result : $result = false;
                            break;
                        case '$regex':
                            preg_match('/'.$val.'/', $field) ? $result : $result = false;
                            break;
                    }

                }

            }
        }
        if ($result == false) {
            break;
        }
    }
    return $result;
}

function wbWhereItem($item, $where = null)
{
    if (null == $where) {
        return true;
    }
    $where = htmlspecialchars_decode($where);
    $res = true;
    if (strpos($where, "}}")) {
        $where = wbSetValuesStr($where, $item);
    }

    if ('%' == $where[0]) {
        $phpif = substr($where, 1);
    } else {
        $phpif = wbWherePhp($where, $item);
    }
    if ($phpif > '') {
        //echo $where."<br>";
        //echo $phpif."<br>";
        eval('return $res = ( '.$phpif.' );');
    }

    return $res;
}

function wbWherePhp($str = '', $item = array())
{
    if (strpos($str, "}}")) {
        $str = wbSetValuesStr($str, $item);
        //$str=preg_replace("~\{\{([^(}})]*)\}\}~","",$str);
        $str=preg_replace("~\{\{(.*)\}\}~", "", $str);
    }
    $cache=md5($str);
    if (!isset($_ENV["cache"][__FUNCTION__])) {
        $_ENV["cache"][__FUNCTION__]=array();
    }
    if (isset($_ENV["cache"][__FUNCTION__][$cache])) {
        return $_ENV["cache"][__FUNCTION__][$cache];
    }
    $exclude = array(
        'AND'	=> 0,
        'OR'	=> 0,
        'ARRAY'	=> 0,
        'LIKE'		=>array("func2"=>"wbWhereLike"),
        'IN_ARRAY'	=>array("func1"=>"in_array"),
        'IN'		=>array("func"=>"in_array"),
        'NOT_LIKE'	=>array("func2"=>"!wbWhereNotLike"),
        'NOT_IN_ARRAY'	=>array("arr"=>"!in_array"),
        'NOT_IN'	=>array("arr"=>"!in_array"),
    );
    $cond=array('<','>','=','==','>=','<=','!=','!==','#',"(",")","<>",",");
    $re = '/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^"\\\\]|\\\\.)*\'|\{\{([^(}})]*)\}\}|\w+(?!\")\b|\[(.*?)\]|[=!#<>\,]+|[\(\)]/ium';
    preg_match_all($re, $str, $arr, PREG_SET_ORDER);
    $str="";
    $len=0;
    $flag=0;
    foreach ($arr as $index => $fld) {
        $fld=$fld[0];
        $sup=strtoupper($fld);
        $exc=isset($exclude[$sup]);
        $con=in_array($fld, $cond);
        if ($flag==1) {
            $flag=2;
        }
        if ((isset($item[$fld]) and ((array)$item[$fld] === $item[$fld])) or isset($tmpfld)) {
            if (isset($arr[$index+1]) and substr($arr[$index+1][0], 0, 1) == "[") {
                if (!isset($tmpfld)) {
                    $tmpfld = str_replace("{$fld}", ' $item["'.$fld.'"]', $fld);
                    $flag=3;
                } else {
                    $tmpfld.= $fld;
                }
            }
        }

        if ($flag!==3) {
            if ((substr($fld, 0, 1)!=='"' or substr($fld, 0, 1)!=="'") and !$exc and !$con) {
                if (isset($item[$fld])) {
                    if (is_array($item[$fld])) {
                        $fld="'".wbJsonEncode($item[$fld])."'";
                        //$fld='wbJsonEncode($item["'.$fld.'"])';
                        if ($fld=="null") {
                            $fld=' "[]" ';
                        } else {
                            $fld=htmlentities($fld);
                        }
                    } else {
                        $fld = str_replace("{$fld}", ' $item["'.$fld.'"] ', $fld);
                    }
                } elseif ((substr($fld, 0, 1)=='"' or substr($fld, 0, 1)=="'") or $fld=="''" or $fld=='""') {
                    // строки в кавычках
                } elseif ($fld > "" and $fld!=="''" and $fld!=='""') {
                    if (!is_numeric($fld) and is_string($fld)) {
                        $fld = str_replace("{$fld}", ' $item["'.$fld.'"] ', $fld);
                    }
                }
            } elseif ($exc and $flag==0) {
                $prev=substr($str, -$len);
                if (isset($exclude[$sup]) and isset($exclude[$sup]["func1"])) {
                    $str=substr($str, 0, -$len);
                    if ($str>"") {
                        $str.="(";
                    }
                    $str.=$exclude[$sup]["func1"];
                    $fld="";
                } elseif (isset($exclude[$sup]) and isset($exclude[$sup]["func2"])) {
                    $str=substr($str, 0, -$len);
                    $str.=$exclude[$sup]["func2"]."(".$prev;
                    $flag = 1;
                    $fld="";
                } elseif (isset($exclude[$sup]) and isset($exclude[$sup]["arr"])) {
                    $str=substr($str, 0, -$len);
                    $str.=$exclude[$sup]["arr"]."(".$prev.", array";
                    $flag = 4;
                    $fld="";
                } elseif (isset($exclude[$sup]) and isset($exclude[$sup]["func1"])) {
                    $fld=$exclude[$sup]["func1"];
                }
            } elseif ($exc and $flag==4) {
                $str.=")";
                $flag=0;
            } elseif ($con) {
                $fld = strtr($fld, array(
                    '>' => '>',
                    '<' => ' < ',
                    '>=' => ' >= ',
                    '<=' => ' <= ',
                    '<>' => ' !== ',
                    '!=' => ' !== ',
                    '!==' => ' !== ',
                    '#' => ' !== ',
                    '==' => ' == ',
                    '=' => ' == ',
                ));
                if ($str=="") {
                    $str='""';
                }
            }
        }
        if ($flag==3 and (!isset($arr[$index+1]) or substr($arr[$index+1][0], 0, 1) !== "[")) {
            $fld=wbSetQuotes($tmpfld.$fld);
            eval('$arr=is_array('.$fld.');');
            if ($arr) {
                eval('$fld=wbJsonEncode('.$fld.');');
            }
            $str.=" ".$fld;
            $flag=0;
        } elseif ($flag==2) {
            $str.=", ".$fld." ) ";
            $flag=0;
        } elseif ($flag!==2 and $flag!==3) {
            $len=strlen($fld);
            $str.=" ".$fld;
        }
    }
    if ($flag==4) {
        $str.=")";
    }
    //$str=preg_replace("~\{\{([^(}})]*)\}\}~","",$str);
    $_ENV["cache"][__FUNCTION__][$cache]=$str;
    return $str;
}

function wbAuthGetContents($url, $get=null, $username=null, $password=null)
{
    if (func_num_args()==3) {
        $password=$username;
        $username=$get;
        $get=array();
    }
    if (!is_array($get)) {
        $get=(array)$get;
    }
    $cred = sprintf('Authorization: Basic %s', base64_encode("$username:$password"));
    $opts = array(
                'http'=>array(
                    'method'=>'GET',
                    'header'=>$cred
                    ."\r\nCookie: ".session_name()."=".session_id()
                    ."\r\nContent-Type: application/x-www-form-urlencoded",
                    'content'=>$get
                ),
                 "ssl"=>array(
                     "verify_peer"=>false,
                     "verify_peer_name"=>false,
                 )
            );
    $context = stream_context_create($opts);
    session_write_close();
    $result = file_get_contents($url, false, $context);
    return $result;
}

function wbAuthPostContents($url, $post=null, $username=null, $password=null)
{
    if (func_num_args()==3) {
        $password=$username;
        $username=$get;
        $post=array();
    }
    if (!is_array($post)) {
        $post=(array)$post;
    }

    $cred = sprintf('Authorization: Basic %s', base64_encode("$username:$password"));
    $post=http_build_query($post);
    $opts = array(
                'http'=>array(
                    'method'=>'POST',
                    'header'=>$cred
                    ."\r\nCookie: ".session_name()."=".session_id()
                    ."\r\nContent-Length: ".strlen($post)
                    ."\r\nContent-Type: application/x-www-form-urlencoded",
                    'content'=>$post
                ),
                 "ssl"=>array(
                     "verify_peer"=>false,
                     "verify_peer_name"=>false,
                 )
            );
    $context = stream_context_create($opts);
    session_write_close();
    $result = file_get_contents($url, false, $context);
    return $result;
}

function wbPasswordCheck($str, $pass)
{
    wbPasswordMake($str) == $pass ? $res=true : $res = false;
    return $res;
}

function wbPasswordMake($str)
{
    if (is_callable('passwordMake')) {
        return passwordMake($str);
    } else {
        return md5($str);
    }
}

function wbPasswordGenerate($length = 8)
{
    if (is_callable('passwordGenerate')) {
        return passwordGenerate($length);
    } else {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        return substr(str_shuffle($chars),0,$length);
    }
}

function wbRouterGet($requestedUrl = null)
{
    return wbRouter::getRoute($requestedUrl);
}

function wbRouterAdd($route = null, $destination = null)
{
    if (null == $route) { // Роутинг по-умолчанию
        $route = wbRouterRead();
    }
    wbRouter::addRoute($route, $destination);
}

function wbAttrToArray($attr)
{
    return wbArrayAttr($attr);
}

function wbAttrAddData($data, $Item, $mode = false)
{
    $data = stripcslashes(html_entity_decode($data));
    $data = json_decode($data, true);
    if (!is_array($Item)) {
        $Item = array($Item);
    }
    if (false == $mode) {
        $Item = array_merge($data, $Item);
    }
    if (true == $mode) {
        $Item = array_merge($Item, $data);
    }

    return $Item;
}

function wbGetWords($str, $w = 100)
{
    $res = '';
    $str = html_entity_decode(strip_tags(trim($str)));
    $arr = explode(' ', trim($str));
    for ($i = 0; $i <= $w; ++$i) {
        if (isset($arr[$i])) {
            $res = $res.' '.$arr[$i];
        }
    }
    if (count($arr) > $w) {
        $res = $res.'...';
    }
    $res = trim($res);

    return $res;
}

function wbCheckUser($login, $type = 'email', $pass = null) {
    $users = wbItemList("users", ['filter' => [
        $type => $login,
        'isgroup' => ['$ne'=>'on'],
        'active' => 'on'
    ]]);
    if (intval($users['count']) == 0) {
        return false;
    }
    $user = array_shift($users['list']);
    $user['group'] = wbItemRead("users", $user['role']);
    $user['group'] ? null : $user['group'] = ['active'=>'on']; 
    $user = wbArrayToObj($user);
    if ($user->group->active == "on" and $pass == null) {
        return $user;
    } else if ($user->group->active == "on" and wbPasswordCheck($pass, $user->password)) {
        return $user;
    }
    return false;
}

function wbPhoneFormat($phoneNumber) {
    $phoneNumber = preg_replace('/[^0-9]/','',$phoneNumber);

    if(strlen($phoneNumber) > 10) {
        $countryCode = substr($phoneNumber, 0, strlen($phoneNumber)-10);
        $areaCode = substr($phoneNumber, -10, 3);
        $nextThree = substr($phoneNumber, -7, 3);
        $lastFour = substr($phoneNumber, -4, 4);

        $phoneNumber = '+'.$countryCode.' ('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if(strlen($phoneNumber) == 10) {
        $areaCode = substr($phoneNumber, 0, 3);
        $nextThree = substr($phoneNumber, 3, 3);
        $lastFour = substr($phoneNumber, 6, 4);

        $phoneNumber = '('.$areaCode.') '.$nextThree.'-'.$lastFour;
    }
    else if(strlen($phoneNumber) == 7) {
        $nextThree = substr($phoneNumber, 0, 3);
        $lastFour = substr($phoneNumber, 3, 4);

        $phoneNumber = $nextThree.'-'.$lastFour;
    }

    return $phoneNumber;
}

function wbSetValuesStr($tag = "", $Item = array(), $limit = 2, $vars = null)
{
    if ((object)$tag === $tag) $tag = $tag->outer();
    if (!strpos($tag, "}}")) return $tag;

    $processor = new WEProcessor($Item);
    $tag = $processor->substitute($tag);
    return $tag;
}



// добавление кавычек к нечисловым индексам
function wbSetQuotes($In)
{
    $err = false;
    $mask = '`\[(%*[\w\d]+)\]`u';
    $nBrackets = preg_match_all($mask, $In, $res, PREG_OFFSET_CAPTURE);				// найти индексы без кавычек
    if ($nBrackets === false) {
        echo 'Ошибка в шаблоне индексов. Обратитесь к разработчику.' . '<br>';
        $err = true;
    } else {
        if ($nBrackets == 0) {
            if (substr($In, 0, 2) != '["') {
                if (!is_numeric($In)) {
                    $In = '"' . $In . '"';
                }
                $In = '[' . $In . ']';
            }
        } else {
            for ($i = 0; $i < $nBrackets; $i++) {
                if (!is_numeric($res[1][$i][0])) {
                    $In = str_replace('['.$res[1][$i][0].']', '["'.$res[1][$i][0].'"]', $In);
                }
            }
        }
    }
    return $In;
}

// заменяем &quot на "
function wbChangeQuot($Tag)
{
    $mask = '`&quot[^;]`u';
    $nQuot = preg_match_all($mask, $Tag, $res, PREG_OFFSET_CAPTURE);				// найти &quot без последеующего ;
    if ($nQuot === false) {
        echo 'Ошибка в шаблоне &quot. Обратитесь к разработчику.' . '<br>';
        $err = true;
        $In = $tag;
    } else {
        if ($nQuot == 0) {
            $In = $Tag;
        } else {
            $In = '';
            $startIn = 0;		// начальная позиция текста за предыдущей заменой
            for ($i = 0; $i < $nQuot; $i++) {
                $beforSize = $res[0][$i][1] - $startIn;
                $In .= substr($Tag, $startIn, $beforSize) . '"';		// исходный текст между предыдущей и текущей &quot
                $startIn += $beforSize + 5;
                if ($i+1 == $nQuot) {		// это была последняя &quot
                    $In .= substr($Tag, $startIn, strlen($Tag) - $startIn);
                }
            }
        }
    }
    return $In;
}

function wbRole($role, $userId = null)
{
    $res = false;
    !is_array($role) ? $role = wbAttrToArray($role) : null;
    !isset($_SESSION['user_role']) ? $_SESSION['user_role'] = '' : null;
    if (null == $userId) {
        $res = in_array($_SESSION['user_role'], $role, true);
    } else {
        $user = wbReadItem('users', $userId);
        $res = in_array($user['role'], $role, true);
    }

    return $res;
}


function wbListForms($exclude = true)
{
    if (isset($_ENV["list_forms"])) {
        return $_ENV["list_forms"];
    }
    if (true == $exclude) {
        $exclude = array('forms/common', 'forms/admin', 'forms/source', 'forms/snippets');
    } elseif (!is_array($exclude)) {
        $exclude = array('forms/snippets');
    }
    $list = array();
    $eList = wbListFilesRecursive($_ENV['path_engine'].'/forms', true);
    $aList = wbListFilesRecursive($_ENV['path_app'].'/forms', true);
    $jList = wbListFilesRecursive($_ENV['path_app'].'/database', true);
    $arr = $eList;
    foreach ($aList as $a) {
        $arr[] = $a;
    }
    foreach ($jList as $a) {
        $arr[] = $a;
    }
    unset($eList,$aList);
    foreach ($arr as $i => $data) {
        $name = $data['file'];
        $path = $data['path'];
        $path = str_replace(array($_ENV['path_engine'], $_ENV['path_app']), array('.', '.'), $path);
        $inc = strpos($name, '.inc');
        $ext = explode('.', $name);
        $ext = $ext[count($ext) - 1];
        $name = substr($name, 0, -(strlen($ext) + 1));
        $name = explode('_', $name);
        $name = $name[0];
        foreach ($exclude as $exc) {
            if (!strpos($path, $exc)) {
                $flag = true;
            } else {
                $flag = false;
            }
        }
        if (('php' == $ext or 'htm' == $ext or 'json' == $ext) && !$inc && true == $flag && $name > '' && $name !== "admin" && !in_array($name, $list, true)) {
            $list[] = $name;
        }
    }
    unset($arr);
    //$merchE=wbCheckoutForms(true);
    //$merchA=wbCheckoutForms();
    //foreach($merchE as $m) {if (in_array($m["name"],$list)) {unset($list[array_search($m["name"],$list)]);}}
    //foreach($merchA as $m) {if (in_array($m["name"],$list)) {unset($list[array_search($m["name"],$list)]);}}
    if (in_array('form', $list, true)) {
        unset($list[array_search('form', $list, true)]);
    }
    $_ENV["list_forms"] = $list;
    return $list;
}

function wbListDrivers()
{
    $arr = [];
    $p=[$_ENV['path_engine'].'/drivers',$_ENV['path_app'].'/drivers'];
    foreach ($p as $d) {
        if (is_dir($d)) {
            $list = scandir($d);
        }
        foreach ($list as $e) {
            if (!in_array($e, [".",".."]) and substr($e, 1)!=="_" and !in_array($e, $arr) and is_dir($d.'/'.$e) and is_file($d.'/'.$e.'/'.$e.".php")) {
                $arr[$e] = $d.'/'.$e.'/'.$e.".php";
            }
        }
    }
    return $arr;
}


function wbListModules()
{
    $arr = [];
    $p=[$_ENV['path_engine'].'/modules',$_ENV['path_app'].'/modules'];
    $flag_liter = '';
    foreach ($p as $d) {
        is_dir($d) ? $list = scandir($d) : null;
        foreach ($list as $e) {
            if (!in_array($e, [".",".."]) and substr($e, 1)!=="_" and !in_array($e, $arr) and is_dir($d.'/'.$e) and is_file($d.'/'.$e.'/'.$e.".php")) {
                $arr[$e] = [
                    'id' => $e,
                    'module' => $d.'/'.$e.'/'.$e.".php",
                    'path' => $d.'/'.$e,
                    'sett' => '',
                    'liter' => strtoupper(substr($e,0,1)),
                    'divider' => ''
                ];
                if (is_file($d.'/'.$e.'/'.$e.'_sett.php')) $arr[$e]['sett'] = $d.'/'.$e.'/'.$e.'_sett.php';
            }
        }
    }
    $list = wbArraySort($arr,['id'=>'a']);
    foreach($list as &$arr) {
        if ($arr['liter'] > '' and $flag_liter !== $arr['liter'] and $arr['sett']> '') {
            $flag_liter = $arr['divider'] = $arr['liter'];
        }
    }
    return $list;
}

function wbListTags()
{
    if (isset($_ENV["list_tags"])) {
        return $_ENV["list_tags"];
    }
    $list = array();
    $eList = wbListFilesRecursive($_ENV['path_engine'].'/tags', true);
    $aList = wbListFilesRecursive($_ENV['path_app'].'/tags', true);
    $arr = $eList;
    foreach ($aList as $a) {
        $arr[] = $a;
    }
    unset($eList,$aList);
    foreach ($arr as $i => $data) {
        $name = $data['file'];
        $path = $filepath = $data['path'];
        $path = str_replace(array($_ENV['path_engine'], $_ENV['path_app']), array('.', '.'), $path);
        $inc = strpos($name, '.inc');
        $ext = explode('.', $name);
        $ext = $ext[count($ext) - 1];
        $name = substr($name, 0, -(strlen($ext) + 1));
        $name = explode('_', $name);
        $name = $name[0];
        if (('php' == $ext) && !$inc && $name > '' && !in_array($name, $list, true)) {
            $list[strtolower($name)] = $filepath."/{$name}.{$ext}";
        }
    }
    unset($arr);
    foreach (array_keys($list) as $name) {
        require_once $list[$name];
    }
    $_ENV["list_tags"] = $list;
    return $list;
}

function wbListLocales(&$app = null)
{
    !$app ? $app = new wbApp() : null;
    isset($_ENV['settings']['locales']) ? $langs = wbAttrToArray($_ENV['settings']['locales']) : $langs = [];
    count($langs) ? null : $langs = ['ru','en'];
    return $langs;
/*
    $out = $app->getForm("admin", "common.ini", true);
    $out->setLocale(parse_ini_string(trim($out->text()), true));
    return $out->locale;
    */
}

function wbListFormsFull()
{
    $list = array();
    $types = array('engine', 'app');
    foreach ($types as $type) {
        $list[$type] = array();
        $fList = wbListFilesRecursive($_ENV['path_'.$type].'/forms');
        foreach ($fList as $fname) {
            $inc = strpos($fname, '.inc');
            $ext = explode('.', $fname);
            $ext = $ext[count($ext) - 1];
            $name = substr($fname, 0, -(strlen($ext) + 1));
            $tmp = explode('_', $name);
            $form = $tmp[0];
            unset($tmp[0]);
            $mode = implode('_', $tmp);
            //$uri_path=str_replace($_SESSION["root_path"],"",$_ENV["path_".$type]);
            $uri_path = '';
            $data = array(
                        'type' => $type,
                        'path' => $_ENV['path_'.$type]."/forms/{$form}/".$name.".{$ext}",
                        'dir' => "/forms/{$form}",
                        'uri' => $uri_path."/forms/{$form}/".$fname,
                        'form' => $form,
                        'file' => $fname,
                        'ext' => $ext,
                        'name' => $name,
                        'mode' => $mode,
                    );
            $list[$type][] = $data;
        }
    }

    return $list;
}

function wbArrayKeyId($array) {
    // присваивает ключ массива по _id или id
    if (isset($array[0]) && isset($array[0]['_id'])) {
        @array_combine(array_column($array, '_id'), $array);
    } else if (isset($array[0]) && isset($array[0]['id'])) {
        @array_combine(array_column($array, '_id'), $array);
    }
    return $array;
}



function wbArraySort($array = array(), $args = array('votes' => 'a'))
{
    // через jsonq не всегда работает, поэтому такой вариант !!!!
    // если передан атрибут, то предварительно готовим массив параметров
    if (is_string($args) && $args > '') {
        $args = wbArrayAttr($args);
        $param = array();
        foreach ($args as $ds) {
            $tmp = explode(':', $ds);
            if (!isset($tmp[1])) {
                $tmp[1] = 'a';
            }
            $param[$tmp[0]] = $tmp[1];
        }
        $args = $param;
        unset($param,$tmp,$ds);
    }
    // сортировка массива по нескольким полям
    uasort($array, function ($a, $b) use ($args) {
        $res = 0;
        $a = (object) $a;
        $b = (object) $b;
        foreach ($args as $k => $v) {
            if (isset($a->$k)) {
                $a->$k=mb_strtolower($a->$k);
            }
            if (isset($b->$k)) {
                $b->$k=mb_strtolower($b->$k);
            }
            if (isset($a->$k) && isset($b->$k)) {
                if ($a->$k == $b->$k) {
                    continue;
                }
                $res = ($a->$k < $b->$k) ? -1 : 1;
                if ('d' == $v) {
                    $res = -$res;
                }
                break;
            }
        }

        return $res;
    });
    
    return $array;
}

function wbArrayAttr($attr)
{
    $attr = str_replace(',', ' ', $attr);
    $attr = str_replace(';', ' ', $attr);
    $attr = explode(" ", trim($attr));
    foreach($attr as $k => $v) {
        if ($v == "") unset($attr[$k]);
    }
    return $attr;
}

    function wbNormalizePath($path)
    {
    	strpos($path,'://') ? $net = true : $net = false;
        $patterns = array('~/{2,}~', '~/(\./)+~', '~([^/\.]+/(?R)*\.{2,}/)~', '~\.\./~');
        $replacements = array('/', '/', '', '');
        $path = preg_replace($patterns, $replacements, $path);
        $net ? $path = str_replace(':/', '://', $path) : null;
        return $path;
    }


function wbClearValues($out, $rep='')
{
    $out = preg_replace('/\{\{([^\}]+?)\}\}+|<script.*text\/template.*?>.*?<\/script>(*SKIP)(*F)|<template.*?>.*?<\/template>(*SKIP)(*F)/isumx', $rep, $out);
    return $out;
}

function wbIsJson($string = '') {
    if ((array)$string === $string) return false;
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}


function wbListTpl()
{
    if (isset($_ENV["list_tpl"])) {
        return $_ENV["list_tpl"];
    }
    $dir=$_ENV['path_tpl'];
    $list = [];
    $result = [];
    if (is_dir($dir)) {
        $list=wbListFilesRecursive($dir, true);
        foreach ($list as $l=> $val) {
            if (('.php' == substr($val['file'], -4) or '.htm' == substr($val['file'], -4) or '.tpl' == substr($val['file'], -4)) and !strpos($val['file'],'.inc.')) {
                $path = str_replace($dir, '', $val['path']);
                $res = substr($path.'/'.$val['file'], 1);
                $result[] = $res;
            }
        }
    }
    sort($result);
    $_ENV["list_tpl"] = $result;
    return $result;
}


function is_tag( $tag = '' ) {
    global $wp_query;
 
    if ( ! isset( $wp_query ) ) {
        _doing_it_wrong( __FUNCTION__, __( 'Conditional query tags do not work before the query is run. Before then, they always return false.' ), '3.1.0' );
        return false;
    }
 
    return $wp_query->is_tag( $tag );
}

    function wbListFilesRecursive($dir, $path = false)
    {
        $list = array();
        if (is_dir($dir)) {
            $stack[] = $dir;
        } else {
            $stack=array();
        }
        while ($stack) {
            $thisdir = array_pop($stack);
            if (is_dir($thisdir) and $dircont = scandir($thisdir)) {
                $i = 0;
                $idx = 0;
                while (isset($dircont[$i])) {
                    if ('.' !== $dircont[$i] && '..' !== $dircont[$i]) {
                        $current_file = "{$thisdir}/{$dircont[$i]}";
                        if (is_file($current_file)) {
                            if (true == $path) {
                                $list[] = array(
    'file' => "{$dircont[$i]}",
    'path' => "{$thisdir}",
    );
                            } else {
                                $list[] = "{$dircont[$i]}";
                            }
                            ++$idx;
                        } elseif (is_dir($current_file)) {
                            $stack[] = $current_file;
                        }
                    }
                    ++$i;
                }
            }
        }

        return $list;
    }

    function wbArrayWhere($arr, $where)
    {
        $res = array();
        $where=wbSetValuesStr($where);
        foreach ($arr as $key => $val) {
            if (wbWhereItem($val, $where)) {
                $res[]=$arr[$key];
            }
            unset($arr[$key]);
        }
        unset($arr);
        return $res;
    }

    function wbEval($code)
    {
        $code = str_replace($_ENV['stop_func'],'',$code);
        try {
            @$res = eval('return '.$code.';');
        } catch (\Throwable $th) {
            $res = false;
        }
        return $res;
    }

    function wbCallFunc($func)
    {
        $exclude = $_ENV['stop_func'];
        $func=ltrim($func);
        foreach ($exclude as $f) {
            $l = strlen($f);
            if (substr($func, 0, $l) == $f) {
                echo "Error!!! PHP function <b>{$func}</b> is disabled !";
                die;
            }
        }
        if (strpos($func, "(")) {
            $res = eval('return '.$func.';');
        } else {
            $res = eval('return '.$func.'();');
        }

        return $res;
    }


    function wbCallFormFunc($name, $Item, $form = null, $mode = null)
    {
        if (!isset($_GET['mode'])) {
            $_GET['mode'] = '';
        }
        if (!isset($_GET['form'])) {
            $_GET['form'] = '';
        }
        if (null == $mode) {
            $mode = $_GET['mode'];
        }
        if ('' == $mode) {
            $mode = 'list';
        }
        if (null == $form) {
            if (isset($Item['form']) && $Item['form'] > '') {
                $form = $Item['form'];
            } else {
                $form = $_GET['form'];
            }
        }
        $sf = $_GET['form'];
        $_GET['form'] = $form;
        // formCurrentInclude($form);
        $func = $form.$name;
        $_func = '_'.$func;
        //$Item=wbItemToArray($Item);
        if (is_callable($func)) {
            $Item = $func($Item, $mode);
        } else {
            if (is_callable($_func)) {
                $Item = $_func($Item, $mode);
            }
        }
        $_GET['form'] = $sf;

        return $Item;
    }

    function wbTranslit($textcyr = null, $textlat = null)
    {
        $cyr = array(
    'ё', 'ж', 'ч', 'щ', 'ш', 'ю', 'а', 'б', 'в', 'г', 'д', 'е', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ъ', 'ы', 'ь', 'э', 'я',
    'Ё', 'Ж', 'Ч', 'Щ', 'Ш', 'Ю', 'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ы', 'Ь', 'Э', 'Я', );
        $lat = array(
    'yo', 'j', 'ch', 'sch', 'sh', 'u', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'i', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', '`', 'y', '', 'e', 'ya',
    'yo', 'j', 'Ch', 'Sch', 'Sh', 'U', 'A', 'B', 'V', 'G', 'D', 'E', 'Z', 'I', 'I', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'c', '`', 'Y', '', 'E', 'ya', );
        if ($textcyr) {
            return str_replace($cyr, $lat, $textcyr);
        } elseif ($textlat) {
            return str_replace($lat, $cyr, $textlat);
        } else {
            return null;
        }
    }

    function wbDigitsOnly($str) {
        return preg_replace('/\D/', '', $str);
    }

    function wbAlphaOnly($str) {
        return preg_replace('/[^a-zA-Zа-яА-Я]/ui', '', $str);
    }

    function wbAlphaDigitsOnly($str) {
        return preg_replace('/[^a-zA-Zа-яА-Я0-9]/ui', '', $str);
    }

    function wbLiteralOnly($str) {
        $str = preg_replace('/[^a-zA-Zа-яА-Я0-9?\_\-[:space:]]/ui', '', $str);
        $str = preg_replace( '/\s+/', ' ', $str);
        return $str;
    }

    function wbUrlOnly($str) {
        $str = wbLiteralOnly($str);
        $str = wbTranslit($str);
        $str = str_replace(' ','_',$str);
        return $str;

    }

    function wbUsageStat()
    {
        (isset($_ENV["cache_used"]) and $_ENV["cache_used"] == 'on') ? $cache = 'Yes' : $cache = 'No';
        $mem = memory_get_usage();
        $mem = wbFormatBytes($mem, 2);
        $peak = memory_get_peak_usage();
        $peak = wbFormatBytes($peak, 2);
        $steps = $_ENV['wb_steps'];

        $sec = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
        echo "<div>Memory usage : {$mem}, Peak: {$peak}, Rendering: {$sec} sec, Cached: {$cache}, Steps: {$steps}</div>";
    }



    function wbFormatBytes($size, $precision = 2)
{
    $base = log($size, 1024);
    $suffixes = array('', 'K', 'M', 'G', 'T');

    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

    function is_email($email)
    {
        $res=true;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $res=false;
        }
        return $res;
    }

    function wbBr2nl($str)
    {
        $str = preg_replace('/(rn|n|r)/', '', $str);
        return preg_replace('=<br */?>=i', 'n', $str);
    }

    function wbDate($format,$date = null,$offset='') {
        $date == null ? $date = 'now' : null;
        return date($format,strtotime($date.$offset));
    }


    function wbCheckPhpCode($code)
    {
        $file=$_ENV["path_app"]."/uploads/".wbNewId().".php";
        $umask=umask(0);
        file_put_contents($file, $code);
        umask($umask);
        exec("php -l ".$file, $error, $code);
        wbFileRemove($file);
        // ошибок нет
        if ($code == 0) {
            return true;
        }
        // ошибки есть
        return false;
    }

function wbMinifyHtml($input)
{
    if (trim($input) === "") {
        return $input;
    }
    // Remove extra white-space(s) between HTML attribute(s)
    $input = preg_replace_callback('#<([^\/\s<>!]+)(?:\s+([^<>]*?)\s*|\s*)(\/?)>#s', function ($matches) {
        return '<' . $matches[1] . preg_replace('#([^\s=]+)(\=([\'"]?)(.*?)\3)?(\s+|$)#s', ' $1$2', $matches[2]) . $matches[3] . '>';
    }, str_replace("\r", "", $input));
    // Minify inline CSS declaration(s)
    if (strpos($input, ' style=') !== false) {
        $input = preg_replace_callback('#<([^<]+?)\s+style=([\'"])(.*?)\2(?=[\/\s>])#s', function ($matches) {
            return '<' . $matches[1] . ' style=' . $matches[2] . minify_css($matches[3]) . $matches[2];
        }, $input);
    }
    if (strpos($input, '</style>') !== false) {
        $input = preg_replace_callback('#<style(.*?)>(.*?)</style>#is', function ($matches) {
            return '<style' . $matches[1] .'>'. minify_css($matches[2]) . '</style>';
        }, $input);
    }
    if (strpos($input, '</script>') !== false) {
        $input = preg_replace_callback('#<script(.*?)>(.*?)</script>#is', function ($matches) {
            return '<script' . $matches[1] .'>'. minify_js($matches[2]) . '</script>';
        }, $input);
    }

    return preg_replace(
        array(
            // t = text
            // o = tag open
            // c = tag close
            // Keep important white-space(s) after self-closing HTML tag(s)
            '#<(img|input)(>| .*?>)#s',
            // Remove a line break and two or more white-space(s) between tag(s)
            '#(<!--.*?-->)|(>)(?:\n*|\s{2,})(<)|^\s*|\s*$#s',
            '#(<!--.*?-->)|(?<!\>)\s+(<\/.*?>)|(<[^\/]*?>)\s+(?!\<)#s', // t+c || o+t
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<[^\/]*?>)|(<\/.*?>)\s+(<\/.*?>)#s', // o+o || c+c
            '#(<!--.*?-->)|(<\/.*?>)\s+(\s)(?!\<)|(?<!\>)\s+(\s)(<[^\/]*?\/?>)|(<[^\/]*?\/?>)\s+(\s)(?!\<)#s', // c+t || t+o || o+t -- separated by long white-space(s)
            '#(<!--.*?-->)|(<[^\/]*?>)\s+(<\/.*?>)#s', // empty tag
            '#<(img|input)(>| .*?>)<\/\1>#s', // reset previous fix
            '#(&nbsp;)&nbsp;(?![<\s])#', // clean up ...
            '#(?<=\>)(&nbsp;)(?=\<)#', // --ibid
            // Remove HTML comment(s) except IE comment(s)
            '#\s*<!--(?!\[if\s).*?-->\s*|(?<!\>)\n+(?=\<[^!])#s'
        ),
        array(
            '<$1$2</$1>',
            '$1$2$3',
            '$1$2$3',
            '$1$2$3$4$5',
            '$1$2$3$4$5$6$7',
            '$1$2$3',
            '<$1$2',
            '$1 ',
            '$1',
            ""
        ),
        $input
    );
}

// CSS Minifier => http://ideone.com/Q5USEF + improvement(s)
function wbMinifyCss($input)
{
    if (trim($input) === "") {
        return $input;
    }
    return preg_replace(
        array(
            // Remove comment(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
            // Remove unused white-space(s)
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
            // Replace `0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)` with `0`
            '#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
            // Replace `:0 0 0 0` with `:0`
            '#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
            // Replace `background-position:0` with `background-position:0 0`
            '#(background-position):0(?=[;\}])#si',
            // Replace `0.6` with `.6`, but only when preceded by `:`, `,`, `-` or a white-space
            '#(?<=[\s:,\-])0+\.(\d+)#s',
            // Minify string value
            '#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
            '#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
            // Minify HEX color code
            '#(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3#i',
            // Replace `(border|outline):none` with `(border|outline):0`
            '#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
            // Remove empty selector(s)
            '#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s'
        ),
        array(
            '$1',
            '$1$2$3$4$5$6$7',
            '$1',
            ':0',
            '$1:0 0',
            '.$1',
            '$1$3',
            '$1$2$4$5',
            '$1$2$3',
            '$1:0',
            '$1$2'
        ),
        $input
    );
}

// JavaScript Minifier
function wbMinifyJs($input)
{
    if (trim($input) === "") {
        return $input;
    }
    return preg_replace(
        array(
            // Remove comment(s)
            '#\s*("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')\s*|\s*\/\*(?!\!|@cc_on)(?>[\s\S]*?\*\/)\s*|\s*(?<![\:\=])\/\/.*(?=[\n\r]|$)|^\s*|\s*$#',
            // Remove white-space(s) outside the string and regex
            '#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/)|\/(?!\/)[^\n\r]*?\/(?=[\s.,;]|[gimuy]|$))|\s*([!%&*\(\)\-=+\[\]\{\}|;:,.<>?\/])\s*#s',
            // Remove the last semicolon
            '#;+\}#',
            // Minify object attribute(s) except JSON attribute(s). From `{'foo':'bar'}` to `{foo:'bar'}`
            '#([\{,])([\'])(\d+|[a-z_][a-z0-9_]*)\2(?=\:)#i',
            // --ibid. From `foo['bar']` to `foo.bar`
            '#([a-z0-9_\)\]])\[([\'"])([a-z_][a-z0-9_]*)\2\]#i'
        ),
        array(
            '$1',
            '$1$2',
            '}',
            '$1$3',
            '$1.$3'
        ),
        $input
    );
}
?>