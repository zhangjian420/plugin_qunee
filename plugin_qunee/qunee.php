<?php
$guest_account=true;

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/plugins/qunee/qunee_functions.php');

$qunee_actions = array(
    1 => __('Delete')
);

$fields_qunee_edit = array(
    'topo' => array(
        'method' => 'hidden',
        'value' => '|arg1:topo|'
    ),
    'emails' => array(
        'method' => 'hidden',
        'value' => '|arg1:emails|'
    ),
    'thold' => array(
        'method' => 'hidden',
        'value' => '|arg1:thold|'
    ),
    'name' => array(
        'method' => 'hidden',
        'value' => '|arg1:name|'
    ),
    'id' => array(
        'method' => 'hidden_zero',
        'value' => '|arg1:id|'
    ),
    'save_component_site' => array(
        'method' => 'hidden',
        'value' => '1'
    )
);

set_default_action();

switch(get_nfilter_request_var('action')) {
    case 'edit':
        general_header();
        qunee_edit();
        bottom_footer();
        break;
    case 'save':
        qunee_save();
        break;
    case 'actions':
        form_actions();
        break;
    case 'ajax_host':
        ajax_hosts();
        break;
    case 'ajax_imgs':
        ajax_imgs();
        break;
    case 'ajax_graph':
        ajax_graph();
        break;
    case 'ajax_data':
        ajax_data();
        break;
    case 'ajax_emails':
        ajax_emails();
        break;
    case 'import':
        import_topo();
        break;
    case "dl_tpl":
        download_tpl("hosts.zip");
        break;
    case "sub":
        general_header();
        qunee_sub_edit();
        bottom_footer();
        break;
    case 'sub_save':
        qunee_sub_save();
        break;
    default:
        general_header();
        qunee_list();
        bottom_footer();
        break;
}
exit;

/**
 * 列表显示所有的拓扑图
 */
