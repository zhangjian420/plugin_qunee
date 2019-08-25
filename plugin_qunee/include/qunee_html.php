<?php
?>
<div class="qtools" id="qtools">
	<button type='button' action="cloud"><i class='fa fa-cloud'></i>添加子网</button>
	<button type='button' action="new"><i class='fa fa-desktop'></i>添加设备</button>
	<button type='button' action="text"><i class='fa fa-font'></i>添加文字</button>
	<button type='button' action="line"><i class='fa fa-arrow-right'></i>添加连线</button>
	<button type='button' action="defa" style="display: none"><i class='fa fa-arrow-right'></i>取消连线</button>
	<button type='button' action="del"><i class='fa fa-minus'></i><span>删除</span></button>
	<button type='button' action="bg"><i class='fa fa-th'></i><span>显示背景</span></button>
	
	<button type='button' class="btn-r" action="save"><i class='fa fa-save'></i><span>保存</span></button>
	<button type='button' class="btn-r" action="import"><i class='fa fa-upload'></i><span>导入设备</span></button>
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
<script type='text/javascript'>
var graph,selected_host_ids = [],selected_graphs = [],sub_nodes = [];
var loadGraphTimer;
$(function(){
	// 提供弹出框
	$(".dmsg").remove();
   	$(document.body).append("<div id='message' class='dmsg' style='display:none;'><span id='spanmessage'></span></div>");
	
	Q.addCSSRule(".maximize",
	"position: fixed;top: 0px;bottom: 0px;right: 0px;left: 0px;z-index: 1030;height: auto !important;");

	resizeCanvas();
	$(window).resize(function(){
    	resizeCanvas();
	});
	
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
			var from = element.from,to = element.to;
			if(from && from.type == "Q.Node" && !from.enableSubNetwork && getUserHost(from) == "0"){
				message("连线开始设备必须要先关联设备！");
				graph.removeElement(element);
			}else if(to && to.type == "Q.Node" && !to.enableSubNetwork && getUserHost(to) == "0"){ // 必须是节点对象才可以提示判断
				message("连线结束设备必须要先关联设备！");
				graph.removeElement(element);
			}
        }
    }

	graph.addCustomInteraction({
		onclick: function(evt, graph){
			var element = graph.getElementByMouseEvent(evt);
			if(!element){ // 没有点击元素对象，关闭右侧弹出
				closeQproPanel();
			}else{
    			$("#qpros").slideShow({ // 显示点击元素的弹出框
					node:element,
					afterApplyNode:afterApplyNode
				});
			}
		},
		ondblclick:function(evt, graph){
			var element = graph.getElementByMouseEvent(evt);
			if (element instanceof Q.Edge) { // 如果是连线
				var ewidth = getUserPro(element,"ewidth");
				if(ewidth == "0"){ // 说明还没有设置带宽
					message("请先设置链路宽带，并且点击保存");
					return;
				}
				window.open("qunee.php?action=sub&node_id="
						+element.id+"&topo_id="+$("#id").val()+"&node_src_id="
						+getUserHost(element.from)+"&node_dest_id="+getUserHost(element.to)
						+"&ewidth="+(ewidth));
			}
		}
	});

	// 如果进入的是编辑页面，需要显示拓扑
	var id = $("#id").val();
	if(id != "0" && id != 0 && $("#topo").val() != ""){ // 说明是修改
		var topo = base64_decode($("#topo").val());
		var json = JSON.parse(topo);
		graph.parseJSON(json);
	}
	$(".qpros-clobtn").click(closeQproPanel);
	// 加载导入进来的数据
    <?php
    if(!empty($_SESSION["import_data"])){
    ?>
	var import_data = <?php print $_SESSION["import_data"]?>;
	$.each(import_data,function(ii,iv){
		var node = graph.createNode(iv.name,graph.viewportBounds.cx-500,graph.viewportBounds.cy-200);
		node.user = {};
		node.user.host = iv.host_id || "0";
		node.user.linkip = iv.linkip;
		node.user.phy = iv.phy;
		node.image = "./include/imgs/server.png";
	});
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
	if(node.type == "Q.Edge"){ // 如果给线赋值完成
		var uis = node.bindingUIs;
		$.each(uis,function(ix,ui){
			node.removeUI(ui);
		});
		var srcg = getUserGraph(node,true);
		var destg = getUserGraph(node);
		if(srcg != "0"){ // 选择了源图形
			var label1 = new Q.LabelUI();
			label1.position = Q.Position.LEFT_BOTTOM;
			label1.anchorPosition = Q.Position.LEFT_BOTTOM;
			label1.offsetY = -5;
			label1.padding = 5;
			label1.fontSize = node.getStyle("label.font.size");
			label1.alignPosition = Q.Position.LEFT_MIDDLE;
			node.addUI(label1,[{
				property : "srcLabelName",
				propertyType : Q.Consts.PROPERTY_TYPE_CLIENT,
				bindingProperty : "data"
			}]);
			node.set("srcLabelName","获取中...");
		}
		if(destg != "0"){
			var label1 = new Q.LabelUI();
			label1.position = Q.Position.RIGHT_BOTTOM;
			label1.anchorPosition = Q.Position.RIGHT_BOTTOM;
			label1.offsetY = -5;
			label1.padding = 5;
			label1.fontSize = node.getStyle("label.font.size");
			label1.alignPosition = Q.Position.LEFT_MIDDLE;
			node.addUI(label1,[{
				property : "destLabelName",
				propertyType : Q.Consts.PROPERTY_TYPE_CLIENT,
				bindingProperty : "data"
			}]);	
			node.set("destLabelName","获取中...");
		}
	}

	forEachGraph();
}

