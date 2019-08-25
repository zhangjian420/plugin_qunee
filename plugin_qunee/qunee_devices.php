<?php
$guest_account=true;

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/plugins/qunee/qunee_functions.php');

$qunee_actions = array(
    1 => __('Delete')
);

/* file: sites.php, action: edit */
$fields_qunee_devices_edit = array(
    'name' => array(
        'method' => 'textbox',
        'friendly_name' => "设备图片名称",
        'description' => "设备图片名称，名称唯一不能重复",
        'value' => '|arg1:name|',
        'size' => '50',
        'default' => "新服务器",
        'max_length' => '100'
    ),
    'path' => array(
        'friendly_name' => "上传图片",
        'description' => "上传设备图片",
        'size' => '50',
        'method' => 'file'
    ),
    'notes' => array(
        'method' => 'textarea',
        'friendly_name' => "设备图片描述",
        'textarea_rows' => '3',
        'textarea_cols' => '70',
        'description' => "设备图片描述",
        'value' => '|arg1:notes|',
        'max_length' => '255',
        'placeholder' => "请输入设备图片描述",
        'class' => 'textAreaNotes'
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
        qunee_devices_edit();
        bottom_footer();
        break;
    case 'save':
        qunee_devices_save();
        break;
	case 'actions':
	    form_actions();
	    break;
	case 'img':
	    device_img();
	    break;
	default:
	    general_header();
	    qunee_devices_list();
	    bottom_footer();
	    break;
}


/**
 * 进入编辑页面
 */
function qunee_devices_edit(){
    global $fields_qunee_devices_edit;
    /* ================= input validation ================= */
    get_filter_request_var('id');
    /* ==================================================== */
    if (!isempty_request_var('id')) {
        $qunee_devices = db_fetch_row_prepared('SELECT * FROM plugin_qunee_devices WHERE id = ?', array(get_request_var('id')));
        $header_label = __('拓扑图设备 [编辑: %s]', html_escape($qunee_devices['name']));
    } else {
        $header_label = __('拓扑图设备 [新建]');
    }
    
    form_start('qunee_devices.php', 'qunee_devices',true);
    
    html_start_box($header_label, '100%', true, '3', 'center', '');
    
    draw_edit_form(
        array(
            'config' => array('no_form_tag' => true),
            'fields' => inject_form_variables($fields_qunee_devices_edit, (isset($qunee_devices) ? $qunee_devices : array()))
        )
    );
    
    html_end_box(true, true);
    
    form_save_button('qunee_devices.php', 'return',"id",false);
}

/**
 * 点击修改或者添加按钮
 */
function qunee_devices_save(){
    global $config;
    if (isset_request_var('save_component_site')) {
//         cacti_log("请求数据id=".get_request_var('id')
//             .",name=".get_request_var('name')
//             .",notes=".get_request_var('notes')
//             , false, 'SYSTEM');
        $save = array();
        $save['id']             = get_request_var('id');
        $save['name']           = get_request_var('name');//form_input_validate(get_request_var('name'), 'name', '', false, 3);
        $save['notes']          = get_request_var('notes');//form_input_validate(get_request_var('emails'), 'emails', '', true, 3);
        $save['last_modified']  = date('Y-m-d H:i:s', time());
        $save['modified_by']    = $_SESSION['sess_user_id'];
        if (isset($_FILES["path"]) && !empty($_FILES["path"]["name"])) {
            $allowedExts = array("gif", "jpeg", "jpg", "png");
            $temp = explode(".", $_FILES["path"]["name"]);
            $extension = end($temp);
            if ((($_FILES["path"]["type"] == "image/gif")
                || ($_FILES["path"]["type"] == "image/jpeg")
                || ($_FILES["path"]["type"] == "image/jpg")
                || ($_FILES["path"]["type"] == "image/png"))
                && in_array($extension, $allowedExts)){
                if ($_FILES["path"]["error"] > 0){
                    raise_message(2,"服务器图片上传错误",MESSAGE_LEVEL_ERROR);
                }else{
                    $ext = pathinfo($_FILES["path"]["name"],PATHINFO_EXTENSION);
                    $now = time();
                    $file_path_src = "/plugins/qunee/upload/src/" . $now . "." . $ext;
                    $file_path_dest = "/plugins/qunee/upload/" . $now . "." . $ext;
                    move_uploaded_file($_FILES["path"]["tmp_name"], $config['base_path'] . $file_path_src);
                    // 等比例缩放图片
                    imgThrum($config['base_path'] . $file_path_src,$config['base_path'] . $file_path_dest,56);
                    $save["path"] = "./upload/" . $now . "." . $ext;
                    $save["src_path"] = $file_path_src;
                    $save["abs_path"] = $file_path_dest;
                    $save["file_name"] = $_FILES["path"]["name"];
                }
            }else{
                raise_message(2,"请上传图片类型文件",MESSAGE_LEVEL_ERROR);
            }
        }
        if (!is_error_message()) {
            $qunee_devices_id = sql_save($save, 'plugin_qunee_devices');
            if ($qunee_devices_id) {
                raise_message(1);
            } else {
                raise_message(2);
            }
        }
        header('Location: qunee_devices.php');
    }
}

function qunee_devices_list(){
    global $qunee_actions,$item_rows,$config;
    
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
    
    validate_store_request_vars($filters, 'sess_qunee_devices');
    /* ================= input validation ================= */
    
    /* if the number of rows is -1, set it to the default */
    if (get_request_var('rows') == -1) {
        $rows = read_config_option('num_rows_table');
    } else {
        $rows = get_request_var('rows');
    }
    
    html_start_box("设备图片", '100%', '', '3', 'center', 'qunee_devices.php?action=edit');
    ?>
    <tr class='even'>
		<td>
			<form id='form_qunee_devices' action='qunee_devices.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						设备图片
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
				strURL  = 'qunee_devices.php?header=false';
				strURL += '&filter='+$('#filter').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'qunee_devices.php?clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#form_qunee_devices').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php
	html_end_box();
	
	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
	    $sql_where = "WHERE (name LIKE '%" . get_request_var('filter') . "%' or notes like '%" . get_request_var('filter') . "%')";
	} else {
	    $sql_where = '';
	}
	
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_qunee_devices $sql_where");
	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	
	$qunee_devices_list = db_fetch_assoc("SELECT * FROM plugin_qunee_devices 
		$sql_where
		$sql_order
		$sql_limit");
	
	$nav = html_nav_bar('qunee_devices.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, "设备图片", 'page', 'main');
	
	form_start('qunee_devices.php', 'chk');
	
	print $nav;
	
	html_start_box('', '100%', '', '3', 'center', '');
	
	$display_text = array(
	    'name'    => array('display' => "设备图片名称", 'align' => 'left',  'sort' => 'ASC', 'tip' => "设备图片名称"),
	    'id'      => array('display' => __('ID'),        'align' => 'right', 'sort' => 'ASC', 'tip' => "ID"),
	    'path'      => array('display' => "图片链接",        'align' => 'right', 'sort' => 'ASC', 'tip' => "图片链接"),
	    'last_modified' => array('display' => __('Last Edited'), 'align' => 'right', 'sort' => 'ASC', 'tip' => "最后编辑时间"),
	    'modified_by' => array('display' => __('Edited By'), 'align' => 'right', 'sort' => 'ASC', 'tip' => "最后编辑人"),
	);
	
	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);
	
	if (cacti_sizeof($qunee_devices_list)) {
	    foreach ($qunee_devices_list as $qunee_device) {
	        $img_url = html_escape($config['url_path'] . 'plugins/qunee/qunee_devices.php?action=img&id='.$qunee_device['id']);
	        form_alternate_row('line' . $qunee_device['id'], true);
	        form_selectable_cell(filter_value($qunee_device['name'], get_request_var('filter'), 'qunee_devices.php?action=edit&id=' . $qunee_device['id']), $qunee_device['id']);
	        form_selectable_cell($qunee_device['id'], $qunee_device['id'], '', 'right');
	        form_selectable_cell('<a class="linkEditMain" target="_blank" href="'.$img_url.'">' . $qunee_device['path'] . '</a>', $qunee_device['id'], '', 'right');
	        form_selectable_cell(substr($qunee_device['last_modified'],0,16), $qunee_device['id'], '', 'right');
	        form_selectable_cell(get_username($qunee_device['modified_by']), $qunee_device['id'], '', 'right');
	        form_checkbox_cell($qunee_device['name'], $qunee_device['id']);
	        form_end_row();
	    }
	} else {
	    print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . "没有数据" . "</em></td></tr>\n";
	}
	
	html_end_box(false);
	
	if (cacti_sizeof($qunee_devices_list)) {
	    print $nav;
	}
	
	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($qunee_actions);
	
	form_end();
}

