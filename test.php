<?php

/**
 *  1. Start proxy server
 *  2. Set env variales (curl)
 *  3. Intercept http calls
 *    3.1 StreamWrapper
 *    3.2 Curl
 *    3.3 Soap
 */
$parentPid = posix_getpid();
$pid = pcntl_fork();
// echo "Parent: {$parentPid} \n";
// echo "Child: {$pid} \n";

if ($pid == -1) {
    die('could not fork');
} else if ($pid) {
    // wait for socket to open, TODO: Optimize
    sleep(1);

    // interceptor
    stream_context_set_default(
        array(
          'http' => array(
            'request_fulluri' => true,
            'proxy' => 'tcp://127.0.0.1:8000',
            'user_agent' => 'Test Agent'
          )
        )
    );

    // 1. set curl env

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://dev.bafoeg2go");
    curl_setopt($ch, CURLOPT_PROXY, 'tcp://127.0.0.1:8000');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    var_dump(curl_exec($ch));
    curl_close($ch);

    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, "http://www.google.com/");
    // // curl_setopt($ch, CURLOPT_PROXY, 'localhost:8000');
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // var_dump(curl_exec($ch));
    // curl_close($ch);

    // var_dump(file_get_contents('http://www.google.com/'));
    // var_dump(file_get_contents('http://www.google.com/'));

    posix_kill($pid, SIGTERM);
    pcntl_wait($pid);

} else {
    // we are the child
    $socket = stream_socket_server("tcp://0.0.0.0:8000", $errno, $errstr);
    stream_set_blocking($socket, false);

    if (!$socket) {
      echo "$errstr ($errno)<br />\n";
    } else {
      echo "socket open\n";

      while ($conn = stream_socket_accept($socket)) {
        // var_dump(stream_get_meta_data($conn));
        $request = stream_get_contents($conn);
        $requestId = sha1($request);
        preg_match('/(CONNECT|Host:) (.*)(\s|\\r)/iU', $request, $matches);
        var_dump($matches);
        $host = $matches[2];
        $fp = fsockopen($host, 80);
        fwrite($fp, $request);
        $response = stream_get_contents($fp);
        fwrite($conn, $response);

        // fwrite($conn, "HTTP/1.1 200 OK\n");
        // fwrite($conn, "Content-Type: text/html;charset=UTF-8\n");
        // fwrite($conn, "\n");
        // fwrite($conn, $requestId);
        // fwrite($conn, $request);
        fclose($conn);
      }
      fclose($socket);
    }
}
