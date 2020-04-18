<?php
use Medoo\Medoo;
use QL\QueryList;
use QL\Ext\CurlMulti;
use GuzzleHttp\Exception\RequestException;

class Collector
{
    protected static $instance;
    protected static $ql;
    protected static $config;
    protected static $redis;
    protected static $mysql;
    protected static $current_url;
    protected static $image_urls = ['img_src'=>[], 'img_loc'=>[]];
    protected static $media_info = [];
    protected static $media_type = [
        'png'=>'img',
        'jpg'=>'img',
        'jpeg'=>'img',
        'gif'=>'img',
        'mp3'=>'audio',
        'wav'=>'audio',
        'mp4'=>'video',
        'webm'=>'video',
        'ogg'=>'video', //only as video (although it may also be audio)
        'doc'=>'doc',
        'docx'=>'doc',
        'xls'=>'doc',
        'xlsx'=>'doc',
        'pdf'=>'doc'];

    public function __construct($config)
    {
        self::$config = [
            'redis' => ['host' => '127.0.0.1', 'port' => 6379],
            'mysql' => [
                'database_type' => 'mysql',
                'database_name' => 'caiji',
                'server' => '127.0.0.1',
                'port' => 3306,
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8'
            ],
            'image_base_dir' => '',
            'audio_base_dir' => '',
            'video_base_dir' => '',
            'doc_base_dir' => ''
        ];
        self::$config = $config + self::$config;
    }
    public static function config()
    {
        return self::$config;
    }
    // private static function instance($config)
    // {
    //     if (empty(self::$instance)) {
    //         self::$instance = new Collector($config);
    //     }
    //     return self::$instance;
    // }
    private static function ql(){
        return QueryList::getInstance();
    }
    private static function redis()
    {
        if (!self::$redis) {
            self::$redis = new Redis();
            self::$redis->connect(self::$config['redis']['host'], self::$config['redis']['port']);
            if (isset(self::$config['redis']['auth'])) {
                self::$redis->auth(self::$config['redis']['auth']);
            }
            if (isset(self::$config['redis']['db_index'])) {
                self::$redis->select(self::$config['redis']['db_index']);
            }
        }
        return self::$redis;
    }
    private static function mysql()
    {
        if(!self::$mysql){
            self::$mysql = new Medoo(self::$config['mysql']);
        }
        return self::$mysql;
    }
    public static function work($rule, $article_url='', $dump_file=false)
    {
        $redis = self::redis();

        $start = time();
        echo "Start at>>> ".date("Y-m-d H:i:s", $start), PHP_EOL;

        if ($article_url != '') {
            self::get_single_page($rule, $article_url, $dump_file);
            echo "Finished>>> ".(time()-$start)." s" , PHP_EOL, PHP_EOL;
            exit();
        }

        echo "  Start Scanning First List: {$rule['list_url']}", PHP_EOL;
        self::scan_list($rule);
        echo "  First List Scanning Done....", PHP_EOL;

        //如果只处理列表第一页
        if ($rule['list_next_max']+0 <= 0) {
            echo "Finished>>> ".(time()-$start)." s", PHP_EOL, PHP_EOL;
            exit();
        }

        //其他列表页及内容页
        if (isset($rule['list_next_url']) && isset($rule['list_next_max']) && isset($rule['list_next_from'])) {
            self::scan_next_lists($rule);
        }
        else {
            echo "  No More List Pages OR List Rule Error....", PHP_EOL;
        }
        echo "Finished>>> ".(time()-$start)." s", PHP_EOL, PHP_EOL;
    }

