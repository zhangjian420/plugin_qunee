(function(Q, $){
	window.doAction = function($btn,graph,action){
		switch(action){
		case "cloud":
			var subnetwork = graph.createNode("",graph.viewportBounds.x + 50,graph.viewportBounds.y + 50);
			subnetwork.image = graphs.group_cloud;
			subnetwork.enableSubNetwork = true;
			break;
		case "new":
			var node = graph.createNode("新设备",graph.viewportBounds.x + 60,graph.viewportBounds.y + 60);
			node.image = "./include/imgs/server.png";
			break;
		case "text":
			graph.createText("文字内容",graph.viewportBounds.x + 50,graph.viewportBounds.y + 50);
			break;
		case "line":
			$btn.hide();
			$("button[action='defa']").show();
			graph.interactionMode = Q.Consts.INTERACTION_MODE_CREATE_EDGE;
			break;
		case "defa":
			$btn.hide();
			$("button[action='line']").show();
			graph.interactionMode = Q.Consts.INTERACTION_MODE_DEFAULT;
			break;
		case "del":
			var selections = graph.selectionModel.datas;
			if(selections.length <= 0){
				message("请先选择需要删除的节点！");
				return;
			}
			queren("确认删除选中节点？",function(){
				graph.removeSelection();
				forEachGraph();
			});
			break;
		case "bg":
			var bg = graph.canvasPanel.style.background;
			if(bg == ""){
				$btn.find("span").text("隐藏背景")
				graph.canvasPanel.style.background = "url('./include/css/vpc-background.png')";	
			}else{
				$btn.find("span").text("显示背景")
				graph.canvasPanel.style.background = "";	
			}
			break;
		case "save":
			var json = base64_encode(graph.exportJSON(true));
			var verify = biaodan([{
				label:"拓扑名称",name:"name",value:$("#name").val()
			},{
				label:"告警阈值(%)",name:"thold",value:$("#thold").val()
			},{
				label:"邮箱地址",name:"emails",value:$("#emails").val()//,from:"qunee.php?action=ajax_emails"
			}],function(data){
				if(data && data.name && data.name == ""){
					alert("请先输入拓扑名称！");
					return false;
				}
				$("#qunee").append("<input type='hidden' name='action' value='save'>");
				$("#qunee").append("<input type='hidden' name='graphs' value='"+selected_graphs.join(",")+"'>");
				$("#name").val(data.name);
				$("#emails").val(data.emails);
				$("#thold").val(data.thold);
				$("#topo").val(json);
				$("#qunee").submit();
			});
			break;
		case "import":
			queren("导入前需要保存拓扑，否则会丢失未保存数据？",function(){
				var id = getQueryParam("id");
				var href = "qunee.php?action=import";
				if(id != null){
					window.location.href = href + "&topo_id="+id;
				}else{
					window.location.href = href;
				}
			});
			break;
		case "save_sub":
			var json = base64_encode(graph.exportJSON(true));
			$("#qunee_sub").append("<input type='hidden' name='action' value='sub_save'>");
			$("#qunee_sub").append("<input type='hidden' name='graphs' value='"+selected_graphs.join(",")+"'>");
			$("#qunee_sub").append("<input type='hidden' name='topo' value='"+json+"'>");
			$("#qunee_sub").append("<input type='hidden' name='line_num' value='"+line_num+"'>");
			$("#qunee_sub").submit();
			break;
		}
	}
	
	var items = getQuneeFormItems();
	var setValue = function(obj,node){
		var input_name = obj.input_name,input_value = obj.input_value,
			obj_from = obj.from || "style",
			obj_value_type = obj.value_type || "string";
		switch(obj_from){
		case "style":
			if(input_name == "pos"){ // 如果是位置，需要特殊处理
				var input_values = input_value.split("-");
				node.setStyle("label.position",input_values[0]);
				node.setStyle("label.anchor.position",input_values[1]);
			}else if(input_name == "dash"){
				input_value = parseInt(input_value);
				node.setStyle("edge.line.dash",[input_value]);
			}else if(input_name == "sub" && input_value != 0 && input_value != "0"){ // 如果是子网的话，需要将节点放入到子网中
				node.parent = graph.getElement(input_value);
			}else{
				if(obj_value_type == "string"){
					node.setStyle(input_name,input_value);
				}else if(obj_value_type == "num"){
					node.setStyle(input_name,parseFloat(input_value));
				}else if(obj_value_type == "bool"){
					node.setStyle(input_name,input_value == "1");
				}
			}
			break;
		case "name":
			node[obj.name] = input_value;
			break;
		case "user":
			if(input_name == "ewidth"){
				if(isNaN(input_value)){
					message("带宽必须为数字！");
					return;
				}
				var w1 = parseFloat((parseFloat(input_value)/32).toFixed(1));
				w1 = w1 > 1 ? w1 : 1;
				node.setStyle("edge.width",w1);
			}
			var user = node.user || {};
			user[input_name] = input_value;
			node.user = user;
			break;
		}
	}
	
	// 点击属性框form中的确定按钮给节点赋值
	var applyNode = function(node_type,node){
		var fields = $("#qpros_frm").serializeArray(); // 用户表单输入的值
		var arr = items[''+node_type+''];
		var props = {};
		$.each(fields, function(i, field){
			var input_name = field["name"],input_value = field["value"]; // 用户表单输入的值
			$.each(arr,function(index,obj){
				if(obj.name == input_name){ // obj为用户的表单配置
					obj.input_name = input_name;
					obj.input_value = input_value;
					setValue(obj,node);
					return false; // 相当于是break
				}
			});
		});
	}
	
	// 点击某个设备，谈出构件属性框form
	var bulidJForm = function(node_type,node,config){
		var arr = items[node_type],styles = node.styles;
		var html = '<form id="qpros_frm">';
			html += '<dl class="dl-horizontal">';
		$.each(arr,function(index,obj){ // obj为用户的表单配置
			if(obj.type == "ajax_select" && node.type == "Q.Edge"){ // 构件点击线时候的表单，如果线的起点node没有设置host，或者终点node没有设置host，跳过构件select
				if(getUserHost(node.from) != "0" && getUserHost(node.to) == "0" && obj.name == "destg"){ // 如果有源端但是没有目的端
					return; // 相当于是continue
				}else if(getUserHost(node.from) == "0" && getUserHost(node.to) != "0" && obj.name == "srcg"){
					return;
				}
			}
			html += "<dt>" + obj.label + "</dt>";
			html += "<dd>";
			if(obj.type == "text"){
				// 如果是从自定义属性中获取值，没有获取到，然后从node属性中获取。
				var objv = (obj.from == "user" ? getUserPro(node,obj.name,obj.value || "") : (node[obj.name] || obj.value || ""));
				html += "<input type='text' name='"+obj.name+"' value='"+objv+"' class='ui-state-default ui-corner-all' style='width:182px;' >";
			}else if(obj.type == "select"){
				html += "<select name='"+obj.name+"'>";
				//-------------以下用于数据回显--开始
				var ck_value = "";
				if(obj.name == "pos"){ // 
					ck_value = (styles["label.position"] ? (styles["label.position"]+"-"+styles["label.anchor.position"]) : obj.value);
				}else if(obj.name == "dash"){
					ck_value = (styles["edge.line.dash"] ? styles["edge.line.dash"][0] : obj.value);
				}else if(obj.name == "image"){
					ck_value = node.image;
				}else if(obj.value_type == "bool"){
					ck_value = (styles[obj.name] ? "1" : "0");
				}else if(obj.from == "user"){
					ck_value = getUserPro(node,obj.name,obj.value);
				}else{
					ck_value = (styles[obj.name] ? styles[obj.name] : obj.value);
				}
				//-------------以下用于数据回显--结束
				$.each(obj.values,function(k,v){
					var sel = ((ck_value == k) ? "selected='selected'" : '');
					html += "<option value='"+k+"' "+sel+">"+v+"</option>";
				});
				html += "</select>";
			}else if(obj.type == "ajax_select"){ // 在 afterShowQproPanel 中设置 加载
				html += "<select name='"+obj.name+"' url='"+obj.url+"'>";
				html += "<option value='0'>无</option>";
				html += "</select>";
			}else if(obj.type == "texta"){
				var objv = (obj.from == "user" ? getUserPro(node,obj.name,obj.value || "") : (node[obj.name] || ""));
				html += "<textarea name='"+obj.name+"' class='ui-state-default ui-corner-all' style='width:194px;height:40px'>"+objv+"</textarea>";
			}
			html += "</dd>";
		});
		html += '<div class="qpros-bottom">';
			html += '<button type="button">确定</button>';
		html += '</div>';
		html += '</form>';
		
		// html构件成功后，需要设置里面的button，input，select需要使用系统样式
		var $html = $(html);
		$html.find("button").button().on("click",function(){ // 当点确定时，node应用属性
			applyNode(node_type,node);
			if(typeof config.afterApplyNode != 'undefined' && config.afterApplyNode instanceof Function){
				config.afterApplyNode($html.find("button")[0],node_type,node);
			}
		});
		$html.find("select").each(function(si,sv){
			$(sv).selectmenu({create: function(){
				if(typeof onSelectMenuCreate != 'undefined' && onSelectMenuCreate instanceof Function){
					onSelectMenuCreate($(sv),node_type,node);
				}
			},change:function(){
				if(typeof onSelectMenuChange != 'undefined' && onSelectMenuChange instanceof Function){
					onSelectMenuChange($(sv),node_type,node);
				}
			}});
		});
		// 颜色默认值是555，
		var lcolor = (styles && styles["label.color"]) ? styles["label.color"] : "#333",
			ecolor = (styles && styles["edge.color"]) ? styles["edge.color"] : "#555",
			fcolor = (styles && styles["render.color"]) ? styles["render.color"] : "#fff";
		$html.find("input[name='label.color']").colorpicker({
			color:lcolor,part:{map:{size:128},bar:{size: 128}},colorFormat:"#HEX",rgb:false,hsv:false
		}).val(lcolor);
		$html.find("input[name='edge.color']").colorpicker({
			color:ecolor,part:{map:{size: 128},bar:{size: 128}},colorFormat:"#HEX",rgb:false,hsv:false
		}).val(ecolor);
		// 云的填充颜色
		$html.find("input[name='render.color']").colorpicker({
			color:fcolor,part:{map:{size: 128},bar:{size: 128}},colorFormat:"#HEX",rgb:false,hsv:false
		}).val(fcolor);
		return $html;
	};
	
	$.fn.extend({
		slideShow:function(config,callback){
			var node = config.node,node_type = "node";
			// 标题修改
			if(node.type == "Q.Node" && !node.enableSubNetwork){
				$("#qpros_title").text("设备信息");
			}else if(node.type == "Q.Edge"){
				$("#qpros_title").text("连线信息");
				node_type = "edge";
			}else if(node.type == "Q.Text"){
				$("#qpros_title").text("文字信息");
				node_type = "text";
			}else if(node.type == "Q.Node" && node.enableSubNetwork){
				$("#qpros_title").text("子网信息");
				node_type = "sub";
			}
			// 构件弹出form
			var $html = bulidJForm(node_type,node,config);
			$("#qpros_content").empty().append($html);
			this.show("slide",{direction:'right'},500);
			if(callback){
				callback($html,node,node_type);	
			}
		}
	});
	
	// 获取该节点的host，如果获取不到返回都是字符串 0 
	window.getUserPro = function(node,name,defv){
		try {
			return node.user[name] || defv || "0";
		} catch (e) {
			return defv || "0";
		}
	}
	window.getUserHost = function(node){
		return getUserPro(node,"host");
	};
	window.getUserGraph = function(node,isFrom){
		if(isFrom){
			return getUserPro(node,"srcg");
		}else{
			return getUserPro(node,"destg");
		}
	};
	
	
	window.message = function(text) {
		$("#spanmessage").text(text);
		$("#message").dialog({
			title : "消息",
			modal : true,
			buttons : {
				"确定" : function() {
					$(this).dialog("close");
				}
			}
		});
	}
	window.queren = function(text, callback) {
		$("#spanmessage").text(text);
		$("#message").dialog({
			title : "消息",
			modal : true,
			resizable : false,
			buttons : {
				"取消" : function() {
					$(this).dialog("close");
				},
				"确认" : function() {
					callback.call();//方法回调
					$(this).dialog("close");
				}
			}
		});
	}
	window.biaodan = function(fields,callback){
		var html = "";
		if(fields && fields.length > 0){
			$.each(fields,function(i,v){
				html += "<div style='margin-bottom:5px'><label style='width:80px;display:inline-block'>"+ v.label + "</label>";
//				if(v.from){ // 说明是ajax请求
//					html += "<select name='"+v.name+"' url='"+v.from+"' style='width:170px'>";
//					html += "<option value='0'>无</option>";
//					html += "</select>";
//				}else{
				html += ("<input type='text' class='ui-state-default ui-corner-all' name='"
						+v.name+"' value='"
						+(v.value||"")+"'>");
//				}
				html += "</div>";
			});
		}
//		var $html = $(html);
//		$html.find("select").each(function(si,sv){
//			$(sv).selectmenu({create: function(){
//				if(typeof onSelectMenuCreate != 'undefined' && onSelectMenuCreate instanceof Function){
//					onSelectMenuCreate($(sv));
//				}
//			}});
//		});
		$("#spanmessage").html(html);
		$("#message").dialog({
			title : "消息",
			modal : true,
			resizable : false,
			buttons : {
				"取消" : function() {
					$(this).dialog("close");
				},
				"确认" : function() {
					var inputs = $("#spanmessage").find("input");
					var json = {};
					inputs.each(function(i,v){
						json[$(v).attr("name")] = $(v).val();
					});
					callback(json);
					$(this).dialog("close");
				}
			}
		});
	}
	window.inSelectGraphs = function(selected_graphs,local_graph_id){
		var ix = -1;
		if(!selected_graphs || selected_graphs.length == 0){
			return ix;
		}
		$.each(selected_graphs,function(i,selected_graph){
			var selected_graph_id = selected_graph.split("_")[0];
			if(selected_graph_id == local_graph_id){
				ix = i;
				return;
			}
		});
		return ix;
	}
	window.getQueryParam = function(name){
	     var reg = new RegExp("(^|&)"+ name +"=([^&]*)(&|$)");
	     var r = window.location.search.substr(1).match(reg);
	     if(r!=null)return unescape(r[2]); return null;
	}
}(Q, jQuery));