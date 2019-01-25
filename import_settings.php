<?php
require_once "../../redcap_connect.php";
require_once "config.php";
require_once "functions.php";

$pid = $_GET["pid"];
$username = "survey_queue_api";

$apiurl = $GLOBALS["apiurl"];

$project_token  = get_api_token($username,$pid); // To Grab the API Token

if(empty($project_token))
{
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
    echo "<p class='red' style='margin:20px 0;'>No API user name (survey_queue_api)/token found on the project.</p>";
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    exit;
}

$apitoken = $project_token;

$form_data = $_POST["rules"];
$Proj = new Project($pid);

$to_import = array();

foreach($form_data as $rule)
{
    /*
     * Fields Needed:
     * 
     * survey_form
     * survey_id
     * event_name
     * event_id
     * arm_name
     * active
     * auto_start
     * conditional_event_name
     * contidional_arm_name
     * conditional_survey_form
     * condition_surveycomplete_survey_id
     * condition_andor
     * condition_logic
     * 
     */

    $survey_form = null;
    $event_name = null;
    $arm_name = null;

    $cond_survey = null;
    $conditional_survey_form = null;
    $conditional_survey_id = null;
    $conditional_event_name = null;
    $conditional_arm_name = null;
    $condition_logic = null;

    $and_or = null;
    $auto_start = "0";
    
    $active = empty($rule["active"]) ? "0" : "1";

    if ($active === "1")
    {
        $survey_form = $rule["survey"];

        $event_name = $rule["event_name"];

        $arm_name = $rule["arm_name"];

        if (!empty($rule["survey_checkbox"]))
        {
            $cond_survey = explode(",", $rule["cond_survey"]);
            $conditional_survey_form = $cond_survey[0];
            $conditional_survey_id = $Proj->forms[$conditional_survey_form]['survey_id'];

            $conditional_event_name = $cond_survey[1];
            $conditional_arm_name = $cond_survey[2];
        }

        if (!empty($rule["logic_checkbox"]))
        {
            $condition_logic = $rule["logic"];
        }

        $and_or = $rule["and_or"];
        $auto_start = empty($rule["auto_start"]) ? "0" : "1";
    }

    $param = array(
        "survey_form" => $survey_form,
        "event_name" => $event_name,
        "arm_name" => $arm_name,
        "active" => $active,
        "auto_start" => $auto_start,
        "conditional_event_name" => $conditional_event_name,
        "conditional_arm_name" => $conditional_arm_name,
        "conditional_survey_form" => $conditional_survey_form,
        "condition_surveycomplete_survey_id" => $conditional_survey_id,
        "condition_andor" => $and_or,
        "condition_logic" => trim($condition_logic)
    );

    if (!empty($param))
    {
        array_push($to_import, $param);
    }
}

$params = array(
    "content" => "surveyqueuesettings",
    "format" => "json",
    "token" => $apitoken,
    "data" => json_encode($to_import)
);
$result = redcap_api($apiurl, $params);

header("Location: index.php?pid=$pid&saved=1");