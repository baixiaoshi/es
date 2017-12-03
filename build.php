<?php
//build数据的脚本，放到定时任务中去
require_once "./FetchData.php";
require_once "./EsHandle.php";

$alias_index = 'b_index';
$es_type = 'my_type';
$default_index = 'b_index_v_1';
$esHandle = new EsHandle();
$fetchData = new FetchData();

$s_time = microtime(true);

//1.获取当前别名信息
$indexInfo = $esHandle->getIndexInfo($alias_index);
if (isset($indexInfo['new_index_name']) && ($indexInfo['new_index_name'] == $default_index)) {
    $create_index = $default_index;
} else {
    $create_index = $indexInfo['new_index_name'];
}

//2.每次创建新索引
$exists = $esHandle->createIndex($create_index);
if ($exists) {
    $esHandle->createMapping($create_index, $es_type);
}
//3.将数据build进新索引中
$fetchData->buildData($create_index, $es_type);

//4.切换别名指向
$ret = $esHandle->doAlias($alias_index, $indexInfo['old_index_name'], $indexInfo['new_index_name']);

//5.删除旧索引
if (!empty($indexInfo['old_index_name'])) {
    $esHandle->deleteIndex($indexInfo['old_index_name']);
}
$e_time = microtime(true);
$spend = $e_time - $s_time;

file_put_contents("/tmp/test.log", "spend_time=$spend\r\n", FILE_APPEND);