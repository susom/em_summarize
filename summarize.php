<?php
namespace Stanford\summarize;
/** @var \Stanford\summarize\summarize $module */

require_once("utilities.php");
use \ExternalModules;
use \REDCap;

class summarize extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();
    }

    function redcap_module_save_configuration($project_id) {
        global $module;

        // Retrieve the Project data dictionary
        $proj = getDataDictionary($project_id);

        // Retrieve the settings for each summarize configuration so we can make sure they are valid
        $settings = getConfigSettings();

        // Retrieve fields that are in each form
        $ddFieldsWithFormsNames = getDDFieldsAndForms($proj);

        $allConfigErrors = "";
        // Loop over each configuration to see if it valid
        for ($ncnt=0; $ncnt < count($settings["informs"]); $ncnt++) {

            $configErrors = "";
            $inForms = $settings["informs"][$ncnt];
            $destinationField = $settings["destField"][$ncnt];
            $inFields = $settings["infields"][$ncnt];
            //$exFields = $settings["exfields"][$ncnt];
            $eventId = $settings["eventId"][$ncnt];

            // Check the destination field and make sure we can find it
            $destError = checkSummarizeField($proj, $destinationField[0]);

            // Add all individual fields together
            $allIndividualFields = array_merge($destinationField, $inFields);

            // If individual fields are included, retrieve the forms they belong to
            $fieldsForms = getFormsFromFields($ddFieldsWithFormsNames, $allIndividualFields);
            $fieldsErrors = $fieldsForms["errorMsg"];

            // Merge all the forms together
            $formsTotal = array_unique(array_merge($inForms, $fieldsForms["formList"]));

            // We now have all the forms where are fields are located
            // Now check events. There are 3 scenarios:
            // 1) If this is a repeating event, make sure all the forms are in the same event
            // 2) If this is a repeating form, make sure all fields are only on that one form
            // 3) If none of the forms are repeating (forms or events), make sure all the fields as
            //      well as the destination field is in the same event
            $eventErrors = checkFormsInEvents($proj, $formsTotal, $eventId);
            if (!empty($eventErrors) or (!empty($fieldsErrors)) or (!empty($destError))) {
                $configErrors = "<div><p>For summarize destination field $destinationField[0]:</p>";
                if (!empty($destError)) {
                    $configErrors .= "<p>   $destError</p>";
                }
                if (!empty($fieldsErrors)) {
                    $configErrors .= "<p>   $fieldsErrors</p>";
                }
                if (!empty($eventErrors)) {
                    $configErrors .= "<p>   $eventErrors</p>";
                }
                $configErrors .= "</div><br>";
             }

            if (!empty($configErrors)) {
                $module->emLog("This is the error message: " . $configErrors);
                // Look for class modal-body and add a <div> before the table to display errors
                $allConfigErrors .= $configErrors;
            } else {
                // Just using this to test
                $this->redcap_save_data($project_id, 1, null, $eventId, null, null, null, 1);
            }
        }
    }

    private function redcap_save_data($project_id, $record, $instrument, $event_id, $group_id,
                              $survey_hash, $response_id, $repeat_instance) {
        global $module;

        // Should I recheck the configuration?????

        // Retrieve the Project data dictionary
        $proj = getDataDictionary($project_id);

        // Retrieve the settings for each summarize configuration so we can make sure they are valid
        $settings = getConfigSettings();

        // Retrieve fields that are in each form
        $fieldsWithFormsNames = getDDFieldsAndForms($proj);

        // Update each of the summarize blocks since the record is being saved
        for ($ncnt=0; $ncnt < count($settings["informs"]); $ncnt++) {

            $inForms = $settings["informs"][$ncnt];
            $destinationField = $settings["destField"][$ncnt];
            $module->emLog("Destination Field: " . json_encode($destinationField));
            $inFields = $settings["infields"][$ncnt];
            $exFields = $settings["exfields"][$ncnt];
            $eventId = $settings["eventId"][$ncnt];
            $title = $settings["title"][$ncnt];

            // If we are not saving this event, no need to update
            if ($event_id == $eventId) {

                $module->emLog("Destination Field 2: " . json_encode($destinationField));
                // Add all the fields in the included forms
                $totalFields = array();
                foreach ($inForms as $form => $formname) {
                    $fields = getFieldsInForms($proj, $formname);
                    $totalFields = array_merge($totalFields, $fields);
                }

                // Add the include fields
                $totalFields = array_unique(array_merge($totalFields, $inFields));

                // Add destination field to the exclude field
                $excludeFields = array_merge($exFields, $destinationField);

                // Exclude the list of exluded fields
                $totalFields = removeExcludeFields($totalFields, $excludeFields);

                // Retrieve the data
                $data = REDCap::getData($project_id, 'array', $record, $totalFields, $eventId,
                    null, null, null, null, null, TRUE);

                $displayData = getSummarizeData($totalFields, $data[$record], $eventId, $proj, $repeat_instance);
                $html = createSummarizeBlock($displayData);

                $saveData = array($proj->table_pk => $record, $destinationField => $html);
                $module->emLog("Data to save: " . json_encode($saveData));
                $return = REDCap::saveData('array', $saveData, 'overwrite');
                $module->emLog("return from save: " . json_encode($return));
            }
        }
    }

    function emLog()
    {
        global $module;
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($module->PREFIX, func_get_args(), "INFO");
    }

    function emDebug()
    {
        // Check if debug enabled
        if ($this->getSystemSetting('enable-system-debug-logging') || $this->getProjectSetting('enable-project-debug-logging')) {
            $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
            $emLogger->emLog($this->PREFIX, func_get_args(), "DEBUG");
        }
    }

    function emError()
    {
        $emLogger = ExternalModules\ExternalModules::getModuleInstance('em_logger');
        $emLogger->emLog($this->PREFIX, func_get_args(), "ERROR");
    }
}

?>