function qunee_list(){
    global $qunee_actions,$item_rows;
    
    /* ================= input validation and session storage ================= */
    $filters = array(
        'rows' => array(
            'filter' => FILTER_VALIDATE_INT,
            'pageset' => true,
            'default' => '-1'
        ),
        'page' => array(
            'filter' => FILTER_VALIDATE_INT,
            'default' => '1'
        ),
        'filter' => array(
            'filter' => FILTER_CALLBACK,
            'pageset' => true,
            'default' => '',
            'options' => array('options' => 'sanitize_search_string')
        ),
        'sort_column' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'name',
            'options' => array('options' => 'sanitize_search_string')
        ),
        'sort_direction' => array(
            'filter' => FILTER_CALLBACK,
            'default' => 'ASC',
            'options' => array('options' => 'sanitize_search_string')
        )
    );
    
    validate_store_request_vars($filters, 'sess_qunee');
    /* ================= input validation ================= */
    
    /* if the number of rows is -1, set it to the default */
    if (get_request_var('rows') == -1) {
        $rows = read_config_option('num_rows_table');
    } else {
        $rows = get_request_var('rows');
    }
    
    $buttons = array(
        array(
            'href'     => 'qunee.php?action=edit',
            'callback' => true,
            'title'    => "添加拓扑图",
            'class'    => 'fa fa-plus'
        ),
        array(
            'href'     => 'qunee_devices.php',
            'callback' => true,
            'title'    => "添加设备图片",
            'class'    => 'fa fa-desktop'
        )
    );
    
    html_start_box("拓扑图", '100%', '', '3', 'center', $buttons);
    ?>
    <tr class='even'>
		<td>
			<form id='form_qunee' action='qunee.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						拓扑图
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						拓扑图
					</td>
					<td>
						<select id='test1' class="searchableSelect">
							<option value="jQuery插件库">jQuery插件库</option>
                          <option value="BlackBerry">BlackBerry</option>
                          <option value="device">device</option>
                          <option value="with">with</option>
                          <option value="entertainment">entertainment</option>
                          <option value="and">and</option>
                          <option value="social">social</option>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'qunee.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'qunee.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_qunee').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
				var s1 = $('#test1').searchableSelect({
					show_srch:true,
					afterShow:function(){
						var me = this;
						console.info(me);
						me.clear();
						$.ajax({
							dataType:"json",
							async:false,
							url:"qunee.php?action=ajax_host",
							success: function(data){
								if(data && data.length > 0){
									$.each(data,function(di,dv){
										me.appendItems([{value:dv.id,text:dv.value}]);
									});
								}
							}
						});
					}
				});
			});

			</script>
		</td>
	</tr>
	<?php
	html_end_box();
	
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
	    $sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%')";
	} else {
	    $sql_where = '';
	}
	if (empty($sql_where)) {
	    $sql_where .= " where topo is not null ";
	}else {
	    $sql_where .= " and topo is not null ";
	}
	
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_qunee $sql_where");
	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	
	$qunee_list = db_fetch_assoc("SELECT * FROM plugin_qunee 
		$sql_where
		$sql_order
		$sql_limit");
	
	$nav = html_nav_bar('qunee.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, "拓扑图", 'page', 'main');
	
	form_start('qunee.php', 'chk');
	
	print $nav;
	
	html_start_box('', '100%', '', '3', 'center', '');
	
	$display_text = array(
	    'name'    => array('display' => "名称", 'align' => 'left',  'sort' => 'ASC', 'tip' => "拓扑图名称"),
	    'id'      => array('display' => __('ID'),        'align' => 'right', 'sort' => 'ASC', 'tip' => "拓扑图ID"),
	    'last_modified' => array('display' => __('Last Edited'), 'align' => 'right', 'sort' => 'ASC', 'tip' => "最后编辑时间"),
	    'modified_by' => array('display' => __('Edited By'), 'align' => 'right', 'sort' => 'ASC', 'tip' => "最后编辑人"),
	);
	
	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);
	
	if (cacti_sizeof($qunee_list)) {
	    foreach ($qunee_list as $qunee) {
	        form_alternate_row('line' . $qunee['id'], true);
	        form_selectable_cell(filter_value($qunee['name'], get_request_var('filter'), 'qunee.php?action=edit&id=' . $qunee['id']), $qunee['id']);
	        form_selectable_cell($qunee['id'], $qunee['id'], '', 'right');
	        form_selectable_cell(substr($qunee['last_modified'],0,16), $qunee['id'], '', 'right');
	        form_selectable_cell(get_username($qunee['modified_by']), $qunee['id'], '', 'right');
	        form_checkbox_cell($qunee['name'], $qunee['id']);
	        form_end_row();
	    }
	} else {
	    print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . "没有数据" . "</em></td></tr>\n";
	}
	
	html_end_box(false);
	
	if (cacti_sizeof($qunee_list)) {
	    print $nav;
	}
	
	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($qunee_actions);
	
	form_end();
	
	
}

/**
 * 进入添加和修改拓扑图
 */
function qunee_edit(){
    global $config,$fields_qunee_edit;
    /* ================= input validation ================= */
    get_filter_request_var('id');
    /* ==================================================== */
    if (!isempty_request_var('id')) {
        $qunee = db_fetch_row_prepared('SELECT * FROM plugin_qunee WHERE id = ?', array(get_request_var('id')));
        $header_label = __('拓扑图 [编辑: %s]', html_escape($qunee['name']));
    } else {
        $save = array();
        $save['last_modified']  = date('Y-m-d H:i:s', time());
        $save['modified_by']    = $_SESSION['sess_user_id'];
        $qunee_id = sql_save($save, 'plugin_qunee');
        $fields_qunee_edit["id"] = array(
            'method' => 'hidden_zero',
            'value' => $qunee_id,
        );
        $header_label = __('拓扑图 [新建]');
    }
    
    form_start('qunee.php', 'qunee');
    
    html_start_box($header_label, '100%', true, '3', 'center', '');
    
    draw_edit_form(
        array(
            'config' => array('no_form_tag' => true),
            'fields' => inject_form_variables($fields_qunee_edit, (isset($qunee) ? $qunee : array()))
        )
    );
    include($config['base_path'] . '/plugins/qunee/include/qunee_html.php');
    kill_session_var("import_data");
    html_end_box(true, true);
    
    //form_save_button('qunee.php', 'return');
}

