<?php
// Author: oleg_frolov@mail.ru
use Nahid\JsonQ\Jsonq;
use Adbar\Dot;
use DQ\DomQuery;

//use Spatie\Async\Pool;

class wbDom extends DomQuery
{
    public function __call($name, $arguments)
    {
        if (method_exists($this->getFirstElmNode(), $name)) {
            return \call_user_func_array(array($this->getFirstElmNode(), $name), $arguments);
        } elseif (substr($name, 0, 3) == 'tag') {
            $class = $name;
            $fname = strtolower(substr($name, 3));
            $file_e = $this->app->vars("_env.path_engine")."/tags/{$fname}/{$fname}.php";
            $file_a = $this->app->vars("_env.path_app")."/tags/{$fname}/{$fname}.php";
            if (is_file($file_a)) {
                require_once $file_a;
                if (class_exists($class)) {
                    return new $class($arguments[0]);
                }
            } elseif (is_file($file_e)) {
                require_once $file_e;
                if (class_exists($class)) {
                    return new $class($arguments[0]);
                }
            } else {
                unset($this->role);
            }
        }

        throw new \Exception('Unknown call '.$name);
    }

    public function outer()
    {
        return $this->getouterHtml();
    }

    public function inner($html=null)
    {
        if ($html == null) {
            return $this->getinnerHtml();
        }
        
        if (!is_string($html) && !is_object($html)) {
            $html='';
        }
        
        $is_tag = preg_match("/<[^<]+>/", $html, $res);

        $this->head ? $esc = "head" : $esc = "wb";

            $html = "<{$esc}>{$html}</{$esc}>"; // magick
            $this->html($html);         // magick
            $this->find("{$esc}")->unwrap("{$esc}"); // magick
            if (!$is_tag) $this->text($html);

        //$this->find("{$esc}")->unwrap("{$esc}");
        return $this;
    }

    public function tag()
    {
        return $this->tagName;
    }

    public function attributes()
    {
        $attributes = [];
        $attrs = $this->attributes;
        foreach ((object)$attrs as $attr) {
            $attributes[$attr->nodeName] = $attr->nodeValue;
        }
        return $attributes;
    }

    public function attrsCopy(&$dom)
    {
        $attrs = $this->attributes();

        foreach ($attrs as $attr => $value) {
            if (substr($attr, 0, 3) !== 'wb-' && $attr !== 'wb') {
                if ($attr !== 'class' && $attr !== 'name') {
                    $dom->attr($attr, $value);
                } elseif ($attr == 'class') {
                    $dom->addClass($value);
                } elseif ($attr == 'name') {
                    $dom->attr('name', $value);
                }
            }
        }

        return $this;
    }


    public function rootError()
    {
        if ($this->is(':root')) {
            $code = trim(htmlentities($this->outer()));
            $code = explode('&gt;', $code);
            $code = $code[0]."&gt";
            die("WB tag can't be a :root element!<br>".$code."<br>...");
        }
    }

    public function parents($tag = ":root")
    {
        $res = false;
        if (isset($this->parent)) {
            if ($this->parent->is($tag)) {
                $res = $this->parent;
            } else {
                $res = $this->parent->parents($tag);
            }
        } else {
            $res = $this;
        }
        return $res;
    }

    public function getField($fld)
    {
        $fields = $this->app->dot($this->item);
        return $fields->get($fld);
    }

    public function setField($fld, $data = [])
    {
        $fields = $this->app->dot($this->item);
        return $fields->set($fld, $data);
    }

    public function where($Item=null)
    {
        $res = true;
        if (!$this->params("where")) {
            return $res;
        }
        if ($this->params("where") == "") {
            return $res;
        }
        if ($Item == null) {
            $Item=$this->data;
        }
        $res = wbWhereItem($Item, $this->params->where);
        return $res;
    }

    public function hasRole($role=null)
    {
        if ($role == null && $this->role) {
            return $this->role;
        } elseif ($role !== null and $role == $this->role) {
            return true;
        } else {
            return false;
        }
    }

    public function hasAttr($attr)
    {
        $attrs = [];
        foreach ($this->attributes as $k => $v) {
            $attrs[] = $v->name;
        }
        return in_array($attr, $attrs);
    }

    public function head($head = null)
    {
        if (!isset($this->head)) {
            return false;
        }
        if ($head == null) {
            return $this->head;
        }
        //$this->head->html($head);
        if (isset($item) && $item == null) {
            $item = $this->item;
        }
    }

    public function fetch($item = null)
    {
        if (!$this->app) $this->app = $_ENV["app"];
        $tmp = $this->app->vars('_env.locale');
        isset($this->root) ? null : $this->root = $this->parents(':root')[0];

        $this->fetchStrict();
        $this->fetchLang();
        if ($this->strict OR isset($this->fetched)) return;
        if (!isset($_ENV['wb_steps'])) {$_ENV['wb_steps'] = 1;} else {$_ENV['wb_steps']++;}
        if ($item == null) $item = $this->item;
        if ($this->tagName == "head") $this->head = $this;
        $this->item = $item;
        $this->fetchParams();
        if ($this->is(":root")) {
            if ($this->func or $this->funca) $this->fetchFunc(); // так нужно для рутовых тэгов
        }
        $childrens = $this->children();
        foreach ($childrens as $wb) {
            $wb->copy($this);
            $wb->root = $this->root;
            $wb->fetchNode();
        }

        $this->setValues();
        $this->app->vars("_env.locale", $tmp);
        if ($this->app->vars('_sett.devmode') == 'on' && $this->is('[rel=preload]')) {
            $href = $this->attr('href');
            if (!strpos('?',$href) && isset($_COOKIE['devmode'])) {
                $this->attr('href',$href.'?'.$_COOKIE['devmode']);
            }
        }
        if ($this->find('.nav-pagination[data-tpl]')->length) $this->fixPagination();
        return $this;
    }

