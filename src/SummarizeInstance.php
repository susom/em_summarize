<?php
namespace Stanford\Summarize;

use \REDCap;

/**
 * Class SummarizeInstance
 * @package Stanford\Summarize
 *
 * This class validates each summarize configuration entered by users for their project.  Once the entered form/field lists
 * are validated, it will create the summarize block and save it to the record.
 *
 */
class SummarizeInstance
{
    // Determines which fields should be included in the summarize block
    private $include_forms;
    private $include_fields;
    private $exclude_fields;
    private $event_id;
    private $destination_field;
    private $destination_form;
    private $remove_form_status;
    private $all_fields;
    private $all_forms;
    private $display_blanks;

    // Determines the layout of the summarize block
    private $title;
    private $disp_value_under_name;
    private $field_label_width;
    private $max_chars_per_column;
    const MIN_LABEL_WIDTH = 10;
    const MAX_LABEL_WIDTH = 90;
    const NO_REPEAT = 0;
    const REPEAT_FORM = 1;
    const REPEAT_EVENT = 2;
    const DEFAULT_PRIM_ROW_COLOR = "#fefefe";
    const DEFAULT_SEC_ROW_COLOR = "#fafafa";

    // Currently only errors is used
    private $status;
    private $warnings;
    private $errors;                 // Array of errors

    public function __construct($module, $instance)
    {
        global $Proj;
        $this->module = $module;

        $this->include_forms            = $this->parseConfigList( $instance['include_forms']  );
        $this->include_fields           = $this->parseConfigList( $instance['include_fields'] );
        $this->exclude_fields           = $this->parseConfigList( $instance['exclude_fields'] );
        $this->event_id                 = $instance['event_id'];
        $this->destination_field        = $instance['destination_field'];
        $this->title                    = $instance['title'];
        $this->disp_value_under_name    = $instance['disp_value_under_name'];
        $this->field_label_width        = $instance['field_label_width'];
        $this->max_chars_per_column     = $instance['max_chars_per_column'];
        $this->remove_form_status       = $instance['remove_form_status'];
        $this->display_blanks           = $instance['display_blanks'];
        $this->prim_color               = ($instance['prim_color'] == '' ? self::DEFAULT_PRIM_ROW_COLOR : $instance['prim_color']);
        $this->sec_color                = ($instance['sec_color'] == '' ? self::DEFAULT_SEC_ROW_COLOR : $instance['sec_color']);

        $this->Proj = $Proj;
        $this->Proj->setRepeatingFormsEvents();

        $this->getAllFields();
    }

    /**
     * This function splits the configuration into an array
     *
     * @param $list - original list
     * @return array - split array
     */
    function parseConfigList($list) {
        $listArray = array();
        $lists = preg_split('/\W/', $list, 0, PREG_SPLIT_NO_EMPTY);
        foreach ($lists as $oneEntry) {
            $listArray[] = trim($oneEntry);
        }
        return $listArray;
    }

    /**
     * This function will create a list of all fields included in this Summarize block
     *
     * @return array
     */
    private function getAllFields() {

        $all_fields = [];
        $all_forms  = [];

        // Get all fields from forms
        foreach ($this->include_forms as $form_name) {
            // A key value array of fields name and field label
            $form_fields = $this->Proj->forms[$form_name]['fields'];
            $all_fields = array_merge($all_fields, $form_fields);
            if ($this->remove_form_status) {
                unset($all_fields[$form_name . "_complete"]);
            }
            $all_forms[] = $form_name;
        }

        // Add fields manually included
        foreach ($this->include_fields as $field_name) {
            if (isset($this->Proj->metadata[$field_name])) {
                $all_fields[$field_name] = $this->Proj->metadata[$field_name]['element_label'];
                $form = $this->Proj->metadata[$field_name]['form_name'];
                if (!in_array($form, $all_forms)) $all_forms[] = $form;
            }
        }

        // Exclude fields listed
        foreach ($this->exclude_fields as $field_name) {
            if (isset($all_fields[$field_name])) unset($all_fields[$field_name]);
        }

        // Include Destination Field Form
        $form = $this->Proj->metadata[$this->destination_field]['form_name'];
        if (!in_array($form, $all_forms)) $all_forms[] = $form;
        $this->destination_form = $form;

        // Remove destination field if included in the source forms/fields
        if (in_array($this->destination_field, array_keys($all_fields))) unset($all_fields[$this->destination_field]);

        $this->all_fields = $all_fields;
        $this->all_forms = $all_forms;

        return $all_fields;
    }