/**
 * 点击添加或者修改按钮
 */
function qunee_save(){
    if (isset_request_var('save_component_site')) {
//         cacti_log("请求数据id=".get_request_var('id')
//             .",name=".get_request_var('name')
//             .",emails=".get_request_var('emails')
//             .",thold=".get_request_var('thold')
//             .",graph_ids=".get_request_var('graph_ids')
//             .",topo=".get_request_var('topo')
//             , false, 'SYSTEM');
        $save = array();
        $save['id']             = get_request_var('id');
        $save['name']           = get_request_var('name');//form_input_validate(get_request_var('name'), 'name', '', false, 3);
        $save['emails']         = get_request_var('emails');//form_input_validate(get_request_var('emails'), 'emails', '', true, 3);
        $save['thold']          = get_request_var('thold');//form_input_validate(get_request_var('thold'), 'thold', '', true, 3);
        $save['graphs']         = get_request_var('graphs');//form_input_validate(get_request_var('graph_ids'), 'graph_ids', '', true, 3);
        $save['topo']           = get_request_var('topo');//form_input_validate(get_request_var('topo'), 'topo', '', true, 3);
        $save['last_modified']  = date('Y-m-d H:i:s', time());
        $save['modified_by']    = $_SESSION['sess_user_id'];
        if (!is_error_message()) {
            $qunee_id = sql_save($save, 'plugin_qunee');
           	if ($qunee_id) {
                raise_message(1);
            } else {
                raise_message(2);
            }
        }
        header('Location: qunee.php?action=edit&id=' . (empty($qunee_id) ? get_nfilter_request_var('id') : $qunee_id));
    }
}