    public function fixPagination() {
        if ($this->find('.nav-pagination[data-tpl]:not(.fixed)')->length) {
            $pags = $this->find('.nav-pagination[data-tpl]');
            foreach($pags as $pag) {
                $pid = $pag->attr('data-tpl');
                if ($this->find($pid.':not(template)')->length && $pag->parent($pid)->length) {
                    $pag->removeClass('nav-pagination');
                    if ($pag->hasClass('pos-top')) {
                        $this->find($pid.':not(template)')->parent()->prepend($pag->outer());
                    } else {
                        $this->find($pid.':not(template)')->parent()->append($pag->outer());
                    }
                    
                    $pag->remove();
                }
            }
        }
    }

    public function fetchNode()
    {
        $this->fetchStrict();
        if ($this->strict OR isset($this->fetched)) {
            return;
        }
        $this->fetchParams();
        if ($this->role and ($this->func or $this->funca)) {
            $this->fetchFunc();
        }
        $this->fetch();
        $this->fetched = true;
    }

    public function fetchLang()
    {
        $langs = $this->children("wb-lang");
        if ($langs->length) {
            foreach ($langs as $wb) {
                $wb->copy($this);
                $wb->fetchNode();
            }
        }
    }

    public function fetchFunc()
    {
        foreach ($this->funca as $func) {
            new $func($this);
        }
        if ($this->func > "") {
            $func = $this->func;
            new $func($this);
        }
        $this->removeAttr("wb");
        return $this;
    }

    public function filterStrict()
    {
        if ($this->params('filter') > '' and $this->params('strict') == 'false') {
            $tmpfl = (array)$this->params('filter');
            foreach ($tmpfl as $key => $val) {
                $val = preg_replace('/^\%(.*)\%$/', "", $val);
                if ($val == '' or $val == null) {
                    unset($tmpfl[$key]);
                }
            }

            $this->params->filter = $tmpfl;
        }
    }

    public function fetchStrict()
    {
        
        if (in_array($this->tagName, ['template', 'code','textarea','pre','[wb-off]'])) {
            $this->strict = true;
            // set locale for template
            if (strpos($this->outer(),'_lang.') !== 0) {
                $locale = $this->app->vars('_env.locale');
                if (isset($locale[$_SESSION["lang"]])) $locale = $locale[$_SESSION["lang"]];
                $this->addParams(['locale'=>$locale]);
            }
            //isset($_ENV["locales"][$_SESSION["lang"]]) ? $data = ["_lang" => $_ENV["locales"][$_SESSION["lang"]]] : $data = [];
            $this->setValues();
        }
    }

    public function addParams($data) {
        $add = $data;
        if (!is_array($data)) {
            $add = json_decode($data, true);
            if (!$add) parse_str($data, $add);
        }
        $params = json_decode($this->attr('data-params'), true);
        if (!$params) parse_str($this->attr('data-params'), $params);
        !$params ? $params = [] : null;
        $params = array_merge($params, $add);
        $this->attr('data-params', json_encode($params));
    }

    public function params($name = null)
    {
        $res = null;
        if ($name == null) {
            if (isset($this->params)) {
                $res = $this->params;
            }
        } else {
            if (isset($this->params->$name)) {
                $res = $this->params->$name;
            }
        }
        return $res;
    }


    public function fetchParams()
    {
        if (isset($this->params)) {
            return $this;
        }
        $this->setAttributes();
        $this->role = false;
        $this->func = false;
        $this->funca = [];
        $this->atrs = (object)[];
        $this->params = (object)[];

        $params = [];
        if (substr($this->tagName, 0, 3) == "wb-") {
            $this->role = substr($this->tagName, 3);
        }
        $attrs = $this->attributes();

        if (count($attrs)) {
            $prms = [];
            foreach ($attrs as $atname => $atval) {
                if ($atname == "wb" or substr($atname, 0, 3) == "wb-") {
                    $name = $atname;
                    $name !== "wb" ? $name = substr($atname, 3) : null;
                    if (in_array($name, ['if','where','change','ajax','save'])) {
                        $prms = [$name => $atval];
                        $this->atrs->$name = $atval;
                    } else {
                        $prms = wbAttrToValue($atval);
                        if ($name !== "wb") {
                            $prms = [$name => $prms];
                            $this->atrs->$name = $atval;
                            // если видим wb-name, но этот тэг внутри другого именованного тэга, то wb-name не удаляем
                            $name == 'name' && $this->parents('[name]')->length ? null : $this->removeAttr($atname);
                        }
                        is_string($prms) ? $prms = ["wb"=>$prms] : null;
                    }
                }
                $params = array_merge($params, $prms);
            }
            $this->params = (object)$params;
        }
        

        if (isset($this->params->module)) {
            $this->role = "module";
        }
        $this->fetchAllows();
        if ($this->role) {
            $func="tag".ucfirst($this->role);
            $file = $this->app->vars("_env.path_engine")."/tags/{$this->role}/{$this->role}.php";
            if (is_file($file)) {
                require_once $file;
                if (class_exists($func)) {
                    $this->func = $func;
                }
            } else {
                unset($this->role);
            }
        }
        foreach ($this->atrs as $attr => $value) {
            $func="attr".ucfirst($attr);
            if (!class_exists($func)) {
                $file = $this->app->vars("_env.path_engine")."/attrs/{$attr}/{$attr}.php";
                if (is_file($file)) {
                    require_once $file;
                }
            }
            if (class_exists($func)) {
                $this->funca[] = $func;
                if (!$this->role) {
                    $this->role = "attr";
                }
            }
        }
    }

