<?php
namespace Stanford\Summarize;
/** @var \Stanford\Summarize\Summarize $module */

require_once("emLoggerTrait.php");
require_once("src/SummarizeInstance.php");

use \REDCap;
use \Plugin;

class Summarize extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public  $Proj;           // A reference to the main Proj
    private $subSettings;   // An array of subsettings under the instance key
    private $configSettings;

    public function __construct()
    {
        parent::__construct();
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

    /**
     * This function takes the settings for each Summarize configuration and rearranges them into arrays of subsettings
     * instead of arrays of key/value pairs.
     *
     * @param $key - JSON key where the subsettings are stored
     * @param $settings - retrieved list of subsettings from the getProjectSettings function
     * @return array - the array of subsettings for each configuration
     */
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

    /**
     * This function loops over each Summarize configuration and determines if they are valid.  They are considered valid if:
     *      1. The Summarize field is specified
     *      2. All forms and fields are present in the project
     *      3. All fields are all in the same event.
     *
     * Once the configuration is validated, the forced refresh checkbox in the configuration will be checked.  If checked, all
     * records will be updated for this configuration.
     *
     * @param $instances - array of Summarize configurations
     * @return array - boolean - if any configuration had an error
     *               - string - message of the error
     */
    public function validateConfigs( $instances ) {
        $result = true;
        $messages = [];

        foreach ($instances as $i => $instance) {
            $sc = new SummarizeInstance($this, $instance);

            // Get result
            $valid = $sc->validateConfig();
            $title = "#" . ($i+1) . ( !empty($instance['title']) ? " '" . $instance['title'] . "'" : "" );

            // Get messages
            if (!$valid) {
                $result = false;
                $messages[] = "<b>Configuration Issues with $title</b>" .
                    "<ul><li>" .
                    implode("</li><li>",$sc->getErrors()) .
                    "</li></ul>"; //$messages + $sc->getErrors();
                $this->emDebug($messages[]);
            } else if ($instance['refresh']) {

                // If the force update is checked, update all the records that use this configuration
                $update = $this->updateAllRecords($sc);
                if ($update) {
                    // If we successfully updated all record using this config, uncheck the refresh checkbox in the configuration settings
                    $refresh_status = $this->getProjectSetting("refresh");
                    $refresh_status[$i] = false;
                    $this->setProjectSetting("refresh", $refresh_status);
                    $this->emDebug("Updated all records using $title due to forced configuration update");
                }
            }
        }

        return array( $result, $messages);
    }

    /**
     * If the force refresh checkbox is selected, update all records using this configuration.
     *
     * @param $sc - address of the handle to the SummarizeInstance class
     * @return bool - update status true/false if the update was successful
     */
    private function updateAllRecords(&$sc) {

        $all_fields = $sc->retrieveAllFields();
        $event_id = $sc->retrieveEventID();
        $all_records = REDCap::getData('array', null, array_keys($all_fields), $event_id);

        // For this config, update all records
        $update_needed = false;
        foreach($all_records as $record_id => $record_data) {
            $data_string = json_encode($record_data);
            $repeat = strstr($data_string, 'repeat_instances');

            foreach($record_data as $event => $event_data) {
                if ($event == 'repeat_instances') {
                    foreach($event_data[$event_id] as $form => $form_data) {
                        foreach($form_data as $instance => $instance_data) {
                            $result = $sc->saveSummarizeBlock($record_id, $instance);
                            if ($result) {
                                $update_needed = true;
                            }
                        }
                    }
                } else {
                    if ($repeat == false) {
                        $result = $sc->saveSummarizeBlock($record_id, null);
                        if ($result) {
                            $update_needed = true;
                        }
                    }
                }
            }
        }

        return $update_needed;
    }

    /**
     * When the Project Settings are saved, this hook will be called to validate each configuration and possibly update each
     * record with a force update.
     *
     * @param $project_id - this is the standard parameter for this hook but since we are in project context, we don't use it
     */
    function redcap_module_save_configuration($project_id) {
        $instances = $this->getSubSettings('instance');
        list($results, $errors) = $this->validateConfigs($instances);

        $this->emDebug("On SAVE", $results, $errors);
   }

    /**
     * When a record is saved, this hook will be called.  If there is a configuration using the event_id of the data being saved
     * the Summarize block will be updated once the configuration is validated. The argument list is the standard list
     * that is sent from REDCap.
     *
     * @param $project_id - project ID
     * @param $record - record number that is being saved
     * @param $instrument - instrument that is being saved
     * @param $event_id - event ID where the data resides
     * @param $group_id - group ID if this record belongs to a group
     * @param $survey_hash -
     * @param $response_id -
     * @param $repeat_instance - instance number for repeating events/fields
     */
    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id,
                              $survey_hash, $response_id, $repeat_instance) {

        // Retrieve each saved config
        $instances = $this->getSubSettings('instance');

        // Loop over all of them
        foreach ($instances as $i => $instance) {

            // Only process this config if the form is in the same event id
            if ($instance["event_id"] == $event_id) {

                // See if this config is valid
                $sc = new SummarizeInstance($this, $instance);
                $valid = $sc->validateConfig();

                // If valid put together the summarize block and save it for this record
                $config_num = $i++;
                if ($valid) {
                    $saved = $sc->saveSummarizeBlock($record, $repeat_instance);
                    if ($saved) {
                        $this->emDebug("Saved summarize block $config_num for record $record and instance $repeat_instance");
                    } else {
                        $this->emLog($sc->getErrors());
                    }
                } else {
                    $this->emError("Skipping Summarize config $config_num for record $record because config is invalid" . json_encode($instance));
                }
            }
        }
    }

}