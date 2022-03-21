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
            ob_start();
            if ($connection->__request->session() !== $request->session()) return;
            $worker = &$connection->worker;
            $this->connection = &$connection;
            $this->request = $request;
            $this->getServer();
            header('Content-Type: text/html;charset=utf-8');
            if (!$this->getStatic()) {
                if (!isset($this->ini['host']) or $_SERVER['ROUTE']['hostname'] !== $this->ini['host']) {
                    $this->header(404);
                } else {
                    $index = $this->root.'/index.php';
                    try {
                        is_file(($index)) ? include($index) :  $this->header(404);
                    } catch (\Throwable $th) {
                        $this->header(500);
                        echo "Error 500";
                    }
                }
            }
            $this->setHeaders();
            $out = ob_get_contents(); 
            ob_end_clean();
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
            $mime = json_decode(file_get_contents(__DIR__.'/mimetypes.json'), true);
            if (!in_array($ext, ['less','scss','php','html'])) {
                header_remove();
                $this->header(200);
                header_remove('Content-Type');
                if (isset($ext,$mime)) header('Content-Type: '.$mime[$ext]);
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
        } else if ($_SERVER['REQUEST_URI'] == '/' && $_SERVER['PATH_INFO'] == '/') {
            $_SERVER['SCRIPT_FILENAME'] = '/index.php';
        }
        $router = new wbRouter();
        $_GET = $request->get();
        $_POST = $request->post();
        $_COOKIE = $request->cookie();
    }

    function header($err)
    {
        is_numeric($err) ? http_response_code($err) : $header = ($err);
        if (isset($header)) header($header);
        return $header;
    }

    function setHeaders() {
        $headers = php_sapi_name() === 'cli' ? xdebug_get_headers() : headers_list();
        $headers = $this->http_status()."\r\n".implode("\r\n", $headers);
        $this->connection->__header = $headers;
    }

    function http_status() {
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
        $code = http_response_code();
        $result = isset($status[$code]) ? $status[$code] : $status[400];
        return ("HTTP/1.1 {$code} {$result}");
    }
}
ini_set('display_errors', 0);
new Server();