    public function fetchAllows()
    {
        if ($this->params('allow') > "") {
            $allow = wbArrayAttr($this->params->allow);
            if (trim($this->params('allow')) == "*") {
                $this->params->allow = true;
            } else if ($allow && !in_array($this->app->vars("_sess.user.role"), $allow)) {
                $this->params->allow = false;
                $this->remove();
            } else {
                $this->params->allow = true;
            }
        }
        if ($this->params('disallow') > "") {
            $disallow = wbArrayAttr($this->params->disallow);
            if (trim($this->params('disallow')) == "*") {
                $this->params->allow = false;
                $this->remove();
            } else if ($disallow && !in_array($this->app->vars("_sess.user.role"), $disallow)) {
                $this->params->allow = true;
            } else {
                $this->params->allow = false;
                $this->remove();
            }
            $this->disallow = $disallow;
        }
        if ($this->params('disabled') > "") {
            $disabled = wbArrayAttr($this->params->disabled);
            if ($disabled && in_array($this->app->vars("_sess.user.role"), $disabled) OR trim($this->params('disabled')) == '*') {
                $this->attr("disabled", true);
            }
        }
        if ($this->params('enabled') > "") {
            $enabled = wbArrayAttr($this->params->enabled);
            if ($enabled && !in_array($this->app->vars("_sess.user.role"), $enabled) OR trim($this->params('enabled')) == '*') {
                $this->attr("disabled", true);
            }
        }
    }

    public function addTpl($real = true)
    {
        if (!$this->params("tpl")) {
            return;
        }
        $this->params->route = $this->app->vars("_route");
        isset($this->locale[$this->app->lang]) ? $this->params->locale = $this->locale[$this->app->lang] : $this->params->locale = [];
        $params = json_encode($this->params);
        $this->attr('data-params', $params);
        if ($this->attr("id") > '') {
            $tplId = $this->attr("id");
        } else if (substr($this->tagName,0,3) == 'wb-' AND $this->parent()->attr("id") > '') {
            $tplId = $this->parent()->attr("id");
        } else {
            $tplId = "tp_".md5($params);
        }
        $this->params->tpl = $tplId;
        if ($real) {
            $tpl = $this->outer();
            $this->after("\n
                  <template id='{$tplId}' data-params='{$params}'>
                      $tpl
                  </template>\n");
            $this->attr("data-wb-tpl", $tplId);
        }
        return $tplId;
    }

    public function setAttributes($Item=null)
    {
        if (!$this->attributes) {
            return $this;
        }
        $Item == null ? $Item = $this->item : null;
        is_object($Item) ? $Item=wbObjToArray($Item) : null;

        foreach ($this->attributes as $at) {
            $atname = $at->name;
            $atval = $at->value;
            if (substr($atname,0,1) == "_" && strpos($atname,".")) {
                $this->removeAttr($atname);
                $atname = $this->app->vars($atname);
                if ($atname == '') break;
            }
            if (strpos($atname, "}}")) {
                unset($this->attributes[$atname]);
                $atname = wbSetValuesStr($atname, $Item);
            }
            $atval = str_replace("%7B%7B", "{{", $atval);
            $atval = str_replace("%7D%7D", "}}", $atval);
            if (strpos($atval, "}}")) {
                $atval = wbSetValuesStr($atval, $Item);
            }
            $this->attr($atname, $atval);
        }
        return $this;
    }

    public function copy(&$parent)
    {
        isset($parent->locale) ? $this->locale = $parent->locale : $this->locale = [];
        isset($parent->head) ? $this->head = $parent->head : $this->head = false;
        isset($parent->strict) ? $this->strict = $parent->strict : $this->strict = false;
        isset($parent->path) && !isset($this->path) ? $this->path = $parent->path : null;

        $this->app = $parent->app;
        $this->item = $parent->item;
        $this->parent = $parent;
        $this->item["_var"] = &$_ENV["variables"];
    }

    public function setSeo()
    {
        isset($this->item['header']) ? $header = $this->item['header'] : $header = $this->app->vars('_sett.header');
        $title = $this->find('title');
        if ($title->text() == '') $title->text($header);
        $seo = $this->app->ItemRead('_settings', 'seo');
        if ($seo and isset($seo['seo']) and $seo['seo'] == 'on') {
            $title->text($seo['title']);
            $this->find('meta[name="keywords"]')->attr('content', $seo['meta_keywords']);
            $this->find('meta[name="description"]')->attr('content', $seo['meta_description']);
        }
        if (isset($this->item['seo']) and $this->item['seo'] == 'on') {
            $title->text($this->item['title']);
            $this->find('meta[name="keywords"]')->attr('content', $this->item['meta_keywords']);
            $this->find('meta[name="description"]')->attr('content', $this->item['meta_description']);
        }
    }

    public function setValues()
    {
        if ($this->strict) return;
        isset($this->item) ? null : $this->item = [];
        $fields = $this->app->dot($this->item);
        $inputs = $this->find("[name]:not([done])");

        foreach ($inputs as $inp) {
            if (!$inp->closest("template")->length) {
                $inp->copy($this);
                $inp->fetchParams();
                $name = $inp->attr("name");
                $value = $fields->get($name);
                ((array)$value === $value and $inp->tagName !== "select") ? $value = wb_json_encode($value) : null;
                if ($value > '') {
                    $value = str_replace('&amp;quot;', '"', $value);
                } // борьба с ковычками в атрибутах тэгов
                if (in_array($inp->tagName, ["input","textarea","select"])) {
                    if ($inp->tagName == "textarea") {
                        if ($inp->params('oconv') > '') {
                            $oconv = $inp->params('oconv');
                            $inp->inner(@$oconv($value));
                        } elseif ($inp->attr('type') == 'json') {
                            $inp->inner($value);
                        } else {
                            $inp->inner(htmlentities($value));
                        }
                        $inp->params('oconv') > '' ? $inp->attr('data-oconv', $inp->params('oconv')) : null;
                        $inp->params('iconv') > '' ? $inp->attr('data-iconv', $inp->params('iconv')) : null;

                    } elseif ($inp->tagName == "select") {
                        if ((array)$value === $value) {
                            foreach ($value as $val) {
                                    $tmp = $inp->find('[value]');
                                    foreach ($tmp as $v) {
                                        if ($v->attr('value') == $val) $v->attr('selected', true);
                                    }

                                $val > "" ? $inp->find("[value='{$val}']")->attr("selected", true) : null;
                            }
                        } elseif ($value > "") {
                            $tmp = $inp->find('[value]');
                            foreach($tmp as $v) {
                                if ($v->attr('value') == $value) $v->attr('selected', true);
                            }
                        }
                    } elseif ($inp->tagName == "input") {
                        if ($inp->attr("type") == "radio") {
                            $inp->attr("value") == $value and $value > '' ? $inp->attr('checked', 'checked') : null;
                        } else {
                            $inp->attr("value", $value);
                            if ($inp->attr("type") == "checkbox") {
                                if ($value == "on" or $value == "true"  or $value == "1") {
                                    $inp->attr("checked", true);
                                    $inp->removeAttr("value");
                                }
                            }
                        }
                    }
                    $inp->attr("done", "");
                } elseif ($inp->hasAttr('type') && !$inp->hasAttr("done")) {
                    $inp->attr("value", $value);
                    $inp->attr("done", "");
                }
            }
        }
        $unset = $this->find("template,textarea,code,pre");
        foreach ($unset as $t) {
            $t->inner(str_replace("{{", "_{_{_", $t->inner()));
        }
        if (strpos($this, "{{")) {
            $render = new wbRender($this);
            $html = $render->exec();
            if ($this->tagName == 'title') {
                $this->text($html);
            } else {
                $this->inner($html);
            }
        }
        $unset = $this->find("template,textarea,code,pre");
        foreach ($unset as $t) {
            $t->inner(str_replace("_{_{_", "{{", $t->inner()));
        }
        return $this;
    }
}

class wbApp
{
    public $settings;
    public $route;
    public $item;
    public $out;
    public $template;
    public $router;
    public $render;

