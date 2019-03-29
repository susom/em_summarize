<?php
namespace Stanford\Summarize;
/** @var \Stanford\Summarize\Summarize $module */

require_once("emLoggerTrait.php");
require_once("src/SummarizeInstance.php");

use \REDCap;

class Summarize extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public  $Proj;           // A reference to the main Proj
    private $subSettings;   // An array of subsettings under the instance key

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
                $results = false;
                $title = "#" . ($i+1) . ( !empty($instance['title']) ? " '" . $instance['title'] . "'" : "" );
                $messages[] = "<b>Configuration Issues with $title</b>" .
                    "<ul><li>" .
                    implode("</li><li>",$sc->getErrors()) .
                    "</li></ul>"; //$messages + $sc->getErrors();
            }
        }
        return array( $result, $messages);
    }


    // CALLED ON SAVE OF CONFIGURATION SETUP
    function redcap_module_save_configuration($project_id) {
        $instances = $this->getSubSettings('instance');
        list($results, $errors) = $this->validateConfigs($instances);

        $this->emDebug("On SAVE", $results, $errors);
   }


    // CALLED ON SAVE OF DATA
    function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id,
                              $survey_hash, $response_id, $repeat_instance) {

        // Retrieve each saved config
        $instances = $this->getSubSettings('instance');

        // Loop over all of them
        foreach ($instances as $i => $config) {

            // Only process this config if the form is in the same event id
            if ($config["event_id"] == $event_id) {

                // See if this config is valid
                $sc = new SummarizeInstance($this, $config);
                $valid = $sc->validateConfig();

                // If valid put together the summarize block and save it for this record
                $config_num = $i++;
                if ($valid) {
                    $saved = $sc->saveSummarizeBlock($record, $repeat_instance);
                    if ($saved) {
                        $this->emLog("Saved summarize block $config_num for record $record and instance $repeat_instance");
                    } else {
                        $this->emLog($sc->getErrors());
                    }
                } else {
                    $this->emLog("Skipping Summarize config $config_num for record $record because config is invalid" . json_encode($config));
                }
            }
        }
    }

}