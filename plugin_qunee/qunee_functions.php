<?php

// 获取数据源
function get_local_data($local_graph_ids){
    $local_datas = db_fetch_assoc("select dl.id as local_data_id,gtg.upper_limit,gtg.local_graph_id,gtg.title_cache  
        from data_local dl
        left join graph_local gl
        on dl.host_id = gl.host_id and dl.snmp_query_id = gl.snmp_query_id 
        and dl.snmp_index = gl.snmp_index
        left join graph_templates_graph gtg on gl.id = gtg.local_graph_id
        where gl.id in (".$local_graph_ids.")");
    return $local_datas;
}

/**
 * 返回的格式： 单位是 bit
 * array(
        "traffic_in" => value1,
        "traffic_out" => value2
    );
 * @return number[]
 */
function qunee_get_ref_value($local_data, $ref_time, $time_range){
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
    if($iv == 0 && $ov == 0){
        return array();
    }
    return array(
        "traffic_in" => getUnitVal($iv),
        "traffic_out" => getUnitVal($ov),
        "alarm_level" => getAlarmVal($iv,$ov,$local_data["upper_limit"])
    );
}

function pollAlarm(){
    //cacti_log('每次poller后执行', false, 'SYSTEM');
    $topos = db_fetch_assoc("select * from plugin_qunee");
    if (cacti_sizeof($topos)) {
        foreach($topos as $topo) { // 1、查询所有的拓扑
            $graph_ids = $topo["graph_ids"];
            if(!empty($graph_ids)){
                $local_datas = get_local_data($graph_ids); // 2、获取拓扑中所有图形对应的数据
                if (cacti_sizeof($local_datas)) {
                    foreach($local_datas as $local_data) {
                        $now = time();
                        $ref_values = qunee_get_ref_value($local_data,$now,600);
                        if (cacti_sizeof($ref_values) != 0) {
//                             cacti_log("topo_id=".$topo["id"].",local_graph_id=".$local_data["local_graph_id"]
//                                 .",local_data_id=".$local_data["local_data_id"]
//                                 .",traffic_in=".$ref_values["traffic_in"]
//                                 .",traffic_out=".$ref_values["traffic_out"]
//                                 .",upper_limit=".$local_data["upper_limit"]
//                                 .",alarm_level=".$ref_values["alarm_level"]
//                                 , false, 'SYSTEM');
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
    $alarm_data = db_fetch_row_prepared("select * from plugin_qunee_alarm where topo_id = ? 
       and local_graph_id = ? and local_data_id = ?",array($topo["id"],$local_data["local_graph_id"],$local_data["local_data_id"]));
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
                sendEmail($topo,$local_data,1);
            }
        }
    }else if($alarm_level == 2){ // 之前没有告警过，但是告警级别已经是严重了，插入一条记录并且发出告警
        //cacti_log('根据'. $topo["id"] .'没有查询到告警数据', false, 'SYSTEM');
        $save = array();
        $save["topo_id"] = $topo["id"];
        $save["local_graph_id"] = $local_data["local_graph_id"];
        $save["local_data_id"] = $local_data["local_data_id"];
        $save["alarm_time"] = $now;
        $save["alarm_status"] = 1;
        sql_save($save, "plugin_qunee_alarm");
        //cacti_log('保存告警结果', false, 'SYSTEM');
        // 发送告警邮件
        sendEmail($topo,$local_data,1);
    }
}

function sendEmail($topo,$local_data,$alarm_status){
    if($alarm_status == 0){ // 如果告警状态是已恢复
        //cacti_log('正在发送恢复邮件，发送联系人'.$topo["emails"].",topo_name=".$topo["name"]."，图形名称=".$local_data["title_cache"], false, 'SYSTEM');
        $msg = "拓扑:".$topo["name"]."<br>
                                            图形:".$local_data["title_cache"]."<br>
                                            消息:告警已经恢复";
        send_mail($topo["emails"],"","拓扑告警恢复",$msg,"","",true);
        //cacti_log('成功发送邮件', false, 'SYSTEM');
    }else {
        //cacti_log('正在发送告警邮件，发送联系人'.$topo["emails"].",topo_name=".$topo["name"]."，图形名称=".$local_data["title_cache"], false, 'SYSTEM');
        $msg = "拓扑:".$topo["name"]."<br>
                                            图形:".$local_data["title_cache"]."<br>
                                            消息:发生告警";
        send_mail($topo["emails"],"","拓扑发生告警",$msg,"","",true);
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
function getAlarmVal($in,$out,$comp){
    //cacti_log("in=".$in.",out=".$out.",comp=".$comp, false, 'SYSTEM');
    if($in >= ($comp * 0.9) || $out >= ($comp * 0.9)){
        //cacti_log("进入严重", false, 'SYSTEM');
        return 2; // 严重
    }else if($in >= ($comp * 0.8) || $out >= ($comp * 0.8)){
        //cacti_log("进入一般", false, 'SYSTEM');
        return 1; // 一般
    }else{
        return 0; // 无告警
    }
}