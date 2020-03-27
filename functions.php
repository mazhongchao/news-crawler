<?php
require 'vendor/autoload.php';
use Medoo\Medoo;

function stored_dir($pre_path = 'images')
{
    $pre_path = rtrim($pre_path, '/');
    $dir = $pre_path.'/'.join('/', explode('-', date("Y-m-d", time())));
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function stored_name($imgurl)
{
    $arr = explode('/', $imgurl);
    $file_ext = explode('.', $arr[count($arr)-1])[1];
    if (strlen($file_ext)>3) {
        $s = substr($file_ext, 0, 4);
        if ($s == 'jpeg'){
            $file_ext = $s;
        }
        else {
            $file_ext = substr($file_ext, 0, 3);
        }
    }
    $file_name = md5($imgurl);
    return [$file_name, join('.', [$file_name, $file_ext])];
}

function parse_imgurl($img_src, $host='')
{
    $loc_dir = stored_dir();
    $name_arr = stored_name($img_src);
    $loc_key = $name_arr[0];
    $loc_name = $name_arr[1];
    global $imgurls;
    $imgurls['img_src'][] = $img_src;
    $imgurls['img_loc'][$loc_key] = join('/', [$loc_dir, $loc_name]);
    return "@HOST@/".join('/', [$loc_dir, $loc_name]);
}

function parse_avurl($av_url, $host='')
{
    $loc_dir = stored_dir('avs');
    return;
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

//not used
function push_mq($redis, $imgurls)
{
    $urls = $imgurls['img_src'];
    $locs = $imgurls['img_loc'];
    foreach($urls as $url){
        $redis->rpush('imgdl_mq',$v);
    }
    foreach($locs as $key=>$local_src){
        $redis->hset('img_path', $key, $local_src);
    }
}
