<?php

namespace BCCHR\SurveyQueueInterface;

use Project;
use REDCap;

class SurveyQueueInterface extends \ExternalModules\AbstractExternalModule
{    
    function __construct() 
    {
        parent::__construct();
    }
    
    private function export_survey_queue()
    {
        $details = array();
        $project_id = $this->getProjectId();
        $sql = "SELECT 
                    rs.form_name as survey_form,
                    em.descrip as event_name,
                    ea.arm_num  as arm_num,
                    sq.active,
                    sq.auto_start,
                    em1.descrip as conditional_event_name,
                    ea1.arm_num as conditional_arm_num,
                    rs2.form_name as conditional_survey_form,
                    sq.condition_andor,
                    sq.condition_logic
                    FROM redcap_surveys_queue sq
                    LEFT JOIN redcap_surveys rs ON sq.survey_id = rs.survey_id
                    LEFT JOIN redcap_projects rp ON rp.project_id = rs.project_id
                    LEFT JOIN redcap_events_metadata em ON em.event_id  = sq.event_id
                    LEFT JOIN redcap_events_arms ea ON ea.arm_id = em.arm_id
                    LEFT JOIN redcap_surveys rs2 ON sq.condition_surveycomplete_survey_id = rs2.survey_id
                    LEFT JOIN redcap_events_metadata em1 ON sq.condition_surveycomplete_event_id = em1.event_id
                    LEFT JOIN redcap_events_arms ea1 ON ea1.arm_id = em1.arm_id
                    where rp.project_id = $project_id
                    ;";
                            
        $result = $this->query($sql);
        while($r = db_fetch_assoc($result))
        {
            $details[] = $r;
        }
        return $details;
    }

