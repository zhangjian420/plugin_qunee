<?php

function get_local_data_id($local_graph_id){
    $graph_local = db_fetch_row_prepared('SELECT * FROM graph_local WHERE id = ?',array($local_graph_id));
    $local_data_id = db_fetch_cell_prepared("select id from data_local
            where host_id = ? and snmp_query_id = ? and snmp_index = ? limit 1"
        ,array($graph_local["host_id"],$graph_local["snmp_query_id"],$graph_local["snmp_index"]));
    return $local_data_id;
}

/**
 * 返回的格式： 单位是 bit
 * array(
        "traffic_in" => value1,
        "traffic_out" => value2
    );
 * @return number[]
 */
function qunee_get_ref_value($local_data_id, $ref_time, $time_range){
    $result = rrdtool_function_fetch($local_data_id, $ref_time-$time_range, $ref_time, $time_range);
    
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
    
    return array(
        "traffic_in" => getUnitVal($iv * 8),
        "traffic_out" => getUnitVal($ov * 8)
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