// 当下拉框创建成功
function onSelectMenuCreate($select,node_type,node){
	if($select.attr("name") == "host"){
		$('#' + $select.attr("id") + '-menu').css('max-height', '250px');
		$.ajax({
			dataType:"json",
			url:$select.attr("url"),
			success: function(data){
				if(data && data.length > 0){
					$select.empty();
					var ck_value = getUserHost(node);
					$.each(data,function(di,dv){
						if($.inArray(dv.id,selected_host_ids) >= 0 && ck_value != dv.id){ // 说明该设备已经被选中过，不能在选择了
							return;
						}
						var sel = ((ck_value == dv.id) ? "selected='selected'" : '');	
						$select.append("<option value='"+dv.id+"' "+sel+">"+dv.value+"</option>");
					});
					$select.selectmenu("refresh");
				}
			}
		});
	}else if($select.attr("name") == "srcg" || $select.attr("name") == "destg"){ // 源端和对端下拉框创建成功后，选择图形
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
	}else if($select.attr("name") == "sub"){
		$select.empty();
		var ck_value = (node.parent ? node.parent.id : 0);
		$select.append("<option value='0'>无</option>");
		$.each(sub_nodes,function(di,sub_node){
			var sel = ((ck_value == sub_node.id) ? "selected='selected'" : '');
			$select.append("<option value='"+sub_node.id+"' "+sel+">"+sub_node.name+"</option>");
		});
		$select.selectmenu("refresh");
	}else if($select.attr("name") == "image"){
		$.ajax({
			dataType:"json",
			url:$select.attr("url"),
			success: function(data){
				if(data && data.length > 0){
					$select.empty();
					var ck_value = node.image;
					$.each(data,function(di,dv){
						var sel = ((ck_value == dv.path) ? "selected='selected'" : '');
						$select.append("<option value='"+dv.path+"' "+sel+">"+dv.name+"</option>");
					});
					$select.selectmenu("refresh");
				}
			}
		});
	}
}

// 当切换下拉框
function onSelectMenuChange($select){
	if($select.attr("name") == "host"){
    	var selected = $select.find("option:selected");
    	var text = selected.text();
    	if(selected.val() == "0"){
    		text = "新设备";
    	}
    	$select.parentsUntil("#qpros_frm").find("input[name='name']").val(text);
	}
}

function loadRealGraph(){
	if(basename($(location).attr('pathname')) != "qunee.php") return;
	forEachGraph(); // 循环页面中的数据
	if(selected_graphs.length <= 0){
		return;
	}
	$.ajax({
		dataType:"json",
		url:"qunee.php?action=ajax_data&topo_id="+$("#id").val()+"&graphs=" + selected_graphs.join(","),
		success: function(data){
			if(data && !(typeof data == 'object' && data.constructor == Array)){ // 如果是数组说明格式错误了
				$.each(selected_graphs,function(idx,selected_graph){
					var arr = selected_graph.split("_");
					var res = data[arr[0]]; // 该图形的请求输出结果
					if(res && res.traffic_in && res.traffic_in != '0k' && res.traffic_out && res.traffic_out != '0k'){
						var label = "入："+(res.traffic_in || "0")+"\n出：" + (res.traffic_out || "0");
						var node_id = arr[1];
						var direct = arr[2];
						var node = graph.getElement(node_id);
						// 1、设置流量值
						if(direct == 1){
							node.set("destLabelName",label);
						}else{
							// 入口流量时，需要计算通道容量占比
							node.name = res.cap + "%"; // 通道容量
							node.set("srcLabelName",label);
						}
						// 2、判断告警
						if(res.alarm_level && res.alarm_level == 2){
							node.setStyle("edge.color","#FF0000");
						}else if(res.alarm_level && res.alarm_level == 1){
							node.setStyle("edge.color","#FFFF00");
						}else{
							node.setStyle("edge.color","#555");
						}
					}
				})
			}
		}
	});
}

// 获取页面中所有设备关联的设备ID--为了完成禁止重复关联同一个设备功能使用
function forEachGraph(){
	selected_host_ids = [], selected_graphs = [],sub_nodes = []; // 清空之前的数据
	if(!graph) return;
	graph.forEach(function(node){
		if(node.type == "Q.Node" && getUserHost(node) != "0"){
			selected_host_ids.push(getUserHost(node));
		}
		if(node.type == "Q.Node" && node.enableSubNetwork){ // 是一个子网
			sub_nodes.push(node);
		}
		if(node.type == "Q.Edge"){ // 获取线两端的label，并且进行ajax请求赋值
			var srcg = getUserGraph(node,true);
			var destg = getUserGraph(node);
			if(srcg != "0") {
				// 需要知道选择的图形id，对应的是哪个线，并且知道这个图形是在源端还是在目的端，源=0，目的=1
				selected_graphs.push(srcg + "_" + node.id + "_0")
			};
			if(destg != "0") {
				selected_graphs.push(destg + "_" + node.id + "_1")
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

function resizeCanvas(){
	var p2 = $("#qtools").offset();
	var p3 = $("#qtools").outerHeight();
	var p4 = $(window).height() - p2.top - p3;
	if(p4 > 500){
		$("#canvas").height(p4);
	}
}

</script>
<?php