    public function __construct($settings=[])
    {
        require_once __DIR__. '/modules/cms/cms_formsclass.php'; // important!!!
        $this->settings = (object)[];

        foreach ($settings as $key => $val) {
            $this->settings->$key = $val;
        }

        isset($this->settings->driver) ? null : $this->settings->driver = 'json' ;

        $this->router = new wbRouter();
        $this->vars = new Dot();
        $vars = [
          '_env'  => &$_ENV,
          '_get'  => &$_GET,
          '_srv'  => &$_SERVER,
          '_post' => &$_POST,
          '_req'  => &$_REQUEST,
          '_route'=> &$_ENV['route'],
          '_sett' => &$_ENV['settings'],
          '_var'  => &$_ENV['variables'],
          '_sess' => &$_SESSION,
          '_user' => &$_SESSION['user'],
          '_cookie'=>&$_COOKIE,
          '_cook'  =>&$_COOKIE,
          '_mode' => &$_ENV['route']['mode'],
          '_form' => &$_ENV['route']['form'],
          '_item' => &$_ENV['route']['item'],
          '_param'=> &$_ENV['route']['param'],
          '_locale'=> &$_ENV['locale'],
          '_lang'   => &$this->lang
      ];
        $this->vars->setReference($vars);
        $this->initApp();
    }

    public function __call($func, $params)
    {
        $wbfunc='wb'.$func;
        $_ENV['app'] = &$this;
        if (method_exists($this, $func)) {
            $this->$func();
        } else if (is_callable($wbfunc)) {
            $prms = [];
            foreach ($params as $k => $i) {
                $prms[] = '$params['.$k.']';
            }
            eval('$res = $wbfunc('.implode(',', $prms).');');
            return $res;
        } elseif (!is_callable($func)) {
            die("Function {$wbfunc} not defined");
        } else {
            $par = [];
            for ($i=0; $i<count($params); $i++) {
                $par[] = '$params['.$i.']';
            }
            eval('$res = $func('.implode(",", $par).');');
            return $res;
        }
    }

    public function getCacheId()
    {
        $uri = $this->route->uri;
        $lang = $this->vars('_sess.lang');
        return md5($uri.'_'.$lang);
    }

