<?php
namespace Stanford\Summarize;
/** @var \Stanford\Summarize\Summarize $module */

//require_once("utilities.php");

require_once("emLoggerTrait.php");
require_once("src/SummarizeInstance.php");

// use \ExternalModules;
use \REDCap;

class Summarize extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public  $Proj;           // A reference to the main Proj
    private $subSettings;   // An array of subsettings under the instance key


    public function __construct()
    {
        parent::__construct();

        global $project_id;
        if (!empty($project_id)) {
            // Load our subsettings
            // $this->subSettings = $this->getSubSettings('instance');

            // // Create a reference to the Proj object
            // global $Proj, $project_id;
            // if(!empty($Proj)) {
            //     //$this->Proj = $Proj;
            //     // $this->Proj->setRepeatingFormEvents();
            // }
        }
    }


    // Converts an array of key => array[values(0..n)] into an array of [i] => [key] => value where i goes from 0..n
    // For example:
    // [
    //   [f1] =>
    //          [
    //              0 => "first",
    //              1 => "second"
    //          ],
    //   [f2] =>
    //          [
    //              0 => "primero",
    //              1 => "secondo"
    // ]
    // gets converted into:
    // [
    //    0   =>
    //          [
    //              "f1" => "first",
    //              "f2" => "primero"
    //          ],
    //    1   =>
    //          [
    //              "f1" => "second",
    //              "f2" => "secondo"
    // ]
    public function parseSubsettingsFromSettings($key, $settings) {
        $config = $this->getSettingConfig($key);
        if ($config['type'] !== "sub_settings") return false;

        // Get the keys that are part of this subsetting
        $keys = [];
        foreach ($config['sub_settings'] as $subSetting) {
            $keys[] = $subSetting['key'];
        }

        // Loop through the keys to pull values from $settings
        $subSettings = [];
        foreach ($keys as $key) {
            $values = $settings[$key];
            foreach ($values as $i => $value) {
                $subSettings[$i][$key] = $value;
            }
        }

        return $subSettings;
    }



    public function validateConfigs( $instances ) {
        $result = true;
        $messages = [];

        foreach ($instances as $i => $instance) {
            $sc = new SummarizeInstance($this, $instance);

            // Get result
            $valid = $sc->validateConfig();

            // Get messages
            if (!$valid) {
                $result = false;
                $title = "#" . ($i+1) . ( !empty($instance['title']) ? " '" . $instance['title'] . "'" : "" );
                $messages[] = "<b>Configuration Issues with $title</b>" .
                    "<ul><li>" .
                    implode("</li><li>",$sc->getErrors()) .
                    "</li></ul>"; //$messages + $sc->getErrors();
            }
        }
        return array( $result, $messages);
    }


    // CALLED ON SAVE
    function redcap_module_save_configuration($project_id) {
        $instances = $this->getSubSettings('instance');
        list($result, $errors) = $this->validateConfigs($instances);

        $this->emDebug("On SAVE", $result, $errors);
    }

    /*
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
                $html = createSummarizeBlock($displayData, $title);

                // Save this summarize field
                if ($displayData["repeat"]) {
                    $saveData[$record]['repeat_instances'][$eventId][""][$repeat_instance] = array($destinationField[0] => $html);
                } else {
                    $saveData[$record][$eventId] = array($destinationField[0] => $html);
                }
                $module->emLog("Data to save: " . json_encode($saveData));
                $return = REDCap::saveData('array', $saveData, 'overwrite');
                $module->emLog("return from save: " . json_encode($return));
            }
        }
    }

    */



}