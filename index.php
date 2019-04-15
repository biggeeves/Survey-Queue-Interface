<?php
// Call the REDCap Connect file in the main "redcap" directory
$surveyQueueInterface = new \BCCHR\SurveyQueueInterface\SurveyQueueInterface();
$surveyQueueSettings = $surveyQueueInterface->generate_user_interface();