<?php
// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "config.php";
require_once "functions.php";

$pid = $_GET["pid"];
$username = "survey_queue_api";

$apiurl = $GLOBALS["apiurl"];
                                
$project_token  = get_api_token($username,$pid); // To Grab the API Token

require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

if(empty($project_token))
{
    echo "<p class='red' style='margin:20px 0;'>No API user name (survey_queue_api)/token found on the project.</p>";
    require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
    exit;
}

$apitoken = $project_token;

$params = array(
    "content" => "surveyqueuesettings",
    "format" => "json",
    "token" => $apitoken
);

$surveyQueueSettings = redcap_api($apiurl, $params);
?>
<!DOCTYPE HTML>
<html>
<head>
    <style>
        table {
            width: 100%;
        }

        table, th, td 
        {
            border: 1px solid black;
            padding: 10px;
        }

        textarea {
            max-width: 500px;
        }
    </style>
</head>
<body>
    <?php if ($_GET["saved"] === "1"): ?>
        <div class="green">
            Survey Queue Settings Saved
        </div>
        <br/><br/>
    <?php endif; ?>

    <form action="import_settings.php?pid=<?php print $pid; ?>" method="post">
        <table>
            <tbody>
                <?php foreach($surveyQueueSettings as $index => $setting): ?>
                    <?php if (!array_key_exists("survey_queue_custom_text", $setting)): ?>
                        <tr>
                        <td>
                            Active?
                            <?php if ($setting["active"] = "0"): ?>
                                <input name="rules[<?php print $index;?>][active]" type="checkbox">
                            <?php else:?>
                                <input name="rules[<?php print $index;?>][active]" type="checkbox" checked>
                            <?php endif;?>
                        </td>
                        <td>
                            <strong>Survey:</strong> <input name="rules[<?php print $index;?>][survey]" value="<?php print $setting["survey_form"]?>" readonly style="width:70%;border:none;">
                            <br/>
                            <strong>Event:</strong> <input name="rules[<?php print $index;?>][event_name]" value="<?php print $setting["event_name"]?>" readonly style="width:70%;border:none;">
                            <br/>
                            <strong>Arm:</strong> <input name="rules[<?php print $index;?>][arm_name]" value="<?php print $setting["arm_name"]?>" readonly style="width:70%;border:none;">
                        </td>
                        <td>
                            <div>
                                <div style="text-indent:-1.9em;margin-left:1.9em;padding:1px 0;">
                                    <?php if (empty($setting["conditional_survey_form"])): ?>
                                        <input name="rules[<?php print $index;?>][survey_checkbox]" type="checkbox">
                                    <?php else: ?>
                                        <input name="rules[<?php print $index;?>][survey_checkbox]" type="checkbox" checked>
                                    <?php endif; ?>
                                    When the following survey is completed:
                                    <br>
                                    <select name="rules[<?php print $index;?>][cond_survey]" class="x-form-text x-form-field" style="font-size:11px;width:100%;max-width:360px;">
                                        <option value="">--- select survey ---</option>
                                        <?php
                                            $params = array(
                                                "token" => $apitoken,
                                                "content" => "formEventMapping",
                                                "format" => "json"
                                            );
                                            $instr_event_mappings = redcap_api($apiurl, $params);

                                            if (!empty($instr_event_mappings))
                                            {
                                                foreach($instr_event_mappings as $i => $mapping)
                                                {
                                                    $arm_num = $mapping["arm_num"];
                                                    $unique_event_name = str_replace("_arm_$arm_num", "", $mapping["unique_event_name"]);
                                                    $form = $mapping["form"];
                                                    if ($setting["conditional_event_name"] === ucwords(str_replace("_", " ", $unique_event_name)))
                                                    {
                                                        print "<option value='$form,$unique_event_name,Arm $arm_num' selected='selected'>$form - $unique_event_name</option>";
                                                    }
                                                    else
                                                    {
                                                        print "<option value='$form,$unique_event_name,Arm $arm_num'>$form - $unique_event_name</option>";
                                                    }
                                                }
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div style="padding:2px 0 1px;">
                                    <select name="rules[<?php print $index;?>][and_or]" style="font-size:11px;">
                                        <?php if ($setting["condition_andor"] == "AND"): ?>
                                            <option value="AND" selected="selected">AND</option>
                                            <option value="OR">OR</option>
                                        <?php else: ?>
                                            <option value="AND">AND</option>
                                            <option value="OR" selected="selected">OR</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div style="text-indent:-1.9em;margin-left:1.9em;">
                                    <?php if (empty($setting["condition_logic"])) : ?>
                                        <input name="rules[<?php print $index;?>][logic_checkbox]" type="checkbox">
                                    <?php else:?>
                                        <input name="rules[<?php print $index;?>][logic_checkbox]" type="checkbox" checked>
                                    <?php endif; ?>
                                    When the following logic becomes true:
                                    <a href="javascript:;" class="opacity65" style="margin-left:50px;text-decoration:underline;font-size:10px;" onclick="helpPopup('ss69')">How to use this</a>
                                    <br>
                                    <textarea
                                        id="cond-logic-<?php print $index;?>"
                                        name="rules[<?php print $index;?>][logic]" 
                                        class="x-form-field" 
                                        style="line-height:12px;font-size:11px;width:100%;max-width:350px;height:32px;resize:both;"
                                        ><?php print $setting["condition_logic"]; ?></textarea>
                                    <br>
                                    <span style="font-family:tahoma;font-size:10px;color:#888;">
                                        (e.g., [enrollment_arm_1][age] &gt; 30 and [enrollment_arm_1][gender] = "1")
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            Auto start?
                            <?php if (empty($setting["auto_start"])) : ?>
                                <input name="rules[<?php print $index;?>][auto_start]" type="checkbox">
                            <?php else: ?>
                                <input name="rules[<?php print $index;?>][auto_start]" type="checkbox" checked>
                            <?php endif; ?>
                        </td>
                        </tr>
                    <?php endif ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <br/>
        <button type="submit">Save Settings</button>
    </form>
</body>
</html>
<?

require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";
