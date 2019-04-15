# Survey-Queue-Interface
REDCap external module that provides an interface that allows exporting and importing the survey queue as a CSV file.

This module has been tested with Classic and Longitudinal projects, but NOT with repeating events. 

## Exporting Survey Queue
The following columns are exported in a CSV file:
- survey_form - Unique name of survey in the queue
- event_name - Name of survey's event
- arm_num - Arm number the survey resides in
- active - Is the form active in the queue?
- auto_start - Used to take the participant immediately to the first incomplete survey in the queue if 'auto start' is enabled for that survey
- conditional_event_name - conditional_survey_form event name
- conditional_arm_num - conditional_survey_form arm number
- conditional_survey_form - Display survey in the queue when this is complete
- condition_andor - Display survey in queue when conditional_survey_form is complete <strong>and|or</strong> condition logic is true
- condition_logic - Display survey when conditional logic is true

## Importing Survey Queue
The import functionality expects a CSV file with the columns listed above, in the same order. The module will validate events, instruments, and 
arms and return the approriate errors without any changes to the database. However, two projects with the same structure will pass validation for each other.
