<?php
if(empty($save["topo"])){ // 说明之前页面中没有
    $src_host = db_fetch_row_prepared("select id,description from host where id = ?",array(get_request_var("node_src_id")));
    $dest_host = db_fetch_row_prepared("select id,description from host where id = ?",array(get_request_var("node_dest_id")));
}else{
    
}
?>
<div class="qtools" id="qtools">
	<button type='button' action="line"><i class='fa fa-arrow-right'></i>添加连线</button>
	<button type='button' action="defa" style="display: none"><i class='fa fa-arrow-right'></i>取消连线</button>
	<button type='button' action="del"><i class='fa fa-minus'></i><span>删除</span></button>
	<button type='button' action="bg"><i class='fa fa-th'></i><span>显示背景</span></button>
	
	<button type='button' class="btn-r" action="save_sub"><i class='fa fa-save'></i><span>保存</span></button>
</div>
<div class="qpanel" id="canvas">
    <!-- 元素属性栏 -->
    <div class="qpros" id="qpros">
    	<div class="qpros-bannel">
			<span class="qpros-title" id="qpros_title">&nbsp;</span>
			<span class="qpros-clobtn">
				<i class="fa fa-times"></i>
			</span>
		</div>
		<div class="qpros-content" id="qpros_content">
    	</div>
    </div>
</div>
<script>
var graph,selected_graphs = [],loadGraphTimer,line_num = 0;
$(function(){
	// 提供弹出框
	$(".dmsg").remove();
   	$(document.body).append("<div id='message' class='dmsg' style='display:none;'><span id='spanmessage'></span></div>");
	
	Q.addCSSRule(".maximize",
	"position: fixed;top: 0px;bottom: 0px;right: 0px;left: 0px;z-index: 1030;height: auto !important;");
	
	graph = new Q.Graph('canvas');
    graph.callLater(function(){
		graph.originAtCenter = false;
		graph.editable = false;
		graph.enableWheelZoom=false;
	});

    $("#qtools button").each(function(index, ele) {
		var action = $(ele).attr("action");
		if(action){
			$(ele).click(function() {
				doAction($(ele),graph,action);
			});
		}
	});

    graph.onElementCreated = function (element, evt) {
        if (element instanceof Q.Edge) { // 如果是连线，需要判断两端的设备是否关联了设备
        	element.name = ""; // 如果是连线，不需要线上的名字
        	element.setStyle(Q.Styles.ARROW_TO, false);
        }
    }

	graph.addCustomInteraction({
		onclick: function(evt, graph){
			var element = graph.getElementByMouseEvent(evt);
			if(!element){ // 没有点击元素对象，关闭右侧弹出
				closeQproPanel();
			}else if(element instanceof Q.Edge){
    			$("#qpros").slideShow({ // 显示点击元素的弹出框
					node:element,
					afterApplyNode:afterApplyNode
				});
			}
		}
	});

	$(".qpros-clobtn").click(closeQproPanel);
	// 加载从上面也能引入过来的两个设备
	<?php
	if(empty($save["topo"])){ // 说明之前页面中没有
	    $src_host = db_fetch_row_prepared("select id,description from host where id = ?",array(get_request_var("node_src_id")));
	    $dest_host = db_fetch_row_prepared("select id,description from host where id = ?",array(get_request_var("node_dest_id")));
        if(!empty($src_host) && !empty($dest_host)){
        ?>
        var node1Name = "<?php print $src_host["description"]?>";
        var node2Name = "<?php print $dest_host["description"]?>";
        var node1 = graph.createNode(node1Name,graph.viewportBounds.cx - 500,graph.viewportBounds.cy);
    	node1.image = "./include/imgs/server.png";
    	node1.user = {};
    	node1.user["host"] = "<?php print $src_host["id"]?>";
        var node2 = graph.createNode(node2Name,graph.viewportBounds.cx + 500,graph.viewportBounds.cy+1);
    	node2.image = "./include/imgs/server.png";
    	node2.user = {};
    	node2.user["host"] = "<?php print $dest_host["id"]?>";
        <?php    
        }
	}else{
	    ?>
	    var topo = base64_decode("<?php print $save["topo"]?>");
		var json = JSON.parse(topo);
		graph.parseJSON(json);
	    <?php 
	}
    ?>
	loadRealGraph();
	if(loadGraphTimer){
		clearInterval(loadGraphTimer);
	}
	loadGraphTimer = setInterval(loadRealGraph,11000);
});

