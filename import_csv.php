<?php
$surveyQueueInterface = new \BCCHR\SurveyQueueInterface\SurveyQueueInterface();
$errors = $surveyQueueInterface->import_survey_queue_csv();

if (!empty($errors))
{
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
    print "<div class='red' style='margin:20px 0;'>";
    foreach($errors as $error)
    {
        print "<p>$error</p>";
    }
    print "</div>";
    require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
}
else
{
    header("Location: " . $surveyQueueInterface->getUrl("index.php") . "&imported=1");
}