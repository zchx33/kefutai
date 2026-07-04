-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2026-06-09 04:04:50
-- 服务器版本： 5.7.44-log
-- PHP 版本： 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `vuevue`
--

DELIMITER $$
--
-- 存储过程
--
CREATE DEFINER=`vuevue`@`localhost` PROCEDURE `GetTenantUsers` (IN `p_tenant_id` INT)   BEGIN
    SELECT 
        u.id,
        u.username,
        u.role as system_role,
        IFNULL(tur.user_role_in_tenant, 'none') as tenant_role,
        u.balance,
        u.expire_time,
        u.created_at
    FROM users u
    LEFT JOIN tenant_user_relations tur ON u.id = tur.user_id AND tur.tenant_id = p_tenant_id
    WHERE tur.tenant_id = p_tenant_id OR u.tenant_id = p_tenant_id
    ORDER BY u.username;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `anti_red_links`
--

CREATE TABLE `anti_red_links` (
  `id` int(11) NOT NULL,
  `domain_name` varchar(100) NOT NULL COMMENT '域名名称，如www.aaa.com',
  `api_url` varchar(255) NOT NULL COMMENT '接口链接，如http://www.aaa.com/aa.html=',
  `price` decimal(10,2) DEFAULT '5.20' COMMENT '价格，单位元',
  `is_sold` tinyint(1) DEFAULT '0' COMMENT '是否已售出：0-未售出，1-已售出',
  `sold_to` varchar(50) DEFAULT NULL COMMENT '购买用户账号',
  `sold_date` date DEFAULT NULL COMMENT '购买日期（年月日）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `wechat_status` varchar(20) DEFAULT 'unknown' COMMENT '微信状态: safe-安全, blocked-被拦截, unknown-未知',
  `last_checked` timestamp NULL DEFAULT NULL COMMENT '最后检测时间',
  `check_count` int(11) DEFAULT '0' COMMENT '检测次数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `anti_red_pricing`
--

CREATE TABLE `anti_red_pricing` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL COMMENT '商品名称',
  `unit_price` decimal(10,2) NOT NULL COMMENT '单价（元）',
  `currency` varchar(10) DEFAULT 'CNY' COMMENT '货币',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否生效：1-生效，0-失效',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `session_key` varchar(100) NOT NULL COMMENT '会话标识: customer_agent',
  `agent_account` varchar(50) NOT NULL,
  `speaker_type` tinyint(4) NOT NULL COMMENT '1:客户, 2:客服',
  `content` text NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `remark` varchar(255) DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `message_type` enum('text','image') DEFAULT 'text',
  `image_path` varchar(500) DEFAULT NULL COMMENT '图片存储路径',
  `image_name` varchar(255) DEFAULT NULL COMMENT '原始图片文件名',
  `image_size` int(11) DEFAULT NULL COMMENT '图片大小（字节）',
  `platform` varchar(50) DEFAULT '默认' COMMENT '消息来源平台，如：闲鱼、转转',
  `client_ip` varchar(45) DEFAULT NULL,
  `is_dummy` tinyint(1) DEFAULT '0',
  `is_read` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `session_key`, `agent_account`, `speaker_type`, `content`, `image_url`, `customer_name`, `remark`, `created_at`, `message_type`, `image_path`, `image_name`, `image_size`, `platform`, `client_ip`, `is_dummy`, `is_read`) VALUES
