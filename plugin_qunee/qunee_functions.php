<?php

// 获取数据源
function get_local_data($local_graph_id){
    //$graph_local = db_fetch_row_prepared('SELECT * FROM graph_local WHERE id = ?',array($local_graph_id));
    //$local_data_id = db_fetch_cell_prepared("select id from data_local
    //        where host_id = ? and snmp_query_id = ? and snmp_index = ? limit 1"
    //    ,array($graph_local["host_id"],$graph_local["snmp_query_id"],$graph_local["snmp_index"]));
    $local_data = db_fetch_row_prepared("select dl.id as local_data_id ,gtg.upper_limit
        from data_local dl
        left join graph_local gl
        on dl.host_id = gl.host_id and dl.snmp_query_id = gl.snmp_query_id 
        and dl.snmp_index = gl.snmp_index
        left join graph_templates_graph gtg on gl.id = gtg.local_graph_id
        where gl.id = ? limit 1",array($local_graph_id));
    return $local_data;
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
        $iv = max($result['values'][$idx_in]);
    }
    if (!isset($result['values'][$idx_out]) || count($result['values'][$idx_out]) == 0) {
        $ov = 0;
    }else{
        $ov = max($result['values'][$idx_out]);
    }
    if($iv == 0 && $ov == 0){
        return array();
    }
    return array(
        "traffic_in" => getUnitVal($iv * 8),
        "traffic_out" => getUnitVal($ov * 8),
        "alarm_level" => getAlarmVal($iv,$ov,$local_data["upper_limit"])
    );
}

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

function getAlarmVal($in,$out,$comp){
    if($in >= ($comp * 0.9) || $out >= ($comp * 0.9)){
        return 2; // 严重
    }else if($in >= ($comp * 0.8) || $out >= ($comp * 0.8)){
        return 1; // 一般
    }else{
        return 0; // 无告警
    }
}