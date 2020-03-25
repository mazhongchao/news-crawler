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
    -d: optional, dump the data into a file which is in 'dump' directory, it is valid only for option -s.
        The default method is to store the data into the database, you need to have a database configuration file in 'config' directory.


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
    //print_r($rule);
    //exit();

    $ql = QueryList::getInstance();
    $ql->use(AbsoluteUrl::class);

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->auth('admin');
    $redis->select(10);

    $start = time();
    echo "Start at>>> ".$start;

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
        echo "\nFinished>>> ".(time()-$start)." s\n\n";
        exit();
    }

    $articles = [];
    $created_at = time();

    echo "\n  Start First List: ".$rule['list_url'];

    //列表第一页
    //$link_arr = QueryList::get($rule['list_url'])->rules($rule['list_rules'])->absoluteUrl($rule['list_url'])->queryData();
    $link_arr = QueryList::get($rule['list_url'])->rules($rule['list_rules'])->queryData();
    //print_r($link_arr);exit();

    //列表第一页的所有详情页
    foreach ($link_arr as $key => $value) {
        $detail_url = $value['detail_link'];
        echo "\n   >>> start detail page ({$key}): {$detail_url}";
        if (!is_collected($redis, $detail_url)) {
            $rt = QueryList::get($detail_url)->rules($rule['detail_rules'])->absoluteUrl($detail_url)->queryData();
            $rt[0]['site'] = $rule['site_name'];
            $rt[0]['source_url'] = $detail_url;
            $rt[0]['source_url_md5'] = md5($detail_url);
            $rt[0]['created_at'] = $created_at;
            $c = $rt[0]['content'];
            $c = trim(str_replace(PHP_EOL, '', $c));
            $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/","",$c);
            $articles[] = $rt[0];
        }
        else{
            echo "\n      collected....";
        }
    }

    //unset($link_arr);
    if (!empty($articles)) {
        dump_to_db($articles);
        add_url_hash($redis, $articles);
    }

    echo "\n  First List Done....";

    //如果只处理列表第一页
    if ($rule['list_next_max']+0 <= 0) {
        echo "\nFinished>>> ".(time()-$start)." s\n\n";
        exit();
    }

    //接下来的列表页及详情页
    if (isset($rule['list_next_url']) && isset($rule['list_next_max']) && isset($rule['list_next_from'])) {
        $max_page = $rule['list_next_max'];
        $next_url = $rule['list_next_url'];
        $i = $rule['list_next_from']+0;
        for ($i; $i<=$max_page; $i++) {
            $articles = [];
            $next_list_url = sprintf($next_url, $i);

            echo "\n   Start The Other Lists ({$i}): ".$next_list_url;
            //$link_arr = QueryList::get($next_list_url)->rules($rule['list_rules'])->absoluteUrl($next_list_url)->queryData();
            $link_arr = QueryList::get($next_list_url)->rules($rule['list_rules'])->queryData();
            foreach ($link_arr as $key => $value) {
                $detail_url = $value['detail_link'];
                echo "\n   >>> start the other detail ({$i} - {$key}): ".$detail_url;
                if (!is_collected($redis, $detail_url)) {
                    $rt = QueryList::get($detail_url)->rules($rule['detail_rules'])->absoluteUrl($detail_url)->queryData();
                    $rt[0]['site'] = $rule['site_name'];
                    $rt[0]['source_url'] = $detail_url;
                    $rt[0]['source_url_md5'] = md5($detail_url);
                    $rt[0]['created_at'] = $created_at;
                    $c = $rt[0]['content'];
                    $c = trim(str_replace(PHP_EOL, '', $c));
                    $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/", "", $c);
                    $articles[] = $rt[0];
                }
            }
            if (!empty($articles)){
                dump_to_db($articles);
                add_url_hash($redis, $articles);
            }
        }
        echo "\n  The Other Lists Done....";
    }
    else {
        echo "\n  No More List Pages OR List Rule Error....";
    }
    echo "\nFinished>>> ".(time()-$start)." ms\n\n";
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