function ajax_graph(){
    $sql_where = '';
    $arr = array();
    if (get_request_var('host_id') != "0") {
        $sql_where = 'where gl.host_id = ' . get_request_var('host_id');
        $graphs = db_fetch_assoc(" SELECT gtg.local_graph_id ,gtg.title_cache,gl.host_id
            FROM graph_templates_graph AS gtg 
            INNER JOIN graph_local AS gl ON gl.id=gtg.local_graph_id 
			$sql_where order by gl.host_id desc");
        $arr[] = array('local_graph_id' => "0",'title_cache' => "无");
        if (cacti_sizeof($graphs)) {
            foreach($graphs as $graph) {
                $arr[] = array('local_graph_id' => $graph['local_graph_id'],'title_cache' => $graph['title_cache']);
            }
        }
    }
    print json_encode($arr);
}

function ajax_data(){
    $ret = array();
    //cacti_log("ajax_data = " . get_request_var('graphs'));
    if (!isempty_request_var("graphs")) {
        $arr_graphs = explode(",", get_request_var('graphs'));
        $graph_map = array();
        foreach($arr_graphs as $graph) {
            $tmp_graphs = explode("_", $graph);
            if(sizeof($tmp_graphs) == 4){
                $tmp_graphs[4] = "1"; // 如果没有传递告警，默认就是告警
            }
            // [0] =graph_id,[1] = node_id,[2] = host_id ,[3] = 0src_or_1dest,[4] = is_alarm
            $graph_map[$tmp_graphs[0]] = array($tmp_graphs[1],$tmp_graphs[2],$tmp_graphs[3],$tmp_graphs[4]); 
        }
        // 根据topoID去查询拓扑的信息，
        if (!isempty_request_var("topo_id")) {
            $topo = db_fetch_row_prepared("select * from plugin_qunee where id = ?",array(get_request_var('topo_id')));
        }
        $graph_ids = implode(array_keys($graph_map), ",");
        //cacti_log("封装得到graph_ids = " . $graph_ids);
        if (!isempty_request_var("line_num")) {
            $upper_limit = get_request_var("ewidth") / get_request_var("line_num"); // 通道容量分母
            //cacti_log("ewidth = ".get_request_var("ewidth").",line_num = ".get_request_var("line_num").",upper_limit=".$upper_limit);
        }
        if(!empty($graph_ids)){
            $local_datas = get_local_data($graph_ids);
            if (cacti_sizeof($local_datas)) {
                foreach($local_datas as $local_data) {
                    if(isempty_request_var("from")){ // 说明是从主拓扑进入的
                        $mod = empty($topo) ? 0.9 : $topo["thold"]/100;
                    }else{ // 从通道容量拓扑进入，固定阈值
                        $local_data["upper_limit"] = $upper_limit;
                        $mod = 0.3;
                    }
                    $ref_values = qunee_get_ref_value($local_data, time(),60,$mod,get_request_var("from"));
                    if (cacti_sizeof($ref_values) == 0) { // 数组里面没有数据
                        continue;
                    }
                    $ret[$local_data["local_graph_id"]] = $ref_values;
                }
            }
        }
        
    }
    print json_encode($ret);
}

function ajax_hosts(){
    $return = array();
    $total_rows = -1;
    $hosts = get_allowed_devices("", 'description', -1, $total_rows);
    if (cacti_sizeof($hosts)) {
        foreach($hosts as $host) {
            $return[] = array('label' => $host['description'], 'value' => $host['description'], 'id' => $host['id']);
        }
    }
    print json_encode($return);
}

function ajax_imgs(){
    $arr = array();
    $devices = db_fetch_assoc("select * from plugin_qunee_devices order by id");
    if (cacti_sizeof($devices)) {
        foreach($devices as $device) {
            $arr[] = array('path' => $device['path'],'name' => $device['name']);
        }
    }
    print json_encode($arr);
}

function ajax_emails(){
    $arr = array();
    $noties = db_fetch_assoc("select * from plugin_notification_lists order by id");
    $arr[] = array('id' => "0",'notiy_name' => "无");
    if (cacti_sizeof($noties)) {
        foreach($noties as $notiy) {
            $arr[] = array('id' => $notiy['id'],'notiy_name' => $notiy['name']);
        }
    }
    print json_encode($arr);
}

/* ------------------------
 The 'actions' function
 ------------------------ */
function form_actions() {
    global $qunee_actions;
    
    /* ================= input validation ================= */
    get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
    /* ==================================================== */
    
    /* if we are to save this form, instead of display it */
    if (isset_request_var('selected_items')) {
        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
        
        if ($selected_items != false) {
            if (get_nfilter_request_var('drp_action') == '1') { /* delete */
                db_execute('DELETE FROM plugin_qunee WHERE ' . array_to_sql_or($selected_items, 'id'));
                db_execute('DELETE FROM plugin_qunee_sub WHERE ' . array_to_sql_or($selected_items, 'topo_id'));
                db_execute('DELETE FROM plugin_qunee_alarm WHERE ' . array_to_sql_or($selected_items, 'topo_id'));
            }
        }
        
        header('Location: qunee.php?header=false');
        exit;
    }
    
    /* setup some variables */
    $qunee_list = ''; $i = 0;
    
    /* loop through each of the graphs selected on the previous page and get more info about them */
    foreach ($_POST as $var => $val) {
        if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
            /* ================= input validation ================= */
            input_validate_input_number($matches[1]);
            /* ==================================================== */
            
            $qunee_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_qunee WHERE id = ?', array($matches[1]))) . '</li>';
            $qunee_array[$i] = $matches[1];
            $i++;
        }
    }
    
    top_header();
    
    form_start('qunee.php');
    
    html_start_box($qunee_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');
    
    if (isset($qunee_array) && cacti_sizeof($qunee_array)) {
        if (get_nfilter_request_var('drp_action') == '1') { /* delete */
            print "<tr>
				<td class='textArea' class='odd'>
					<p>点击'继续'删除以下拓扑图</p>
					<div class='itemlist'><ul>$qunee_list</ul></div>
				</td>
			</tr>\n";
            
            $save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='删除拓扑图'>";
        }
    } else {
        raise_message(40);
        header('Location: qunee.php?header=false');
        exit;
    }
    
    print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($qunee_array) ? serialize($qunee_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";
			
			html_end_box();
			
			form_end();
			
			bottom_footer();
}

function import_topo(){
    if(!empty(get_request_var("save_component_site"))){ // 如果是保存方法，下面开始上传
        if (isset($_FILES["file"])) {
            $allowedExts = array("csv");
            $temp = explode(".", $_FILES["file"]["name"]);
            $extension = end($temp);
            if ($_FILES["file"]["type"] == "application/vnd.ms-excel" && in_array($extension, $allowedExts)){
                if ($_FILES["file"]["error"] > 0){
                    raise_message(2,"CSV上传错误",MESSAGE_LEVEL_ERROR);
                }else{
                    $handle = fopen($_FILES['file']['tmp_name'],'r');
                    setlocale(LC_ALL, 'zh_CN');
                    $line_number = 0;
                    $op = get_request_var("op");
                    $import_data_arr = array();
                    while(($value = fgetcsv($handle)) !== FALSE){
                        if($line_number == 0){
                            $line_number++;
                            continue;
                        }
                        $value = eval('return '.iconv('gbk','utf-8',var_export($value,true)).';');
                        if(sizeof($value) != 4){
                            raise_message(2,"拓扑设备导入列数量不正确",MESSAGE_LEVEL_ERROR);
                            header('Location: qunee.php?action=import'.(isempty_request_var("topo_id")?"":'&topo_id='.get_request_var("topo_id")));
                            exit();
                        }
                        $host_id = db_fetch_cell_prepared("select id from host where hostname = ? limit 1",array($value[1]));
                        $import_data_arr[] = array("name"=>$value[0],"ip"=>$value[1],"linkip"=>$value[2],"phy"=>$value[3],"host_id"=>$host_id);
                    }
                    fclose($handle);
                    $_SESSION['import_data'] = json_encode($import_data_arr);
                    header('Location: qunee.php?action=edit'.(isempty_request_var("topo_id")?"":'&id='.get_request_var("topo_id")));
                    exit();
                }
            }else{
                raise_message(2,"请上传CSV类型文件",MESSAGE_LEVEL_ERROR);
            }
            header('Location: host.php?action=import');
            exit();
        }
    }else{
        $form_array = array(
            'file' => array(
                'friendly_name' => "导入CSV",
                'description' => "从CSV导入拓扑设备",
                'size' => '50',
                'method' => 'file'
            ),
            'save_component_site' => array(
                'method' => 'hidden',
                'value' => '1'
            ),
            'action' => array(
                'method' => 'hidden',
                'value' => 'import'
            ),
            'topo_id' => array(
                'method' => 'hidden',
                'value' => get_request_var("topo_id","0")
            )
        );
        general_header();
        form_start('qunee.php', 'qunee_import',true);
        $buttons = array(
            array(
                'href'     => 'qunee.php?action=dl_tpl',
                'callback' => false,
                'title'    => "下载CSV模板",
                'class'    => 'fa fa-download'
            )
        );
        html_start_box("导入拓扑设备", '100%', '', '3', 'center', $buttons);
        draw_edit_form(
            array(
                'config' => array('no_form_tag' => true),
                'fields' => inject_form_variables($form_array)
            )
            );
        html_end_box(true, true);
        form_save_buttons(array(
            array('id' => 'btn_ret', 'value' =>"取消"),
            array('id' => 'btn_imp', 'value' =>"从导入中添加")
            //,array('id' => 'btn_edit', 'value' =>"从导入中修改"),
            //array('id' => 'btn_del', 'value' =>"从导入中删除")
        ),false);
        ?>
		<script type='text/javascript'>
		$(function() {
			$("input[id^='btn_']").click(function(){
				var value = $(this).attr("id").split("_")[1];
				if(value == "ret"){
					window.history.back();
				}else if(!checkFile()){
    				alert("请先选择要上传的文件！");
    				return;
				}else{
    				$("#qunee_import").append("<input type='hidden' name='op' value='"+value+"'>");
    				$("#qunee_import").submit();
				}
			});
		});
		function checkFile(){
			if($("#file").val() != ""){
				return true;
			}
			return false;
		}
		</script>
		<?php
        bottom_footer();
    }
}

/**
 * 进入添加和修改拓扑图
 */
function qunee_sub_edit(){
    global $config;
    $fields_qunee_sub = array(
        'id' => array(
            'method' => 'hidden_zero',
            'value' => '|arg1:id|'
        ),
        'topo_id' => array(
            'method' => 'hidden',
            'value' => get_request_var("topo_id")
        ),
        'node_id' => array(
            'method' => 'hidden',
            'value' => get_request_var("node_id")
        ),
        'node_src_id' => array(
            'method' => 'hidden',
            'value' => get_request_var("node_src_id")
        ),
        'node_dest_id' => array(
            'method' => 'hidden',
            'value' => get_request_var("node_dest_id")
        ),
        'ewidth' => array(
            'method' => 'hidden',
            'value' => get_request_var("ewidth") * 1000000000
        ),
        'save_component_site' => array(
            'method' => 'hidden',
            'value' => '1'
        )
    );
    /* ================= input validation ================= */
    get_filter_request_var('id');
    /* ==================================================== */
    // 保存通道流量拓扑完成
    $save = array();
    // 先看是否之前保存过
    $save = db_fetch_row_prepared("select * from plugin_qunee_sub where topo_id = ? and node_id = ? limit 1"
                ,array(get_request_var("topo_id"),get_request_var("node_id")));
    if(empty($save)){
        $save['last_modified']  = date('Y-m-d H:i:s', time());
        $save['topo_id']  = get_request_var("topo_id");
        $save['node_id']  = get_request_var("node_id");
        $save['node_src_id']  = get_request_var("node_src_id");
        $save['node_dest_id']  = get_request_var("node_dest_id");
        $save['ewidth']  = get_request_var("ewidth") * 1000000000;
        $qunee_sub_id = sql_save($save, 'plugin_qunee_sub');
        $fields_qunee_sub["id"] = array(
            'method' => 'hidden_zero',
            'value' => $qunee_sub_id,
        );
    }else{
        $fields_qunee_sub["id"] = array(
            'method' => 'hidden_zero',
            'value' => $save["id"],
        );
    }
    form_start('qunee.php', 'qunee_sub');
    
    html_start_box("通道流量", '100%', true, '3', 'center', '');
    
    draw_edit_form(
        array(
            'config' => array('no_form_tag' => true),
            'fields' => inject_form_variables($fields_qunee_sub, array())
        )
    );
    include_once($config['base_path'] . '/plugins/qunee/include/qunee_sub_html.php');
    
    html_end_box(true, true);
    
    //form_save_button('qunee.php', 'return');
}

function qunee_sub_save(){
    if (isset_request_var('save_component_site')) {
        $save = array();
        $save['id']             = get_request_var('id');
        $save['topo_id']        = get_request_var('topo_id');//form_input_validate(get_request_var('name'), 'name', '', false, 3);
        $save['node_id']        = get_request_var('node_id');//form_input_validate(get_request_var('emails'), 'emails', '', true, 3);
        $save['node_src_id']    = get_request_var('node_src_id');//form_input_validate(get_request_var('thold'), 'thold', '', true, 3);
        $save['node_dest_id']   = get_request_var('node_dest_id');//form_input_validate(get_request_var('graph_ids'), 'graph_ids', '', true, 3);
        $save['graphs']         = get_request_var('graphs');//form_input_validate(get_request_var('graph_ids'), 'graph_ids', '', true, 3);
        $save['ewidth']         = get_request_var('ewidth');//form_input_validate(get_request_var('topo'), 'topo', '', true, 3);
        $save['topo']           = get_request_var('topo');
        $save['line_num']       = get_request_var('line_num');
        $save['last_modified']  = date('Y-m-d H:i:s', time());
        if (!is_error_message()) {
            $qunee_id = sql_save($save, 'plugin_qunee_sub');
            if ($qunee_id) {
                raise_message(1);
            } else {
                raise_message(2);
            }
        }
        header('Location: qunee.php?action=sub&id=' . (empty($qunee_id) ? get_nfilter_request_var('id') : $qunee_id)
            ."&node_id=".get_request_var('node_id')."&topo_id=".get_request_var('topo_id')
            ."&node_src_id=".get_request_var('node_src_id')."&node_dest_id=".get_request_var('node_dest_id')
            ."&ewidth=".(get_request_var('ewidth')/1000000000)
            );
    }
}