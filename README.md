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
```javascript
"autoload": {
    "classmap": ["lib/"]
}
```
and run:
```
composer dump-autoload
```

Then, to create  `rules` directory and adding rule files. To create `config` directory and adding `task.php` & `config.php`. Some demos are below.

## rules/yjb_cn.php demo

e.g., `rules/yjb_cn.php` is used to define collection(crawler) rule.

```php
$rule = [
    'site_name' => 'YJB.CN',
    'list_url' => 'http://www.jyb.cn/rmtlistxqjy/',
    'list_next_url' => 'http://www.jyb.cn/rmtlistxqjy/index_%u.html',
    'list_next_from' => 1,
    'list_next_max' => 3,
    'list_rules' => [
        'article_link' => ['.yxj_list li>a', 'href'],
        'article_title' => ['.yxj_list li>a', 'text'],
    ],
    'article_rules' => [
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
If the data of item in `article_rules` needs to be saved to database, the name of item must match the filed name in the table `article`. Refering to `create.sql`.

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
        'yjb_cn',  //to find collectiong rule in this file: rules/yjb_cn.php
        '<SITE_NAME_A>'
    ],
    '<TASK_NAME_2>' => [
        '<SITE_NAME_B>',
        '<SITE_NAME_C>'
    ]
];

return $task;
```
`<TASK_NAME_1>`, `<SITE_NAME_A>` and so on, of the above, need to be replaced with your actual value.

## Create a database
Refer to `create.sql` file to create a database and tables.

## Some usage
`<TASK_NAME>`, `<SITE_NAME>`, `<ARTICLE_URL>` need to be replaced with your actual value.
```bash
php main.php -t=<TASTK_NAME>
php main.php -t=<TASTK_NAME> -n=<SITE_NAME>
php main.php -t=<TASTK_NAME> -n=<SITE_NAME> -f
php main.php -t=<TASTK_NAME> -n=<SITE_NAME> -s=<ARTICLE_URL>
```
