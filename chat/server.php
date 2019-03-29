<?php
/*
聊天室server
*/
include '../config.php';

$listenfd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(FALSE === $listenfd) {
    exit('socket create error: '.socket_last_error());
}

if(FALSE === socket_bind($listenfd, SERVER_ADDR, SERVER_PORT)) {
    exit('socket bind error: '.socket_last_error());
}

socket_listen($listenfd);
socket_getsockname($listenfd, $addr, $port);
echo "server listening on {$addr}:{$port}".PHP_EOL;

$writefds = null;
$except = null;
$connfds = [$listenfd];
$readfds = array($listenfd);

while(TRUE) {   
    if(socket_select($readfds, $writefds, $except, NULL) > 0) {
        if(in_array($listenfd, $readfds)) {
            $newfd = socket_accept($listenfd);
            socket_getpeername($newfd, $client_addr, $client_port);
            $client_name = "{$client_addr}:{$client_port}";
            echo "connection accepted from {$client_name}".PHP_EOL;
            $connfds[] = $newfd;
            @socket_write($newfd, "hi {$client_name}".PHP_EOL);           
        } else {
            foreach($readfds as $readfd) {
                socket_getpeername($readfd, $client_addr, $client_port);
                $client_name = "{$client_addr}:{$client_port}";
                echo "readable socket: {$client_name}".PHP_EOL;

                $data = @socket_read($readfd, 8192, PHP_NORMAL_READ);

                // 客户端断开连接
                if(!$data) {
                    echo "offline socket: {$client_name}".PHP_EOL;
                    socket_close($readfd);
                    $key = array_search($readfd, $connfds);
                    if(FALSE !== $key) {
                        unset($connfds[$key]);
                    }
                }
                
                // 把消息发给所有客户端
                $data = trim($data);
                if(!empty($data)) {
                    foreach($connfds as $connfd) {
                        if(in_array($connfd, [$readfd, $listenfd])) { // 不发给自己和server
                            continue;
                        }
                        $msg = "{$client_name}: {$data}".PHP_EOL;
                        if(FALSE === @socket_write($connfd, $msg)) {
                            echo 'write fail, error: '.socket_last_error().PHP_EOL;
                        }
                    }
                }
            }
        }
        $readfds = $connfds; // 加入监听
    }
    
}

socket_close($socket);