    public function setCache($out = '')
    {
        if (!isset($_GET['update']) and (count($_GET) or count($_POST))) {
            return;
        }
        $cid = $this->getCacheId();
        $sub = substr($cid, 0, 2);
        $dir = $this->vars('_env.dbac').'/'.$sub;
        $name = $dir.'/'.$cid.'.html';
        strpos(' '.$out, '<!DOCTYPE html>') ? null : $out = '<!DOCTYPE html>'.$out;
        is_dir($dir) ? null : mkdir($dir, 0777, true);
        file_put_contents($name, $out, LOCK_EX);
        $lastModified = filemtime($name);
    }

    public function cacheControl() {
        $this->vars('_sett.devmode') == 'on' ? $cache = null : $cache = true;
        if ($cache && isset($_SERVER['HTTP_CACHE_CONTROL'])) {
            parse_str($_SERVER['HTTP_CACHE_CONTROL'], $cc);
            isset($cc['no-cache']) ? $cache = null : null;
        }
        $cache && ((!count($_POST) and isset($_GET['update']) and count($_GET) == 1) or count($_POST) or count($_GET)) ? $cache = null : null;
        return $cache;
    }



    public function getCache()
    {          
        $cache = $this->cacheControl();
        if ($cache == null) {
            header("Cache-Control: no-cache, no-store, must-revalidate"); 
            header("Pragma: no-cache");
            return null;
        }

        $cid = $this->getCacheId();
        $sub = substr($cid, 0, 2);
        $dir = $this->vars('_env.dbac').'/'.$sub;
        $name = $dir.'/'.$cid.'.html';

        if (is_file($name)) {
            if ($this->vars('_sett.cache') > ''  AND ((time() - filectime($name)) >= intval($this->vars('_sett.cache')))) {
                // Делаем асинхронный запрос с обновлением кэша
                header("Cache-Control: no-cache, no-store, must-revalidate");
                header("Pragma: no-cache");
                $this->shadow($this->route->uri);
                return null;
            } else {
                header("Cache-control: public");
                header("Pragma: cache");
                header("Expires: " . gmdate("D, d M Y H:i:s", time()+$this->vars('_sett.cache')) . " GMT");
                header("Cache-Control: max-age=".$this->vars('_sett.cache'));
            }
            return file_get_contents($name);
        }
        return null;
    }


    public function shadow($uri)
    {
        // отправка url запроса без ожидания ответа
        $fp = stream_socket_client("tcp://{$this->route->hostname}:{$this->route->port}", $errno, $errstr, 120,STREAM_CLIENT_ASYNC_CONNECT|STREAM_CLIENT_CONNECT);
        if (!$fp) {
            echo "$errstr ($errno)<br />\n";
            wbLog("func", __FUNCTION__, $errno, $errstr);

        } else {
            //strpos($uri, '?') ? $uri .= '&update' : $uri .= '?update';
            fwrite($fp, "GET {$uri} HTTP/1.0\r\nHost: {$this->route->hostname}\r\nCache-Control: no-cache\r\nAccept: */*\r\n\r\n");
            fgets($fp, 10);
            fclose($fp);
        }
    }

    public function router()
    {
        $this->router->init();
        $route = $this->router->getRoute();
        $_ENV["route"] = $route;
        $this->route = wbArrayToObj($route);
        return $this->route;
    }

    public function getField($item, $fld)
    {
        $fields = new Dot();
        $fields->setReference($item);
        return $fields->get($fld);
    }

    public function login($user) {
        is_string($user) ? $user = $this->itemRead('users',$user) : null;
        is_object($user) ? null : $user = $this->arrayToObj($user);
        isset($user->avatar) ? null : $user->avatar = [0=>['img'=>"",'alt'=>'User','title'=>'']];
        (array)$user->avatar === $user->avatar ? null : $user->avatar=['img'=>"/uploads/users/{$user->id}/{$user->avatar->img}",'alt'=>'User','title'=>''];
        $user->group = wbArrayToObj(wbItemRead("users", $user->role));
        if (!$user->group OR $user->group->active !== 'on' OR $user->active !== 'on') {
            return false;
        }
        $user->group->url_logout == "" ? $user->group->url_logout = "/" : null;
        $user->group->url_login == "" ? $user->group->url_login = "/" : null;

        unset($user->password);
        $arr = $this->objToArray($user);
        $this->vars("_sess.user", $arr);
        $this->vars("_env.user", $arr);
        setcookie("user", $user->id, time()+3600);
        $this->user = $user;
        $this->token = $this->getToken();
        return $user;
    }

    public function initApp()
    {
        $this->InitEnviroment();
        $this->router();
        $this->ErrorList();
        //$this->RouterAdd();
        $this->driver();
        $this->InitSettings($this);
        $this->InitFunctions($this);
        $this->controller();
}

    public function driver()
    {
            $this->settings->_driver = 'json';
            if (is_file($this->route->path_app."/database/_driver.ini")) {
                $drv = file_get_contents($this->route->path_app."/database/_driver.ini");
                $drv = wbSetValuesStr($drv);
                $drv = parse_ini_string($drv, true);
                if (isset($drv["driver"])) {
                    $drvlist = $drv["driver"];
                    unset($drv["driver"]);
                } else {
                    $drvlist = [];
                }
                $flag = true;
                foreach ($drv as $driver => $options) {
                    if ($flag) {
                        $this->settings->_driver = $driver;
                        $flag = false;
                    }
                    $this->settings->driver_options[$driver] = $options;
                }
                $this->settings->driver_tables = &$drvlist;
            }
        include_once $this->route->path_engine."/drivers/json/init.php";
        include_once $this->route->path_engine."/drivers/init.php";
    }

