<?php
require 'vendor/autoload.php';
use QL\QueryList;
use QL\Ext\CurlMulti;
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
//ugly global variable, KILL IT
$imgurls = ['img_src'=>[], 'img_loc'=>[]];

date_default_timezone_set('PRC');
require "functions.php";
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
            //only first list page
            if (array_key_exists('f', $options)) {
                $rule['list_next_max'] = 0;
            }
            //only specific list page
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
else {
    echo "\nTASK IS NOT EXISTS.";
}

function work($rule, $detail_url = '', $dump_file = false)
{
    $ql = QueryList::getInstance();
    $ql->use(AbsoluteUrl::class);

    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->auth('admin');
    $redis->select(10);

    $start = time();
    echo "Start at>>> ".date("Y-m-d H:i:s", $start);

    if ($detail_url != '') {
        $headers = req_headers();
        $html = $ql->get($detail_url, $headers)->getHtml();
        if (is_gb_html($html)) {
            $html = html_gb2utf8($html);
        }
        $rt = $ql->rules($rule['detail_rules'])->queryData();

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
    echo "\n  Start First List: ".$rule['list_url'];

    //列表第一页
    $headers = req_headers();
    $html = $ql->get($rule['list_url'], $headers)->getHtml();
    if (is_gb_html($html)) {
        $html = html_gb2utf8($html);
        $ql->html($html);
    }
    $link_arr = $ql->rules($rule['list_rules'])->queryData();
    // print_r($link_arr);
    // exit();

    //列表第一页的所有详情页
    foreach ($link_arr as $key => $link) {
        $detail_url = $link['detail_link'];
        echo "\n   >>> start detail page ({$key}): {$detail_url}";
        if ($detail_url!='') {
            if (!is_collected($redis, $detail_url)) {
                $article = parse_detail($detail_url, $link, $rule);
                if ($article['content']!='') {
                    $articles[] = $article;
                }
            }
            else{
                echo "\n      collected....";
            }
        }
    }
    if (!empty($articles)) {
        dump_to_db($articles);
        add_url_hash($redis, $articles);
    }
    download_imgages();
    echo "\n  First List Done....";

    //如果只处理列表第一页
    if ($rule['list_next_max']+0 <= 0) {
        echo "\nFinished>>> ".(time()-$start)." s\n\n";
        exit();
    }

    //其他列表页及详情页
    if (isset($rule['list_next_url']) && isset($rule['list_next_max']) && isset($rule['list_next_from'])) {
        $max_page = $rule['list_next_max'];
        $next_url = $rule['list_next_url'];
        $i = $rule['list_next_from']+0;
        for ($i; $i<=$max_page; $i++) {
            $articles = [];
            $next_list_url = sprintf($next_url, $i);

            echo "\n   Start The Other Lists ({$i}): ".$next_list_url;
            $headers = req_headers();
            $html = $ql->get($next_list_url, $headers)->getHtml();
            if (is_gb_html($html)) {
                $html = html_gb2utf8($html);
                $ql->html($html);
            }
            $link_arr = $ql->rules($rule['list_rules'])->queryData();
            foreach ($link_arr as $key => $link) {
                $detail_url = $link['detail_link'];
                echo "\n   >>> start the other detail ({$i} - {$key}): ".$detail_url;
                if ($detail_url!='') {
                    if (!is_collected($redis, $detail_url)) {
                        $article = parse_detail($detail_url, $link, $rule);
                        if ($article['content']!='') {
                            $articles[] = $article;
                        }
                    }
                    else{
                        echo "\n      collected....";
                    }
                }
            }
            if (!empty($articles)){
                dump_to_db($articles);
                add_url_hash($redis, $articles);
            }
            download_imgages();
        }
        echo "\n  The Other Lists Done....";
    }
    else {
        echo "\n  No More List Pages OR List Rule Error....";
    }
    echo "\nFinished>>> ".(time()-$start)." s\n\n";
}

function parse_detail($detail_url, $link, $rule)
{
    $ql = QueryList::getInstance();
    $html = $ql->get($detail_url)->getHtml();
    if (is_gb_html($html)) {
        $html = html_gb2utf8($html);
        $ql->html($html);
    }
    $headers = req_headers();
    $rt = $ql->rules($rule['detail_rules'], $headers)->absoluteUrl($detail_url)->queryData();
    $rt[0]['site'] = $rule['site_name'];
    $rt[0]['source_url'] = $detail_url;
    $rt[0]['source_url_md5'] = md5($detail_url);
    $t = time();
    $rt[0]['created_at'] = $t;
    $rt[0]['collect_time'] = date("Y-m-d H:i:s", $t);
    if (isset($rt[0]['content'])) {
        $c = $rt[0]['content'];
        $c = trim(str_replace(PHP_EOL, '', $c));
        $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/","",$c);
    }
    else {
        $rt[0]['content'] = '';
    }
    if (isset($link['summary'])){
        $imgsrc = $link['summary'];
        $rt[0]['summary'] = $link['summary'];
    }
    if (isset($link['thumb'])){
        $imgsrc = $link['thumb'];
        $rt[0]['thumb'] = $link['thumb'];
    }
    if (isset($link['author_profile'])) {
        $rt[0]['author_profile'] = $link['author_profile'];
    }
    if (isset($link['cover_img'])){
        $rt[0]['cover_img'] = $link['cover_img'];
    }
    if (isset($link['tags'])){
        $rt[0]['tags'] = $link['tags'];
    }
    if (isset($link['read_count'])){
        $rt[0]['read_count'] = $link['read_count'];
    }
    if (isset($link['reprinted'])){
        $rt[0]['reprinted'] = $link['reprinted'];
    }
    //print_r($rt[0]);
    return $rt[0];
}

function download_imgages()
{
    echo "\nDownload images....\n";
    global $imgurls;
    $ql = QueryList::getInstance();
    $ql->use(CurlMulti::class);
    if (!empty($imgurls['img_src'])) {
        $ql->curlMulti($imgurls['img_src'])->success(function (QueryList $ql, CurlMulti $curl, $r) use($imgurls){
        echo "image url:{$r['info']['url']} \n";
        $source_url = ($r['info']['url']);
        $key = md5($source_url);
        $arr = explode('/', $source_url);
        $file = $arr[count($arr)-1];
        $filename = $imgurls['img_loc'][$key];
        $img = $r['body'];
        $fp = @fopen($filename, 'a');
        fwrite($fp, $img);
        fclose($fp);
        })->error(function ($errorInfo, CurlMulti $curl){
        echo "image url:{$errorInfo['info']['url']} \n";
        print_r($errorInfo['error']);
        })->start([
            'maxThread' => 3,
            'maxTry' => 3,
            'opt' => [
                CURLOPT_TIMEOUT => 90,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_RETURNTRANSFER => true
            ],
            /*
            'cache' => ['enable' => false, 'compress' => false, 'dir' => null, 'expire' =>86400, 'verifyPost' => false]
            */
        ]);
    }
    else {
        echo "No images needed to download....\n";
    }
}
