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

$task = require "task.php";
$task_name = $options['t'];

if (isset($task[$task_name])) {
    $sites = $task[$task_name];
    if (isset($options['n']) && isset($sites[$options['n']])) {
        $rule_file = $sites[$options['n']];
        if (is_file($rule_file)) {
            $rule = require $rule_file;
            $detail_url = '';
            $dump_file = false;
            //only first page of list
            if (array_key_exists('f', $options)) {
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
                if (array_key_exists('f', $options)) {
                    $rule['list_next_max'] = 0;
                }
                work($rule);
            }
        }
    }
}

function work($rule, $detail_url = '', $dump_file = false)
{
    print_r($rule);
    exit();

    $ql = QueryList::getInstance();
    $ql->use(AbsoluteUrl::class);

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(10);

    $start = time();
    echo "strarting>>> ".$start."\n";

    //only for single detail page
    if ($detail_url != '') {
        $rt = QueryList::get($detail_url)->rules($rule)->queryData();
        if ($dump_file) {
            //write the data into file, not implement it yet.
        }
        else {
            if (!is_collected($redis, $detail_url)) {
                $db = new Medoo($dbconfig);
                $db->insert("article", $rt[0]);
                add_url_hash($redis, $rt[0]);
            }
        }
        exit();
    }

    $articles = [];
    $created_at = time();

    //列表第一页
    $link_arr = QueryList::get($rule['list_url'])->rules($rule['list_rules'])->queryData();
    //列表第一页的所有详情页
    foreach ($link_arr as $key => $value) {
        $detail_link = $value['detail_link'];
        if (!is_collected($redis, $detail_url)) {
            $rt = QueryList::get($detail_link)->rules($rule['detail_rules'])->absoluteUrl($detail_link)->queryData();
            $rt[0]['site'] = $rule['site_name'];
            $rt[0]['source_url'] = $detail_link;
            $rt[0]['source_url_md5'] = md5($detail_link);
            $rt[0]['created_at'] = $created_at;
            $articles[] = $rt[0];
        }
    }
    //print_r($articles);
    unset($link_arr);
    dump_to_db($articles);
    add_url_hash($redis, $articles);

    //只处理列表第一页
    if ($rule['list_next_max']+0 <= 0) {
        exit();
    }

    //接下来的列表页及详情页
    /*
    if iseet($rule['list_next_url'] && $rule['list_next_max']) {
        $max_page = $rule['list_next_max'];
        $next_url = $rule['list_next_url'];
        for ($i=1; $i<=$max_page; i++) {
            $articles = [];
            $next_list_url = sprintf($next_url, $i);
            $link_arr = QueryList::get($next_list_url)->rules($site['list_rules'])->queryData();
            foreach ($link_arr as $key => $value) {
                $detail_link = $value['detail_link'];
                if (!is_collected($redis, $detail_url)) {
                    $rt = QueryList::get($detail_link)->rules($rule['detail_rules'])->absoluteUrl($detail_link)->queryData();
                    $rt[0]['site'] = $rule['site_name'];
                    $rt[0]['source_url'] = $detail_link;
                    $rt[0]['source_url_md5'] = md5($detail_link);
                    $rt[0]['created_at'] = $created_at;
                    $articles[] = $rt[0];
                }
                print_r($rt);
            }
            dump_to_db($articles);
        }
    }
    */
    echo "finished>>> ".(time()-$start)." ms";
}

function is_collected($redis, $url)
{
    if ($redis->hExists('url_hash', md5($url))) {
        return true;
    }
    return false;
}

function add_url_hash($redis, $articles)
{
    foreach ($articles as $article) {
        $redis->hset('url_hash', $article['source_url_md5'], $article['created_at']);
    }
}

function dump_to_db($articles)
{
    $dbconfig = require "./config/db-config.php";
    $db = new Medoo($dbconfig);
    $db->insert("article", $articles);
    //var_dump($db->error());
    //echo $db->last_query();
}