# ansio
async event based on libevent 基于libevent的异步服务框架，通过自定义内存数据处理和异步延迟方式持久花数据可以同时处理百万级别并发连接(内置websocket服务)底层基于linux epoll

#安装
安装libevent模块php部分加入libevent.so路径在runtime下面

#demo:


1. server启动：php server.php demo start (demo中同时运行2个实例包括websocket。websocket也可以独立运行)
2. client debug: 终端直接  nc 127.0.0.1 9999 回车 然后输入{"c":"test\\\test","p":{"argu1":123,"argu2":456}}\~\~code\~end\~\~
3. websocket 客户端在 webscketclient目录。通过 http方式访问
