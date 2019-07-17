(function(Q, $){
	window.doAction = function($btn,graph,action){
		switch(action){
		case "new":
			var node = graph.createNode("新设备",graph.viewportBounds.x + 50,graph.viewportBounds.y + 50);
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
			var verify = neirong("拓扑名称",function(value){
				if(value == "" || value.trim() == "" || value.trim().length <= 0){
					alert("请先输入拓扑名称！");
					return false;
				}else{
					$("#qunee").append("<input type='hidden' name='action' value='save'>");
					$("#name").val(value);
					$("#topo").val(json);
					$("#qunee").submit();
				}
			},$("#name").val());
			break;
		}
	}
	
	var pageName = basename($(location).attr('pathname'));
	var items = {
		node:[{
			label:"关联设备",
			type:"ajax_select",
			name:"host",
			from:"ajax",
			url:pageName + "?action=ajax_host"
		},{
			label:"标签",
			type:"text",
			name:"name",
			from:"name"
		},{
			label:"标签位置",
			type:"select",
			name:"pos",
			values:{"cb-ct":"下","ct-cb":"上","lm-rm":"左","rm-lm":"右"}
		},{
			label:"设备图形",
			type:"select",
			name:"image",
			values:{
				"./include/imgs/server.png":"服务器1",
				"./include/imgs/server2.png":"服务器2",
				"./include/imgs/router.png":"路由器1",
				"./include/imgs/exchange.png":"交换机1",
				"./include/imgs/exchange2.png":"交换机2",
				"./include/imgs/exchange3.png":"交换机3",
				"./include/imgs/pc.png":"PC1",
				"./include/imgs/firewall.png":"防火墙1"
			},
			from:"name"
		},{
			label:"文字大小",
			type:"select",
			name:"label.font.size",
			values:{
				"12":"12",
				"16":"16",
				"20":"20",
				"24":"24",
				"30":"30",
				"40":"40"
			},
			value_type:"num"
		},{
			label:"文字颜色",
			type:"text",
			name:"label.color"
		}],
		edge:[{
			label:"标签",
			type:"text",
			name:"name",
			from:"name"
		},{
			label:"粗细",
			type:"select",
			name:"edge.width",
			values:{
				"1.5":"正常",
				"1":"细",
				"3":"粗",
				"4":"很粗"
			},
			value_type:"num"
		},{
			label:"实虚",
			type:"select",
			name:"dash",
			values:{
				"0":"实线",
				"5":"虚线"
			}
		},{
			label:"显示箭头",
			type:"select",
			name:"arrow.to",
			values:{
				"1":"显示",
				"0":"不显示"
			},
			value_type:"bool"
		},{
			label:"文字大小",
			type:"select",
			name:"label.font.size",
			values:{
				"12":"12",
				"16":"16",
				"20":"20",
				"24":"24",
				"30":"30",
				"40":"40"
			},
			value_type:"num"
		},{
			label:"文字颜色",
			type:"text",
			name:"label.color"
		},{
			label:"连线颜色",
			type:"text",
			name:"edge.color"
		}],
		text:[{
			label:"文字内容",
			type:"text",
			name:"name",
			from:"name"
		},{
			label:"文字大小",
			type:"select",
			name:"label.font.size",
			values:{
				"12":"12",
				"16":"16",
				"20":"20",
				"24":"24",
				"30":"30",
				"40":"40"
			},
			value_type:"num"
		},{
			label:"文字颜色",
			type:"text",
			name:"label.color"
		}]
	};
	
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
		case "ajax":
			var user = {};
			user[input_name] = input_value;
			node.user = user;
			break;
		}
	}
	
	// 给节点赋值
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
	
	// 构件属性框form
	var bulidJForm = function(node_type,node){
		var arr = items[node_type],styles = node.styles;
		var html = '<form id="qpros_frm">';
			html += '<dl class="dl-horizontal">';
		$.each(arr,function(index,obj){ // obj为用户的表单配置
			html += "<dt>" + obj.label + "</dt>";
			html += "<dd>";
			if(obj.type == "text"){
				html += "<input type='text' name='"+obj.name+"' value='"+(node[obj.name])+"'>";
			}else if(obj.type == "select"){
				html += "<select name='"+obj.name+"'>";
				//-------------以下用于数据回显--开始
				var ck_value = "";
				if(obj.name == "pos"){ // 
					ck_value = (styles["label.position"] ? (styles["label.position"]+"-"+styles["label.anchor.position"]) : "");
				}else if(obj.name == "dash"){
					ck_value = (styles["edge.line.dash"] ? styles["edge.line.dash"][0] : "");
				}else if(obj.name == "image"){
					ck_value = node.image;
				}else if(obj.value_type == "bool"){
					ck_value = (styles[obj.name] ? "1" : "0");
				}else{
					ck_value = (styles[obj.name] ? styles[obj.name] : "");
				}
				//-------------以下用于数据回显--结束
				$.each(obj.values,function(k,v){
					var sel = ((ck_value == k) ? "selected='selected'" : '');
					html += "<option value='"+k+"' "+sel+">"+v+"</option>";
				});
				html += "</select>";
			}else if(obj.type == "ajax_select"){ // 在 afterShowQproPanel 中设置 加载
				html += "<select name='"+obj.name+"' url='"+obj.url+"'>";
				html += "<option value='-1'>无</option>";
				html += "</select>";
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
		});
		$html.find("select").selectmenu();
		$html.find("input").addClass('ui-state-default ui-corner-all').css("width","182px");
		// 颜色默认值是555，
		var lcolor = (styles && styles["label.color"]) ? styles["label.color"] : "#555",
			ecolor = (styles && styles["edge.color"]) ? styles["edge.color"] : "#555";
		$html.find("input[name='label.color']").colorpicker({
			color:lcolor,part:{map:{size:128},bar:{size: 128}},colorFormat:"#HEX",rgb:false,hsv:false
		}).val(lcolor);
		$html.find("input[name='edge.color']").colorpicker({
			color:ecolor,part:{map:{size: 128},bar:{size: 128}},colorFormat:"#HEX",rgb:false,hsv:false
		}).val(ecolor);
		
		return $html;
	};
	
	$.fn.extend({
		slideShow:function(config,callback){
			var node = config.node,node_type = "node";
			// 标题修改
			if(node.type == "Q.Node"){
				$("#qpros_title").text("设备信息");
			}else if(node.type == "Q.Edge"){
				$("#qpros_title").text("连线信息");
				node_type = "edge";
			}else if(node.type == "Q.Text"){
				$("#qpros_title").text("文字信息");
				node_type = "text";
			}
			// 构件弹出form
			var $html = bulidJForm(node_type,node);
			$("#qpros_content").empty().append($html);
			this.show("slide",{direction:'right'},500);
			if(callback){
				callback($html,node,node_type);	
			}
		}
	});
	

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
	window.neirong = function(label, callback, default_input) {
		$("#spanmessage").html(label + " <input type='text' class='ui-state-default ui-corner-all' value='"+default_input+"'>");
		$("#message").dialog({
			title : "消息",
			modal : true,
			resizable : false,
			buttons : {
				"取消" : function() {
					$(this).dialog("close");
				},
				"确认" : function() {
					var text = $("#spanmessage").find("input").val();
					callback(text);
					$(this).dialog("close");
				}
			}
		});
	}
}(Q, jQuery));