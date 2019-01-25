<?php

/*Function: To call the REDCap API */
function redcap_api($api_url,$export_project_info_data)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_VERBOSE,1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_AUTOREFERER, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($export_project_info_data, '', '&'));
    $output = curl_exec($ch);
    $output_json = json_decode($output,true);
    return $output_json;
    curl_close($ch);
}

function get_api_token($username,$pid)
{
  	$sql = "SELECT api_token FROM redcap_user_rights where project_id = '$pid' and username = '$username';";
	$result  = db_query($sql);		   
	$result_fetch = db_fetch_array($result);
    $token = $result_fetch["api_token"];
	return $token;
}