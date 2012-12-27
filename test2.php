<?php

error_reporting(E_ALL|E_STRICT);

// Objekte:
//  - VCR (configuration, cassettes, proxy, libraryHooks)
//  - Configuration (hooks, cassette_folder, proxyUrl)
//  - Cassette (name, request, response)
//  - Proxy
//  - LibraryHooks: StreamWrapper, Curl?, Soap, Zlib
//  - Request (host, port, header, body) -> Symfony?
//  - Response (header, body, serializable) -> Symfony?

// TODO:
//  - Introduce Request object
//  - Intoduce namespaces
//  - Move classes
//  - Comments ;-)


/**
 * Factory.
 */
class VCR
{
    const RECORD_MODE = 1;
    const PLAYBACK_MODE = 1;

    public static $isOn = false;
    private $cassette;
    private $config;
    private $proxy;
    private $libraryHooks = array();

    public function __construct($config = null)
    {
        $this->config = $config ?: new Configuration;
        $this->cassette = shm_attach(ftok(__FILE__, 'a'), 1024);
        if ($this->config->getTurnOnAutomatically()) {
            $this->turnOn();
        }
    }

    /**
     * Initializes VCR and all it's dependencies.
     * @return void
     */
    public function turnOn()
    {
        if (self::$isOn) {
            return;
        }
        $this->proxy = $this->createProxy();
        $this->proxy->start();

        $this->libraryHooks = $this->createLibraryHooks();

        self::$isOn = true;
    }

    /**
     * Shuts down VCR and all it's dependencies.
     * @return void
     */
    public function turnOff()
    {
        if ($this->proxy) {
            $this->proxy->stop();
        }

        // disable library hooks
        foreach ($this->libraryHooks as $hook) {
            $hook->disable();
        }

        // delete all shared memory segments
        if ($this->cassette) {
            $this->getCurrentCassette()->writeToDisk();
            shm_remove($this->cassette);
            $this->cassette = null;
        }

        self::$isOn = false;
    }

    public function insertCassette($cassetteName)
    {
        $cassette = new Cassette($cassetteName, $this->config);
        shm_put_var($this->cassette, 1, $cassette);
        $cassette->readFromDisk();
    }

    public function getCurrentCassette()
    {
        if (shm_has_var($this->cassette, 1)) {
            return shm_get_var($this->cassette, 1);
        }

        return;
    }

    public function handleConnection($connection)
    {
        if ($this->getCurrentCassette() === null) {
            throw new BadMethodCallException(
                  'Invalid http request. No cassette inserted. '
                . ' Please make sure to insert a cassette in your unit-test using '
                . '$vcr->insertCassette(\'name\'); or annotation @vcr:cassette(\'name\').');
        }

        $request = Request::fromConnection($connection);
        $cassette = $this->getCurrentCassette();

        if ($cassette->hasResponse($request)) {
            $response = $cassette->playback($request, $connection);
        } else {
            $response = $request->execute();
            $cassette->record($request, $response);
        }

        fwrite($connection, $response);
    }

    /**
     * Factory method which retunrs a proxy object.
     * @return Proxy HTTP Proxy.
     */
    private function createProxy()
    {
        $that = $this;
        return new Proxy(
            $this->config->getProxySocket(),
            function ($connection) use ($that) {
                $that->handleConnection($connection);
            }
        );
    }

    /**
     * Factory method which returns all configured library hooks.
     * @return array Library hooks.
     */
    private function createLibraryHooks()
    {
        $hooks = array();
        foreach ($this->config->getLibraryHooks() as $hookName) {
            $hooks[] = new $hookName($this->config);
            end($hooks)->enable();
        }
        return $hooks;
    }

    /**
     * Turns off VCR.
     */
    public function __destruct()
    {
        if (self::$isOn) {
            $this->turnOff();
        }
    }
}


/**
 * Configuration.
 */
class Configuration
{
    private $cassettePath = 'tests/fixtures';
    private $proxySocket = 'tcp://0.0.0.0:8000';
    private $libraryHooks = array(
        'StreamWrapper'
    );
    private $turnOnAutomatically = true;

    public function getTurnOnAutomatically()
    {
        return $this->turnOnAutomatically;
    }

    public function getCassettePath()
    {
        return $this->cassettePath;
    }

    public function getLibraryHooks()
    {
        return $this->libraryHooks;
    }

    public function getProxySocket()
    {
        return $this->proxySocket;
    }
}

class Cassette
{
    private $name;
    private $config;
    private $httpInteractions = array();

