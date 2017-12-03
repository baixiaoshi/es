<?php

    //此脚本用于拉取线上商品信息到自己的数据库

    //启动一个daemon进程,fork两次，一次创建子进程，一次脱离终端
    function daemonize() {
        //设置文件权限掩码
        umask(0);
        //fork child process
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new Exception('fork fail');
        } elseif ($pid > 0) {
            // parent child
            exit(0);
        }
        //set child process to session leader
        if (-1 === posix_setsid()) {
            throw new Exception('setsid fail');
        }

        //fork again out terminal
        $pid = pcntl_fork();
        
        if (-1 === $pid) {
            throw new Exception("fork fail");   
        } elseif (0 !== $pid) {
            exit(0);
        }
    }
    //设置超时和内存限制
    set_time_limit(0);
    ini_set('memory_limit', '512M');
    daemonize();

    require "./FetchData.php";
    $fetchData = new FetchData();
    //下面的代码都是在daemon进程中执行了
    //线上29w多的数据，开15个进程，每个进程处理2w的记录条数,就可以确保全部处理完,如果拉取100条数据耗费3秒，理论应该大于1分钟小于2分钟就可以拉取完成
    //但是考虑到cpu切换成本，可能要翻倍了
    
    $pid_arr = [];
    for ($i = 1; $i <= 15; $i++) {

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new Exception('fork fail');
        } elseif ($pid == 0) { //child process
            $pid_arr = posix_getpid();
            $start_id = ($i - 1) * 20000;
            $end_id = $start_id + 20000;
            $fetchData->getRows($start_id, $end_id);
            //子进程处理完成必须退出
            exit(0);
        }
    }

    //当数据处理完之后，父进程回收所有的子进程资源，不然cpu不敢释放子进程的所占用的资源
    if ($pid_arr) {
        foreach ($pid_arr as $_key => $_pid) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);
            if($res == -1 || $res > 0) {
                unset($childs[$_key]);
            }
        }
    }


