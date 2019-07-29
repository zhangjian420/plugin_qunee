<?php

/**
 * 安装时的方法
 */
function plugin_qunee_install() {
    /* core plugin functionality */
    api_plugin_register_hook('qunee', 'top_header_tabs', 'qunee_show_tab', 'setup.php');
    api_plugin_register_hook('qunee', 'top_graph_header_tabs', 'qunee_show_tab', 'setup.php');
    
    // Breadcrums
    api_plugin_register_hook("qunee", 'draw_navigation_text', 'qunee_draw_navigation_text', 'setup.php');
    
    api_plugin_register_hook('qunee', 'poller_bottom', 'qunee_poller_bottom', 'setup.php');
    api_plugin_register_hook('qunee', 'page_head', 'plugin_qunee_page_head', 'setup.php');
    
    api_plugin_register_realm('qunee', 'qunee.php', '气象图', 1);
    
    qunee_setup_table();
}

/**
 * 卸载时候的方法
 */
function plugin_qunee_uninstall() {
   // db_execute('DROP TABLE IF EXISTS plugin_qunee');
}

/**
 * 用于检查插件的版本，并提供更多信息
 * @return mixed
 */
function plugin_qunee_version() {
    global $config;
    $info = parse_ini_file($config['base_path'] . '/plugins/qunee/INFO', true);
    return $info['info'];
}

/**
 * 用于确定您的插件是否已准备好在安装后启用
 */
function plugin_qunee_check_config() {
    return true;
}

/**
 * 显示顶部选项卡
 */
function qunee_show_tab() {
    global $config;
    if (substr_count($_SERVER['REQUEST_URI'], 'qunee.php')) {
        print '<a href="' . $config['url_path'] . 'plugins/qunee/qunee.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor_down.gif" alt="气象图"></a>';
    } else {
        print '<a href="' . $config['url_path'] . 'plugins/qunee/qunee.php"><img src="' . $config['url_path'] . 'plugins/monitor/images/tab_monitor.gif" alt="气象图"></a>';
    }
}


function qunee_poller_bottom() {
    global $config;
    include_once($config['base_path'] . '/plugins/qunee/qunee_functions.php');
    pollAlarm();
}

/**
 * 自定义js
 */
function plugin_qunee_page_head() {
    print get_md5_include_css('plugins/qunee/include/css/qunee.css') . PHP_EOL;
    print get_md5_include_js('plugins/qunee/include/js/qunee.min.js') . PHP_EOL;
    print get_md5_include_js('plugins/qunee/include/js/graphs.js') . PHP_EOL;
    print get_md5_include_js('plugins/qunee/include/js/qunee.json.js') . PHP_EOL;
    print get_md5_include_js('plugins/qunee/include/js/qunee.formitem.js') . PHP_EOL;
    print get_md5_include_js('plugins/qunee/include/js/qunee.common.js') . PHP_EOL;
}

function qunee_draw_navigation_text ($nav) {
    $nav['qunee.php:'] = array('title' => '气象图', 'mapping' => '', 'url' => 'qunee.php', 'level' => '1');
    
    return $nav;
}

function plugin_monitor_check_config() {
    return true;
}

function plugin_monitor_upgrade() {
    return false;
}

function plugin_monitor_version() {
    global $config;
    $info = parse_ini_file($config['base_path'] . '/plugins/qunee/INFO', true);
    return $info['info'];
}

function qunee_setup_table() {
    if (!db_table_exists('plugin_qunee')) {
        db_execute("CREATE TABLE IF NOT EXISTS plugin_qunee (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            `modified_by` int(10) unsigned DEFAULT 1,
            `emails` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            `topo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
}