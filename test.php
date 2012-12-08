<?php

/**
 *  1. Start proxy server
 *  2. Set env variales (curl)
 *  3. Intercept http calls
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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://www.example.com/");
    curl_setopt($ch, CURLOPT_PROXY, 'localhost:8000');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    var_dump(curl_exec($ch));
    curl_close($ch);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://www.example.com/");
    curl_setopt($ch, CURLOPT_PROXY, 'localhost:8000');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    var_dump(curl_exec($ch));
    curl_close($ch);

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

      // http_interactions
      //   uri
      //   body
      //       encoding
      //       string
      //       headers
      // response

      while ($conn = stream_socket_accept($socket)) {
        $request = stream_get_contents($conn);
        $requestId = sha1($request);
        fwrite($conn, $requestId);
        // var_dump(http_parse_headers($request));
        // fwrite($conn, print_r($conn, true));
        // fwrite($conn, $request);
        // fwrite($conn, 'The local time is ' . date('n/j/Y g:i a') . "\n");
        fclose($conn);
      }
      fclose($socket);
    }
}