function device_img(){
    global $config;
    $qunee_devices = db_fetch_row_prepared('SELECT * FROM plugin_qunee_devices WHERE id = ?', array(get_request_var('id')));
    $path = $qunee_devices["src_path"];
    $pathinfo = pathinfo($path);
    $path_src = $config['base_path'] . $path;
    if(file_exists($path_src)){
        $img = file_get_contents($path_src,true);
        header("Content-Type: image/".$pathinfo["extension"].";");
        echo $img;
        exit;
    }
    
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
                db_execute('DELETE FROM plugin_qunee_devices WHERE ' . array_to_sql_or($selected_items, 'id'));
            }
        }
        
        header('Location: qunee_devices.php?header=false');
        exit;
    }
    
    /* setup some variables */
    $qunee_devices_list = ''; $i = 0;
    
    /* loop through each of the graphs selected on the previous page and get more info about them */
    foreach ($_POST as $var => $val) {
        if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
            /* ================= input validation ================= */
            input_validate_input_number($matches[1]);
            /* ==================================================== */
            
            $qunee_devices_list .= '<li>' . html_escape(db_fetch_cell_prepared('SELECT name FROM plugin_qunee_devices WHERE id = ?', array($matches[1]))) . '</li>';
            $qunee_devices_array[$i] = $matches[1];
            $i++;
        }
    }
    
    top_header();
    
    form_start('qunee_devices.php');
    
    html_start_box($qunee_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');
    
    if (isset($qunee_devices_array) && cacti_sizeof($qunee_devices_array)) {
        if (get_nfilter_request_var('drp_action') == '1') { /* delete */
            print "<tr>
				<td class='textArea' class='odd'>
					<p>点击'继续'删除以下设备图形</p>
					<div class='itemlist'><ul>$qunee_devices_list</ul></div>
				</td>
			</tr>\n";
            
            $save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='删除设备图片'>";
        }
    } else {
        raise_message(40);
        header('Location: qunee_devices.php?header=false');
        exit;
    }
    
    print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($qunee_devices_array) ? serialize($qunee_devices_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>\n";
			
			html_end_box();
			
			form_end();
			
			bottom_footer();
}