(1, 'lbC7U1_admin', 'admin', 1, '1', NULL, 'lbC7U1', '', '2026-06-08 23:33:18', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(2, 'lbC7U1_admin', 'admin', 2, '1', NULL, 'lbC7U1', '', '2026-06-09 01:01:39', 'text', NULL, NULL, NULL, '默认', NULL, 0, 1),
(3, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 01:39:21', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(4, 'yr8rGd_admin', 'admin', 1, '2', NULL, 'yr8rGd', '', '2026-06-09 01:39:38', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(5, 'yr8rGd_admin', 'admin', 1, '222', NULL, 'yr8rGd', '', '2026-06-09 01:39:53', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(6, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 01:45:47', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(7, 'yr8rGd_admin', 'admin', 1, '22', NULL, 'yr8rGd', '', '2026-06-09 01:45:54', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(8, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 01:52:06', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(9, 'yr8rGd_admin', 'admin', 1, '4', NULL, 'yr8rGd', '', '2026-06-09 01:52:08', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(10, 'yr8rGd_admin', 'admin', 1, '666', NULL, 'yr8rGd', '', '2026-06-09 01:52:23', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(11, 'yr8rGd_admin', 'admin', 1, '2', NULL, 'yr8rGd', '', '2026-06-09 01:52:27', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(12, 'yr8rGd_admin', 'admin', 1, '2', NULL, 'yr8rGd', '', '2026-06-09 01:52:30', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(13, 'yr8rGd_admin', 'admin', 1, '2', NULL, 'yr8rGd', '', '2026-06-09 01:59:10', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(14, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 03:17:56', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(15, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 03:34:53', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(16, 'yr8rGd_admin', 'admin', 1, '您好', NULL, 'yr8rGd', '', '2026-06-09 03:42:50', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(17, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 03:42:53', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(18, 'yr8rGd_admin', 'admin', 1, '1', NULL, 'yr8rGd', '', '2026-06-09 03:43:35', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(19, '0TsBAJ_aaaa', 'aaaa', 1, '在吗', NULL, '0TsBAJ', '', '2026-06-09 03:47:18', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0),
(20, '0TsBAJ_aaaa', 'aaaa', 1, '11', NULL, '0TsBAJ', '', '2026-06-09 03:53:11', 'text', NULL, NULL, NULL, '闲鱼', NULL, 0, 0);

-- --------------------------------------------------------

--
-- 表的结构 `chat_session_settings`
--

CREATE TABLE `chat_session_settings` (
  `id` int(11) NOT NULL,
  `session_key` varchar(100) NOT NULL,
  `agent_account` varchar(50) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `chat_settings`
--

CREATE TABLE `chat_settings` (
  `id` int(11) NOT NULL,
  `session_key` varchar(100) NOT NULL,
  `agent_account` varchar(50) NOT NULL,
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_muted` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `dummy_settings`
--

CREATE TABLE `dummy_settings` (
  `id` int(11) NOT NULL,
  `session_key` varchar(100) NOT NULL,
  `dummy_name` varchar(50) DEFAULT '技术顾问',
  `dummy_avatar` varchar(255) DEFAULT '/assets/img/dummy1.png',
  `is_dummy_mode` tinyint(1) DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `freeantired`
--

CREATE TABLE `freeantired` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `api_url` varchar(500) NOT NULL,
  `remark` varchar(200) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `gpt_config`
--

CREATE TABLE `gpt_config` (
  `id` int(11) NOT NULL,
  `api_key` text NOT NULL COMMENT 'DeepSeek API密钥',
  `qwen_api_key` text COMMENT '通义千问 API密钥',
  `model` varchar(50) NOT NULL DEFAULT 'deepseek-v3.2' COMMENT '主模型选择',
  `max_tokens` int(11) NOT NULL DEFAULT '120' COMMENT '最大回复长度',
  `temperature` decimal(3,1) NOT NULL DEFAULT '0.6' COMMENT '温度参数',
  `max_history` int(11) NOT NULL DEFAULT '8' COMMENT '最大历史记录',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI配置表';

-- --------------------------------------------------------

--
-- 表的结构 `payment_pages`
--

CREATE TABLE `payment_pages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `page_title` varchar(255) NOT NULL COMMENT '付款页标题',
  `amount` decimal(10,2) NOT NULL,
  `api_url` varchar(500) NOT NULL COMMENT '支付接口URL',
  `payment_method` varchar(20) DEFAULT 'alipay',
  `status` enum('active','inactive') DEFAULT 'active',
  `page_code` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户自定义付款页表';

-- --------------------------------------------------------

--
-- 表的结构 `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `user_type` enum('agent','customer') NOT NULL DEFAULT 'agent',
  `endpoint` varchar(500) NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth_key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `recharge_orders`
--

CREATE TABLE `recharge_orders` (
  `id` int(11) NOT NULL,
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `customer_name` varchar(100) NOT NULL COMMENT '客户名称',
  `phone_number` varchar(20) DEFAULT NULL COMMENT '手机号',
  `amount` decimal(10,2) NOT NULL COMMENT '充值金额',
  `operator` varchar(20) NOT NULL COMMENT '运营商',
  `region` varchar(20) NOT NULL COMMENT '归属地',
  `price` decimal(10,2) NOT NULL COMMENT '支付价格',
  `status` tinyint(4) DEFAULT '0' COMMENT '订单状态: 0-待支付, 1-已支付, 2-充值中, 3-充值成功, 4-充值失败',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='话费充值订单表';

-- --------------------------------------------------------

--
-- 表的结构 `recharge_records`
--

CREATE TABLE `recharge_records` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `usdt_amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(20) DEFAULT 'USDT',
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text,
  `user_notes` text,
  `show_notes_to_user` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `site_visits`
--

CREATE TABLE `site_visits` (
  `id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `visits` int(11) DEFAULT '0',
  `unique_visitors` int(11) DEFAULT '0',
  `page_views` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `site_visits`
--

INSERT INTO `site_visits` (`id`, `visit_date`, `visits`, `unique_visitors`, `page_views`, `created_at`) VALUES
(1, '2026-06-08', 1, 1, 1, '2026-06-08 15:33:13'),
(2, '2026-06-09', 6, 4, 6, '2026-06-08 17:02:39');

-- --------------------------------------------------------

--
-- 表的结构 `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '租户名称',
  `description` text COMMENT '租户描述',
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `max_users` int(11) DEFAULT '10' COMMENT '最大用户数',
  `expire_time` datetime DEFAULT NULL COMMENT '租户到期时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `description`, `status`, `max_users`, `expire_time`, `created_at`, `updated_at`) VALUES
(1, '平台管理租户', '系统默认管理租户', 'active', 1000, NULL, '2026-01-19 07:14:42', '2026-01-19 07:14:42');

-- --------------------------------------------------------

--
-- 表的结构 `userantired`
--

CREATE TABLE `userantired` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `remark` varchar(100) DEFAULT NULL,
  `api_url` varchar(500) NOT NULL,
  `encoding` enum('base64','url','none') DEFAULT 'base64',
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(32) NOT NULL,
  `second_password` varchar(32) DEFAULT NULL COMMENT '二级密码（MD5加密）',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT '上次登录IP地址',
  `visitor_token` varchar(40) DEFAULT NULL COMMENT '访客Token',
  `role` varchar(20) DEFAULT 'user',
  `balance` decimal(10,2) DEFAULT '0.00',
  `expire_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `username`, `password`, `second_password`, `last_login_ip`, `visitor_token`, `role`, `balance`, `expire_time`, `created_at`) VALUES
(7, NULL, 'admin', '74aebfe7c9f5afd3e9bfa649b15efc17', 'e10adc3949ba59abbe56e057f20f883e', '162.159.120.215', 'xile-QHcajN75dPA5yY8DIbQzVJ3V48g4bD43', 'admin', 0.00, '2028-02-11 17:57:00', '2026-05-13 10:58:01'),
(9, NULL, 'aaaa', 'e10adc3949ba59abbe56e057f20f883e', NULL, '2409:8a04:2620:1710:115b:3b48:db9a:aca9', 'xile-TwlDUSR0tq54cboXKQVyBJ4qDSRiH8aT', 'user', 0.00, '2026-07-08 19:45:00', '2026-06-08 19:45:07');

-- --------------------------------------------------------

--
-- 表的结构 `user_anti_red_config`
--

CREATE TABLE `user_anti_red_config` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `redirect_to_browser` tinyint(1) DEFAULT '1' COMMENT '引到浏览器开关：1-开启，0-关闭',
  `apply_status` enum('on','off') DEFAULT 'off' COMMENT '应用状态：on-已应用，off-未应用',
  `applied_domain` varchar(100) DEFAULT NULL COMMENT '应用的防红域名，如www.test.shop',
  `applied_api_url` varchar(500) DEFAULT NULL COMMENT '已应用的接口URL',
  `custom_encoding_mode` varchar(20) DEFAULT 'base64' COMMENT '自定义编码模式',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `auto_check_wechat` tinyint(1) DEFAULT '1' COMMENT '自动检测微信状态: 1-开启, 0-关闭'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `user_anti_red_config`
--

INSERT INTO `user_anti_red_config` (`id`, `username`, `redirect_to_browser`, `apply_status`, `applied_domain`, `applied_api_url`, `custom_encoding_mode`, `updated_at`, `auto_check_wechat`) VALUES
(1, 'admin', 1, 'off', NULL, NULL, 'base64', '2026-06-08 17:07:36', 1);

-- --------------------------------------------------------

--
-- 表的结构 `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `username`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 7, 'admin', '修改二级密码', '二级密码已成功修改', '172.71.24.147', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-06-08 15:58:34'),
(2, 7, 'admin', '修改密码', '密码已成功修改，所有设备已注销', '172.71.24.147', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '2026-06-08 16:15:31'),
(3, 7, 'admin', '修改密码', '密码已成功修改，所有设备已注销', '141.101.98.163', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '2026-06-08 16:17:07');

-- --------------------------------------------------------

--
-- 表的结构 `user_online_status`
--

CREATE TABLE `user_online_status` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `is_online` tinyint(1) DEFAULT '0',
  `window_status` enum('window_visible','window_hidden','window_closed') DEFAULT 'window_closed',
  `last_seen` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_heartbeat` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `user_type` enum('customer','agent') NOT NULL,
  `session_key` varchar(100) DEFAULT NULL,
  `page_visibility` tinyint(1) DEFAULT '1' COMMENT '1=可见, 0=隐藏',
  `client_ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `device_type` varchar(20) DEFAULT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `platform` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `user_online_status`
--

INSERT INTO `user_online_status` (`id`, `username`, `is_online`, `window_status`, `last_seen`, `last_activity`, `last_heartbeat`, `user_type`, `session_key`, `page_visibility`, `client_ip`, `user_agent`, `device_type`, `browser`, `os`, `platform`) VALUES
(1, 'admin', 1, 'window_closed', '2026-06-09 04:04:20', '2026-06-09 04:04:20', '2026-06-09 04:04:49', 'agent', '', 1, '0.0.0.0', NULL, NULL, NULL, NULL, NULL),
(7, 'lbC7U1', 1, 'window_visible', '2026-06-09 00:04:37', '2026-06-09 00:04:37', '2026-06-09 00:04:37', 'customer', NULL, 1, '223.104.41.122', NULL, NULL, NULL, NULL, NULL),
(158, 'yr8rGd', 1, 'window_visible', '2026-06-09 04:04:50', '2026-06-09 04:04:50', '2026-06-09 04:04:50', 'customer', 'ayr8rGdz-padmins', 1, '0.0.0.0', NULL, NULL, NULL, NULL, NULL),
(505, 'aaaa', 0, 'window_closed', '2026-06-09 04:00:51', '2026-06-09 04:00:51', '2026-06-09 04:00:51', 'customer', '', 1, '0.0.0.0', 'WebSocket Client', NULL, NULL, NULL, NULL),
(513, '0TsBAJ', 1, 'window_visible', '2026-06-09 04:01:20', '2026-06-09 04:01:20', '2026-06-09 04:01:20', 'customer', NULL, 1, '2409:8a04:2620:1710:a5da:4fd:604b:ede4', NULL, NULL, NULL, NULL, NULL),
(549, 'aaaa', 0, 'window_closed', '2026-06-09 04:00:51', '2026-06-09 04:00:51', '2026-06-09 04:00:51', 'agent', '', 1, '0.0.0.0', 'WebSocket Client', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL COMMENT '用户ID',
  `session_token` varchar(64) NOT NULL COMMENT '会话令牌',
  `ip_address` varchar(45) DEFAULT NULL COMMENT '登录IP地址',
  `user_agent` text COMMENT '浏览器User Agent',
  `device_info` json DEFAULT NULL COMMENT '设备信息JSON',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否有效 1=有效 0=无效',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
  `login_type` varchar(20) NOT NULL DEFAULT 'password' COMMENT '登录类型: password/token'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户会话表';

--
-- 转存表中的数据 `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `device_info`, `is_active`, `created_at`, `expires_at`, `login_type`) VALUES
(1, 7, '0bd32cb0335a4ac5d9b565ac8a3c4156d94cb7c7ca416362ac103980f4e346cd', '172.71.24.147', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 0, '2026-06-09 00:15:11', '2026-07-09 00:15:11', 'password'),
(2, 7, 'c927c571531a210f87050755fc52ca6fb3af544cfab55146c0af52dc1e2b2b48', '172.71.24.147', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 0, '2026-06-09 00:16:08', '2026-07-09 00:16:08', 'password'),
(3, 7, '9ff980b8f3eec9bc113bde1fbe9e4aa62785305cdac0fe74971126b56d8ffd32', '141.101.98.163', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 0, '2026-06-09 00:16:33', '2026-07-09 00:16:33', 'password'),
(4, 7, 'e6f82137040047c860b8c12cbb2977539859bb37312939ed118c5965ad407943', '172.64.215.118', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 00:20:49', '2026-07-09 00:20:49', 'password'),
(5, 7, '6afc46e50110bcd03863de8c01c765d8db3400b6f3c12bce209ef426f93a6ab8', '162.158.41.46', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 00:49:44', '2026-07-09 00:49:44', 'password'),
(6, 7, 'c56f00afe247b80843d3e66e657419ba38a898164c35b0b3d1065350a2190619', '162.158.41.46', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 00:59:24', '2026-07-09 00:59:24', 'password'),
(7, 7, '27bb2a604d252ac0be757daa5542592415a67f597899ced8487ba061a0403c03', '162.158.41.46', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 01:01:21', '2026-07-09 01:01:21', 'token'),
(8, 7, '0013eb4e8ff15071394a86fea756df370ca7c09fbf972b7b74a9294d362a5b33', '162.159.120.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 01:02:35', '2026-07-09 01:02:35', 'password'),
(9, 7, 'ba41c7bc743769d61b7ff5beefe41fc761f60732b2636916d74079529533fac5', '162.159.120.214', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 01:38:46', '2026-07-09 01:38:46', 'password'),
(10, 7, 'd222a31970aa420d170bcff3846248ee21554e70e37257407d07b58fb4b4a94c', '162.158.114.169', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 02:36:34', '2026-07-09 02:36:34', 'password'),
(11, 7, '5dfd00c903057bff8699d24b29a74aa771cef33ff72b091aa90f015b5d5f8ded', '172.68.23.34', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 02:44:04', '2026-07-09 02:44:04', 'password'),
(12, 7, '0f039a486f07f63e9a4c737398bdd2b91b4a52f4872f435f0d99eaa5f0016cae', '162.159.98.9', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 03:02:38', '2026-07-09 03:02:38', 'password'),
(13, 7, '9856674801a3fd867eba6d7143eff6d8f9dc89843b19cf623c6a14769ac19ef6', '162.158.193.84', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 03:26:36', '2026-07-09 03:26:36', 'password'),
(14, 7, 'dada048564aface3aaf3901a3868ac3d0bbcaf890e7004bc2f1ea95cac126179', '172.68.225.197', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 03:42:26', '2026-07-09 03:42:26', 'password'),
(15, 9, 'ab3f9b252e941e99eb8edcea35feb2547421579ece3ab7508419f592e14ae25a', '162.158.179.51', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 03:45:20', '2026-07-09 03:45:20', 'password'),
(16, 9, '08e56147ace9df50222316100a0d4f59ed8d60deb1e0b5a1af70b65174ed2038', '162.158.41.46', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 03:47:37', '2026-07-09 03:47:37', 'password'),
(17, 7, '023a5b3089614bf66d3c8b0175647328c964df9557b151a8d26fb5400a8580ce', '172.68.23.34', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 04:03:18', '2026-07-09 04:03:18', 'password'),
(18, 7, 'aebb1d37a14d10d613b703d8f448474d07ced0920320280bfe53bfdda93ff67c', '162.159.120.215', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', NULL, 1, '2026-06-09 04:04:01', '2026-07-09 04:04:01', 'password');

-- --------------------------------------------------------

--
-- 表的结构 `visit_logs`
--

CREATE TABLE `visit_logs` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `referrer` varchar(500) DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `visit_logs`
--

INSERT INTO `visit_logs` (`id`, `ip_address`, `user_agent`, `referrer`, `page_url`, `session_id`, `user_id`, `created_at`) VALUES
(1, '223.104.41.122', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '', '/ChatGoofish?id=albC7U1z-padmins&XEDATA=92558e4ed8fb3148fd5361948f881fa5', 'kio3f6fpe8b7siabo97vf716u9', NULL, '2026-06-08 15:33:13'),
(2, '172.68.174.65', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', '', '/ChatGoofish?id=albC7U1z-padmins&XEDATA=92558e4ed8fb3148fd5361948f881fa5', '3kl15lgvc8j3122hm6tbum4vnv', NULL, '2026-06-08 17:02:39'),
(3, '162.159.120.214', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '', '/ChatGoofish?id=ayr8rGdz-padmins&XEDATA=8cb99eb1f957ad045b80390d6a9afd9d', 'oqakskivo9t6o203jdj3b87bia', NULL, '2026-06-08 17:39:00'),
(4, '162.159.120.214', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '', '/ChatGoofish?id=ayr8rGdz-padmins&XEDATA=8cb99eb1f957ad045b80390d6a9afd9d', 'oqakskivo9t6o203jdj3b87bia', NULL, '2026-06-08 17:52:24'),
(5, '162.158.41.47', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '', '/ChatGoofish?id=ayr8rGdz-padmins&XEDATA=8cb99eb1f957ad045b80390d6a9afd9d', 'oqakskivo9t6o203jdj3b87bia', NULL, '2026-06-08 19:42:47'),
(6, '162.158.41.47', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', '', '/ChatGoofish?id=ayr8rGdz-padmins&XEDATA=8cb99eb1f957ad045b80390d6a9afd9d', 'oqakskivo9t6o203jdj3b87bia', NULL, '2026-06-08 19:43:28'),
(7, '162.158.41.46', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', '', '/ChatGoofish?id=a0TsBAJz-paaaas&XEDATA=a1b40724090003a09af196905ccfe279', 't52n0u6fppafbecld0bk0okehl', NULL, '2026-06-08 19:47:14');

-- --------------------------------------------------------

--
-- 表的结构 `webconfig`
--

CREATE TABLE `webconfig` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `telegram_username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `storage_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `popup_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `popup_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `popup_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pwa_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XE控制台',
  `pwa_short_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'XE控制台',
  `pwa_icon` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/xe-icon.png',
  `site_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `site_url_enabled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 转存表中的数据 `webconfig`
--

INSERT INTO `webconfig` (`id`, `site_name`, `telegram_username`, `storage_type`, `popup_enabled`, `popup_title`, `popup_content`, `created_at`, `updated_at`, `pwa_name`, `pwa_short_name`, `pwa_icon`, `site_url`, `site_url_enabled`) VALUES
(1, 'xile', '@x60898', 'local', 0, '网站公告', 'ces', '2026-05-15 00:22:03', '2026-05-17 15:33:25', 'XE控制台', 'XE控制台', '/xe-icon.png', '', 0);

-- --------------------------------------------------------

--
-- 表的结构 `XE-SKDJWKSNCDATA`
--

CREATE TABLE `XE-SKDJWKSNCDATA` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL COMMENT 'aJ8X294z-padmins 这样的ID',
  `xedata_token` varchar(32) NOT NULL COMMENT 'XEDATA 验证令牌',
  `customer_name` varchar(50) NOT NULL,
  `agent_account` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL COMMENT '过期时间',
  `is_used` tinyint(1) DEFAULT '0' COMMENT '是否已使用',
  `platform` varchar(50) DEFAULT '抖音' COMMENT '分享平台',
  `status` enum('active','expired','used') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='聊天会话验证表';

--
-- 转存表中的数据 `XE-SKDJWKSNCDATA`
--

INSERT INTO `XE-SKDJWKSNCDATA` (`id`, `session_id`, `xedata_token`, `customer_name`, `agent_account`, `created_at`, `expires_at`, `is_used`, `platform`, `status`) VALUES
(1, 'a6k7fZJz-padmins', '540d6d8718f24141842253857c9ecb87', '6k7fZJ', 'admin', '2026-06-08 23:04:11', '2026-06-15 23:04:11', 0, '闲鱼', 'active'),
(2, 'albC7U1z-padmins', '92558e4ed8fb3148fd5361948f881fa5', 'lbC7U1', 'admin', '2026-06-08 23:33:08', '2026-06-15 23:33:08', 0, '闲鱼', 'active'),
(3, 'asB8eVnz-padmins', '5f1c04ee1c281696a47f93591f071812', 'sB8eVn', 'admin', '2026-06-08 23:34:10', '2026-06-15 23:34:10', 0, '闲鱼', 'active'),
(4, 'a0SvlwRz-padmins', 'fbe9d23952e68f92fdabe6b6057a1e7c', '0SvlwR', 'admin', '2026-06-08 23:34:11', '2026-06-15 23:34:11', 0, '闲鱼', 'active'),
(5, 'aiKeX2sz-padmins', '9af9a85dea9fcfba6e83ba030145ea37', 'iKeX2s', 'admin', '2026-06-08 23:46:00', '2026-06-15 23:46:00', 0, '闲鱼', 'active'),
(6, 'aBxQaKMz-padmins', 'f7d5d232ab4227510076cd9f3c65f632', 'BxQaKM', 'admin', '2026-06-09 01:10:31', '2026-06-16 01:10:31', 0, '闲鱼', 'active'),
(7, 'aUdHZCHz-padmins', '4eed0b433e95b8a0424c58395062331c', 'UdHZCH', 'admin', '2026-06-09 01:10:36', '2026-06-16 01:10:36', 0, '闲鱼', 'active'),
(8, 'ayr8rGdz-padmins', '8cb99eb1f957ad045b80390d6a9afd9d', 'yr8rGd', 'admin', '2026-06-09 01:38:57', '2026-06-16 01:38:57', 0, '闲鱼', 'active'),
(9, 'a0TsBAJz-paaaas', 'a1b40724090003a09af196905ccfe279', '0TsBAJ', 'aaaa', '2026-06-09 03:47:08', '2026-06-16 03:47:08', 0, '闲鱼', 'active');

-- --------------------------------------------------------

--
-- 表的结构 `XEDF_pages`
--

CREATE TABLE `XEDF_pages` (
  `XEDF_id` int(11) NOT NULL,
  `XEDF_user_id` int(11) NOT NULL,
  `XEDF_app_name` varchar(255) DEFAULT NULL COMMENT 'APP名称',
  `XEDF_net_name` varchar(100) DEFAULT NULL COMMENT '网名',
  `XEDF_real_name` varchar(50) DEFAULT NULL COMMENT '真实姓名',
  `XEDF_avatar_url` varchar(500) DEFAULT NULL COMMENT '头像URL',
  `XEDF_page_title` varchar(255) NOT NULL COMMENT '付款页标题',
  `XEDF_product_title` varchar(255) DEFAULT NULL COMMENT '商品标题',
  `XEDF_amount` decimal(10,2) NOT NULL,
  `XEDF_api_url` varchar(500) NOT NULL COMMENT '支付接口URL',
  `XEDF_payment_method` varchar(20) DEFAULT 'alipay' COMMENT '支付方式：用于识别并跳转至对应付款页',
  `XEDF_status` enum('active','inactive') DEFAULT 'active',
  `XEDF_page_code` varchar(50) NOT NULL,
  `XEDF_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEDF_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='代付页表';

-- --------------------------------------------------------

--
-- 表的结构 `XEDF_verify_pages`
--

CREATE TABLE `XEDF_verify_pages` (
  `XEDF_verify_id` int(11) NOT NULL,
  `XEDF_user_id` int(11) NOT NULL,
  `XEDF_verify_remark` varchar(500) NOT NULL COMMENT '验证页备注',
  `XEDF_verify_code` varchar(50) NOT NULL COMMENT '验证页唯一代码',
  `XEDF_page_title` varchar(255) NOT NULL COMMENT '验证页标题',
  `XEDF_status` enum('active','inactive') DEFAULT 'active',
  `XEDF_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEDF_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证页管理表';

-- --------------------------------------------------------

--
-- 表的结构 `XEDF_verify_submissions`
--

CREATE TABLE `XEDF_verify_submissions` (
  `XEDF_submit_id` int(11) NOT NULL,
  `XEDF_verify_id` int(11) NOT NULL,
  `XEDF_id_card` varchar(20) NOT NULL COMMENT '身份证号',
  `XEDF_bank_card` varchar(30) NOT NULL COMMENT '银行卡号',
  `XEDF_real_name` varchar(50) NOT NULL COMMENT '真实姓名',
  `XEDF_phone` varchar(15) NOT NULL COMMENT '手机号',
  `XEDF_submit_ip` varchar(45) DEFAULT NULL COMMENT '提交IP',
  `XEDF_user_agent` text COMMENT '用户浏览器信息',
  `XEDF_submit_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='验证信息提交表';

-- --------------------------------------------------------

--
-- 表的结构 `XEmsg_pages`
--

CREATE TABLE `XEmsg_pages` (
  `XEmsg_id` int(11) NOT NULL,
  `XEmsg_user_id` int(11) NOT NULL,
  `XEmsg_page_title` varchar(255) NOT NULL COMMENT '客服标题',
  `XEmsg_company_name` varchar(255) DEFAULT NULL COMMENT '公司名称',
  `XEmsg_company_subtitle` varchar(255) DEFAULT NULL COMMENT '公司副标题',
  `XEmsg_badge_text` varchar(100) DEFAULT NULL COMMENT '认证徽章文字',
  `XEmsg_service_hours` varchar(100) DEFAULT NULL COMMENT '服务时间',
  `XEmsg_top_badge_1` varchar(255) DEFAULT NULL COMMENT '顶部徽章1号',
  `XEmsg_top_badge_2` varchar(255) DEFAULT NULL COMMENT '顶部徽章2号',
  `XEmsg_welcome_message` text COMMENT '欢迎语',
  `XEmsg_avatar_url` varchar(500) DEFAULT NULL COMMENT '头像URL',
  `XEmsg_status` enum('active','inactive') DEFAULT 'active',
  `XEmsg_page_code` varchar(50) NOT NULL,
  `XEmsg_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEmsg_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `XEmsg_poster_entry_text` varchar(255) DEFAULT NULL COMMENT '海报入口文案',
  `XEmsg_share_param` varchar(255) DEFAULT NULL COMMENT '分享参数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服页面表';

-- --------------------------------------------------------

--
-- 表的结构 `XEpxb7`
--

CREATE TABLE `XEpxb7` (
  `XEpxb7_id` int(11) NOT NULL,
  `XEpxb7_user_id` int(11) NOT NULL,
  `XEpxb7_product_name` varchar(255) NOT NULL COMMENT '商品名称',
  `XEpxb7_game_name` varchar(255) DEFAULT '',
  `XEpxb7_product_code` varchar(100) NOT NULL COMMENT '商品编号',
  `XEpxb7_product_amount` decimal(15,2) NOT NULL COMMENT '商品金额',
  `XEpxb7_page_status` enum('active','inactive') DEFAULT 'active' COMMENT '页面激活状态',
  `XEpxb7_page_code` varchar(50) NOT NULL,
  `XEpxb7_product_image` varchar(500) DEFAULT NULL COMMENT '商品图片',
  `XEpxb7_seller_avatar` varchar(500) DEFAULT NULL COMMENT '卖家头像',
  `XEpxb7_no_stock_compensation` enum('是','否') DEFAULT '否' COMMENT '是否开启无货立赔',
  `XEpxb7_retrieve_compensation` enum('是','否') DEFAULT '否' COMMENT '是否开启找回包赔',
  `XEpxb7_customer_service` enum('螃蟹交易专员','螃蟹咨询专员','螃蟹售后专员') DEFAULT '螃蟹交易专员' COMMENT '客服身份',
  `XEpxb7_dummy_identity` varchar(20) DEFAULT '买家',
  `XEpxb7_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEpxb7_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `XEpxb7_share_link` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品信息表';

-- --------------------------------------------------------

--
-- 表的结构 `XEpzds`
--

CREATE TABLE `XEpzds` (
  `XEpzds_id` int(11) NOT NULL,
  `XEpzds_user_id` int(11) NOT NULL,
  `XEpzds_product_name` varchar(255) NOT NULL COMMENT '商品名称',
  `XEpzds_product_code` varchar(100) NOT NULL COMMENT '商品编号',
  `XEpzds_product_amount` decimal(15,2) NOT NULL COMMENT '商品金额',
  `XEpzds_compensation_type` enum('全额包赔','双倍包赔','充值包赔') DEFAULT '全额包赔' COMMENT '包赔类型',
  `XEpzds_page_status` enum('active','inactive') DEFAULT 'active' COMMENT '页面激活状态',
  `XEpzds_page_code` varchar(50) NOT NULL,
  `XEpzds_product_image` varchar(500) DEFAULT NULL COMMENT '商品图片',
  `XEpzds_seller_avatar` varchar(500) DEFAULT NULL COMMENT '卖家头像',
  `XEpzds_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEpzds_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `XEpzds_share_link` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品信息表';

-- --------------------------------------------------------

--
-- 表的结构 `XEyouxige`
--

CREATE TABLE `XEyouxige` (
  `XEyouxige_id` int(11) NOT NULL,
  `XEyouxige_user_id` int(11) NOT NULL,
  `XEyouxige_trader_name` varchar(255) NOT NULL,
  `XEyouxige_group_code` varchar(100) NOT NULL,
  `XEyouxige_welcome_message` text,
  `XEyouxige_page_status` varchar(20) DEFAULT 'active',
  `XEyouxige_page_code` varchar(50) NOT NULL,
  `XEyouxige_seller_avatar` varchar(500) DEFAULT NULL,
  `XEyouxige_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `XEyouxige_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转储表的索引
--

--
-- 表的索引 `anti_red_links`
--
ALTER TABLE `anti_red_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_domain` (`domain_name`),
  ADD KEY `idx_is_sold` (`is_sold`),
  ADD KEY `idx_sold_to` (`sold_to`);

--
-- 表的索引 `anti_red_pricing`
--
ALTER TABLE `anti_red_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_item_name` (`item_name`);

--
-- 表的索引 `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_key`),
  ADD KEY `idx_agent` (`agent_account`),
  ADD KEY `idx_customer` (`customer_name`),
  ADD KEY `idx_client_ip` (`client_ip`),
  ADD KEY `idx_session_ip` (`session_key`,`client_ip`);

--
-- 表的索引 `chat_session_settings`
--
ALTER TABLE `chat_session_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_setting` (`session_key`,`agent_account`,`setting_key`);

--
-- 表的索引 `chat_settings`
--
ALTER TABLE `chat_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_agent` (`session_key`,`agent_account`);

--
-- 表的索引 `dummy_settings`
--
ALTER TABLE `dummy_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`session_key`),
  ADD KEY `idx_session_key` (`session_key`),
  ADD KEY `idx_last_updated` (`last_updated`);

--
-- 表的索引 `freeantired`
--
ALTER TABLE `freeantired`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `gpt_config`
--
ALTER TABLE `gpt_config`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `payment_pages`
--
ALTER TABLE `payment_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_code` (`page_code`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_page_code` (`page_code`);

--
-- 表的索引 `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`,`user_type`);

--
-- 表的索引 `recharge_orders`
--
ALTER TABLE `recharge_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `customer_name` (`customer_name`),
  ADD KEY `status` (`status`);

--
-- 表的索引 `recharge_records`
--
ALTER TABLE `recharge_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `site_visits`
--
ALTER TABLE `site_visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`visit_date`);

--
-- 表的索引 `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `userantired`
--
ALTER TABLE `userantired`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_tenant_role` (`tenant_id`,`role`);

--
-- 表的索引 `user_anti_red_config`
--
ALTER TABLE `user_anti_red_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_apply_status` (`apply_status`);

--
-- 表的索引 `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `user_online_status`
--
ALTER TABLE `user_online_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_username_user_type` (`username`,`user_type`),
  ADD KEY `idx_last_heartbeat` (`last_heartbeat`),
  ADD KEY `idx_window_status` (`window_status`),
  ADD KEY `idx_session_key` (`session_key`);

--
-- 表的索引 `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- 表的索引 `visit_logs`
--
ALTER TABLE `visit_logs`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `webconfig`
--
ALTER TABLE `webconfig`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `XE-SKDJWKSNCDATA`
--
ALTER TABLE `XE-SKDJWKSNCDATA`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD UNIQUE KEY `xedata_token` (`xedata_token`),
  ADD KEY `agent_account` (`agent_account`),
  ADD KEY `expires_at` (`expires_at`);

--
-- 表的索引 `XEDF_pages`
--
ALTER TABLE `XEDF_pages`
  ADD PRIMARY KEY (`XEDF_id`),
  ADD UNIQUE KEY `XEDF_page_code` (`XEDF_page_code`),
  ADD KEY `idx_xedf_user_id` (`XEDF_user_id`),
  ADD KEY `idx_xedf_status` (`XEDF_status`),
  ADD KEY `idx_xedf_page_code` (`XEDF_page_code`);

--
-- 表的索引 `XEDF_verify_pages`
--
ALTER TABLE `XEDF_verify_pages`
  ADD PRIMARY KEY (`XEDF_verify_id`),
  ADD UNIQUE KEY `XEDF_verify_code` (`XEDF_verify_code`),
  ADD KEY `idx_xedf_verify_user_id` (`XEDF_user_id`),
  ADD KEY `idx_xedf_verify_status` (`XEDF_status`),
  ADD KEY `idx_xedf_verify_code` (`XEDF_verify_code`);

--
-- 表的索引 `XEDF_verify_submissions`
--
ALTER TABLE `XEDF_verify_submissions`
  ADD PRIMARY KEY (`XEDF_submit_id`),
  ADD KEY `idx_xedf_verify_id` (`XEDF_verify_id`),
  ADD KEY `idx_xedf_submit_time` (`XEDF_submit_time`);

--
-- 表的索引 `XEpxb7`
--
ALTER TABLE `XEpxb7`
  ADD PRIMARY KEY (`XEpxb7_id`),
  ADD UNIQUE KEY `XEpxb7_product_code` (`XEpxb7_product_code`),
  ADD UNIQUE KEY `XEpxb7_page_code` (`XEpxb7_page_code`),
  ADD KEY `idx_XEpxb7_user_id` (`XEpxb7_user_id`),
  ADD KEY `idx_XEpxb7_product_code` (`XEpxb7_product_code`),
  ADD KEY `idx_XEpxb7_page_status` (`XEpxb7_page_status`);

--
-- 表的索引 `XEpzds`
--
ALTER TABLE `XEpzds`
  ADD PRIMARY KEY (`XEpzds_id`),
  ADD UNIQUE KEY `XEpzds_product_code` (`XEpzds_product_code`),
  ADD UNIQUE KEY `XEpzds_page_code` (`XEpzds_page_code`),
  ADD KEY `idx_XEpzds_user_id` (`XEpzds_user_id`),
  ADD KEY `idx_XEpzds_product_code` (`XEpzds_product_code`),
  ADD KEY `idx_XEpzds_page_status` (`XEpzds_page_status`);

--
-- 表的索引 `XEyouxige`
--
ALTER TABLE `XEyouxige`
  ADD PRIMARY KEY (`XEyouxige_id`),
  ADD UNIQUE KEY `unique_user_page` (`XEyouxige_user_id`,`XEyouxige_page_code`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `anti_red_links`
--
ALTER TABLE `anti_red_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `anti_red_pricing`
--
ALTER TABLE `anti_red_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- 使用表AUTO_INCREMENT `chat_session_settings`
--
ALTER TABLE `chat_session_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `chat_settings`
--
ALTER TABLE `chat_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `dummy_settings`
--
ALTER TABLE `dummy_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `freeantired`
--
ALTER TABLE `freeantired`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `gpt_config`
--
ALTER TABLE `gpt_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `payment_pages`
--
ALTER TABLE `payment_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `recharge_orders`
--
ALTER TABLE `recharge_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `recharge_records`
--
ALTER TABLE `recharge_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `site_visits`
--
ALTER TABLE `site_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `userantired`
--
ALTER TABLE `userantired`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `user_anti_red_config`
--
ALTER TABLE `user_anti_red_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `user_online_status`
--
ALTER TABLE `user_online_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=614;

--
-- 使用表AUTO_INCREMENT `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- 使用表AUTO_INCREMENT `visit_logs`
--
ALTER TABLE `visit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- 使用表AUTO_INCREMENT `webconfig`
--
ALTER TABLE `webconfig`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `XE-SKDJWKSNCDATA`
--
ALTER TABLE `XE-SKDJWKSNCDATA`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `XEDF_pages`
--
ALTER TABLE `XEDF_pages`
  MODIFY `XEDF_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `XEDF_verify_pages`
--
ALTER TABLE `XEDF_verify_pages`
  MODIFY `XEDF_verify_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `XEDF_verify_submissions`
--
ALTER TABLE `XEDF_verify_submissions`
  MODIFY `XEDF_submit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `XEpxb7`
--
ALTER TABLE `XEpxb7`
  MODIFY `XEpxb7_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `XEpzds`
--
ALTER TABLE `XEpzds`
  MODIFY `XEpzds_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `XEyouxige`
--
ALTER TABLE `XEyouxige`
  MODIFY `XEyouxige_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 限制导出的表
--

--
-- 限制表 `payment_pages`
--
ALTER TABLE `payment_pages`
  ADD CONSTRAINT `payment_pages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `recharge_records`
--
ALTER TABLE `recharge_records`
  ADD CONSTRAINT `recharge_records_ibfk_1` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON DELETE CASCADE;

--
-- 限制表 `userantired`
--
ALTER TABLE `userantired`
  ADD CONSTRAINT `userantired_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL;

--
-- 限制表 `XEDF_pages`
--
ALTER TABLE `XEDF_pages`
  ADD CONSTRAINT `XEDF_pages_ibfk_1` FOREIGN KEY (`XEDF_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `XEDF_verify_pages`
--
ALTER TABLE `XEDF_verify_pages`
  ADD CONSTRAINT `XEDF_verify_pages_ibfk_1` FOREIGN KEY (`XEDF_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 限制表 `XEDF_verify_submissions`
--
ALTER TABLE `XEDF_verify_submissions`
  ADD CONSTRAINT `XEDF_verify_submissions_ibfk_1` FOREIGN KEY (`XEDF_verify_id`) REFERENCES `XEDF_verify_pages` (`XEDF_verify_id`) ON DELETE CASCADE;

--
-- 限制表 `XEpzds`
--
ALTER TABLE `XEpzds`
  ADD CONSTRAINT `XEpzds_ibfk_1` FOREIGN KEY (`XEpzds_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
