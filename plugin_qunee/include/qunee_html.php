<?php
?>
<div class="qtools" id="qtools">
	<button type='button' action="cloud"><i class='fa fa-cloud'></i>添加云</button>
	<button type='button' action="new"><i class='fa fa-desktop'></i>添加设备</button>
	<button type='button' action="text"><i class='fa fa-font'></i>添加文字</button>
	<button type='button' action="line"><i class='fa fa-arrow-right'></i>添加连线</button>
	<button type='button' action="defa" style="display: none"><i class='fa fa-arrow-right'></i>取消连线</button>
	<button type='button' action="del"><i class='fa fa-minus'></i><span>删除</span></button>
	<button type='button' action="bg"><i class='fa fa-th'></i><span>显示背景</span></button>
	
	<button type='button' action="save" style="float: right"><i class='fa fa-save'></i><span>保存</span></button>
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
var graph,host_ids = [];
$(function(){
	// 提供弹出框
   	$(document.body).append("<div id='message' style='display:none;'><span id='spanmessage'></span></div>");
	
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
			var from = element.from,to = element.to;
			if(from && from.type == "Q.Node" && (!from.user || !from.user.host || from.user.host == "0" || from.user.host.length <= 0)){
				message("连线开始设备必须要先关联设备！");
				graph.removeElement(element);
			}else if(to && to.type == "Q.Node" && (!to.user || !to.user.host || to.user.host == "0" || to.user.host.length <= 0)){ // 必须是节点对象才可以提示判断
				message("连线结束设备必须要先关联设备！");
				graph.removeElement(element);
			}
			return;
        }
    }

    var selectEle;
	graph.addCustomInteraction({
		onclick: function(evt, graph){
			var element = graph.getElementByMouseEvent(evt);
			if(!element){
				closeQproPanel();
			}else{
    			selectEle = element;
    			$("#qpros").slideShow({
					node:element,
					afterApplyNode:afterApplyNode
				});
			}
		}
	});

	// 如果进入的是编辑页面，需要显示拓扑
	var id = $("#id").val();
	if(id != "0" && id != 0){ // 说明是修改
		var topo = base64_decode($("#topo").val());
		var json = JSON.parse(topo);
		graph.parseJSON(json);
	}
	$(".qpros-clobtn").click(closeQproPanel);
	refreshHostIds();
});

// 点击确定，节点的数据赋值完成后，回调
function afterApplyNode(btn,node_type,node){
	if(node.type == "Q.Edge"){ // 如果给线赋值完成
		var srcg = getUserGraph(node,true);
		var destg = getUserGraph(node);
		if(srcg != "0"){ // 选择了源图形
			var label1 = new Q.LabelUI();
			label1.position = Q.Position.RIGHT_TOP;
			label1.anchorPosition = Q.Position.RIGHT_TOP;
			label1.border = 1;
			label1.offsetY = 10;
			label1.showPointer = true;
			label1.padding = 5;
			label1.alignPosition = Q.Position.LEFT_MIDDLE;
			label1.data="入口流量：40G\n出口流量：100G";
			node.addUI(label1,[{
				property : "srcLabelName",
				propertyType : Q.Consts.PROPERTY_TYPE_CLIENT,
				bindingProperty : "data"
			}]);	
		}
		if(destg != "0"){
			var label1 = new Q.LabelUI();
			label1.position = Q.Position.LEFT_BOTTOM;
			label1.anchorPosition = Q.Position.LEFT_BOTTOM;
			label1.border = 1;
			label1.offsetY = -10;
			label1.showPointer = true;
			label1.padding = 5;
			label1.alignPosition = Q.Position.LEFT_MIDDLE;
			label1.data="入口流量：100G\n出口流量：10G";
			node.addUI(label1,[{
				property : "destLabelName",
				propertyType : Q.Consts.PROPERTY_TYPE_CLIENT,
				bindingProperty : "data"
			}]);	
		}
	}

	refreshHostIds();
}

// 当下拉框创建成功
function onSelectMenuCreate($select,node_type,node){
	if($select.attr("name") == "host"){
		$.ajax({
			dataType:"json",
			url:$select.attr("url"),
			success: function(data){
				if(data && data.length > 0){
					$select.empty();
					var ck_value = getUserHost(node);
					$.each(data,function(di,dv){
						if($.inArray(dv.id,host_ids) >= 0 && ck_value != dv.id){ // 说明该设备已经被选中过，不能在选择了
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
				if(data){
					console.info(data);
					$select.empty();
					$.each(data,function(di,dv){
						$select.append("<option value='"+dv.local_graph_id+"'>"+dv.title_cache+"</option>");
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

// 获取页面中所有设备关联的设备ID--为了完成禁止重复关联同一个设备功能使用
function refreshHostIds(){
	host_ids = [];
	if(!graph) return;
	graph.forEach(function(e){
		if(e.type == "Q.Node" && getUserHost(e) != "0"){
			host_ids.push(e.user.host);
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
