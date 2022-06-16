<?php

/****  PLEASE READ THIS ****/
/* script.php origignally created by user5363, modified by Sanyastik */
/* https://community.cloudflare.com/t/bulk-delete-dns-record/89540/13 */
/* WordPress instance required. Place this script in root of WP app */
/* After execution, do not forget to remove this script from Server */
/* Launch it from Terminal by command $php script.php */


//Control parameters block, <zoneid>, limit and production mode status.
$zoneid = array(
    "zone1", 
    "zone2", 
    "zone3"
);
$limit = 3000;
$productionMode = true;

//<apikey>, <apiemail> control below
//Arguments function, so we could call it multiple times without duplication
function args(&$method = 'GET'){
    $apikey = 'InputApiKeyHere';
    $apiemail = 'PasteEmailHere';
    $args = array(
        'method' => $method,
        'headers' => array(
            'X-Auth-Key' => $apikey,
            'X-Auth-Email' => $apiemail,
            'Content-Type' => 'application/json',
        ),
        'body' => ''
    );
return $args;
}

//remote_request function sender, removing duplications
function request($zoneid){
    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zoneid . '/dns_records';
    $response = wp_remote_request($url, args());
    $res = json_decode($response['body']);
    return $res;
}

//main function to handle all active output and that called request() and args() functions
function worker($zoneid, $limit, $productionMode = false){
    $res = request($zoneid);

    echo $res->{'result'}[0]->{'zone_name'}."\n";
    $total_amount = $res->result_info->total_count;
    echo "Number of records in Domain: ".$total_amount."\n";

    if ($productionMode) {
        //introduced limit parameter for Debug purposes
        $checklimit = 0;

        //progress bar preparations
        @ob_start();
        $shell = system("tput cols");
        @ob_end_clean();
       
        //the only limitation to prevent output of bars more then shell length 
        $progress_bar_step = $total_amount/$shell;
        $progress_bar_tracker = 0;
        do{
        foreach ($res->result as $r) {
            //introduced limitation handler for Debug purposes
            if ( $checklimit >= $limit ){ break; } $checklimit++;

            //progress bar draw
            if ( $progress_bar_step <= $progress_bar_tracker ) {
                    echo "█"; $progress_bar_tracker=0; 
            }
            $progress_bar_tracker++;

            //deleter
            $url = 'https://api.cloudflare.com/client/v4/zones/' . $zoneid . '/dns_records/' . $r->id;
            wp_remote_request($url, args($method = $productionMode ? 'DELETE' : 'GET'));
        }
        $res = request($zoneid);
        echo "\n"."Number of records in Domain after clearance: ".$res->result_info->total_count."\n";
        //if domain have more then 100 records, loop 
        }while($res->result_info->total_count!=0);
    }
    /* Debug block */
    if (!$res->{'success'}) {
        //res is the Object, so fields are requested by {''}. errors is the array() inside of res object{''} and [0] is the object{''} inside of errors array()
        echo 'Sorry Bro, it is error: ' . $res->{'errors'}[0]->{'message'} ? $res->{'errors'}[0]->{'message'} : var_export($res);
        echo "\n";
    }
    /* Debug block END */

    return $productionMode ? 'prod mode passed'."\n"."\n" : 'test mode passed'."\n"."\n";
}

define('WP_USE_THEMES', false);
//Here we call WP core to use it's wp_remote_request() function. But we don't want to load Theme files
include_once('wp-load.php');
//Regarding that Operational time for that script equals to time to interact with CloudFlare server, the script must have no time limits
set_time_limit(0);

echo "Total amount of domains to cleanup: ".count($zoneid)."\n";

//Cycle For purposed to be usefull with few domains in one account
for ($i = 0; $i < count($zoneid); $i++) {
    echo "Domain №".($i + 1) . "\n";
    echo worker($zoneid[$i], $limit, $productionMode);
}

echo "Script over." . "\n";