    function __construct($name, Configuration $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function hasResponse(Request $request)
    {
        return $this->getResponse($request) !== null;
    }

    public function playback(Request $request, resource $connection)
    {
        $response = $this->getResponse($request);
        fwrite($connection, $response);
        return $response;
    }

    public function record(Request $request, $response)
    {
        $this->httpInteractions[$request->getSHA1()] = array($request, $response);
    }

    /**
     * Writes all http interactions to disk.
     * @return void
     */
    public function writeToDisk()
    {
        file_put_contents($this->getCassettePath(), json_encode($this->httpInteractions));
    }

    /**
     * Reads all http interactions from disk.
     * @return void
     */
    public function readFromDisk()
    {
        if (file_exists($this->getCassettePath())) {
            $this->httpInteractions = json_decode(file_get_contents($this->getCassettePath()));
        }
    }

    /**
     * Returns a response for given request or null if not found.
     *
     * @param  Request $request Request.
     * @return string Response for specified request.
     */
    private function getResponse(Request $request)
    {
        if (isset($this->httpInteractions[$request->getSHA1()])) {
            return $this->httpInteractions[$request->getSHA1()][1];
        }

        return null;
    }

    private function getCassettePath()
    {
        return $this->config->getCassettePath() . DIRECTORY_SEPARATOR . $this->name;
    }
}

class Request
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function getSHA1()
    {
        return sha1($this->request);
    }

    public static function fromConnection($connection)
    {
        return new Request(stream_get_contents($connection));
    }

    public function execute()
    {
        preg_match('/(CONNECT|Host:) (.*)(\s|\\r)/iU', $this->request, $matches);
        $host = $matches[2];
        $fp = fsockopen($host, 80);
        fwrite($fp, $this->request);
        return stream_get_contents($fp);
    }
}

/**
 * Proxy.
 */
class Proxy
{
    private $socketPath;
    private $socket;
    private $callback;

    public function __construct($socketPath, \closure $callback)
    {
        $this->socketPath = $socketPath;
        $this->callback = $callback;
    }

    public function start()
    {
        // setup IPC (inter process communication)
        $ipcPipes = array();
        if (!socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $ipcPipes)) {
            die(socket_strerror(socket_last_error()));
        }

        // fork proxy
        $this->proxyPid = pcntl_fork();

        if ($this->proxyPid == -1) {
            die('could not fork');
        } else if ($this->proxyPid) {
            // var_dump('master pid: ' . posix_getpid());
            $this->waitForProxy($ipcPipes);
        } else {
            // var_dump('child pid: ' . posix_getpid());
            $this->startProxy($ipcPipes);
        }
    }

    public function waitForProxy(array $ipcPipes)
    {
        socket_read($ipcPipes[1], 1024, PHP_NORMAL_READ);
        socket_close($ipcPipes[1]);
    }

    public function startProxy(array $ipcPipes)
    {
        // start proxy
        $socket = stream_socket_server($this->socketPath, $errno, $errstr);
        // $socket = socket_create_listen(8000);
        if (!is_resource($socket)) {
            throw new InvalidArgumentException("Unable to start proxy at {$this->socketPath}."
            . "Error ({$errno}): {$errstr}");
        }

        // stream_set_blocking($socket, false);

        // notify master
        if (!socket_write($ipcPipes[0], "success\n", strlen("success\n"))) {
            die(socket_strerror(socket_last_error()));
        }

        if (!$socket) {
            echo "$errstr ($errno)<br />\n";
        } else {
            // waiting for connections
            // while ($conn = socket_accept($socket)) {
            while ($conn = stream_socket_accept($socket, -1)) {
                $callback = $this->callback;
                $callback($conn);
                fclose($conn);
            }
            // Socket is closed when kill signal from master process is sent
            var_dump('can close socket here');
        }
    }

    public function stop()
    {
        var_dump('killing child process: ' . $this->proxyPid);
        posix_kill($this->proxyPid, SIGTERM);
        pcntl_wait($status);
    }
}


/**
 * StreamWrapper.
 */
class StreamWrapper
{
    private $config;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function enable()
    {
        stream_context_set_default(
            array(
                'http' => array('proxy' => $this->config->getProxySocket(),
                                'request_fulluri' => true),
                'https' => array('proxy' => $this->config->getProxySocket()),
                'ftp' => array('proxy' => $this->config->getProxySocket()),
                'ftps' => array('proxy' => $this->config->getProxySocket()),
            )
        );
    }

    public function disable()
    {
        stream_context_set_default(array('http' => array()));
    }
}

$vcr = new VCR(new Configuration);
// $vcr->turnOn();

// // throw exception
// // var_dump(file_get_contents('http://dev.bafoeg2go'));

$vcr->insertCassette('bafoeg2go');

var_dump(file_get_contents('http://dev.bafoeg2go'));

// $ch = curl_init();
// curl_setopt($ch, CURLOPT_URL, "http://dev.bafoeg2go");
// curl_setopt($ch, CURLOPT_PROXY, 'tcp://127.0.0.1:8000');
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// var_dump(curl_exec($ch));
// curl_close($ch);

