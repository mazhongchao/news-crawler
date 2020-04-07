<?php
require dirname(__FILE__).'/vendor/autoload.php';
use Medoo\Medoo;

function stored_dir($pre_path = '')
{
    if (empty($pre_path)) {
        $pre_path = dirname(__FILE__).'/images';
    }
    $pre_path = rtrim($pre_path, '/');
    //$pre_path = '/data/projects/yjximg';
    $dir = $pre_path.'/'.join('/', explode('-', date("Y-m-d", time())));
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

function stored_name($imgurl)
{
    $arr = explode('/', $imgurl);
    $full_name = $arr[count($arr)-1];
    $file_ext = '';
    if (strpos($full_name, '.')!==false) {
        $name_arr = explode('.', $full_name);
        $file_ext = $name_arr[count($name_arr)-1];
    }
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
    if ($file_ext!='') {
        return [$file_name, join('.', [$file_name, $file_ext])];
    }
    else {
        return [$file_name, $file_name];
    }
}

function parse_imgurl($img_src, $host='')
{
    //$loc_dir = "images/2020/03/30";
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
    $dbconfig = require dirname(__FILE__)."/config/db-config.php";
    $db = new Medoo($dbconfig);
    $db->insert("article", $articles);
    //var_dump($db->error());
    //echo $db->last();
}

function is_gb_html($html)
{
    $matches = [];
    preg_match('/<meta([^>]*)charset\s*=\s*("|\')*\s*([\w-]+)/i', $html, $matches);
    if ($matches && isset($matches[3])) {
        return strtoupper(substr($matches[3], 0, 2)) == 'GB';
    }
    $encode = mb_detect_encoding($html, ['ASCII', 'EUC-CN', 'UTF-8']);
    if ('EUC-CN' == $encode) {
        return true;
    }
    return false;
}

function html_gb2utf8($html)
{
    if (empty($html)) return $html;
    $html = mb_convert_encoding($html, "UTF-8", "GBK");
    $html = preg_replace('/<meta([^>]*)charset\s*=\s*("|\')*\s*([\w-]+)/i', '<meta$1charset=$2utf-8', $html);
    return $html;
}

//not used
function push_avmq($redis, $avurls)
{
    $urls = $imgurls['av_src'];
    $locs = $imgurls['av_loc'];
    foreach($urls as $url){
        $redis->rpush('avdl_mq',$v);
    }
    foreach($locs as $key=>$local_src){
        $redis->hset('av_path', $key, $local_src);
    }
}

function push_imgmq($redis, $imgurls)
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
function parse_html_encode($html)
{
    $matches = [];
    preg_match('/<meta([^>]*)charset\s*=\s*("|\')*\s*([\w-]+)/i', $html, $matches);
    if ($matches && isset($matches[3])) {
        return $matches[3];
    }
    mb_detect_encoding($text, ['ASCII', 'EUC-CN', 'UTF-8']);
    if ('EUC-CN' == $encode) {
        return 'GBK';
    }
    return false;
}
