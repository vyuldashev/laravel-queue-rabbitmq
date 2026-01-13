<?php

if (($sock = socket_create(AF_INET, SOCK_STREAM, 0)) === false) {
    echo 'socket_create() failed: reason: '.socket_strerror(socket_last_error())."\n";
    exit;
}

if (socket_bind($sock, '127.0.0.1', $argv[1]) === false) {
    echo 'socket_bind() failed: reason: '.socket_strerror(socket_last_error($sock))."\n";
    exit;
}

if (socket_listen($sock, 5) === false) {
    echo 'socket_listen() failed: reason: '.socket_strerror(socket_last_error($sock))."\n";
    exit;
}

do {
    // Just read and do nothing
    $buf = socket_read($sock, 10, PHP_NORMAL_READ);
    usleep(10000);
} while (true);
