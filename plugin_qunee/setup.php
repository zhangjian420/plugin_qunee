<?php

/**
 * ��װʱ�ķ���
 */
function plugin_qunee_install() {
	/* core plugin functionality */
	api_plugin_register_hook('qunee', 'top_header_tabs', 'qunee_show_tab', 'setup.php');
	api_plugin_register_hook('qunee', 'top_graph_header_tabs', 'qunee_show_tab', 'setup.php');
}

/**
 * ж��ʱ��ķ���
 */
function plugin_qunee_uninstall() {
   
}

/**
 * ���ڼ�����İ汾�����ṩ������Ϣ
 * @return mixed
 */
function plugin_qunee_version() {
    global $config;
    $info = parse_ini_file($config['base_path'] . '/plugins/monitor/INFO', true);
    return $info['info'];
}

/**
 * ����ȷ�����Ĳ���Ƿ���׼�����ڰ�װ������
 */
function plugin_qunee_check_config() {
    return true;
}

/**
 * ��ʾ����ѡ�
 */
function qunee_show_tab() {
    global $config;
    print '<a href="' . $config['url_path'] . 'plugins/qunee/qunee.php"></a>';
}
