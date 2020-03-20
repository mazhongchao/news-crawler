<?php
// 采集规则
$rules = [
    'yjb.cn' => [
        'site_name' => '中国教育新闻网',
        'list_url' => 'http://www.jyb.cn/rmtlistxqjy/',
        'list_rules' => [
            'detail_link' => ['.yxj_list li>a', 'href']
        ],
        //'list_range' => '.yxj_list',
        'detail_rules' => [
            'title' => ['h1', 'text'],
            'author' => ['h2>span:eq(1)', 'text'],
            'pub_date' => ['h2>span:eq(0)', 'text'],
            'content' => ['.xl_text', 'html'],
            'image' => []
        ],
    ]
];

return $rules;
?>