    public function controller($controller = null)
    {
        if (is_callable('customRoute')) customRoute($this->route);

        if ($this->route->controller !== 'module' && substr($this->mime($this->route->uri), 0, 6) == 'image/') {
            $this->route->controller = 'thumbnails';
        }

        $controller ? null : $controller = $this->route->controller;
        if ($controller) {
            if (isset($this->route->file) && in_array($this->route->fileinfo->extension, ["php","html"])) {
                return;
            }
            $path = "/controllers/{$controller}.php";
            if (is_file($this->route->path_app . $path)) {
                require_once $this->route->path_app . $path;
            } elseif (is_file($this->route->path_engine.$path)) {
                require_once $this->route->path_engine.$path;
            }
            $class = "ctrl".ucfirst($controller);
            if (!class_exists($class)) {
                echo "Controller not found: {$controller}";
            } else {
                new $class($this);
            }
            return $this;
        }
    }


    public function filterItem($item)
    {
        if ($this->vars("_post._filter")) {
            $filter = $this->vars("_post._filter");
        }
        if (!isset($filter)) {
            return true;
        }

        $vars = new Dot();
        $vars->setReference($item);
        foreach ($filter as $fld => $val) {
            if (is_string($val)) {
                $val = preg_replace('/^\%(.*)\%$/', "", $val);
            }
            if ($val !== "") {
                if (in_array(substr($fld, -5), ["__min","__max"])) {
                    if (substr($fld, -5) == "__min" and $val > $vars->get(substr($fld, 0, -5))) {
                        return false;
                    }
                    if (substr($fld, -5) == "__max" and $val < $vars->get(substr($fld, 0, -5))) {
                        return false;
                    }
                } elseif ((string)$val === $val and $vars->get($fld) !== $val) {
                    return false;
                } elseif ((array)$val === $val and !in_array($vars->get($fld), $val) and $val !== []) {
                    return false;
                }
            }
        }
        return true;
    }


    public function fieldBuild($dict=[], $data=[])
    {
        (array)$dict == $dict ? $dict = wbArrayToObj($dict) : null;
        
        if ($dict->name == "") {
            return "";
        }
        $this->dict = $dict;
        isset($data["data"]) ? $this->item = $data["data"] : $this->item = [];
        $this->data = $data;
        $this->tpl = $this->getForm('snippets', $dict->type);
        //$this->tpl = $this->fromString('<html>'.$this->tpl->outer().'</html>');
        if (!is_object($this->tpl)) {
            $this->tpl = $this->fromString("<b>Snippet {$dict->type} not found</b>");
        }
        $this->tpl->dict = $this->dict;
        $this->tpl->item = $this->item;

        $this->tpl->setAttributes($dict);
        $this->tpl->find("input")->attr("name", $this->dict->name);
        if (isset($this->dict->prop) and $this->dict->prop->style > "") {
            $this->tpl->find("[style]")->attr("style", $this->dict->prop->style);
        } else {
            $this->tpl->find("[style]")->removeAttr("style");
        }
        $func = __FUNCTION__ . "_". $dict->type;
        !method_exists($this, $func) ? $func = __FUNCTION__ . "_". "common" : null;
        return $this->$func();
    }


    public function fieldBuild_multiinput()
    {
        $mult = $this->tpl;
        $mult->item = $this->item;
        $mult->dict = $this->dict;
        $mult->fetch();
        return $mult;
    }

    public function fieldBuild_treeselect()
    {
        $tag = $this->tpl;
        $tag->find("select")->setAttributes((array)$this->dict);
        $tag->fetch($this->item);
        return $tag;
    }

    public function fieldBuild_image()
    {
        $img = $this->tpl;
        $img->item = $this->item;
        $img->item['_name'] = $this->dict->name;
        $img->item['_form'] = 'treedata';
        $img->item['_item'] = $this->data['id'];
        $img->fetch();
        return $img;
    }

    public function fieldBuild_images()
    {
        $img = $this->tpl;
        $img->item = $this->item;
        $img->item['_name'] = $this->dict->name;
        $img->item['_form'] = 'treedata';
        $img->item['_item'] = $this->data['id'];
        $img->fetch();
        return $img;
    }

    public function fieldBuild_forms()
    {
        $form = $this->tpl;
        $form->item = $this->item;
        $form->dict = $this->dict;
        $form->find("wb-include")->setAttributes($form->dict->prop);
        $form->fetch();
        return $form;
    }

    public function fieldBuild_common()
    {
        $common = &$this->tpl;
        $common->find("[wb]")->setAttributes((array)$this->tpl->dict);
        $common->find("wb-module")->setAttributes((array)$this->tpl->dict);
        $common->fetch();
        if ($this->tpl->dict->type == 'langinp') {
            $common->find('.mod-langinp[placeholder]')->attr('placeholder', $common->dict->label);
        }
        return $common;
    }

