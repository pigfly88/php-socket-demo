<?php
include '../config.php';

/*
创建socket
domain(协议)
AF_INET是基于IPv4网络协议
AF_UNIX是本地通讯协议（进程间通讯）

type(套接字类型)
SOCK_STREAM: TCP
SOCK_DGRAM: UDP


protocol(指定domail套接字下的具体协议类型)
TCP: SOL_TCP
UDP: SOL_UDP
也可以通过getprotobyname方法获得，例如：getprotobyname('tcp')
*/
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(FALSE === $socket) {
    exit('socket create error: '.socket_last_error());
}

/*
给套接字绑定名字
如果套接字是 AF_INET 族，那么 address 必须是一个四点分法的 IP 地址（例如 127.0.0.1 ） 
如果套接字是 AF_UNIX 族，那么 address 是 Unix 套接字一部分（例如 /tmp/my.sock ） 
端口仅在协议为AF_INET时要指定
*/
if(FALSE === socket_bind($socket, SERVER_ADDR, SERVER_PORT)) {
    exit('socket bind error: '.socket_last_error());
}

/*
设置为非阻塞模式
当执行connect, accept, receive, send等操作的时候，脚本不会暂停，而是立即返回之后继续执行后面的代码
*/
//socket_set_nonblock($socket);

/*
让套接字进入监听状态
第二个参数backlog是请求队列的最大长度
同一时间套接字只能处理一个连接请求，新来的请求会放进缓冲区进行排队，这个缓冲区就叫做请求队列
如果设置为SOMAXCONN，它取决于系统的值
默认值为0我理解为不允许排队
*/
socket_listen($socket, 2);
socket_getsockname($socket, $addr, $port);
echo "server listening on {$addr}:{$port}".PHP_EOL;
//var_dump(socket_get_option($socket, SOL_SOCKET, SO_SNDBUF));

/*
accept接受客户端请求
代码执行到这里会暂停（如果是阻塞模式），控制权转移到操作系统的socket api，直到有客户端连接过来
客户端连接过来之后，socket api在现有的套接字基础上拷贝一个新的套接字来和客户端通信，返回给应用程序，此时控制权重新回到应用程序
*/
while(TRUE) {
    $new_socket = @socket_accept($socket);

    if(is_resource($new_socket)) {
        socket_getpeername($new_socket, $client_addr, $client_port);
        echo "connection accepted from {$client_addr}:{$client_port}".PHP_EOL;
    }

    /*
    阻塞模式下，如果客户端没有数据发送过来，代码执行到这里也会暂停，直到有数据过来
    */
    $data = @socket_read($new_socket, 8192);

    if(!empty($data)) {
        echo 'read: '.$data.PHP_EOL;
        if(FALSE === @socket_write($new_socket, $data)) {
            echo 'write fail, error: '.socket_last_error().PHP_EOL;
        } else {
            echo 'write: '.$data.PHP_EOL;
        }
    }
}

socket_close($socket);

/*
启动这个php脚本以后是单个进程在处理客户端请求
在阻塞模式下，当代码进入while条件以后，直到执行完while里面的所有代码才会回到while最开始的地方：accept新的连接，
也就是说这个脚本同一时间只能处理一个客户端的请求，如果有新的请求过来，只能等上一个请求处理完了才行
另外，accept，read，write操作都是有可能会阻塞的
比如accept完一个客户端连接后，客户端没有发数据过来，这时候进程会阻塞在read这里，这个时候如果另外一个客户端连接请求过来，也是没法处理的，只能排队
*/
