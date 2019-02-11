<?php

function export_survey_queue($project_id)
{
	$details = array();
    if (isset($project_id) && $project_id!=='') 
    {
        $sql = "SELECT 
			        sq.survey_id,
					rs.form_name as survey_form,
					sq.event_id,
					em.descrip as event_name,
					ea.arm_name as arm_name,
					ea.arm_num  as arm_num,
					sq.active,
					sq.auto_start,
					sq.condition_surveycomplete_event_id,
					em1.descrip as conditional_event_name,
					ea1.arm_name as conditional_arm_name,
					ea1.arm_num as conditional_arm_num,
					sq.condition_surveycomplete_survey_id,
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
                        
        $result = db_query($sql);
        while($r = db_fetch_assoc($result))
        {
			$details[] = $r;
		}
			
		$sql = "select survey_queue_custom_text from redcap_projects where project_id = $project_id";
		$result = db_query($sql);  
        while($r2 = db_fetch_assoc($result))
        {	
			$details[] = $r2;
		}	
    }
    return $details;
}

function import_survey_queue($data_array,$project_id)
{
   $Proj = new Project($project_id);	
   $result = array();
   
   // Delete old entries from table (if in table)
   $sql = "delete from redcap_surveys_queue where survey_id IN (SELECT survey_id FROM redcap_surveys where project_id = $project_id)";
   $q = db_query($sql);
   
   for($i=0; $i < count($data_array); $i++)
   {
        $survey_form  		 = $data_array[$i]["survey_form"];
        $survey_id             = $Proj->forms[$survey_form]['survey_id'];
        
        $event_name  			 = $data_array[$i]["event_name"];
        $arm_name    			 = $data_array[$i]["arm_name"];
        $unique_event_name     = strtolower(str_replace(" ","_",$event_name)."_".str_replace(" ","_",$arm_name)); // Generate Event Name
        $event_id 			 = $Proj->getEventIdUsingUniqueEventName($unique_event_name);

        $active      			 = $data_array[$i]["active"];
        $autoStart   		     = $data_array[$i]["auto_start"];
        
        $condition_event_name  = $data_array[$i]["conditional_event_name"];
        $condition_event_arm   = $data_array[$i]["conditional_arm_name"];
        $unique_event_name     = strtolower(str_replace(" ","_",$condition_event_name)."_".str_replace(" ","_",$condition_event_arm)); // Generate Event Name
        $surveyCompEventId     =     $Proj->getEventIdUsingUniqueEventName($unique_event_name);
        
        $condition_survey_form = $data_array[$i]["conditional_survey_form"];
        $surveyCompSurveyId    = $Proj->forms[$condition_survey_form]['survey_id'];
        
        $andOr  =  $data_array[$i]["condition_andor"];  
        $conditionLogic       = $data_array[$i]["condition_logic"];
        $survey_queue_custom_text  = $data_array[$i]["survey_queue_custom_text"];
        
        if(!empty($survey_id) OR !empty($survey_form))
        {
            $sql = "insert into redcap_surveys_queue (survey_id, event_id, active, condition_surveycomplete_survey_id, condition_surveycomplete_event_id,
                            condition_andor, condition_logic, auto_start) values
                            ('".db_escape($survey_id)."', '".db_escape($event_id)."', $active, ".checkNull($surveyCompSurveyId).", ".checkNull($surveyCompEventId).", '$andOr',
                            ".checkNull($conditionLogic).", $autoStart)
                            on duplicate key update active = $active, condition_surveycomplete_survey_id = ".checkNull($surveyCompSurveyId).",
                            condition_surveycomplete_event_id = ".checkNull($surveyCompEventId).", condition_andor = '$andOr',
                            condition_logic = ".checkNull($conditionLogic).", auto_start = $autoStart";
        
            $q = db_query($sql);
            $result[] = db_insert_id();
        }
        
        // Custom Survey Queue Settings
        if(!empty($survey_queue_custom_text) || $survey_queue_custom_text != NULL || $survey_queue_custom_text != " ")
        {	  
            $sql = "update redcap_projects set survey_queue_custom_text = ".checkNull($survey_queue_custom_text)." where project_id = $project_id";
            $q = db_query($sql);
        }
	} 	
	return $result;
}