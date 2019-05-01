<?php
// Call the REDCap Connect file in the main "redcap" directory
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php"; 
$surveyQueueInterface = new \BCCHR\SurveyQueueInterface\SurveyQueueInterface();
$surveyQueueSettings = $surveyQueueInterface->generate_user_interface();
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";