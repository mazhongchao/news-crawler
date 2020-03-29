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
                foreach ($imgs as $img) {
                    $local_src = parse_imgurl(pq($img)->attr('src'));
                    pq($img)->removeAttr('*');
                    pq($img)->attr('src', $local_src);
                }
            }
            return $doc->htmlOuter();
        }],
    ],
];
return $rule;