    private static function scan_list($rule)
    {
        $articles = [];
        $list_url = $rule['list_url'];
        $list_rule = $rule['list_rules'];
        $ql = self::ql();
        try {
            $html = $ql->get($list_url)->getHtml();
        }
        catch(RequestException $e){
            echo "HTTP Error >>> ".$e->getMessage(), PHP_EOL;
            return;
        }
        if (Util::is_gb_html($html)) {
            $html = Util::html_gb2utf8($html);
            $ql->html($html);
        }
        $link_arr = $ql->rules($list_rule)->queryData();
        //print_r($link_arr);
        //exit();

        //列表第一页的所有内容页
        foreach ($link_arr as $key => $link_data) {
            $article_url = $link_data['article_link'];
            echo "   >>> start collecting content page ({$key}): {$article_url}", PHP_EOL;
            if ($article_url!='') {
                if (!self::article_collected($article_url)) {
                    $article = self::article_parse($article_url, $link_data, $rule);
                    if ($article['content']!='') {
                        $articles[] = $article;
                    }
                }
                else{
                    echo "      collected....", PHP_EOL;
                }
            }
        }
        if (!empty($articles)) {
            self::article_save($articles);
            self::add_url_hash($articles);
            self::download_images();
            self::save_media_info();
        }
    }
    private static function scan_next_lists($rule)
    {
        $max_page = $rule['list_next_max'];
        $next_url = $rule['list_next_url'];
        $i = $rule['list_next_from']+0;
        for ($i; $i<=$max_page; $i++) {
            $articles = [];
            $next_list_url = sprintf($next_url, $i);

            echo "   Start Scanning Next List Pages ({$i}): {$next_list_url}", PHP_EOL;
            $ql = self::ql();
            try {
                $html = $ql->get($next_list_url)->getHtml();
            }
            catch(RequestException $e){
                echo "HTTP Error >>> ".$e->getMessage(), PHP_EOL;
                continue;
            }
            if (Util::is_gb_html($html)) {
                $html = Util::html_gb2utf8($html);
                $ql->html($html);
            }
            $link_arr = $ql->rules($rule['list_rules'])->queryData();
            foreach ($link_arr as $key => $link_data) {
                $article_url = $link_data['article_link'];
                echo "   >>> start collecting content page ({$i} - {$key}): $article_url", PHP_EOL;
                if ($article_url!='') {
                    if (!self::article_collected($$article_url)) {
                        $article = self::article_parse($article_url, $link_data, $rule);
                        if ($article['content']!='') {
                            $articles[] = $article;
                        }
                    }
                    else{
                        echo "      collected....", PHP_EOL;
                    }
                }
            }
            if (!empty($articles)){
                self::article_save($articles);
                self::add_url_hash($articles);
                self::download_images();
                self::save_media_info();
            }
        }
        echo "   Next List Pages Scanning Done....", PHP_EOL;
    }
    private static function get_single_page($rule, $article_url, $dump_file=false)
    {
        self::$current_url = $article_url;
        $article = [];
        if (!self::article_collected($article_url)) {
            $article = self::article_parse($article_url, [], $rule);
        }
        else {
            echo "Single page <$article_url> has been collected....", PHP_EOL;
        }
        if ($dump_file) {
            if (!empty($article)){
                $text_arr = [];
                foreach ($article as $key => $value) {
                    $text_arr[] = join('', [$key,': ',str_replace(['\n', '\r\n', '\r'], '', trim($value))]);
                }
                $text = join('\n', $text_arr);
                $filename = dirname(__FILE__).'/pagetext/'.md5($article_url).'.txt';
                $fp = @fopen($filename, 'w');
                fwrite($fp, $text);
                fclose($fp);
                echo "<$article_url> has been saved as $filename", PHP_EOL;
            }
        }
        else {
            if (!empty($article) && isset($article['content'])) {
                $articles[] = $article;
                //print_r($articles);
                self::article_save($articles);
                self::add_url_hash($articles);
                self::download_images();
                self::save_media_info();
            }
            else {
                echo "Single page <$article_url> content is empty....", PHP_EOL;
            }
        }
    }
    private static function article_collected($article_url)
    {
        $redis = self::redis();
        if ($redis->hExists('url_hash', md5($article_url))) {
            return true;
        }
        return false;
    }

