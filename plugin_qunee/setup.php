<?php

/**
 * 安装时的方法
 */
function plugin_qunee_install() {
	/* core plugin functionality */
	api_plugin_register_hook('qunee', 'top_header_tabs', 'qunee_show_tab', 'setup.php');
	api_plugin_register_hook('qunee', 'top_graph_header_tabs', 'qunee_show_tab', 'setup.php');
}

/**
 * 卸载时候的方法
 */
function plugin_qunee_uninstall() {
   
}

/**
 * 用于检查插件的版本，并提供更多信息
 * @return mixed
 */
function plugin_qunee_version() {
    global $config;
    $info = parse_ini_file($config['base_path'] . '/plugins/monitor/INFO', true);
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
    print '<a href="' . $config['url_path'] . 'plugins/qunee/qunee.php"></a>';
}
