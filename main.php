<?php
require 'vendor/autoload.php';
use QL\QueryList;
use QL\Ext\AbsoluteUrl;
use Medoo\Medoo;

$usage =<<<STR
    php main.php -t=task [-n=site_name [-f [-l=list_url [-s=single_page [-d]]]]]
    OR:
    php main.php -tTASK [-nSITE_NAME [-f [-lLIST_URL [-sSINGLE_PAGE [-d]]]]]
    -t: required, task name.
    -n: optional, specific web site name in task setting file.
    -f: optional, only first list page of the web site.
    -l: optional, specific list url of the web site.
    -s: optional, specific datail page of the web site.
    -d: optional, dump the data into a file which is in 'dump' directory, it is valid only for option -s. The default method is to store the data into the database, you need to have a database configuration file in 'config' directory.


STR;

$options = getopt("t:n::fl::s::d");
if (count($options) < 1) {
    echo $usage;
    exit();
}
$task_name = $options['t'];

$dbconfig = require "./config/db-config.php";
$task = require "task.php";

if (isset($task[$task_name])) {
    $sites = $task[$task_name];
    if (isset($options['n']) && isset($sites[$options['n']])) {
        $rule_file = $sites[$options['n']];
        if (is_file($rule_file)) {
            $rule = require $rule_file;
            $detail_url = '';
            $dump_file = false;
            //only first page of list
            if (array_key_exists('f', $options)) { //
                $rule['list_next_max'] = 0;
            }
            //only specific list
            else if (isset($options['l'])) {
                $rule['list_url'] = $options['l'];
                $rule['list_next_max'] = 0;
            }
            //only specific detail page
            else if (isset($options['s'])) {
                $rule = $rule['detail_rules'];
                $detail_url = $options['s'];
                if (isset($options['d'])) {
                    $dump_file = true;
                }
            }
            work($rule, $detail_url, $dump_file);
        }
    }
    else {
        foreach ($sites as $site => $rule_file) {
            if (is_file($rule_file)) {
                $rule = require $rule_file;
                if (array_key_exists('f', $options)) { //
                    $rule['list_next_max'] = 0;
                }
                work($rule);
            }
            else {
                continue;
            }
            unset($rule);
        }
    }
}

function work($rule, $detail_url = '')
{
    print_r($rule);
    exit();

    $ql = QueryList::getInstance();
    $ql->use(AbsoluteUrl::class);

    $start = time();
    echo "strarting>>> ".$start."\n";

    if ($detail_url != '') {
        //
        exit();
    }

    //列表第一页
    $link_arr = QueryList::get($rule['list_url'])->rules($rule['list_rules'])->queryData();

    $articles = [];
    $created_at = time();

    //列表第一页的所有详情页
    foreach ($link_arr as $key => $value) {
        $detail_link = $value['detail_link'];
        $rt = QueryList::get($detail_link)->rules($rule['detail_rules'])->absoluteUrl($detail_link)->queryData();
        $rt[0]['site'] = $rule['site_name'];
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

    //只处理列表第一页
    if ($rule['list_next_max']+0 <= 0) {
        exit();
    }
    echo "finished>>> ".(time()-$start)." ms";

    //接下来的列表页及详情页
    /*
    if iseet($rule['list_next_url'] && $rule['list_next_max']) {
        $max_page = $rule['list_next_max'];
        $next_url = $rule['list_next_url'];
        for ($i=1; $i<=$max_page; i++) {
            $next_list_url = sprintf($next_url, $i);
            $link_arr = QueryList::get($next_list_url)->rules($site['list_rules'])->queryData();

            foreach ($link_arr as $key => $value) {
                $detail_link = $value['detail_link'];
                $rt = QueryList::get($detail_link)->rules($rule['detail_rules'])->absoluteUrl($detail_link)->queryData();
                print_r($rt);
            }
        }
    }
    */
}
