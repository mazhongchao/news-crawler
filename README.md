## PHP Dependency
* composer require jaeger/querylist 4.1.0
* composer require jaeger/querylist-curl-multi
* composer require jaeger/querylist-absolute-url
* composer require catfan/medoo

## Middleware
* MySQL 5.7+
* Redis

## Add the Collector
Editing composer.json:
```
"autoload": {
    "classmap": ["lib/"]
}
```
and run:
```
composer dump-autoload
```

Then, to create  `rules` directory, adding rule files, to create  `config` directory, and adding `task.php` & `config.php`. Some demos are below.

## rules/yjb_rule.php demo

e.g., `rules/yjb_rule.php` is used to define collection(crawler) rule.

```php
$rule = [
    'site_name' => 'YJB.CN',
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
                    $local_src = Collector::imgurl_parse(pq($img)->attr('src'));
                    pq($img)->removeAttr('*');
                    pq($img)->attr('src', $local_src);
                }
            }
            return $doc->htmlOuter();
        }],
    ],
];

return $rule;
```

## config/config.php demo
`config/config.php` is used to configure the items required by the collection(crawler) program.
```php
$config = [
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
    'image_base_dir' => dirname(dirname(__FILE__)).'/images',
    'audio_base_dir' => dirname(dirname(__FILE__)).'/audios',
    'video_base_dir' => dirname(dirname(__FILE__)).'/videos',
    'doc_base_dir'   => dirname(dirname(__FILE__)).'/docs'
];

return $config;
```

## config/task.php demo
`config/task.php` is used to define collection tasks, so that the crawler program can find the collection rule files accroding to the configuration.
```php
$task = [
    '<TASK_NAME_1>' => [
        'YJB' => 'rules/yjb_rule.php',
        '<SITE_A>' => 'rules/<SITE_A_RULE>.php'
    ],
    '<TASK_NAME_2>' => [
        '<SITE_B>' => 'rules/<SITE_B_RULE>.php',
        '<SITE_C>' => 'rules/<SITE_C_RULE>.php'
    ]
];

return $task;
```
`<TASK_NAME_1>`, `<SITE_A>` and `<SITE_A_RULE>` and so on, need to be replaced with your actual value.

## Some usage
```bash
php main.php -t=3h
php main.php -t=3h -n=YJB
php main.php -t=3h -n=YJB -f
php main.php -t=3h -n=YJB -s=http://www.jyb.cn/rmtzcg/xwy/wzxw/202003/t20200317_307896.html
```