    private static function article_parse($article_url, $list_data, $rule)
    {
        self::$current_url = $article_url;
        $ql = self::ql();
        try {
            $html = $ql->get($article_url)->getHtml();
        }
        catch(RequestException $e){
            echo "HTTP Error >>> ".$e->getMessage(), PHP_EOL;
            $rt[0]['content']='';
            return $rt[0];
        }
        if (Util::is_gb_html($html)) {
            $html = Util::html_gb2utf8($html);
            $ql->html($html);
        }
        //$rt = $ql->rules($rule['article_rules'])->absoluteUrl($article_url)->queryData();
        $rt = $ql->rules($rule['article_rules'])->queryData();
        $rt[0]['site'] = $rule['site_name'];
        $rt[0]['source_url'] = $article_url;
        $rt[0]['source_url_md5'] = md5($article_url);
        $t = time();
        $rt[0]['created_at'] = $t;
        $rt[0]['collect_time'] = date("Y-m-d H:i:s", $t);
        if (isset($rt[0]['content'])) {
            $c = trim($rt[0]['content']);
            //$c = str_replace(PHP_EOL, '', $c);
            //$c = str_replace(['\n', '\r\n', '\r'], '', trim($c));

            //setting cover image use the first image of the article content.
            // $doc=\phpQuery::newDocumentHTML($c);
            // $imgs = pq($doc)->find('img');
            // if (isset($imgs) && count($imgs)>0) {
            //     $rt[0]['cover_img'] = pq($imgs[0])->attr('src');
            // }
            // else {
            //     $rt[0]['cover_img'] = '';
            // }
            //end of setting cover image

            //remove html comments
            $rt[0]['content'] = preg_replace("/<!--[^\!\[]*?(?<!\/\/)-->/","",$c);
        }
        else {
            $rt[0]['content'] = '';
        }
        if (isset($list_data['summary']) && !isset($rt[0]['summary'])){
            $rt[0]['summary'] = $list_data['summary'];
        }
        if (isset($list_data['thumbnail']) && !isset($rt[0]['thumbnail'])){
            $rt[0]['thumbnail'] = $list_data['thumbnail'];
        }
        if (isset($list_data['author_profile']) && !isset($rt[0]['author_profile'])) {
            $rt[0]['author_profile'] = $list_data['author_profile'];
        }
        if (isset($list_data['cover_img'])){
            $rt[0]['cover_img'] = $list_data['cover_img'];
        }
        if (isset($list_data['tags']) && !isset($rt[0]['tags'])){
            $rt[0]['tags'] = $list_data['tags'];
        }
        if (isset($list_data['read_count']) && !isset($rt[0]['read_count'])){
            $rt[0]['read_count'] = $list_data['read_count'];
        }
        if (isset($list_data['reprinted']) && !isset($rt[0]['reprinted'])){
            $rt[0]['reprinted'] = $list_data['reprinted'];
        }
        //print_r($rt[0]);
        return $rt[0];
    }
    private static function article_save($articles)
    {
        $mysql = self::mysql();
        foreach ($articles as $key => $article) {
            $source_url_md5 = $article['source_url_md5'];
            $a = $mysql->select('article', ['id', 'source_url_md5'], ['source_url_md5'=>$source_url_md5]);
            //print_r($a);
            if (!empty($a)) {
                $mysql->update('article', $article, ['id'=>$a[0]['id']]);
            }
            else {
                $mysql->insert('article', $article);
            }
        }
    }
    private static function add_url_hash($articles)
    {
        $redis = self::redis();
        foreach ($articles as $article) {
            $redis->hset('url_hash', $article['source_url_md5'], $article['created_at']);
        }
    }
    private static function download_images()
    {
        echo "Download images....", PHP_EOL;
        $ql = self::ql();
        $ql->use(CurlMulti::class);
        if (!empty(self::$image_urls['img_src'])) {
            $ql->curlMulti(self::$image_urls['img_src'])->success(function (QueryList $ql, CurlMulti $curl, $r) {
                echo "image url:{$r['info']['url']}", PHP_EOL;
                $source_url = $r['info']['url'];
                $key = md5($source_url);
                $arr = explode('/', $source_url);
                $file = $arr[count($arr)-1];
                $filename = self::$image_urls['img_loc'][$key];
                $img = $r['body'];
                $fp = @fopen($filename, 'w');
                fwrite($fp, $img);
                fclose($fp);
                $t = time();
                $media_data = [
                    'media_type' => 'image',
                    'media_src' => $source_url,
                    'media_key' => $key,
                    'media_loc' => $filename,
                    'collect_time' => date('Y-m-d H:i:s',$t),
                    'collect_ret' => '1',
                    'collect_msg' => 'SUCCESS',
                    'created_at' => $t
                ];
                self::$media_info[] = $media_data;
                $idx = array_search($source_url, self::$image_urls['img_src']);
                unset(self::$image_urls['img_src'][$idx]);
                unset(self::$image_urls['img_loc'][$key]);
            })->error(function ($errorInfo, CurlMulti $curl){
                echo "download image error: {$errorInfo['info']['url']}", PHP_EOL;
                print_r($errorInfo['error']);
                $source_url = $errorInfo['info']['url'];
                $key = md5($source_url);
                $t = time();
                $media_data = [
                    'media_type' => 'image',
                    'media_src' => $source_url,
                    'media_key' => $key,
                    'media_loc' => self::$image_urls['img_loc'][$key],
                    'collect_time' => date('Y-m-d H:i:s',$t),
                    'collect_ret' => '-1',
                    'collect_msg' => json_encode($errorInfo['error']),
                    'created_at' => $t
                ];
                self::$media_info[] = $media_data;
            })->start([
                'maxThread' => 3,
                'maxTry' => 3,
                'opt' => [
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_RETURNTRANSFER => true
                ],
                /*
                'cache' => ['enable' => false, 'compress' => false, 'dir' => null, 'expire' =>86400, 'verifyPost' => false]
                */
            ]);
        }
        else {
            echo "No image needed to download....", PHP_EOL;
        }
        //self::$image_urls = ['img_src'=>[], 'img_loc'=>[]];
        //print_r(self::$image_urls);
    }