    private function import_survey_queue($data_array)
    {
        $result = array();
        $project_id = $this->getProjectId();
        $Proj = new Project($project_id);	
        
        // Delete old entries from table (if in table)
        $sql = "delete from redcap_surveys_queue where survey_id IN (SELECT survey_id FROM redcap_surveys where project_id = $project_id)";
        $q = $this->query($sql);
            
        for($i=0; $i < count($data_array); $i++)
        {
            $survey_form = $data_array[$i]["survey_form"];
            $survey_id = $Proj->forms[$survey_form]["survey_id"];
                    
            $event_name = $data_array[$i]["event_name"];
            $arm_num = $data_array[$i]["arm_num"];
            $unique_event_name = strtolower(str_replace(" ","_",$event_name)."_".str_replace(" ","_",$arm_num)); // Generate Event Name

            $event_id = $Proj->getEventIdUsingUniqueEventName($unique_event_name);

            $active = $data_array[$i]["active"];
            $autoStart = $data_array[$i]["auto_start"];
                    
            $condition_event_name = $data_array[$i]["conditional_event_name"];
            $condition_event_arm_num = $data_array[$i]["conditional_arm_num"];
            $unique_event_name = strtolower(str_replace(" ","_",$condition_event_name)."_".str_replace(" ","_",$condition_event_arm_num)); // Generate Event Name
            $surveyCompEventId = $Proj->getEventIdUsingUniqueEventName($unique_event_name);
                    
            $condition_survey_form = $data_array[$i]["conditional_survey_form"];
            $surveyCompSurveyId = $Proj->forms[$condition_survey_form]['survey_id'];
                    
            $andOr = $data_array[$i]["condition_andor"];  
            $conditionLogic = $data_array[$i]["condition_logic"];
                    
            if(!empty($survey_id) OR !empty($survey_form))
            {
                $sql = "insert into redcap_surveys_queue (survey_id, event_id, active, condition_surveycomplete_survey_id, condition_surveycomplete_event_id,
                        condition_andor, condition_logic, auto_start) values
                        ('".db_escape($survey_id)."', '".db_escape($event_id)."', $active, ".checkNull($surveyCompSurveyId).", ".checkNull($surveyCompEventId).", '$andOr',
                        ".checkNull($conditionLogic).", $autoStart)
                        on duplicate key update active = $active, condition_surveycomplete_survey_id = ".checkNull($surveyCompSurveyId).",
                        condition_surveycomplete_event_id = ".checkNull($surveyCompEventId).", condition_andor = '$andOr',
                        condition_logic = ".checkNull($conditionLogic).", auto_start = $autoStart";
                    
                $q = $this->query($sql);
                $result[] = db_insert_id();
            }
        } 	
        return $result;
    }

    private function get_first_arm_num()
    {
        $arm_num = '';
        $project_id = $this->getProjectId();
        $sql = "SELECT arm_num from redcap_events_arms where project_id = $project_id order by arm_num ASC LIMIT 1;";
        $result = $this->query($sql);
        while($r = db_fetch_assoc($result))
        {
            $arm_num = "Arm " . $r["arm_num"];
        }
        return $arm_num;
    }

    // PHP translated version of the JS function that's used in REDCap's survey queue interface
    // Is not very extensive
    private function check_logic_errors($logic)
    {
        $errors = array();
        
        if (!empty($logic))
        {
            // Must have at least one [ or ]
            if (strpos($logic, "[") === FALSE && strpos($logic, "]") === FALSE)
            {
                array_push($errors, "Square brackets are missing. You have either not included any variable names in the logic or you have forgotten to put square brackets around the variable names.");
            }

            // If longitudinal and forcing event notation for fields, then must be referencing events for variable names
            if (REDCap::isLongitudinal() && (sizeOf(explode("][", $logic)) <= 1
                || (sizeOf(explode("][", $logic)) - 1) * 2  != (sizeOf(explode("[", $logic)) - 1)
                || (sizeOf(explode("][", $logic)) - 1) * 2  != (sizeOf(explode("]", $logic)) - 1))
                ) 
            {
                array_push(
                    $errors,
                    "One or more fields are not referenced by event. Since this is a longitudinal project, you must specify the unique event name
                    when referencing a field in the logic. For example, instead of using [age], you must use [enrollment_arm_1][age],
                    assuming that enrollment_arm_1 is a valid unique event name in your project. You can find a list of all your project's
                    unique event names on the Define My Events page."
                );
            }

            // Check symmetry of "
            if ((sizeof(explode("\"", $logic)) - 1) % 2 > 0)
            {
                array_push($errors, "Odd number of double quotes exist");
            }

            // Check symmetry of '
            if ((sizeof(explode("'", $logic)) - 1) % 2 > 0)
            {
                array_push($errors, "Odd number of single quotes exist");
            }

            // Check symmetry of [ with ]
            if (sizeof(explode("[", $logic)) != sizeof(explode("]", $logic)))
            {
                array_push($errors, "Square bracket is missing");
            }

            // Check symmetry of ( with )
            if (sizeof(explode("(", $logic)) != sizeof(explode(")", $logic)))
            {
                array_push($errors, "Parenthesis bracket is missing");
            }

            // Make sure does not contain $ dollar signs
            if (strpos($logic, "$") !== FALSE)
            {
                array_push($errors, "Illegal use of dollar sign ($). Please remove.");
            }

            // Make sure does not contain ` backtick character
            if (strpos($logic, "`") !== FALSE)
            {
                array_push($errors, "Illegal use of backtick character (`). Please remove.");
            }
        }

        return $errors;
    }

    private function check_logic_events_and_fields($logic)
    {
        $errors = array();

        if (!empty($logic))
        {
            $events = REDCap::getEventNames(true, true); // If there are no events (the project is classical), the method will return false
            $fields = REDCap::getFieldNames();

            // Get all occurences of an opening square bracket "["
            $lastPos = 0;
            $openingBrackets = array();
            while (($lastPos = strpos($logic, "[", $lastPos)) !== FALSE)
            {
                array_push($openingBrackets, $lastPos);
                $lastPos = $lastPos + strlen("[");
            }

            if (!empty($openingBrackets))
            {
                // Get the event/field name between each opening and closing bracket, and check if it exists
                foreach($openingBrackets as $bracket)
                {
                    $startPos = $bracket+1;
                    $closingBracket = strpos($logic, "]", $startPos);
                    if ($closingBracket !== FALSE)
                    {
                        $var = substr($logic, $startPos, $closingBracket - $startPos);

                        if ($events === FALSE && !in_array($var, $fields))
                        {
                            array_push($errors, "$var is not a valid field in this project");
                        }
                        else if (!in_array($var, $fields) && !in_array($var, $events))
                        {
                            array_push($errors, "$var is not a valid event/field in this project");
                        }
                    }
                    else
                    {
                        array_push($errors, "Square bracket is missing");
                    }
                }
            }
            else
            {
                array_push($errors, "You have either not included any variable names in the logic or you have forgotten to put square brackets around the variable names.");
            }
        }

        return $errors;
    }

    public function download_survey_queue_csv()
    {
        $errors = array();

        $pid = $this->getProjectId();
        
        $surveyQueueSettings = $this->export_survey_queue();

        if (empty($surveyQueueSettings))
        {
            $errors[] = "Nothing was found in the survey queue. Please make sure it is enabled";
        }
        else
        {
            $filename = "survey_queue_settings_pid_$pid.csv";

            header('Content-Description: File Transfer');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="'.basename($filename).'"');

            $file = fopen("php://output", "w");

            if ($file === FALSE)
            {
                $errors[] = "Error opening $filename to write.";
            }
            else
            {
                $separator = ",";
                $headers = array (
                    "survey_form",
                    "event_name",
                    "arm_num",
                    "active",
                    "auto_start",
                    "conditional_event_name",
                    "conditional_arm_num",
                    "conditional_survey_form",
                    "condition_andor",
                    "condition_logic",
                );

                // Write headers to file
                if (fputcsv($file, $headers, $separator) === FALSE)
                {
                    $errors[] = "Error exporting survey queue headers.";
                }
                else
                {
                    // Loop through each setting and write it to CSV file
                    foreach($surveyQueueSettings as $index => $setting)
                    {
                        $row = array();
                        foreach($headers as $header)
                        {
                            $headerValue = $setting[$header];
                            if (is_null($headerValue))
                            {
                                $row[] = "NULL";
                            }
                            else
                            {
                                $row[] = $headerValue;
                            }
                        }

                        if (!empty($row))
                        {
                            if (fputcsv($file, $row, $separator) === FALSE)
                            {
                                $errors[] = "Error exporting row (" . implode(", ", $row). ")";
                            }
                        }
                    }

                    if (fclose($file) === FALSE)
                    {
                        $errors[] = "Error closing $filename.";
                    }
                }
            }
        }

        return $errors;
    }

    public function import_survey_queue_csv()
    {
        $errors = array();

        $pid = $this->getProjectId();

        // Move import file from temporary dir
        if (isset($_FILES["import_file"]))
        {
            $err = $_FILES["import_file"]["error"];
            $filename = $_FILES["import_file"]["name"];
            $filetmp = $_FILES["import_file"]["tmp_name"];

            if ($err != UPLOAD_ERR_OK)
            {
                switch($err) 
                {
                    case UPLOAD_ERR_NO_FILE:
                        $errors[] = "[ERROR] No file was sent!";
                        break;
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errors[] = "[ERROR] Exceeded filsize limit with upload!";
                        break;
                    default:
                        $errors[] = "[ERROR] Unknown error uploading file!";
                        break;
                }
            }
            else
            {
                $allowed_mime_types = array("text/comma-separated-values", "text/csv", "application/csv", "application/excel", "application/vnd.ms-excel", "application/vnd.msexcel", "text/anytext", "text/plain");
                $mime = mime_content_type($filetmp);
                if (!in_array($mime, $allowed_mime_types))
                {
                    $errors[] = "[ERROR] Type of file isn't allowed! You can only upload CSV files!"; 
                }
                else if (file_exists($filetmp))
                {
                    $form_data = array();
                    $headers = array (
                        "survey_form",
                        "event_name",
                        "arm_num",
                        "active",
                        "auto_start",
                        "conditional_event_name",
                        "conditional_arm_num",
                        "conditional_survey_form",
                        "condition_andor",
                        "condition_logic"
                    );
                    $rownum = 1;
                    $file = fopen($filetmp, "r");
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
                                    $errors[] = "[ERROR] Number of fields in row $rownum didn't match number of headers";
                                }
                                else
                                {
                                    $form_data[] = $arr;
                                }
                            }
                            $rownum++;
                        }

                        if (fclose($file) === FALSE)
                        {
                            $errors[] = "[ERROR] Unable to close import file";
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
                            $arm_num = "Arm 1";

                            $cond_survey = null;
                            $conditional_survey_form = null;
                            $conditional_event_name = "Event 1";
                            $conditional_arm_num = "Arm 1";
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
                                    $csvErrors[] = "[ROW] $rownum [ERROR] row $rownum is missing its instrument name";
                                }
                                else if (!in_array($survey_form, array_keys($instruments)))
                                {
                                    $csvErrors[] = "[ROW] $rownum [ERROR] $survey_form does not exist in project instruments";
                                }

                                if (!empty($conditional_survey_form) && !in_array($conditional_survey_form, array_keys($instruments)))
                                {
                                    $csvErrors[] = "[ROW] $rownum [ERROR] $conditional_survey_form does not exist in project instruments";
                                }

                                $logic_errors = $this->check_logic_errors($condition_logic);
                                if (!empty($logic_errors))
                                {
                                    foreach($logic_errors as $logic_error)
                                    {
                                        $errors[] = "[ROW] $rownum [ERROR] conditional_logic - $logic_error";
                                    }
                                }
                                else
                                {
                                    $logic_errors = $this->check_logic_events_and_fields($condition_logic);
                                    if (!empty($logic_errors))
                                    {
                                        foreach($logic_errors as $logic_error)
                                        {
                                            $errors[] = "[ROW] $rownum [ERROR] conditional_logic - $logic_error";
                                        }
                                    }
                                }

                                if ($events !== FALSE)
                                {
                                    $first_arm = $this->get_first_arm_num();
                                    $event_name = $rule["event_name"];
                                    $arm_num = empty($rule["arm_num"]) ? $first_arm : "Arm " . $rule["arm_num"];
                                    $conditional_event_name = $rule["conditional_event_name"];
                                    $conditional_arm_num = empty($rule["conditional_arm_num"]) ? $first_arm : "Arm " . $rule["conditional_arm_num"];

                                    if (empty($event_name))
                                    {
                                        $csvErrors[] = "[ROW] $rownum [ERROR] event name is missing";
                                    }
                                    else 
                                    {
                                        $eventAndArm = strtolower(str_replace(" ", "_", $event_name)) . "_" . strtolower(str_replace(" ", "_", $arm_num));
                                        if (!in_array($eventAndArm, $events))
                                        {   
                                            $csvErrors[] = "[ROW] $rownum [ERROR] $event_name does not exist in $arm_num (if the arm wasn't specified it will default to the first arm). Please check that both are correct.";
                                        }
                                    }

                                    if (!empty($conditional_event_name))
                                    {
                                        $condEventAndArm = strtolower(str_replace(" ", "_", $conditional_event_name)) . "_" . strtolower(str_replace(" ", "_", $conditional_arm_num));
                                        if (!in_array($condEventAndArm, $events))
                                        {
                                            $csvErrors[] = "[ROW] $rownum [ERROR] $conditional_event_name does not exist in $conditional_arm_num (if the arm wasn't specified it will default to the first arm). Please check that both are correct.";
                                        }
                                    }
                                }
                            }
                            
                            if (empty($csvErrors))
                            {
                                $param = array(
                                    "survey_form" => $survey_form,
                                    "event_name" => $event_name,
                                    "arm_num" => $arm_num,
                                    "active" => $active,
                                    "auto_start" => $auto_start,
                                    "conditional_event_name" => $conditional_event_name,
                                    "conditional_arm_num" => $conditional_arm_num,
                                    "conditional_survey_form" => $conditional_survey_form,
                                    "condition_andor" => $and_or,
                                    "condition_logic" => trim($condition_logic)
                                );
                                $to_import[] = $param;
                            }
                        }
                        $errors = array_merge($errors, $csvErrors);

                        if (empty($errors) && !empty($to_import))
                        {
                            $result = $this->import_survey_queue($to_import);
                            if (empty($result))
                            {
                                $errors[] = "[ERROR] Couldn't import survey queue to project.";
                            }
                            else
                            { 
                                foreach($result as $rowid)
                                {
                                    REDCap::logEvent(strtolower(USERID) . " inserted settings into redcap_surveys_queue where sq_id = $rowid");
                                }
                            }
                        }
                    }
                    else
                    {
                        $errors[] = "[ERROR] Couldn't open $filename to import.";
                    }
                }
            }

            return $errors;
        }
    }

    public function generate_user_interface()
    {
        $Proj = new Project();
        ?>
        <!DOCTYPE HTML>
        <html>
        <body>
            <h4>Import/Export Survey Queue</h4>
            <?php if ($_GET["imported"] === "1"): ?>
                <div class="green">Survey Queue Imported</div>
                <br/>
            <?php endif; ?>
            <p><strong><span style="color:red">**IMPORTANT**</span> Make sure your survey queue settings correspond to the correct project.</strong></p>
            <h5>Instructions</h4>
            <p>The csv import requires <strong>all</strong> the following columns in the <strong>below order</strong>, the same columns the csv export will contain:</p>
            <ul>
                <li>survey_form - Unique name of survey in the queue</li>
                <li>event_name - Name of survey's event</li>
                <li>arm_num - Arm number the survey resides in</li>
                <li>active - Is the form active in the queue? </li>
                <li>auto_start - Used to take the participant immediately to the first incomplete survey in the queue if 'auto start' is enabled for that survey</li>
                <li>conditional_event_name - conditional_survey_form event name</li>
                <li>conditional_arm_num - conditional_survey_form arm number</li>
                <li>conditional_survey_form - Display survey in the queue when this is complete</li>
                <li>condition_andor - Display survey in queue when conditional_survey_form is complete <strong>and|or</strong> condition logic is true</li>
                <li>condition_logic - Display survey when conditional logic is true</li>
            </ul>
            <form method="post" action=<?php print $this->getUrl("import_csv.php");?> enctype="multipart/form-data" style="border: 1px solid black; padding: 10px">
                <?php if ($Proj->project['surveys_enabled']): ?>
                    <p>Select CSV to upload:</p>
                    <input type="file" name="import_file">
                    <p><input type="checkbox" name="has_headers"> The first row contains headers?</p>
                    <button type="submit">Import CSV</button>
                    <button type="submit" formaction=<?php print $this->getUrl("export_csv.php");?>>Export CSV</button>
                <?php else: ?>
                    <p><i>You must enable surveys before using this external module</i></p>
                <?php endif; ?>
            </form>
        </body>
        </html>
        <?php
    }
}