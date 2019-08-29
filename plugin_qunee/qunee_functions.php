<?php

// 获取数据源
function get_local_data($local_graph_ids){
    $local_datas = db_fetch_assoc("select dl.id as local_data_id,gtg.upper_limit,gtg.local_graph_id,gtg.title_cache,
        h.description as host_desc,h.id as host_id  
        from data_local dl
        left join graph_local gl
        on dl.host_id = gl.host_id and dl.snmp_query_id = gl.snmp_query_id 
        and dl.snmp_index = gl.snmp_index
        left join graph_templates_graph gtg on gl.id = gtg.local_graph_id 
        left join `host` h on gl.host_id = h.id 
        where gl.id in (".$local_graph_ids.")");
    return $local_datas;
}

/**
 * 返回的格式： 单位是 bit
 * array(
        "traffic_in" => value1,
        "traffic_out" => value2,
        "alarm_level" => 
    );
 * @return number[]
 */
function qunee_get_ref_value($local_data, $ref_time, $time_range,$alarm_mod,$alarm_from=0){
    if (empty($local_data["local_data_id"])) {
        return array();
    }
    $result = rrdtool_function_fetch($local_data["local_data_id"], $ref_time-$time_range, $ref_time, $time_range); // 单位是字节，返回时要转行成bit
    $idx_in = array_search("traffic_in", $result['data_source_names']);
    $idx_out = array_search("traffic_out", $result['data_source_names']);
    if (!isset($result['values'][$idx_in]) || count($result['values'][$idx_in]) == 0) {
        $iv = 0;
    }else {
        $iv = max($result['values'][$idx_in]) * 8;
    }
    if (!isset($result['values'][$idx_out]) || count($result['values'][$idx_out]) == 0) {
        $ov = 0;
    }else{
        $ov = max($result['values'][$idx_out]) * 8;
    }
    
//     cacti_log("local_graph_id = ".$local_data["local_graph_id"].",local_data_id = ".$local_data["local_data_id"]
//         .",iv = ".$iv.",ov = ".$ov);
    
    if($iv == 0 && $ov == 0){
        return array();
    }
    if(!empty($alarm_from)){
        $alarm_level = getAlarmSubVal($iv,$ov,$local_data["upper_limit"],$alarm_mod);
    }else{
        $alarm_level = getAlarmVal($iv,$ov,$local_data["upper_limit"],$alarm_mod);
    }
    return array(
        "traffic_in" => getUnitVal($iv),
        "traffic_in_byte" => $iv,
        "traffic_out" => getUnitVal($ov),
        "traffic_out_byte" => $ov,
        "alarm_level" => $alarm_level[0],
        "cap" => $alarm_level[1]
    );
}

function pollAlarm(){
    global $config;
    //cacti_log('每次poller后执行', false, 'SYSTEM');
    $topos = db_fetch_assoc("select q.id,q.name,q.emails,q.thold,q.graphs,'' as `from`,0 as line_num ,0 as ewidth  
                    from plugin_qunee q where graphs is not null and graphs !='' union all 
                    select q.id,q.name,q.emails,q.thold,qs.graphs,'sub' as `from`,qs.line_num,qs.ewidth from plugin_qunee_sub qs 
                    left join plugin_qunee q on qs.topo_id = q.id where qs.graphs is not null and qs.graphs != '' ");
    if (cacti_sizeof($topos)) {
        foreach($topos as $topo) { // 1、查询所有的拓扑
//             cacti_log("topo 中 q.id=".$topo["id"].",q.name=".$topo["name"]
//                 .",q.emails=".$topo["emails"].",graphs=".$topo["graphs"]
//                 .",from=".$topo["from"].",line_num=".$topo["line_num"].",ewidth=".$topo["ewidth"]);
            $arr_graphs = explode(",", $topo["graphs"]);
            $graph_map = array();
            foreach($arr_graphs as $graph) {
                // [0] = graph_id,[1] = node_id,[2] = host_id ,[3] = 0src_or_1dest,[4] = is_alarm
                $tmp_graphs = explode("_", $graph);
                // 存入后 [0] = node_id,[1] = host_id ,[2] = 0src_or_1dest,[3] = is_alarm
                $graph_map[$tmp_graphs[0]] = array($tmp_graphs[1],$tmp_graphs[2],$tmp_graphs[3],$tmp_graphs[4]); 
            }
            $graph_ids = implode(array_keys($graph_map), ",");
            if (!empty($topo["from"])) {
                $upper_limit = $topo["ewidth"] / $topo["line_num"]; // 通道容量分母
                //cacti_log("通道容量计算分母=".$upper_limit);
            }
            if(!empty($graph_ids)){
                $local_datas = get_local_data($graph_ids); // 2、获取拓扑中所有图形对应的数据
                if (cacti_sizeof($local_datas)) {
                    foreach($local_datas as $local_data) {
                        $host_id = $graph_map[$local_data["local_graph_id"]][1];
                        if (api_plugin_is_enabled('maint')) { // 判断主机是否正在维护
                            include_once($config['base_path'] . '/plugins/maint/functions.php');
                            if (plugin_maint_check_cacti_host($host_id)) {
                                continue;
                            }
                        }
                        $is_alarm = $graph_map[$local_data["local_graph_id"]][3];
                        if ($is_alarm == 0) { // 如果不要发送告警邮件
                            continue;
                        }
                        if(empty($topo["from"])){ // 说明是从主拓扑进入的
                            $mod = empty($topo) ? 0.9 : $topo["thold"]/100;
                        }else{ // 从通道容量拓扑进入，固定阈值
                            $is_src = $graph_map[$local_data["local_graph_id"]][2];
                            if(isset($is_src) && $is_src == 1){ // 如果图形是目的的话，不用计算是否告警
                                //cacti_log("是目的图形，不用计算,graph_id=".$local_data["local_graph_id"]);
                                continue;
                            }
                            $local_data["upper_limit"] = $upper_limit;
                            $mod = 0.3;
                        }
                        $now = time();
                        $ref_values = qunee_get_ref_value($local_data,$now,60,$mod,$topo["from"]);
                        if (cacti_sizeof($ref_values) != 0) {
                            handAlarm($topo,$local_data,$ref_values["alarm_level"],$now);
                        }else {
                            cacti_log("根据local_data_id=".$local_data["local_data_id"].'，没有获取到rrd文件数据', false, 'SYSTEM');
                        }
                    }
                }
            }else {
                cacti_log("拓扑id=".$topo["id"].'，没有graph_ids，请核实下', false, 'SYSTEM');
            }
        }
    }
}

// 判断是否要发送邮件或者恢复发送恢复邮件
function handAlarm($topo,$local_data,$alarm_level,$now){
    $from = empty($topo["from"]) ? "" : $topo["from"];
    $alarm_data = db_fetch_row_prepared("select * from plugin_qunee_alarm where topo_id = ? 
       and local_graph_id = ? and local_data_id = ? and `from` = ? ",
        array($topo["id"],$local_data["local_graph_id"],$local_data["local_data_id"],$from));
    if (cacti_sizeof($alarm_data)) { // 说明之前已经告警过了，判断 时间是否大于 10分钟了，并且之前告警的状态
        $alarm_status = $alarm_data["alarm_status"]; // 上次告警状态0-已恢复，1-告警中
        //cacti_log('根据'. $topo["id"] .'查询到以前告警数据，之前的告警状态='.$alarm_status.",新数据告警级别=".$alarm_level, false, 'SYSTEM');
        if ($alarm_status == 0 && $alarm_level == 2) { // 如果之前已恢复，现在又来一条严重告警
            db_execute("update plugin_qunee_alarm set alarm_status = 1,alarm_time = $now where id = " .$alarm_data["id"]);
            // 发送告警邮件
            sendEmail($topo,$local_data,1);
        }else if($alarm_status == 1){ // 如果是告警中，现在来了一条不告警的数据，那么需要恢复；来了一条告警数据，那么需要判断时间是不是大于10分钟
            if ($alarm_level != 2) { // 1和0都属于恢复了
                //cacti_log('恢复了，发送恢复邮件', false, 'SYSTEM');
                db_execute("update plugin_qunee_alarm set alarm_status = 0,alarm_time = $now  where id = " .$alarm_data["id"]);
                // 发送恢复邮件
                sendEmail($topo,$local_data,0);
            }else if($now - $alarm_data["alarm_time"] > 600){ // 持续告警中，判断是不是超过10分钟，超过10分钟，任然发送告警邮件，否则不发送。
                //cacti_log('10分钟还不恢复，根据告警时间，再次发送', false, 'SYSTEM');
                db_execute("update plugin_qunee_alarm set alarm_time = $now where id = " .$alarm_data["id"]);
                // 发送告警邮件
                sendEmail($topo,$local_data,1,1);
            }
        }
    }else if($alarm_level == 2){ // 之前没有告警过，但是告警级别已经是严重了，插入一条记录并且发出告警
        //cacti_log('根据'. $topo["id"] .'没有查询到告警数据', false, 'SYSTEM');
        $save = array();
        $save["topo_id"] = $topo["id"];
        $save["host_id"] = $local_data["host_id"];
        $save["local_graph_id"] = $local_data["local_graph_id"];
        $save["local_data_id"] = $local_data["local_data_id"];
        $save["alarm_time"] = $now;
        $save["alarm_status"] = 1;
        $save["from"] = $from;
        sql_save($save, "plugin_qunee_alarm");
        //cacti_log('保存告警结果', false, 'SYSTEM');
        // 发送告警邮件
        sendEmail($topo,$local_data,1);
    }
}

function sendEmail($topo,$local_data,$alarm_status,$is_always = 0){
    $prefix  = empty($topo["from"]) ? "" : "通道容量";
    if($alarm_status == 0){ // 如果告警状态是已恢复
        //cacti_log('正在发送恢复邮件，发送联系人'.$topo["emails"].",topo_name=".$topo["name"]."，图形名称=".$local_data["title_cache"], false, 'SYSTEM');
        $msg = "拓扑:".$topo["name"]."<br>
                                           设备名称:".$local_data["host_desc"]."<br>
                                            图形:".$local_data["title_cache"]."<br>
                                            消息:".$prefix."告警已经恢复";
        send_mail($topo["emails"],"","拓扑".$prefix."告警恢复",$msg,"","",true);
        //cacti_log('成功发送邮件', false, 'SYSTEM');
    }else {
        //cacti_log('正在发送告警邮件，发送联系人'.$topo["emails"].",topo_name=".$topo["name"]."，图形名称=".$local_data["title_cache"], false, 'SYSTEM');
        $msg = "拓扑:".$topo["name"]."<br>
                                           设备名称:".$local_data["host_desc"]."<br>
                                            图形:".$local_data["title_cache"]."<br>
                                            消息:".$prefix."发生告警 ". ($is_always == 1 ? "，持续时间10分钟未恢复" : "");
        send_mail($topo["emails"],"","拓扑".$prefix."发生告警",$msg,"","",true);
        //cacti_log('成功发送邮件', false, 'SYSTEM');
    }
}

// 封装成页面中显示的值
function getUnitVal($val){
    if(0 <= $val && $val < 1000000){
        $val = round($val / 1000,2) . "k";
    }else if(1000000 <= $val && $val < 1000000000){
        $val = round($val / 1000000,2) . "M";
    }else if(1000000000 <= $val && $val < 1000000000000){
        $val = round($val / 1000000000,2) . "G";
    }
    return $val;
}

// 判断告警严重级别
function getAlarmVal($in,$out,$comp,$mod = 0.9){
    $v = $in > $out ? $out : $in; // 如果最小的值都大于0.8，那么肯定也会告警
    $v1 = $in > $out ? $in : $out;
    $cap = number_format($v1/$comp*100,2);
    if($v >= ($comp * $mod)){
        //cacti_log("进入严重", false, 'SYSTEM');
        return array(2,$cap); // 严重
    }else if($v >= ($comp * 0.8)){
        //cacti_log("进入一般", false, 'SYSTEM');
        return array(1,$cap); // 一般
    }else{
        return array(0,$cap); // 无告警
    }
}

function getAlarmSubVal($in,$out,$comp,$mod = 0.3){
    $v = $in > $out ? $in : $out;
    $cap = number_format($v/$comp*100,2);
    if($v < ($comp * $mod)){
        return array(2,$cap); // 严重
    }else{
        return array(0,$cap); // 严重
    }
}

/**
 *       裁剪图片为新的图片   ，如果$newhei不填，默认使用等比例缩放
 * @param  $src
 * @param  $newwid
 * @param  $newhei
 */
function imgThrum($src,$dest,$newwid,$newhei = 0){
    //cacti_log("进入缩放图 " , false, 'SYSTEM');
    $imgInfo = getimagesize($src);
    $imgType = image_type_to_extension($imgInfo[2], false);
    $imagecreatefrom = "imagecreatefrom{$imgType}";
    $imageout = "image{$imgType}";
    //cacti_log("图片信息 = " .$imagecreatefrom . ",,," . $imageout, false, 'SYSTEM');
    //声明图片   打开图片 在内存中
    $image = $imagecreatefrom($src);
    // 源图片的宽度和高度
    $wid=$imgInfo[0];
    $hei=$imgInfo[1];
   //cacti_log("元图片宽高 = $wid === $hei" . $imageout, false, 'SYSTEM');
    if(empty($newhei)){ // 如果没有设置新图片的高度，默认使用等比例缩放
        $newhei = $newwid/($wid/$hei);
    }
   // cacti_log("计算比例宽带 = 56 高度等于 = ".$newhei . $imageout, false, 'SYSTEM');
    //在内存中建立一张图片
    $images2 = imagecreatetruecolor($newwid, $newhei); //建立一个500*320的图片
    //将原图复制到新建图片中
    imagecopyresampled($images2, $image, 0, 0, 0, 0, $newwid,$newhei, $wid,$hei);
    if($imgInfo[2] == 2){ // jpg
        $imageout($images2,$dest,100);
    }else {
        $imageout($images2,$dest);
    }
    //销毁
    imagedestroy($image);
    imagedestroy($images2);
}
