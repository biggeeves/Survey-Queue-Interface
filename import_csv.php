<?php
// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "config.php";
require_once "functions.php";

if (!SUPER_USER)
{
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
    print "<div class='red' style='margin:20px 0;'>Only super admins have access to this plugin</div>";
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    exit;
}

$temp = $GLOBALS["temp_folder"];
if (empty($temp))
{
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
    print "<div class='red' style='margin:20px 0;'>config.php requires 'temp_folder' to be set in \$GLOBALS. You've either not done so, or forgot to create a config.php file.</div>";
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    exit;
}

$pid = $_GET["pid"];

// Move import file from temporary dir
if (isset($_FILES["import_file"]))
{
    $errors = array();

    $temp = $GLOBALS["temp_folder"];

    $filename = $_FILES["import_file"]["name"];
    $filesize = $_FILES["import_file"]["size"];
    $filetmp = $_FILES["import_file"]["tmp_name"];
    $ext = strtolower(end(explode(".", $filename)));

    if ($filesize == 0)
    {
        array_push($errors, "[ERROR] You must select a file to upload!");
    }
    else
    {
        if ($ext !== "csv")
        {
            array_push($errors, "[ERROR] You can only upload CSV files!"); 
        }
        else
        {
            if (move_uploaded_file($filetmp, "{$temp}{$filename}") === FALSE)
            {
                array_push($errors, "[ERROR] Unable to move file from temporary directory");
            }
            else
            {
                if (file_exists("{$temp}{$filename}"))
                {
                    $form_data = array();
                    $headers = array (
                        "survey_id",
                        "survey_form",
                        "event_id",
                        "event_name",
                        "arm_name",
                        "active",
                        "auto_start",
                        "condition_surveycomplete_event_id",
                        "conditional_event_name",
                        "conditional_arm_name",
                        "condition_surveycomplete_survey_id",
                        "conditional_survey_form",
                        "condition_andor",
                        "condition_logic"
                    );
                    $rownum = 1;
                    $file = fopen("{$temp}{$filename}", "r");
                    if ($file !== FALSE)
                    {
                        while (($row = fgetcsv($file, 1000, ",")) !== FALSE)
                        {
                            if (empty($_POST["has_headers"]) || $rownum != 1)
                            {
                                foreach ($row as $index => $value)
                                {
                                    if (strtoupper($value) == "NULL" || is_null($value))
                                    {
                                        $row[$index] = null;
                                    }
                                    else
                                    {
                                        $row[$index] = trim($value);
                                    }
                                }
                                
                                $arr = array_combine($headers, $row);
                                if ($arr === FALSE)
                                {
                                    array_push($errors, "[ERROR] Number of fields in row $rownum didn't match number of headers");
                                }
                                else
                                {
                                    array_push($form_data, array_combine($headers, $row));
                                }
                            }
                            $rownum++;
                        }

                        if (fclose($file) === FALSE)
                        {
                            array_push($errors, "[ERROR] Unable to close import file");
                        }

                        if (unlink("{$temp}{$filename}") === FALSE)
                        {
                            array_push($errors, "[ERROR] Unable to delete import file");
                        }

                        $Proj = new Project($pid);

                        $to_import = array();
                        $csvErrors = array();

                        $events = REDCap::getEventNames(true, true); // will return false if project isn't longitudinal
                        $instruments = REDCap::getInstrumentNames();

                        foreach($form_data as $index => $rule)
                        {    
                            /*
                            * Fields Needed:
                            * 
                            * survey_form
                            * event_name
                            * arm_name
                            * active
                            * auto_start
                            * conditional_event_name
                            * contidional_arm_name
                            * conditional_survey_form
                            * condition_andor
                            * condition_logic
                            * 
                            */
                            $rownum = empty($_POST["has_headers"]) ? $index+1 : $index+2;

                            $survey_form = null;
                            $event_name = "Event 1";
                            $arm_name = "Arm 1";

                            $cond_survey = null;
                            $conditional_survey_form = null;
                            $conditional_survey_id = null;
                            $conditional_event_name = "Event 1";
                            $conditional_arm_name = "Arm 1";
                            $condition_logic = null;

                            $and_or = null;
                            $auto_start = "0";
                            
                            $active = empty($rule["active"]) || ($rule["active"] !== "0" && $rule["active"] !== "1") ? "0" : $rule["active"];

                            if ($active === "1")
                            {
                                $survey_form = $rule["survey_form"];

                                $conditional_survey_form = $rule["conditional_survey_form"];

                                $condition_logic = $rule["condition_logic"];

                                $and_or = empty($rule["condition_andor"]) || (strtolower($rule["condition_andor"]) !== "and" && strtolower($rule["condition_andor"]) !== "or") ? "AND" : strtoupper($rule["condition_andor"]);

                                $auto_start = empty($rule["auto_start"]) || ($rule["auto_start"] !== "0" && $rule["auto_start"] !== "1") ? "0" : $rule["auto_start"];

                                if (empty($survey_form))
                                {
                                    array_push($csvErrors, "[ROW] $rownum [ERROR] row $rownum is missing its instrument name");
                                }
                                else if (!in_array($survey_form, array_keys($instruments)))
                                {
                                    array_push($csvErrors, "[ROW] $rownum [ERROR] $survey_form does not exist in project instruments");
                                }

                                if (!empty($conditional_survey_form) && !in_array($conditional_survey_form, array_keys($instruments)))
                                {
                                    array_push($csvErrors, "[ROW] $rownum [ERROR] $conditional_survey_form does not exist in project instruments");
                                }

                                $logic_errors = check_logic_errors($condition_logic);
                                if (!empty($logic_errors))
                                {
                                    foreach($logic_errors as $logic_error)
                                    {
                                        array_push($errors, "[ROW] $rownum [ERROR] conditional_logic - $logic_error");
                                    }
                                }
                                else
                                {
                                    $logic_errors = check_logic_events_and_fields($condition_logic);
                                    if (!empty($logic_errors))
                                    {
                                        foreach($logic_errors as $logic_error)
                                        {
                                            array_push($errors, "[ROW] $rownum [ERROR] conditional_logic - $logic_error");
                                        }
                                    }
                                }

                                if ($events !== FALSE)
                                {
                                    $event_name = $rule["event_name"];
                                    $arm_name = empty($rule["arm_name"]) ? "Arm 1" : $rule["arm_name"];
                                    $conditional_event_name = $rule["conditional_event_name"];
                                    $conditional_arm_name = empty($rule["conditional_arm_name"]) ? "Arm 1" : $rule["conditional_arm_name"];

                                    if (empty($event_name))
                                    {
                                        array_push($csvErrors, "[ROW] $rownum [ERROR] event name is missing");
                                    }
                                    else 
                                    {
                                        $eventAndArm = strtolower(str_replace(" ", "_", $event_name)) . "_" . strtolower(str_replace(" ", "_", $arm_name));
                                        if (!in_array($eventAndArm, $events))
                                        {
                                            array_push(
                                                $csvErrors, 
                                                "[ROW] $rownum [ERROR] $event_name does not exist in $arm_name (if the arm wasn't specified it will default to 'Arm 1'). Please check that both are correct."
                                            );
                                        }
                                    }

                                    if (!empty($conditional_event_name))
                                    {
                                        $condEventAndArm = strtolower(str_replace(" ", "_", $conditional_event_name)) . "_" . strtolower(str_replace(" ", "_", $conditional_arm_name));
                                        if (!in_array($condEventAndArm, $events))
                                        {
                                            array_push(
                                                $csvErrors, 
                                                "[ROW] $rownum [ERROR] $conditional_event_name does not exist in $conditional_arm_name (if the arm wasn't specified it will default to 'Arm 1'). Please check that both are correct."
                                            );
                                        }
                                    }
                                }
                            }
                            
                            if (empty($csvErrors))
                            {
                                $param = array(
                                    "survey_form" => $survey_form,
                                    "event_name" => $event_name,
                                    "arm_name" => $arm_name,
                                    "active" => $active,
                                    "auto_start" => $auto_start,
                                    "conditional_event_name" => $conditional_event_name,
                                    "conditional_arm_name" => $conditional_arm_name,
                                    "conditional_survey_form" => $conditional_survey_form,
                                    "condition_andor" => $and_or,
                                    "condition_logic" => trim($condition_logic)
                                );
                                array_push($to_import, $param);
                            }
                        }
                        $errors = array_merge($errors, $csvErrors);

                        if (empty($errors) && !empty($to_import))
                        {
                            $result = import_survey_queue($to_import, $pid);
                            if (empty($result))
                            {
                                array_push($errors, "[ERROR] Couldn't import survey queue to project.");
                            }
                            else
                            {
                                foreach ($result as $rowid)
                                {
                                    REDCap::logEvent(strtolower(USERID) . " inserted settings into redcap_surveys_queue where sq_id = $rowid");
                                }
                                header("Location: index.php?pid=$pid&imported=1");
                                exit;
                            }
                        }
                    }
                    else
                    {
                        array_push($errors, "[ERROR] Couldn't open $filename to import.");
                    }
                }
            }
        }
    }

    if (!empty($errors))
    {
        require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
        print "<div class='red' style='margin:20px 0;'>";
        foreach($errors as $error)
        {
            print "<p>$error</p>";
        }
        print "</div>";
        require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    }
}