<?php
include '../config.php';

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$conn = @socket_connect($socket, SERVER_ADDR, SERVER_PORT);

if(TRUE !== $conn) {
    echo 'connect error: '.socket_last_error();
} else {
    // 连接成功
    echo 'connect to '.SERVER_ADDR.':'.SERVER_PORT.' success'.PHP_EOL;
    fwrite(STDOUT, 'say something:');
    
    while('q' !== ($data = fgets(STDIN))) {
        //echo "write: {$data}".PHP_EOL;
        if(FALSE === socket_write($socket, $data)) {
            echo 'write error: '.socket_last_error().PHP_EOL;
        }
        
        $data = @socket_read($socket, 8192);
        if(FALSE === $data) {
            echo 'read error: '.socket_last_error().PHP_EOL;
        } else {
            echo 'read: '.$data.PHP_EOL;
        }
    }
    if(fgets(STDIN) == 'q') {
        socket_close($socket);
    }
    
    
}