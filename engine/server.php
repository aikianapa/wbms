<?php

use Workerman\Worker;
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/wbrouter.php';
//require __DIR__ . '/static.php';


class Server
{
    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        ini_set('display_errors', 0);
        $this->root = realpath(__DIR__.'/..');
        $this->ini = parse_ini_file($this->root.'/server.ini');
        if (!isset($this->ini['port']) or intval($this->ini['port']) < 80) $this->ini['port'] = 8000;
        $context = [
            'ssl' => [
                'local_cert'  => '/your/path/of/server.pem',
                'local_pk'    => '/your/path/of/server.key',
                'verify_peer' => false,
            ]
            ];
        $worker = new Worker("http://0.0.0.0:{$this->ini['port']}", $context);
        // $worker->transport = 'ssl';
        $worker->count = 4; 
        $worker->channels = ['wb'];

        $worker->onWorkerStart = function ($worker) {
            //echo "Worker starting...\n";
        };

        $worker->onWorkerStop = function ($worker) {
            //echo "Worker stopped...\n";
        };


        $worker->onConnect = function ($connection) {
            //echo "New connection\n";
        };

        $worker->onMessage = function ($connection, &$request) {
            $worker = &$connection->worker;
            $this->connection = &$connection;
            //$this->headers = [];
            $this->request = $request;
            ob_start();
            $this->getServer();
            if (!$this->getStatic()) {
                if (!isset($this->ini['host']) or $_SERVER['ROUTE']['hostname'] !== $this->ini['host']) {
                    $this->header(404);
                } else {
                    try {
                        $inc = is_file(($this->root.'/index.php')) ? $this->root.'/index.php' : $this->root.'/engine/engine.php';
                        include($inc);
                    } catch (\Throwable $th) {
                        $this->header(500);
                        echo "Error 500";
                    }
                }
            }
            $this->setHeaders();
            $out = ob_get_clean();
            ini_set('display_errors', 0);
            $connection->send($out);
        };

        $worker->onClose = function ($connection) {
            $connection->send($connection->id);

            //echo "Connection closed\n";
        };

        // Run worker
        Worker::runAll();
    }

    function getStatic() {
        $route = (object)$_SERVER['ROUTE'];
        if (isset($route->file) && is_file($route->file)) {
            $info = (object)pathinfo($route->file);
            $ext  = $info->extension;
            if (!in_array($ext, ['less','scss','php','html'])) {
                $mime = wbMime($route->file);
                header_remove('Content-Type');
                header('Content-Type: '.$mime.';');
                echo file_get_contents($route->file);
            } elseif ($ext == 'php') {
                include($route->file);
            }
            return true;
        }
        return false;
    }


    function getServer()
    {
        $request = &$this->request;
        $header = $request->header();
        $url = parse_url($header['host'].$request->uri());
        $_SERVER=[
            'DOCUMENT_ROOT' => $this->root,
            'HTTP_HOST' => $url['host'],
            'HTTP_ACCEPT' => $header['accept'],
            'HTTP_ACCEPT_LANGUAGE' => $header['accept-language'],
            'HTTP_ACCEPT_ENCODING' => $header['accept-encoding'],
            'HTTP_USER_AGENT'=> $header['user-agent'],
            'HTTP_CONNECTION' => $header['connection'],
            'SERVER_PORT' => $url['port'],
            'REQUEST_METHOD' => $request->method(),
            'REMOTE_ADDR' => $request->connection->getRemoteIp(),
            'REMOTE_PORT' => $request->connection->getRemotePort(),
            'REQUEST_URI' => $request->uri(),
            'SCRIPT_FILENAME' => '',
            'PATH_INFO'  => $url['path'],
            'ROUTE' => []
        ];

        $_SERVER['QUERY_STRING'] = isset($url['query']) ? $url['query'] : '';
        $_SERVER['HTTP_REFERER'] = isset($header['referer']) ? $header['referer'] : '';
        $_SERVER['HTTP_ACCEPT_CHARSET'] = isset($header['accept-charset']) ? $header['accept-charset'] : '';

        $_SERVER['ROUTE']['path_app'] = $_ENV['path_app'] = $this->root;
        $_SERVER['ROUTE']['path_engine'] = $_ENV['path_engine'] = realpath(__DIR__);

        if (is_file($this->root.$url['path'])) {
            $_SERVER['SCRIPT_FILENAME'] = $this->root.$url['path'];
        }
        $router = new wbRouter();
        $_GET = $request->get();
        $_POST = $request->post();
        $_COOKIE = $request->cookie();
    }

    function header($err)
    {
        $status = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-status',
            208 => 'Already Reported',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => 'Switch Proxy',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Time-out',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Large',
            415 => 'Unsupported Media Type',
            416 => 'Requested range not satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a teapot',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Unordered Collection',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Time-out',
            505 => 'HTTP Version not supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            511 => 'Network Authentication Required',
        ];

        if (is_numeric($err)) {
            $header = ("HTTP/1.1 {$err} {$status[$err]}");
        } else {
            $header = ($err);
        }
        header($header);

    }

    function setHeaders() {
        $headers = php_sapi_name() === 'cli' ? xdebug_get_headers() : headers_list();
        $this->connection->__header = $headers;
    }
}
ini_set('display_errors', 0);
new Server();
