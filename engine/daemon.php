<?php
session_start();
if (!isset($_SESSION['checksum'])) $_SESSION['checksum'] = null;
while(1) {
    $dir = realpath(__DIR__.'/..');
    $list = recursiveScanDir($dir);
    $str='';
    foreach ($list as $filename) {
            @$mtime = filemtime($filename);
            $str.= $filename. $mtime . "\n";
    }
    $md5 = md5($str);

    if ($md5 !== $_SESSION['checksum']) {
        echo $md5."\n";
        $_SESSION['checksum'] = $md5;
        $dir = realpath(__DIR__);
        exec("cd {$dir} && php server.php restart -d");
    }

    sleep(1);
}

function recursiveScanDir($dir) {
    $list = scandir("{$dir}");
    $incl = [];
    foreach($list as $i => $fn)  {
        if (in_array($fn,['.','..'])) {
            unset($list[$i]);
        } else {
            $list[$i] = $dir.'/'.$fn;
            $info = pathinfo($list[$i]);
            if (is_dir($list[$i])) {
                $subdir = recursiveScanDir($list[$i]);
                unset($list[$i]);
                $incl = array_merge_recursive($incl, $subdir);
            } else if (isset($info['extension']) && in_array($info['extension'],['pid','log','tmp'])) {
                unset($list[$i]);
            }
        }
    }
    $list = array_merge_recursive($list,$incl);
    return $list;
}
?>