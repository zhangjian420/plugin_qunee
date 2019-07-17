<?php
$guest_account=true;

chdir('../../');
include_once('./include/auth.php');

$qunee_actions = array(
    1 => __('Delete')
);

$fields_qunee_edit = array(
    'topo' => array(
        'method' => 'hidden',
        'value' => '|arg1:topo|'
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

set_default_action("list");

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
	case 'list':
	    general_header();
	    qunee_list();
	    bottom_footer();
	    break;
	case 'ajax_host':
	    $sql_where = '';
	    if (get_request_var('site_id') > 0) {
	        $sql_where = 'site_id = ' . get_request_var('site_id');
	    }
	    
	    get_allowed_ajax_hosts(false, 'applyFilter', $sql_where);
	    break;
	default:
	    break;
}

/**
 * 列表显示所有的气象图
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
    
    html_start_box("气象图", '100%', '', '3', 'center', 'qunee.php?action=edit');
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
						气象图
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
	
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_qunee $sql_where");
	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;
	
	$qunee_list = db_fetch_assoc("SELECT * FROM plugin_qunee 
		$sql_where
		$sql_order
		$sql_limit");
	
	$nav = html_nav_bar('qunee.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, "气象图", 'page', 'main');
	
	form_start('qunee.php', 'chk');
	
	print $nav;
	
	html_start_box('', '100%', '', '3', 'center', '');
	
	$display_text = array(
	    'name'    => array('display' => "名称", 'align' => 'left',  'sort' => 'ASC', 'tip' => "气象图名称"),
	    'id'      => array('display' => __('ID'),        'align' => 'right', 'sort' => 'ASC', 'tip' => "气象图ID"),
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
 * 进入添加和修改气象图
 */
function qunee_edit(){
    global $config,$fields_qunee_edit;
    /* ================= input validation ================= */
    get_filter_request_var('id');
    /* ==================================================== */
    if (!isempty_request_var('id')) {
        $qunee = db_fetch_row_prepared('SELECT * FROM plugin_qunee WHERE id = ?', array(get_request_var('id')));
        $header_label = __('气象图 [编辑: %s]', html_escape($qunee['name']));
    } else {
        $header_label = __('气象图 [新建]');
    }
    
    form_start('qunee.php', 'qunee');
    
    html_start_box($header_label, '100%', true, '3', 'center', '');
    
    draw_edit_form(
        array(
            'config' => array('no_form_tag' => true),
            'fields' => inject_form_variables($fields_qunee_edit, (isset($qunee) ? $qunee : array()))
        )
    );
    
    include_once($config['base_path'] . '/plugins/qunee/include/qunee_html.php');
    
    html_end_box(true, true);
    
    //form_save_button('qunee.php', 'return');
}

/**
 * 点击添加或者修改按钮
 */
function qunee_save(){
    if (isset_request_var('save_component_site')) {
        cacti_log("进来方法");
        $save['id']           = get_filter_request_var('id');
        $save['name']         = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
        $save['topo']     = form_input_validate(get_nfilter_request_var('topo'), 'topo', '', true, 3);
        $save['last_modified'] = date('Y-m-d H:i:s', time());
        $save['modified_by']   = $_SESSION['sess_user_id'];
        cacti_log("参数成功");
        if (!is_error_message()) {
            $qunee_id = sql_save($save, 'plugin_qunee');
            cacti_log("保存成功");
            if ($qunee_id) {
                raise_message(1);
            } else {
                raise_message(2);
            }
        }
        header('Location: qunee.php?action=edit&id=' . (empty($qunee_id) ? get_nfilter_request_var('id') : $qunee_id));
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
                db_execute('DELETE FROM plugin_qunee WHERE ' . array_to_sql_or($selected_items, 'id'));
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
					<p>点击'继续'删除以下气象图</p>
					<div class='itemlist'><ul>$qunee_list</ul></div>
				</td>
			</tr>\n";
            
            $save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue') . "' title='删除气象图'>";
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