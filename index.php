<?php
// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "functions.php";

$pid = $_GET["pid"];
$Proj = new Project();

require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";
?>
<!DOCTYPE HTML>
<html>
<body>
    <h4>Import/Export Survey Queue</h4>
    <?php if (SUPER_USER): ?>
        <?php if ($_GET["imported"] === "1"): ?>
            <div class="green">Survey Queue Imported</div>
            <br/>
        <?php endif; ?>
        <?php if ($Proj->project['surveys_enabled']): ?>
            <p><strong><span style="color:red">**IMPORTANT**</span> Make sure your survey queue settings correspond to the correct project.</strong></p>
            <form method="post" action="import_csv.php?pid=<?php print $pid;?>" enctype="multipart/form-data" style="border: 1px solid black; padding: 10px">
                <p>Select CSV to upload:</p>
                <input type="file" name="import_file">
                <p><input type="checkbox" name="has_headers"> The first row contains headers?</p>
                <button type="submit">Import CSV</button>
                <?php if (Survey::surveyQueueEnabled()): ?>
                    <button type="submit" formaction="export_csv.php?pid=<?php print $pid;?>">Export CSV</button>
                <?php else:?>
                    <button type="submit" formaction="export_csv.php?pid=<?php print $pid;?>" disabled>Export CSV</button>
                    <i>The survey queue has not been enabled, so there is nothing to export</i>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="red">Surveys have not been enabled on this project</div>
        <?php endif; ?>
    <?php else: ?>
        <div class="red">Only super admins have access to this plugin</div>
    <?php endif;?>
</body>
</html>
<?

require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";