    public static function imgurl_parse($img_src)
    {
        $img_src = Util::absolute_url($img_src, self::$current_url);
        if (!$img_src) {
            return '';
        }
        $image_base_dir = '';
        if (isset(self::$config['image_base_dir'])) {
            $image_base_dir = self::$config['image_base_dir'];
        }
                                                       //data/projects/yjximg/2020/03/30
        $loc_dir = self::stored_dir($image_base_dir);  //$image_base_dir/images/2020/03/30
        $name_arr = self::stored_name($img_src);       //[md5, md5.ext]

        $loc_key = $name_arr[0];
        $loc_name = $name_arr[1];

        self::$image_urls['img_src'][] = $img_src;
        self::$image_urls['img_loc'][$loc_key] = join('/', [$loc_dir, $loc_name]);

        $path_arr = explode('/', $loc_dir);
        $v_path_arr = array_slice($path_arr, -3);
        $v_path = 'images/'.join('/', $v_path_arr);
        return "@HOST@/".join('/', [$v_path, $loc_name]);
    }
    public static function mediaurl_parse($src, $type='video')
    {
        $src = Util::absolute_url($src, self::$current_url);
        if (!$src) {
            return '';
        }
        $base_dir = '';
        $v_dir = '';
        if ($type == 'video') {
            $base_dir = self::$config['video_base_dir'];
            $v_dir = 'videos';
        }
        elseif ($type == 'audio') {
            $base_dir = self::$config['audio_base_dir'];
            $v_dir = 'audios';
        }
        else {
            $base_dir = self::$config['doc_base_dir'];
            $v_dir = 'docs';
        }

                                                    //data/projects/yjxavs/2020/03/30
        $loc_dir = self::stored_dir($base_dir);     //$avs_base_dir/avs/2020/03/30
        $name_arr = self::stored_name($src);        //[md5, md5.ext]

        $loc_key = $name_arr[0];
        $loc_name = $name_arr[1];

        $media_data = [
            'media_type' => substr($v_dir, 0, strlen($v_dir)-1),
            'media_src' => $src,
            'media_key' => $loc_key,
            'media_loc' => join('/', [$loc_dir, $loc_name]),
            'created_at' => time()
        ];
        self::$media_info[] = $media_data;

        $path_arr = explode('/', $loc_dir);
        $v_path_arr = array_slice($path_arr, -3);
        $v_path = "$v_dir/".join('/', $v_path_arr);
        return "@HOST@/".join('/', [$v_path, $loc_name]);
    }
    private static function stored_dir($base_dir='')
    {
        $base_dir = rtrim($base_dir, '/');
        if (empty($base_dir)) {
            $base_dir = dirname(dirname(__FILE__)).'/images';
        }
        $dir = $base_dir.'/'.join('/', explode('-', date("Y-m-d", time())));
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private static function stored_name($asset_url)
    {
        $path_arr = pathinfo($asset_url);
        $file_ext = '';
        if (isset($path_arr['extension'])) {
            $file_ext = $path_arr['extension'];
        }
        // if (strlen($file_ext)>3) {
        //     $s = substr($file_ext, 0, 4);
        //     if ($s == 'jpeg'){
        //         $file_ext = $s;
        //     }
        //     else {
        //         $file_ext = substr($file_ext, 0, 3);
        //     }
        // }
        $file_key = $file_name = md5($asset_url);
        if ($file_ext!='') {
            return [$file_key, join('.', [$file_name, $file_ext])];
        }
        else {
            return [$file_key, $file_name];
        }
    }
    //Just judge by file extension
    private static function is_media($media_url)
    {
        $ext = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
        if (in_array($ext, self::$media_type)) {
            return true;
        }
        return false;
    }
    public static function media_type($media_url)
    {
        $ext = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
        if (array_key_exists($ext, self::$media_type)) {
            return self::$media_type[$ext];
        }
        return null;
    }
    //图片、音频、视频、文档信息保存到数据库
    private static function save_media_info()
    {
        //print_r(self::$media_info);
        if (!empty(self::$media_info)) {
            $mysql = self::mysql();
            $mysql->insert('media', self::$media_info);
            self::$media_info = [];
        }
    }
    //not used
    private static function media_collected($md5key) {
        $redis = self::redis();
        if ($redis->hExists('media_hash', $md5key)) {
            return $redis->hget('media_hash', $md5key);
        }
        else {
            $mysql = self::mysql();
            $data = $mysql->select('media', 'id, media_location', ['source_url_md5'=>$md5key]);
            return $data[0]['media_location'];
        }
        return '';
    }
}
