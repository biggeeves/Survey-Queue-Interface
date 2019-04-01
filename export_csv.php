<?php
// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "config.php";
require_once "functions.php";

if (!SUPER_USER)
{
    require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
    print "<div class='red' style='margin:20px 0;'>Only super admins have access to this plugin</div?";
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    exit;
}

$pid = $_GET["pid"];
$errors = array();

// Get survey queue settings
$surveyQueueSettings = export_survey_queue($pid);

if (empty($surveyQueueSettings))
{
    array_push($errors, "Error exporting survey queue. " . $surveyQueueSettings["error"]);
}
else
{
    $folder = $GLOBALS["temp_folder"];
    $filename = "{$folder}survey_queue_settings_pid_$pid.csv";
    $file = fopen($filename, "w");

    if ($file === FALSE)
    {
        array_push($errors, "Error opening $filename to write.");
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
            array_push($errors, "Error exporting survey queue headers.");
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
                        array_push($row, "NULL");
                    }
                    else
                    {
                        array_push($row, $headerValue);
                    }
                }

                if (!empty($row))
                {
                    if (fputcsv($file, $row, $separator) === FALSE)
                    {
                        array_push($errors, "Error exporting row (" . implode(", ", $row). ")");
                    }
                }
            }

            if (fclose($file) === FALSE)
            {
                array_push($errors, "Error closing $filename.");
            }
            else if (empty($errors))
            {
                if (file_exists($filename)) {
                    $filesize = filesize($filename);
                    
                    if ($filesize === FALSE)
                    {
                        array_push($errors, "Error retrieving size of $filename for download.");
                    }
                    else
                    {
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="'.basename($filename).'"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . $filesize);
                        readfile($filename);
                    }
                }
            }
        }

        if (unlink($filename) === FALSE)
        {
            array_push($errors, "Error deleting export file");
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