    /**
     * Performs checks on entered configuration to make sure it is valid. Checks include:
     *  1. Event ID was entered
     *  2. All forms exist
     *  3. All fields exist
     *  4. All forms are in the event selected
     *  5. If there is a repeating form, all fields are on that form.
     *
     * @return bool - true/false if the configuration is valid
     */
    public function validateConfig() {

        // Make sure there is an event selected
        if (!isset($this->event_id)) {
            $this->errors[] = "Event ID is required";
        }

        // Ensure forms exist in project
        foreach ($this->include_forms as $form_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->forms[$form_name])) {
                $this->errors[] = "Form $form_name is not found in project";
            }
        }

        // Ensure include fields exist in project
        foreach ($this->include_fields as $field_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->metadata[$field_name])) {
                $this->errors[] = "Field $field_name is not found in project";
            }
        }

        // Ensure exclude fields exist in project
        foreach ($this->exclude_fields as $field_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->metadata[$field_name])) {
                $this->errors[] = "Excluded field $field_name is not found in project";
            }
        }

        // Ensure destination field exists
        if (empty($this->Proj->metadata[$this->destination_field])) {
            $this->errors[] = "Destination field is required";
        } else if (!isset($this->Proj->metadata[$this->destination_field])) {
            $this->errors[] = "Destination field $this->destination_field is not found in project";
        } else {
            // Verify it is a text/text-area field
            if (!in_array($this->Proj->metadata[$this->destination_field]['element_type'], array("text","textarea"))) {
                $this->errors[] = "Destination field $this->destination_field is not of type text or textarea";
            }
        }

        // Ensure forms exist in event
        if (!empty($this->event_id)) {
            foreach ($this->all_forms as $form_name) {
                // A key value array of fields name and field label
                if (!in_array($form_name, $this->Proj->eventsForms[$this->event_id])) {
                    $event_name = $this->Proj->eventInfo[$this->event_id]['name'];
                    $this->errors[] = "Form $form_name is not found/enabled in $event_name";
                }
            }
        }

        // Only one form if any form is a repeating form
        $repeating_forms_events = $this->Proj->getRepeatingFormsEvents();

        if (!empty($repeating_forms_events[$this->event_id]) && $repeating_forms_events[$this->event_id] != "WHOLE") {
            // Not a repeating event, but we do have at least one repeating form
            $non_repeating_forms = array_diff($this->all_forms, array_keys($repeating_forms_events[$this->event_id]));

            if (count($this->all_forms) != count($non_repeating_forms)) {
                // One or more of our summarized forms includes one or more repeating forms
                if (count($this->all_forms) > 1) {
                    $this->errors[] = "If a form is repeating in an event, only fields from that single form can be summarized";
                    //$this->module->emDebug($this->all_forms, array_keys($repeating_forms_events[$this->event_id]), $non_repeating_forms, count($this->all_forms));
                }
            }
        }

        // Validate that all is right with this config
        $result = empty($this->errors);

        // Log the fact that these configuration errors were found
        if (!empty($this->errors)) {
            REDCap::logEvent("Summarize Configuration Errors", json_encode($this->errors));
        }

        return $result;
    }

    /**
     * This function will retrieve REDCap data, format the data into the Summarize block and save the html back to the
     * Summarize field.
     *
     * @param $record - project record that is being updated
     * @param $repeat_instance - for repeating forms
     * @return bool - true/false if Summarize block was successfully saved
     */
    public function saveSummarizeBlock($record, $instrument, $repeat_instance, $deleteAction = null)
    {
        $saved = true;
        // Retrieve the data
        $data = REDCap::getData('array', $record, array_keys($this->all_fields), $this->event_id,
            null, null, null, null, null, TRUE);

        if (!empty($instrument) && !in_array($instrument, $this->all_forms)) {
            // This form is not part of summarize - lets skip
            //$this->module->emDebug("Skipping $instrument in event $this->event_id because it is not affected", $instrument, $this->all_forms);
            return false;
        }

        // Use CONST, NO_REPEAT (0), REPEAT_FORM (1) and REPEAT_EVENT (2)
        $repeat = $this->isThisARepeatingForm();

        // Are we deleting a form on a repeating form page?
        if (@$deleteAction === "deleteForm" && $this->destination_form === @$instrument) {
            // do not save the new summary because this instance is being deleted
            //$this->module->emDebug("Skipping save - deleting instance of form $instrument");
            return false;
        }


        $displayData = $this->retrieveSummarizeData($data[$record], $repeat, $repeat_instance);
        $html = $this->createSummarizeBlock($displayData);

        // Save this summarize field
        if ($repeat === self::REPEAT_FORM) {
            $saveData[$record]['repeat_instances'][$this->event_id][$this->destination_form][$repeat_instance] = array($this->destination_field => $html);
        } else if ($repeat === self::REPEAT_EVENT) {
            $saveData[$record]['repeat_instances'][$this->event_id][""][$repeat_instance] = array($this->destination_field => $html);
        } else {
            $saveData[$record][$this->event_id] = array($this->destination_field => $html);
        }

        $return = REDCap::saveData('array', $saveData, 'overwrite');
        if (!empty($return["errors"])) {
            $saved = false;
            $this->errors = "Error saving summarize block: " . json_encode($return["errors"]);
            $this->module->emError($this->errors);
       }

        return $saved;
    }

    /**
     * Determine if this is a repeating form based on the instrument and event_id.
     * We must go to the data dictionary in order to make a determination.
     *
     * @return bool - true if this is a repeating form and false if not
     */
    public function isThisARepeatingForm()
    {
        // Determine if this is a repeating form
        $repeat = self::NO_REPEAT;
        $repeating = $this->Proj->RepeatingFormsEvents[$this->event_id];
        if (!empty($repeating)) {
            if ($repeating == 'WHOLE') {
                $repeat = self::REPEAT_EVENT;
            } else {
                $repeatingForms = array_keys($this->Proj->RepeatingFormsEvents[$this->event_id]);
                $nonRepeatForms = array_diff($this->all_forms, $repeatingForms);
                if (empty($nonRepeatForms)) {
                    $repeat = self::REPEAT_FORM;
                }
            }
        }

        return $repeat;
    }


    /**
     * This function re-formats the data retrieved from REDCap for the Summarize blocks.
     *
     * @param $data - Data retrieved from REDCap
     * @param $repeat = true if this is a repeat instance/event or false if not
     * @param $repeat_instance - instance of the repeating form
     * @return array - User friendly array of data
     */
    private function retrieveSummarizeData($data, $repeat, $repeat_instance) {

        // Retrieve the data we want from the return REDCap data.
        $fields = array();
        foreach($data as $eventID => $eventInfo) {
            if (($repeat === self::REPEAT_FORM) or ($repeat === self::REPEAT_EVENT)) {
                foreach ($eventInfo[$this->event_id] as $formName => $formData) {
                    foreach ($formData[$repeat_instance] as $fieldname => $fieldValue) {
                        if ($fieldValue !== '' or $this->display_blanks) {
                            $thisField = $this->Proj->metadata[$fieldname];
                            $eachField = array();
                            $eachField["fieldLabel"] = $thisField["element_label"];
                            $eachField["value"] = $this->getLabel($thisField, $fieldValue);
                            if ($eachField["value"] != '' or $this->display_blanks) {
                                $fields[$fieldname] = $eachField;
                            }
                        }
                    }
                }
            } else {

                // This is for non-repeating instances
                foreach(array_keys($this->all_fields) as $fieldkey => $fieldname) {
                    if ($eventInfo[$fieldname] !== '' or $this->display_blanks) {
                        $thisField = $this->Proj->metadata[$fieldname];
                        $eachField = array();
                        $eachField["fieldLabel"] = $thisField["element_label"];
                        $eachField["value"] = $this->getLabel($thisField, $eventInfo[$fieldname]);
                        if ($eachField["value"] != '' or $this->display_blanks) {
                            $fields[$fieldname] = $eachField;
                        }
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * This function takes the retrieved data and creates an html table for this configuration.  Any display
     * options provided the user needs to be accounted for here
     *
     * @param $display_data - Data to display in Summarize block
     * @return string - Formatted html table
     */
    private function createSummarizeBlock($display_data, $instrument=null) {

        // Check to see how the user wants this displayed
        if (!$this->disp_value_under_name) {
            // If the user does not want to display the values on a separate row from
            // the label, see what the field label width should be.
            if (empty($this->field_label_width)) {
                $label_width = '';
            } else if ($this->field_label_width < self::MIN_LABEL_WIDTH) {
                $label_width = self::MIN_LABEL_WIDTH;
            } else if ($this->field_label_width > self::MAX_LABEL_WIDTH) {
                $label_width = self::MAX_LABEL_WIDTH;
            } else {
                $label_width = $this->field_label_width;
            }
            $value_width = 100-$label_width;
        }

        $html = "<div style='background-color: " . $this->prim_color . "; padding:5px;'>";
        if (!empty($this->title)) {
            $html .= "<h6 style='text-align:center'><b>$this->title</b></h6>";
        }
        $html .= "<table style='border: 1px solid " . $this->prim_color . "; border-spacing:0px; width:100%;'>";
        if (empty($this->disp_value_under_name) || empty($label_width)) {
            $html .= "<tr><th style='width:" . $label_width . "%'></th><th style='width:" . $value_width . "%'></th></tr>";
        }

        $odd = false;
        foreach ($display_data as $fieldName => $fieldValue) {
            $label = $fieldValue["fieldLabel"];
            $value = $fieldValue["value"];
            $text = str_replace("\n","<br>",$value);
            $filteredText = REDCap::filterHtml($text);
            $filteredLabel = REDCap::filterHtml($label);

            //$color = ($odd ? '#fefefe' : '#fafafa');
            $color = ($odd ? $this->prim_color : $this->sec_color);

            // Decide if this field value should be on a new line. If the length of the field value is longer than
            // the allowed length set in the configuration, display on a new line.
            $new_line = (isset($this->max_chars_per_column) && (($this->max_chars_per_column == 0) || (strlen($filteredText) > $this->max_chars_per_column)) ? 1 : 0);

            if ($this->disp_value_under_name || $new_line) {
                $html .= "<tr style='background: $color;'><td colspan=2 style='padding:5px; width:100%'>{$filteredLabel}<div style='font-weight:normal; padding:5px 20px; width=100%;'>{$filteredText}</div></td></tr>";
            } else {
                $html .= "<tr style='background: $color;'><td style='padding:5px;'>{$filteredLabel}</td><td style='font-weight:normal;'>{$filteredText}</td></tr>";
            }
            $odd = !$odd;
        }
        $html .= "</table></div>";

        return $html;
    }

    /**
     * This function converts the "raw" value of a field to the field label
     *
     * @param $fieldInfo - Data Dictionary for field
     * @param $value - raw value of field
     * @return string|null - label corresponding to raw value
     */
    private function getLabel($fieldInfo, $value)
    {
        global $module;

        $label = null;
        switch ($fieldInfo["element_type"]) {
            case "select":
            case "radio":
            case "yesno":
            case "truefalse":

                $optionList = $fieldInfo["element_enum"];
                $options = explode('\n', $optionList);
                foreach ($options as $optionKey => $optionValue) {

                    $option = explode(',', $optionValue, 2);
                    if (trim($option[0]) == $value) {
                        if (empty($label)) {
                            $label = trim($option[1]);
                        } else {
                            $label .= ', ' . trim($option[1]);
                        }
                    }
                }

                break;
            case "checkbox":

                $optionList = $fieldInfo["element_enum"];
                $options = explode('\n', $optionList);
                foreach ($options as $optionKey => $optionValue) {
                    $option = explode(',', $optionValue);
                    if ($value[trim($option[0])] == 1) {
                        if (empty($label)) {
                            $label = trim($option[1]);
                        } else {
                            $label .= ', ' . trim($option[1]);
                        }
                    }
                }
                break;
            default:
                $label = $value;
        }

        return $label;
    }

    /**
     * Allow callers to retrieve the list of fields for this configuration
     *
     * @return - array of fields
     */
    public function retrieveAllFields() {
        return $this->all_fields;
    }

    /**
     * Allow callers to retrieve the event ID
     *
     * @return - event ID
     */
    public function retrieveEventID() {
        return $this->event_id;
    }

    /**
     * @return array of strings when the configurations are not valid
     */
    public function getErrors() {
        return $this->errors;
    }

}