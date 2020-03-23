<?php
require 'vendor/autoload.php';
use QL\QueryList;
use QL\Ext\AbsoluteUrl;
use Medoo\Medoo;

$dbconfig = require "./config/db-config.php";
$rule = require './rules/yjb_cn_rule.php';

$ql = QueryList::getInstance();
$ql->use(AbsoluteUrl::class);

$start = time();
echo "Start at {$start}\n";

//列表第一页
$link_arr = QueryList::get($rule['list_url'])->rules($rule['list_rules'])->queryData();

$articles = [];
$created_at = time();
echo "  Start First List: ".$rule['list_url'];

//列表第一页的所有详情页
foreach ($link_arr as $key => $value) {
    $detail_url = $value['detail_link'];
    echo "\n   >>> start detail page ({$key}): {$detail_url}";
    $rt = QueryList::get($detail_url)->rules($rule['detail_rules'])->absoluteUrl($detail_url)->queryData();
    $rt[0]['site'] = $rule['site_name'];
    $rt[0]['source_url'] = $detail_url;
    $rt[0]['source_url_md5'] = md5($detail_url);
    $rt[0]['created_at'] = $created_at;
    $c = $rt[0]['content'];
    $c = trim(str_replace(PHP_EOL, '', $c));
    $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/","",$c);
    $articles[] = $rt[0];
    //print_r($rt);
}
//unset($link_arr);
//print_r($articles);

if (!empty($articles)) {
    $db = new Medoo($dbconfig);
    $db->insert("article", $articles);
    //var_dump($db->error());
    //echo $db->last_query();
}

echo "\n  First List Done....";

if ($rule['list_next_max']+0 <= 0) {
    echo "\nFinished: ".(time()-$start)." s\n\n";
    exit();
}

//接下来的列表页及详情页

if (isset($rule['list_next_url']) && isset($rule['list_next_max']) && isset($rule['list_next_from'])) {
    $max_page = $rule['list_next_max'];
    $next_url = $rule['list_next_url'];
    $i = $rule['list_next_from']+0;
    for (; $i<=$max_page; $i++) {
        $articles = [];
        $next_list_url = sprintf($next_url, $i);

        echo "\n   Start The Other Lists ({$i}): {$next_list_url}";
        $link_arr = QueryList::get($next_list_url)->rules($rule['list_rules'])->queryData();
        foreach ($link_arr as $key => $value) {
            $detail_url = $value['detail_link'];
            echo "\n   >>> start the other detail ({$i} - {$key}): {$detail_url}";
            $rt = QueryList::get($detail_url)->rules($rule['detail_rules'])->absoluteUrl($detail_url)->queryData();
            $rt[0]['site'] = $rule['site_name'];
            $rt[0]['source_url'] = $detail_url;
            $rt[0]['source_url_md5'] = md5($detail_url);
            $rt[0]['created_at'] = $created_at;
            $c = $rt[0]['content'];
            $c = trim(str_replace(PHP_EOL, '', $c));
            $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/", "", $c);
            $articles[] = $rt[0];
            //print_r($rt);
        }
    }
    echo "\n  The Other Lists Done....";
}
else {
    echo "\n  No More List Pages OR List Rule Error....";
}

echo "\nFinished: ".(time()-$start)." s\n\n";

