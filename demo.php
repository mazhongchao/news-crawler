<?php

require 'vendor/autoload.php';

use QL\QueryList;

$rules = require 'demo_rules.php';

//列表页
#$link_arr = QueryList::get($rules['yjb.cn']['list_url'])->rules($rules['yjb.cn']['list_rules'])->range($rules['yjb.cn']['list_range'])->queryData();
$link_arr = QueryList::get($rules['yjb.cn']['list_url'])->rules($rules['yjb.cn']['list_rules'])->queryData();
print_r($link_arr);

foreach ($link_arr as $key => $value) {
    $detail_link = $value['detail_link'];
    $rt = QueryList::get($detail_link)->rules($rules['yjb.cn']['detail_rules'])->queryData();
    print_r($rt);
}

//另一种写法
#$rt = QueryList::get($url)->rules($rules['yjb.cn']['detail_rules'])->query()->getData()->all();
