CREATE DATABASE `yjx` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
CREATE TABLE `article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '原文标题',
  `author` varchar(200) NOT NULL DEFAULT '' COMMENT '原文作者',
  `pub_date` char(19) NOT NULL DEFAULT '' COMMENT '原文发布时间，完整格式：2020-03-18 17:00:00',
  `origin` varchar(100) NOT NULL DEFAULT '' COMMENT '原文的来源',
  `content` text NOT NULL COMMENT ' 原文正文',
  `summary` varchar(255) NOT NULL DEFAULT '' COMMENT '原文摘要',
  `site` varchar(100) NOT NULL DEFAULT '' COMMENT '采集站点名称',
  `source_url` varchar(255) NOT NULL DEFAULT '' COMMENT '原文url地址',
  `source_url_md5` varchar(255) NOT NULL DEFAULT '' COMMENT '原文url地址的md5值',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '采集入库时间戳',
  `collect_time` datetime NOT NULL DEFAULT '1000-01-01 00:00:00' COMMENT '采集时间',
  `synced_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '同步时间戳',
  `sync_time` datetime NOT NULL DEFAULT '1000-01-01 00:00:00' COMMENT '同步时间',
  `author_profile` varchar(255) NOT NULL DEFAULT '' COMMENT '原文作者头像地址',
  `thumbnail` varchar(255) NOT NULL DEFAULT '' COMMENT '原文缩略图地址(列表中)',
  `cover_img` varchar(255) NOT NULL DEFAULT '' COMMENT '原文封面图地址',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '原文标签',
  `read_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '原文阅读数：0为未提供',
  `reprinted` varchar(10) NOT NULL DEFAULT '' COMMENT '原文是转载还是原创，空为不明确',
  PRIMARY KEY (`id`),
  KEY `KEY1` (`title`),
  KEY `KEY2` (`pub_date`),
  KEY `KEY3` (`site`),
  KEY `KEY4` (`source_url`),
  KEY `KEY5` (`created_at`),
  KEY `KEY6` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