// 点击确定，节点的数据赋值完成后，回调
function afterApplyNode(btn,node_type,node){
	if(node.type == "Q.Edge"){
		node.name = "获取中...";
	}
	forEachGraph();
}

// 当下拉框创建成功
function onSelectMenuCreate($select,node_type,node){
	if($select.attr("name") == "srcg" || $select.attr("name") == "destg"){ // 源端和对端下拉框创建成功后，选择图形
		$('#' + $select.attr("id") + '-menu').css('max-height', '250px');
		$.ajax({
			dataType:"json",
			url:$select.attr("url")+"&host_id=" + ($select.attr("name") == "srcg" ? getUserHost(node.from) : getUserHost(node.to)),
			success: function(data){
				if(data && data.length > 0){
					$select.empty();
					var ck_value = getUserGraph(node,($select.attr("name") == "srcg" ? true : false));
					$.each(data,function(di,dv){
						if(inSelectGraphs(selected_graphs,dv.local_graph_id) >= 0 && ck_value != dv.local_graph_id){ // 说明该图形已经被选中过，不能在选择了
							return;
						}
						var sel = ((ck_value == dv.local_graph_id) ? "selected='selected'" : '');
						$select.append("<option value='"+dv.local_graph_id+"' "+sel+">"+dv.title_cache+"</option>");
					});
					$select.selectmenu("refresh");
				}
			}
		});
	}
}

// 当切换下拉框
function onSelectMenuChange($select){
	
}

function loadRealGraph(){
	if(basename($(location).attr('pathname')) != "qunee.php") return;
	forEachGraph(); // 循环页面中的数据
	if(selected_graphs.length <= 0){
		return;
	}
	$.ajax({
		dataType:"json",
		url:"qunee.php?action=ajax_data&from=sub"+"&graphs="+selected_graphs.join(",")
				+"&line_num="+line_num+"&ewidth="+$("#ewidth").val(),
		success: function(data){
			if(data && !(typeof data == 'object' && data.constructor == Array)){ // 如果是数组说明格式错误了
				$.each(selected_graphs,function(idx,selected_graph){
					var arr = selected_graph.split("_");
					var res = data[arr[0]]; // 该图形的请求输出结果
					if(res && res.traffic_in && res.traffic_in != '0k' && res.traffic_out && res.traffic_out != '0k'){
						var node_id = arr[1];
						var direct = arr[2];
						var node = graph.getElement(node_id);
						// 1、设置通道占比
						if(direct == 0){ // 计算源的通道容量
    						var ib = parseFloat(res.traffic_in_byte || 0),ob = parseFloat(res.traffic_out_byte || 0);
    						var cap_t = ib > ob ? ("入："+(res.traffic_in || "0")) : ("出：" + (res.traffic_out || "0"));
    						node.name = cap_t + "，容：" + res.cap + "%"; // 通道容量
    						// 2、判断告警
    						if(res.alarm_level && res.alarm_level == 2){
    							node.setStyle("edge.color","#FF0000");
    						}else if(res.alarm_level && res.alarm_level == 1){
    							node.setStyle("edge.color","#FFFF00");
    						}else{
    							node.setStyle("edge.color","#555");
    						}
						}
					}
				})
			}
		}
	});
}

// 获取页面中所有设备关联的设备ID--为了完成禁止重复关联同一个设备功能使用
function forEachGraph(){
	selected_graphs = [],line_num = 0; // 清空之前的数据
	if(!graph) return;
	graph.forEach(function(node){
		if(node.type == "Q.Edge"){ // 获取线两端的label，并且进行ajax请求赋值
			var srcg = getUserGraph(node,true);
			var destg = getUserGraph(node);
			var alarm = getUserPro(node,"alarm","1");
			if(srcg != "0") {
				// 需要知道选择的图形id，对应的是哪个线，并且知道这个图形是在源端还是在目的端，源=0，目的=1
				selected_graphs.push(srcg + "_" + node.id + "_0_" + alarm);
    			line_num++;
			};
			if(destg != "0") {
				selected_graphs.push(destg + "_" + node.id + "_1_" + alarm);
			};
		}
	}, graph);
}

// 关闭弹出属性框
function closeQproPanel(){
	$("#qpros").hide("slide",{direction:'right'},500);
}

// base64解密
function base64_decode(string) {
	return decodeURIComponent(escape(atob(unescape(string))));
}

</script>
<?php
