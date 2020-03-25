<?php
$rule = [
    'site_name' => '中国教育新闻网',
    'list_url' => 'http://www.jyb.cn/rmtlistxqjy/',
    'list_next_url' => 'http://www.jyb.cn/rmtlistxqjy/index_%u.html',
    'list_next_from' => 1,
    'list_next_max' => 3,
    'list_rules' => [
        'detail_link' => ['.yxj_list li>a', 'href']
    ],
    //'list_range' => '.yxj_list',
    'detail_rules' => [
        'title' => ['h1', 'text'],
        'author' => ['h2>span:eq(1)', 'text', '', function($content){
            $p = mb_strpos($content, '：');
            return mb_substr($content, $p+1);
        }],
        'origin' => ['h2>span:eq(2)', 'text', '', function($content){
            $p = mb_strpos($content, '：');
            return mb_substr($content, $p+1);
        }],
        'pub_date' => ['h2>span:eq(0)', 'text', '', function($content){
            $p = mb_strpos($content, '：');
            return mb_substr($content, $p+1);
        }],
        'content' => ['.xl_text', 'html', 'a span -script -br', function($content){
            $doc=\phpQuery::newDocumentHTML($content);
            $ps = pq($doc)->find('p');
            foreach($ps as $p) {
                pq($p)->removeAttr('*');
            }
            $imgs = pq($doc)->find('img');
            if (isset($imgs) && count($imgs)>0) {
                $dir = 'images/'.join('/', explode('-', date("Y-md",time())));
                if (!is_dir($dir) && !mkdir($dir, 0777, true)){
                    return $doc->htmlOuter();
                }
                foreach ($imgs as $img) {
                    $image_url = pq($img)->attr('src');
                    $arr = explode('/', $image_url);
                    $file_ext = explode('.', $arr[count($arr)-1])[1];
                    $local_src = $dir.'/zt_'.md5($image_url).'.'.$file_ext;
                    $stream = file_get_contents($image_url);
                    file_put_contents($local_src, $stream);
                    pq($img)->removeAttr('*');
                    pq($img)->attr('src', $local_src);
                }
            }
            return $doc->htmlOuter();
        }],
    ],
];
return $rule;
