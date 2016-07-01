SET NAMES utf8;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

DELIMITER ;;

DROP FUNCTION IF EXISTS `get_page_id`;;
CREATE FUNCTION `get_page_id`(`p_url` varchar(255), `p_parent` int) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
BEGIN

DECLARE v_id INT;
DECLARE v_redirect_to INT DEFAULT NULL;

IF p_parent IS NOT NULL THEN
    SELECT id, redirect_to INTO v_id, v_redirect_to FROM wiki_pages WHERE url = p_url AND parent_id = p_parent;
ELSE
    SELECT id, redirect_to INTO v_id, v_redirect_to FROM wiki_pages WHERE url = p_url AND parent_id IS NULL;
END IF;

WHILE v_redirect_to IS NOT NULL DO
    SELECT id, redirect_to INTO v_id, v_redirect_to FROM wiki_pages WHERE id = v_redirect_to;
END WHILE;

RETURN v_id;

END;;

DELIMITER ;

DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  `related_page_id` int(11) NOT NULL,
  `created` datetime NOT NULL,
  `last_modified` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `bytes` bigint(20) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `related_page_id_name` (`related_page_id`,`name`),
  CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`type`) REFERENCES `attachment_type` (`id`),
  CONSTRAINT `attachments_ibfk_2` FOREIGN KEY (`related_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_ibfk_4` FOREIGN KEY (`type`) REFERENCES `attachment_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `attachments_history`;
CREATE TABLE `attachments_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attachment` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(11) NOT NULL,
  `type` int(11) NOT NULL,
  `bytes` bigint(20) NOT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `attachment` (`attachment`),
  KEY `type` (`type`),
  CONSTRAINT `attachments_history_ibfk_1` FOREIGN KEY (`attachment`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `attachments_history_ibfk_3` FOREIGN KEY (`attachment`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_history_ibfk_4` FOREIGN KEY (`type`) REFERENCES `attachment_type` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `attachments_meta`;
CREATE TABLE `attachments_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attachment_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `value` varchar(1024) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attachment_id_revision_name` (`attachment_id`,`revision`,`name`),
  CONSTRAINT `attachments_meta_ibfk_1` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `attachments_references`;
CREATE TABLE `attachments_references` (
  `attachment_id` int(11) NOT NULL,
  `page_id` int(11) NOT NULL,
  PRIMARY KEY (`attachment_id`,`page_id`),
  KEY `page_id` (`page_id`),
  CONSTRAINT `attachments_references_ibfk_1` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attachments_references_ibfk_2` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `attachment_type`;
CREATE TABLE `attachment_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `owner_user_id` int(11) DEFAULT NULL,
  `edit_user_id` int(11) DEFAULT NULL,
  `anonymous_name` varchar(255) COLLATE utf8_general_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created` datetime NOT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `hidden` tinyint(1) NOT NULL DEFAULT '0',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `text_wiki` mediumtext COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `page_id` (`page_id`),
  KEY `parent_id` (`parent_id`),
  KEY `owner_user_id` (`owner_user_id`),
  KEY `edit_user_id` (`edit_user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_4` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `comments_ibfk_5` FOREIGN KEY (`edit_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `comments_history`;
CREATE TABLE `comments_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `last_modified` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `text_wiki` mediumtext COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `comment_id` (`comment_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_history_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `comments_references`;
CREATE TABLE `comments_references` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `ref_page_id` int(11) NOT NULL,
  `ref_page_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `comment_id` (`comment_id`),
  KEY `ref_page_id` (`ref_page_id`),
  CONSTRAINT `comments_references_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_references_ibfk_2` FOREIGN KEY (`ref_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `page_acl_group`;
CREATE TABLE `page_acl_group` (
  `page_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `page_read` tinyint(1) DEFAULT NULL,
  `page_write` tinyint(1) DEFAULT NULL,
  `page_admin` tinyint(1) DEFAULT NULL,
  `comment_read` tinyint(1) DEFAULT NULL,
  `comment_write` tinyint(1) DEFAULT NULL,
  `comment_admin` tinyint(1) DEFAULT NULL,
  `attachment_write` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`page_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `page_acl_group_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_acl_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `page_acl_user`;
CREATE TABLE `page_acl_user` (
  `page_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `page_read` tinyint(1) DEFAULT NULL,
  `page_write` tinyint(1) DEFAULT NULL,
  `page_admin` tinyint(1) DEFAULT NULL,
  `comment_read` tinyint(1) DEFAULT NULL,
  `comment_write` tinyint(1) DEFAULT NULL,
  `comment_admin` tinyint(1) DEFAULT NULL,
  `attachment_write` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`page_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `page_acl_user_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `page_acl_user_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessid` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `activity` datetime NOT NULL,
  `lifetime` int(11) NOT NULL DEFAULT '3600',
  `ip` char(32) COLLATE utf8_general_ci NOT NULL,
  `name` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `persistent` tinyint(1) NOT NULL DEFAULT '1',
  `type` enum('plain','binary') CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value` text CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sessid_name` (`sessid`,`name`),
  KEY `activity` (`activity`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `system_config` (`id`, `name`, `value`) VALUES
  (1,  'Mail.enabled', '0'),
  (2,  'Mail.from', ''),
  (3,  'DefaultPage', 'MainPage'),
  (4,  'PasswordSecurity.minLength', '6'),
  (5,  'PasswordSecurity.digits', '1'),
  (6,  'PasswordSecurity.special', '0'),
  (7,  'PasswordSecurity.capital', '0'),
  (8,  'Attachments.MaxSize', '10MB'),
  (9,  'Attachments.Location', 'attachments/'),
  (10, 'Title', 'GCM::Wiki'),
  (11, 'Attachments.FileMode', '0664'),
  (12, 'Attachments.DirectoryMode', '0775'),
  (13, 'Attachments.Previews.image', 'contain64x64,contain120x120,contain240x240,contain640x480,contain800x600,contain1024x768,contain1280x720,contain1920x1080');

DROP TABLE IF EXISTS `system_privileges`;
CREATE TABLE `system_privileges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `default_value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `system_privileges` (`id`, `name`, `default_value`) VALUES
  (1,  'admin_users', 0),
  (2,  'admin_user_privileges', 0),
  (3,  'admin_groups', 0),
  (4,  'admin_pages', 0),
  (5,  'acl_page_read', 1),
  (6,  'acl_page_write', 1),
  (7,  'acl_page_admin', 0),
  (8,  'acl_comment_read', 1),
  (9,  'acl_comment_write', 1),
  (10, 'admin_superadmin', 0),
  (11, 'acl_comment_admin', 0),
  (12, 'acl_attachment_write', 1);

DROP TABLE IF EXISTS `system_privileges_group`;
CREATE TABLE `system_privileges_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_id_privilege_id` (`group_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `system_privileges_group_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `system_privileges_group_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `system_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `system_privileges_user`;
CREATE TABLE `system_privileges_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL,
  `value` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_privilege_id` (`user_id`,`privilege_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `system_privileges_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `system_privileges_user_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `system_privileges` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `system_privileges_user` (`id`, `user_id`, `privilege_id`, `value`) VALUES
  (1,  1, 1,  1),
  (2,  1, 2,  1),
  (3,  1, 3,  1),
  (4,  1, 4,  1),
  (5,  1, 5,  1),
  (6,  1, 6,  1),
  (7,  1, 7,  1),
  (8,  1, 8,  1),
  (9,  1, 9,  1),
  (10, 1, 10, 1),
  (11, 1, 11, 1),
  (12, 1, 12, 1);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `password` varchar(64) COLLATE utf8_general_ci NOT NULL,
  `salt` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `email` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `registered` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `status_id` int(11) NOT NULL DEFAULT '1',
  `email_token` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `password_token` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
  `email_verified` tinyint(1) NOT NULL DEFAULT '0',
  `show_comments` tinyint(1) NOT NULL DEFAULT '1',
  `show_attachments` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `users` (`id`, `name`, `password`, `salt`, `email`, `registered`, `last_login`, `status_id`, `email_token`, `password_token`, `email_verified`, `show_comments`, `show_attachments`) VALUES
  (0, 'Anonymous',    '', '', '', '2014-05-27 20:38:44',  NULL,   1,  '', '', 0,  1,  1),
  (1, 'admin',  SHA2('admin', 256), '', '', NOW(), NULL,  1,  '', '', 0,  0,  0);


DROP TABLE IF EXISTS `user_group`;
CREATE TABLE `user_group` (
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `user_group_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_group_ibfk_2` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `wiki_pages`;
CREATE TABLE `wiki_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `url` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `created` datetime NOT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL DEFAULT '1',
  `body_wiki` mediumtext COLLATE utf8_general_ci NOT NULL,
  `small_change` tinyint(1) NOT NULL,
  `summary` text COLLATE utf8_general_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `acl_page_read` tinyint(1) DEFAULT NULL,
  `acl_page_write` tinyint(1) DEFAULT NULL,
  `acl_page_admin` tinyint(1) DEFAULT NULL,
  `acl_comment_read` tinyint(1) DEFAULT NULL,
  `acl_comment_write` tinyint(1) DEFAULT NULL,
  `acl_comment_admin` tinyint(1) DEFAULT NULL,
  `acl_attachment_write` tinyint(1) DEFAULT NULL,
  `redirect_to` int(11) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `renderer` varchar(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `template` varchar(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `url_parent_id` (`url`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  KEY `user_id` (`user_id`),
  KEY `redirect_to` (`redirect_to`),
  CONSTRAINT `wiki_pages_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `wiki_pages` (`id`),
  CONSTRAINT `wiki_pages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `wiki_pages_ibfk_3` FOREIGN KEY (`redirect_to`) REFERENCES `wiki_pages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `wiki_pages_history`;
CREATE TABLE `wiki_pages_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `url` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `body_wiki` mediumtext COLLATE utf8_general_ci NOT NULL,
  `last_modified` datetime NOT NULL,
  `revision` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `small_change` tinyint(1) NOT NULL,
  `summary` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `redirect_to` int(11) DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `renderer` varchar(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `page_id_revision` (`page_id`,`revision`),
  KEY `redirect_to` (`redirect_to`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `wiki_pages_history_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wiki_pages_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `wiki_pages_history_ibfk_3` FOREIGN KEY (`redirect_to`) REFERENCES `wiki_pages` (`id`),
  CONSTRAINT `wiki_pages_history_ibfk_4` FOREIGN KEY (`page_id`) REFERENCES `wiki_pages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `wiki_page_references`;
CREATE TABLE `wiki_page_references` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `wiki_page_id` int(11) NOT NULL,
  `wiki_page_revision` int(11) NOT NULL,
  `ref_page_id` int(11) DEFAULT NULL,
  `ref_page_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `is_template` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `wiki_page_id_revision_id_ref_page_id_ref_page_name` (`wiki_page_id`,`wiki_page_revision`,`ref_page_id`,`ref_page_name`),
  KEY `ref_page_id` (`ref_page_id`),
  KEY `ref_page_name` (`ref_page_name`),
  CONSTRAINT `wiki_page_references_ibfk_1` FOREIGN KEY (`wiki_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wiki_page_references_ibfk_2` FOREIGN KEY (`ref_page_id`) REFERENCES `wiki_pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;


DROP TABLE IF EXISTS `wiki_text_cache`;
CREATE TABLE `wiki_text_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `valid` tinyint(1) NOT NULL DEFAULT '1',
  `wiki_text` mediumtext CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  UNIQUE KEY `key_valid` (`key`,`valid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
