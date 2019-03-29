<?php
include '../config.php';

/*
创建socket
一个socket就是一个文件描述符（File Descriptor）,简称fd，是个整数
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
$listenfd = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if(FALSE === $listenfd) {
    exit('socket create error: '.socket_last_error());
}

/*
给套接字绑定名字
如果套接字是 AF_INET 族，那么 address 必须是一个四点分法的 IP 地址（例如 127.0.0.1 ） 
如果套接字是 AF_UNIX 族，那么 address 是 Unix 套接字一部分（例如 /tmp/my.sock ） 
端口仅在协议为AF_INET时要指定
*/
if(FALSE === socket_bind($listenfd, SERVER_ADDR, SERVER_PORT)) {
    exit('socket bind error: '.socket_last_error());
}

/*
设置为非阻塞模式
当执行connect, accept, receive, send等操作的时候，脚本不会暂停，而是立即返回之后继续执行后面的代码
*/
//socket_set_nonblock($listenfd);

/*
让套接字进入监听状态
第二个参数backlog是请求队列的最大长度
同一时间套接字只能处理一个连接请求，新来的请求会放进缓冲区进行排队，这个缓冲区就叫做请求队列
如果设置为SOMAXCONN，它取决于系统的值
默认值为0我理解为不允许排队
*/
socket_listen($listenfd);
socket_getsockname($listenfd, $addr, $port);
echo "server listening on {$addr}:{$port}".PHP_EOL;
//var_dump(socket_get_option($socket, SOL_SOCKET, SO_SNDBUF));

/*
accept接受客户端请求

代码执行到这里会暂停（如果是阻塞模式），控制权转移到操作系统的，
操作系统会返回在请求队列里的第一个socket，如果请求队列是空的，那么将阻塞在这里，直到有客户端连接过来
客户端连接过来之后，socket api在现有的套接字基础上拷贝一个新的套接字来和客户端通信，返回给应用程序，此时控制权重新回到应用程序
*/

/*
一些基本规则：
◦应始终尝试使用socket_select（）而不会超时。如果没有可用数据，您的程序应该无所事事，所以正确的做法是tv_sec设置为NULL。依赖于超时的代码通常不可移植且难以调试。
◦如果您不打算在socket_select（）调用之后检查其结果，则必须向监听集合添加套接字资源，并进行相应的响应。 socket_select（）返回后，必须检查所有数组中的所有套接字资源。必须写入任何可用于写入的套接字资源，并且必须读取可用于读取的任何套接字资源。
◦如果您对套接字进行读/写，则返回数组，请注意它们不一定读/写您请求的全部数据。准备好甚至只能读/写一个字节。
◦大多数套接字实现都常见的是，except数组捕获的唯一异常是套接字上收到的越界数据。
注意select执行以后readfds会变化，所以要在下一次while之前重新把需要监听的socket加到readfds里面去
*/
$writefds = null;
$except = null;
$connfds = [$listenfd];
$readfds = array($listenfd);
while(TRUE) {
    
    if(socket_select($readfds, $writefds, $except, NULL) > 0) { // 操作系统老大，这些socket可读的时候告我一声
        if(in_array($listenfd, $readfds)) { // 监听的fd可读，说明有连接过来了
            $newfd = socket_accept($listenfd); // accept会copy一个fd，里面有客户端的IP端口标识，为了能区分多个客户端
            socket_getpeername($newfd, $client_addr, $client_port);
            echo "connection accepted from {$client_addr}:{$client_port}".PHP_EOL;
            $connfds[] = $newfd;
            
            
        } else { // 有数据可以读了！
            
            foreach($readfds as $readfd) {
                socket_getpeername($readfd, $client_addr, $client_port);
                echo "readable socket: {$client_addr}:{$client_port}".PHP_EOL;

                $data = '';
                while($buf = socket_read($readfd, 8192)) {
                    $data .= $buf;
                }
                
                if(!empty($data)) {
                    echo 'read: '.$data.PHP_EOL;
                    
                    if(FALSE === @socket_write($readfd, $data)) {
                        echo 'write fail, error: '.socket_last_error().PHP_EOL;
                    } else {
                        echo 'write: '.$data.PHP_EOL;
                    }
                    
                }
                // 从监听里面移除掉
                socket_close($readfd);
                
                $key = array_search($readfd, $connfds);
                if(FALSE !== $key) {
                    unset($connfds[$key]);
                }
                    
                
            }
            
            
            
        }
        
        $readfds = $connfds; // 加入监听

        
    }
    
    
    
    
    
    
}

socket_close($socket);

/*
启动这个php脚本以后是单个进程在处理客户端请求
在阻塞模式下，当代码进入while条件以后，直到执行完while里面的所有代码才会回到while最开始的地方：accept新的连接，
也就是说这个脚本同一时间只能处理一个客户端的请求，如果有新的请求过来，只能等上一个请求处理完了才行
另外，accept，read，write操作都是有可能会阻塞的
比如accept完一个客户端连接后，客户端没有发数据过来，这时候进程会阻塞在read这里，这个时候如果另外一个客户端连接请求过来，也是没法处理的，只能排队
select可以让操作系统处理多个socket，一旦有socket可读写select就会返回
*/
