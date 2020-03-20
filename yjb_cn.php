<?php
require 'vendor/autoload.php';
use QL\QueryList;
use QL\Ext\AbsoluteUrl;

$rules = require './rules/yjb_cn_rules.php';

$ql = QueryList::getInstance();
$ql->use(AbsoluteUrl::class);

//列表第一页
$link_arr = QueryList::get($site['list_url'])->rules($site['list_rules'])->queryData();

//第一页的所有详情页
foreach ($link_arr as $key => $value) {
    $detail_link = $value['detail_link'];
    $rt = QueryList::get($detail_link)->rules($site['detail_rules'])->absoluteUrl($detail_link)->queryData();
    print_r($rt);
}
unset($link_arr);

//接下来的列表页及详情页
//if iseet($site['list_next_url'] && $site['list_next_max']) {
//    $max_page = $site['list_next_max'];
//    $next_url = $site['list_next_url'];
//    for ($i=1; $i<=$max_page; i++) {
//        $next_list_url = sprintf($next_url, $i);
//        $link_arr = QueryList::get($next_list_url)
//        ->rules($site['list_rules'])->range($site['list_range'])->query()->getData();
//
//        foreach ($link_arr as $key => $value) {
//            $detail_link = $value['detail_link'];
//            $rt = QueryList::get($detail_link)->rules($site['detail_rules'])->absoluteUrl($detail_link)->queryData();
//            print_r($rt);
//        }
//    }
//}