    public function fieldBuild_enum()
    {
        $lines=[];
        if (isset($this->dict->prop) && isset($this->dict->prop->enum) && $this->dict->prop->enum > "") {
            $arr=explode(",", $this->dict->prop->enum);
            foreach ($arr as $i => $line) {
                $lines[$line] = ['id' => $line, 'name' => $line];
            }
        }
        $res = $this->tpl->fetch(["enum" => $lines]);
        if (isset($this->data['data'][$this->dict->name]) && $this->data['data'][$this->dict->name] > '') {
            $res->find('option[value="'.$this->data['data'][$this->dict->name].'"]')->attr("selected", true);
        } else {
            $res->find("option[value]:first")->attr("selected", true);
        }
        return $res;
    }


    public function fieldBuild_module()
    {
        $this->tpl->setAttributes($this->dict);
        return $this->tpl->fetch();
    }

    public function addEvent($name, $params=[])
    {
        $evens = json_decode(base64_decode($this->vars("_cookie.events")), true);
        $events[$name] = $params;
        $events = base64_encode(json_encode($events));
        setcookie("events", $events, time()+3600, "/"); // срок действия сутки
    }

    public function addEditor($name, $path, $label = null)
    {
        $this->addTypeModule("editor", $name, $path, $label);
    }

    public function addModule($name, $path, $label = null)
    {
        $this->addTypeModule("module", $name, $path, $label);
    }

    public function addDriver($name, $path, $label = null)
    {
        $this->addTypeModule("driver", $name, $path, $label);
    }

    public function addTypeModule($type, $name, $path, $label = null)
    {
        $types = [
             "module"=>"_env.modules.{$name}"
            ,"editor"=>"_env.editors.{$name}"
            ,"driver"=>"_env.drivers.{$name}"
            ,"uploader"=>"_env.drivers.{$name}"
        ];
        $dir = dirname($path);

        $dir = realpath($dir);

        if (in_array($type, array_keys($types))) {
            if ($label == null) {
                $label = $name;
            }
            if (!$this->vars($types[$type])) {
                $this->vars($types[$type], [
                   "name"=>$name
                   ,"path"=>$path
                   ,"dir"=>$dir
                   ,"label"=>$label
                 ]);
            } elseif ($label !== $name) {
                $this->vars($types[$type].".label", $label);
            }
        } else {
            throw new \Exception('Wrong module type: '.$type.' Use available types: '.implode(", ", array_keys($types)));
        }
    }

    public function module()
    {
        $args = func_get_args();
        if (!isset($args[0])) return null;
        $mod = $args[0];
        unset($args[0]);
        if (!count($args)) $args[]=$this;
        $class = 'mod' . ucfirst($mod);
        /*
        if (is_file($this->vars('_env.path_app')."/modules/{$mod}/{$mod}.php")) {
            require $this->vars('_env.path_app')."/modules/{$mod}/{$mod}.php";
        } else if (is_file($this->vars('_env.path_engine')."/modules/{$mod}/{$mod}.php")) {
            require $this->vars('_env.path_engine')."/modules/{$mod}/{$mod}.php";
        } else {
            return null;
        }
        */
        $rc = new ReflectionClass($class);
        return @$rc->newInstanceArgs($args);
    }

    public function json($data)
    {
        $json = new Jsonq();
        if (is_string($data)) {
            $data=wbItemList($data);
        } elseif (!is_array($data)) {
            $data=(array)$data;
        }
        return $json->collect($data);
    }

    public function dot(&$array=[])
    {
        $dot = new Dot();
        $dot->setReference($array);
        return $dot;
    }

    public function cond($condition, $item) 
    {
        // пытаемся преобразовать в json строку с одинарными ковычками
        $re = '/\'\{(.*)\'(.*)\:(.*)}\'/mu';
        preg_match($re,$condition,$matches);
        if (isset($matches[0])) {
            $repl = substr($matches[0],1,-1);
            $json = str_replace("'",'"',$repl);
            $this->isJson($json) ? $condition = str_replace($repl,$json,$condition) : null;
        }
        // ======
        if (in_array(substr(trim($condition), 0, 1), ['"',"'"])) {
            $res = wbEval($condition);
        } else {
            $cond = explode(" ", $condition);
            if (!strpos($cond[0], "(")) {
                $dot = $this->dot($item);
                $cond[0] = eval('return $dot->get("'. $cond[0] .'");');
                (array)$cond[0] === $cond[0] ? $cond[0] = wbJsonEncode($cond[0]) : null;
                $cond[0] = "'".$cond[0]."'";
                $condition = implode(' ', $cond);
            }
            $res = wbEval($condition);
        }
        return $res;
    }

    public function settings()
    {
        $this->settings = &$_ENV["settings"];
        return $this->settings;
    }

    public function vars()
    {
        $count = func_num_args();
        $args = func_get_args();
        if ($count == 0) {
            return;
        }
        if ($count == 1) {
            return $this->vars->get($args[0]);
        }
        if ($count == 2) {
            return $this->vars->set($args[0], $args[1]);
        }
    }


    public function getRoute()
    {
        $this->route = &$_ENV["route"];
        return $this->route;
    }

    public function template($name="default.php")
    {
        $this->template=wbGetTpl($name);
        $this->dom = clone $this->template;
        return $this->dom;
    }

