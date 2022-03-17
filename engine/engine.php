<?php
//require __DIR__."/modules/setup/requrements.php";

class wbEngine {

    function __construct() {
        $this->init();
    }

    function init() {
        @session_start([
            "gc_probability" => 5,
            "gc_divisor" => 80,
            "gc_maxlifetime" => 84600,
            "cookie_lifetime" => 0
        ]);
        header('Cache-Control: max-age=31536000');
        if (!isset($_SESSION["lang"])) {
            $_SESSION["lang"] = "ru";
        }
        //if (!isset($app) OR ( isset($app) && !($app === false))) $app = new wbApp();

    }

}

new wbEngine();
?>
