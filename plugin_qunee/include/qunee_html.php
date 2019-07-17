<?php
?>
<div class="qtools" id="qtools">
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
$(function(){
	// 提供弹出框
   	$(document.body).append("<div id='message' style='display:none;'><span id='spanmessage'></span></div>");
	
	Q.addCSSRule(".maximize",
	"position: fixed;top: 0px;bottom: 0px;right: 0px;left: 0px;z-index: 1030;height: auto !important;");
	
	var graph = new Q.Graph('canvas');
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

    var selectEle;
	graph.addCustomInteraction({
		onclick: function(evt, graph){
			var element = graph.getElementByMouseEvent(evt);
			if(!element){
				closeQproPanel();
			}else{
    			selectEle = element;
    			$("#qpros").slideShow({
					node:element
				},afterShowQproPanel);
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
	
});

function afterShowQproPanel($html,node){
	var $host = $html.find("select[name='host']");
	$host.selectmenu({
		open:function(event,ui) {
			$.ajax({
				async:false,
				dataType:"json",
				url:$host.attr("url"),
				success: function(data){
					if(data && data.length > 0){
						$host.empty();
						var ck_value = (node.user && node.user.host) ? node.user.host : "-1";
						$.each(data,function(di,dv){
							var sel = ((ck_value == dv.id) ? "selected='selected'" : '');	
							$host.append("<option value='"+dv.id+"' "+sel+">"+dv.value+"</option>");
						});
						$host.selectmenu("refresh");
					}
				}
			});
		}
	});
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