    public function getForm($form = null, $mode = null, $engine = null)
    {
        $_ENV['error'][__FUNCTION__] = '';
        $error = null;
        null == $form ? $form = $this->vars->get("_route.form") : 0;
        null == $mode ? $mode = $this->vars->get("_route.mode") : 0;
        $form == '_settings' ? $formname = substr($form, 1) : $formname = $form;

        $modename = $mode;
        strtolower(substr($modename, -4)) == ".ini" ? $ini = true : $ini = false;

        if (!in_array(strtolower(substr($modename, -4)), [".php",".ini",".htm",".tpl"])) {
            $modename = $modename.".php";
        }

        $aCall = $form.'_'.$mode;
        $eCall = $form.'__'.$mode;

        $loop=false;
        foreach (debug_backtrace() as $func) {
            $aCall==$func["function"] ? $loop=true : null;
            $eCall==$func["function"] ? $loop=true : null;
        }

        if (is_callable($aCall) and $loop == false) {
            $out = $aCall();
        } elseif (is_callable($eCall) and false !== $engine and $loop == false) {
            $out = $eCall();
        }

        if (!isset($out)) {
            $current = '';
            $flag = false;
            $path = ["/forms/{$form}/{$formname}_{$modename}"
                    ,"/forms/{$form}/{$modename}"
                    ,"/forms/common/common_{$modename}"
                ];
                
            foreach ($path as $form) {
                    $current = wbNormalizePath($_ENV['path_app'].$form);
                    if (is_file($current)) break;
                    $current = wbNormalizePath($_ENV['path_engine'].$form);
                    if (is_file($current)) break;
                    $current = '';
            }

            //unset($form);
            if ('' == $current) {
                    strtolower(substr($mode, -4)) == '.php' ? $arg = $modename : $arg = $aCall;
                    $out = $error = wbError('func', __FUNCTION__, 1012, [$arg]);
            } else {
                if ($ini) {
                    $out = file_get_contents($current);
                    $out = $this->fromString($out, true);
                } else {
                    $out = $this->fromFile($current);
                }
            }
        }
        if (is_object($out)) {
            $out->path = $current;
        } else {
            $out = $this->fromString('<html>'.$out.'</html>');
        }
        $out->error = $error;
        return $out;
    }

    public function fromString($string)
    {
        $dom = new wbDom($string);
        $dom->app = $this;
        $dom->fetchLang();
        return $dom;
    }

    public function fromFile($file="")
    {
        $res = "";
        $context = null;
        if ($file=="") {
            return null;
        } else {
            //session_write_close(); Нельзя, иначе проблемы с логином
            $url=parse_url($file);
            if (isset($url["scheme"])) {
                $context = stream_context_create(array(
                     'http'=>array(
                             'method'=>"POST",
                             'header'=>	"Accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7\r\n" .
                             "Cache-Control: no-cache\r\n" .
                             'Content-Type:' . " application/x-www-form-urlencoded\r\n" .
                             'Cookie: ' . $_SERVER['HTTP_COOKIE']."\r\n" .
                             'Connection: ' . " Close\r\n\r\n",
                             'content' => http_build_query($_POST)
                     ),
                     "ssl"=>array(
                         "verify_peer"=>false,
                         "verify_peer_name"=>false,
                     )
                 ));
                session_write_close();
                $res=@file_get_contents($file, true, $context);
                session_start();
            } else {
                if (!is_file($file)) {
                    $file = str_replace($_ENV["path_app"], "", $file);
                    $file=$_ENV["path_app"].$file;
                    return null;
                } else {
                    $fp = fopen($file, "r");
                    flock($fp, LOCK_SH);
                    $res=file_get_contents($file, false, $context);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }
            }
            $dom = $this->fromString($res);
            $dom->path = str_replace($_ENV["dir_app"], "", dirname($file, 1));
            return $dom;
        }
    }

    public function getTpl($tpl = null, $path = false)
    {
        $cur = null;
        $out = null;
        if (true == $path) {
            !$cur and is_file($_ENV['path_app']."/{$tpl}") ? $cur = wbNormalizePath($_ENV['path_app']."/{$tpl}") : null;
        } else {
            !$cur and is_file($_ENV['path_tpl']."/{$tpl}") ? $cur = wbNormalizePath($_ENV['path_tpl']."/{$tpl}") : null;
            !$cur and is_file($_ENV['path_engine']."/tpl/{$tpl}") ? $cur = wbNormalizePath($_ENV['path_engine']."/tpl/{$tpl}") : null;
        }
        $cur > "" ? $out = $this->fromFile($cur) : null;
        $_ENV['tpl_realpath'] = dirname($cur);
        $_ENV['tpl_path'] = substr(dirname($cur),strlen($_ENV['path_app']));

        if (!$out) {
            $cur =  $path !== false ? wbNormalizePath($path."/{$tpl}") : wbNormalizePath($_ENV['path_tpl']."/{$tpl}");
            $cur=str_replace($_ENV["path_app"], "", $cur);
            wbError('func', __FUNCTION__, 1011, array($cur));
        } else if (!$out->is('html')) {
            $out = $out->outer();
            $out = $this->fromString('<html>'.$out.'</html>');
        }
        return $out;
    }

    public function render()
    {
        $render = new wbRender($this);
        return $render->run();
    }
}

class wbRender extends WEProcessor
{
    public function __construct($dom)
    {
        $this->vars = new Dot();
        $this->inner = $dom->inner();
        $this->parser = new parse_engine(new weparser($this));
        $this->context = $dom->item;
        isset($dom->app->vars) ? $vars = $dom->app->vars : $vars = [];
        $vars = (array)$vars;
        $this->vars->setReference($vars);
    }

    public function exec($item = null)
    {
        !isset($this->item) ? $this->item = [] : null;
        $item == null ? $item = $this->item : null;
        return $this->substitute($this->inner);
    }
}