<?php
require 'vendor/autoload.php';
use QL\QueryList;
use QL\Ext\AbsoluteUrl;
use Medoo\Medoo;

$usage =<<<STR
    php main.php -t=task [-n=site_name [-l=list_url [-s=single_page [-d=dump_way]]]]
    OR:
    php main.php -tTASK [-nSITE_NAME [-lLIST_URL [-sSINGLE_PAGE [-dDUMP_WAY]]]] 
    -t: required, task name.
    -n: optional, specific web site name in task setting file.
    -l: optional, specific list url of web site.
    -d: optional, the data dump method(eg: name of file stored). The default method is to store data into the database, you need to have a database configuration file in config directory.


STR;

$options = getopt("t:n::l::s::d::");
if (count($options) < 1) {
    echo $usage;
    exit();
}

$dbconfig = require "./config/db-config.php";
$task = require "task.php";
$task_name = $options['t'];

if (isset($task[$task_name])) {
    $sites = $task[$task_name];
    foreach ($sites as $rule_file) {
        if (is_file($rule_file)) {
            $rules = require $rule_file;
            work($rules);
        }
        else {
            continue;
        }
        unset($rules);
        //print $rule_file."\n";
    }
}

function work($rules)
{
    print_r($rules);
    exit();
    $site = $rules['yjb.cn'];

    $ql = QueryList::getInstance();
    $ql->use(AbsoluteUrl::class);

    $start = time();
    echo "strarting>>> ".$start."\n";

    //列表第一页
    $link_arr = QueryList::get($site['list_url'])->rules($site['list_rules'])->queryData();

    $articles = [];
    $created_at = time();

    //列表第一页的所有详情页
    foreach ($link_arr as $key => $value) {
        $detail_link = $value['detail_link'];
        $rt = QueryList::get($detail_link)->rules($site['detail_rules'])->absoluteUrl($detail_link)->queryData();
        $rt[0]['site'] = $site['site_name'];
        $rt[0]['source_url'] = $detail_link;
        $rt[0]['source_url_md5'] = md5($detail_link);
        $rt[0]['created_at'] = $created_at;
        $articles[] = $rt[0];
        //print_r($rt);
    }
    unset($link_arr);
    //print_r($articles);

    $db = new Medoo($dbconfig);
    $db->insert("article", $articles);
    //var_dump($db->error());
    //echo $db->last_query();

    echo "finished>>> ".(time()-$start)." ms";

    //接下来的列表页及详情页
    /*
    if iseet($site['list_next_url'] && $site['list_next_max']) {
        $max_page = $site['list_next_max'];
        $next_url = $site['list_next_url'];
        for ($i=1; $i<=$max_page; i++) {
            $next_list_url = sprintf($next_url, $i);
            $link_arr = QueryList::get($next_list_url)->rules($site['list_rules'])->queryData();

            foreach ($link_arr as $key => $value) {
                $detail_link = $value['detail_link'];
                $rt = QueryList::get($detail_link)->rules($site['detail_rules'])->absoluteUrl($detail_link)->queryData();
                print_r($rt);
            }
        }
    }
    */
}
