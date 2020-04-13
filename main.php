<?php
require dirname(__FILE__).'/vendor/autoload.php';

$usage =<<<STR
    php main.php -t=task [-n=site_name [-f [-l=list_url [-s=single_page_url [-d]]]]]
    OR:
    php main.php -tTASK [-nSITE_NAME [-f [-lLIST_URL [-sSINGLE_PAGE [-d]]]]]
    -t: required, task name.
    -n: optional, name of web site, defined in task setting file(config/task.php).
    -f: optional, collect only first list page.
    -l: optional, collect a specific list page.
    -s: optional, collect a specific single page.
    -d: optional, dump the data into a file which is in 'pagetext' directory, this option is valid only for option -s.
        By default, to store the data into the database, you need to have a database configuration items in 'config' directory.


STR;

$options = getopt("t:n::fl::s::d");
if (count($options) < 1) {
    echo $usage;
    exit();
}

date_default_timezone_set('PRC');

$task = require dirname(__FILE__).'/config/task.php';
$config = require dirname(__FILE__).'/config/config.php';

$task_name = $options['t'];
if (isset($task[$task_name])) {
    $sites = $task[$task_name];
    if (isset($options['n']) && isset($sites[$options['n']])) {
        $rule_file = dirname(__FILE__).'/'.$sites[$options['n']];
        echo $rule_file, PHP_EOL;
        if (is_file($rule_file)) {
            $rule = require $rule_file;
            $article_url = '';
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
                $article_url = $options['s'];
                if (isset($options['d'])) {
                    $dump_file = true;
                }
            }
            $collector = new Collector($config);
            //print_r($collector->config());exit();
            $collector->work($rule, $article_url, $dump_file);
        }
        else {
            echo PHP_EOL, "The Rule File <$rule_file> IS NOT EXISTS....", PHP_EOL;
        }
    }
    else {
        $collector = new Collector($config);
        foreach ($sites as $site => $rulefile) {
            $rule_file = dirname(__FILE__).'/'.$rulefile;
            if (is_file($rule_file)) {
                $rule = require $rule_file;
                if (array_key_exists('f', $options)) {
                    $rule['list_next_max'] = 0;
                }
                $collector->work($rule);
            }
        }
    }
}
else {
    echo PHP_EOL, "TASK IS NOT EXISTS....", PHP_EOL;
}
