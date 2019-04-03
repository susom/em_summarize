<?php
namespace Stanford\Summarize;

use \REDCap;

class SummarizeInstance
{
    /** @var \Stanford\Summarize\Summarize $module */
    private $module;

    private $include_forms;
    private $include_fields;
    private $exclude_fields;
    private $event_id;
    private $destination_field;
    private $title;

    private $status;
    private $warnings;
    private $errors;                 // Array of errors


    private $all_fields;
    private $all_forms;


    public function __construct($module, $instance)
    {
        $this->module = $module;

        $this->include_forms     = $this->parseConfigList( $instance['include_forms']  );
        $this->include_fields    = $this->parseConfigList( $instance['include_fields'] );
        $this->exclude_fields    = $this->parseConfigList( $instance['exclude_fields'] );
        $this->event_id          = $instance['event_id'];
        $this->destination_field = $instance['destination_field'];
        $this->title             = $instance['title'];

        global $Proj;
        $this->Proj = $Proj;
        $this->Proj->setRepeatingFormsEvents();

        $this->getAllFields();

        $module->emDebug("Forms", $this->include_forms);
    }



    function parseConfigList($list) {
        $listArray = array();
        $lists = preg_split('/\W/', $list, 0, PREG_SPLIT_NO_EMPTY);
        foreach ($lists as $oneEntry) {
            $listArray[] = trim($oneEntry);
        }
        return $listArray;
    }

    // Assume the forms and fields are valid!
    private function getAllFields() {

        $all_fields = [];
        $all_forms  = [];

        // Get all fields from forms
        foreach ($this->include_forms as $form_name) {
            // A key value array of fields name and field label
            $form_fields = $this->Proj->forms[$form_name]['fields'];
            $all_fields = array_merge($all_fields, $form_fields);
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

        // Remove destination field if included in the source forms/fields
        if (in_array($this->destination_field, array_keys($all_fields))) unset($all_fields[$this->destination_field]);

        $this->all_fields = $all_fields;
        $this->all_forms = $all_forms;

        return $all_fields;
    }


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
        $repeating_forms_events = $this->Proj->getRepeatingFormsEvents($this->event_id);
        if (!empty($repeating_forms_events) && $repeating_forms_events != "WHOLE") {
            $array_intersection = array_intersect($this->all_forms, $repeating_forms_events);
            if (!empty($array_intersection) && count($this->all_forms) > 1) {
                $this->errors[] = "If a form is repeating in an event, only fields from that single form can be summarized";
            }
        }

        // Validate that all is right with this config
        $result = empty($this->errors);

        return $result;
    }

    public function saveSummarizeBlock($record, $repeat_instance)
    {
        $saved = true;
        // Retrieve the data
        $data = REDCap::getData('array', $record, array_keys($this->all_fields), $this->event_id,
            null, null, null, null, null, TRUE);

        $displayData = $this->retrieveSummarizeData($data[$record], $repeat_instance);
        $html = $this->createSummarizeBlock($displayData);

        // Save this summarize field
        if ($displayData["repeat"]) {
            if (count($this->include_forms) == 1) {
                $saveData[$record]['repeat_instances'][$this->event_id][$this->include_forms[0]][$repeat_instance] = array($this->destination_field => $html);
            } else {
                $saveData[$record]['repeat_instances'][$this->event_id][""][$repeat_instance] = array($this->destination_field => $html);
            }
        } else {
            $saveData[$record][$this->event_id] = array($this->destination_field => $html);
        }

        $return = REDCap::saveData('array', $saveData, 'overwrite');
        if (!empty($return["errors"])) {
            $saved = false;
            $this->errors = "Error saving summarize block: " . $return["errors"];
        }

        return $saved;
    }

    private function retrieveSummarizeData($data, $repeat_instance) {

        // Retrieve the data we want from the return REDCap data.  We are adding a repeat entry if this
        // data is from a repeating form/event so we know how to send back the data to REDCap.
        $fields = array();
        foreach($data as $eventID => $eventInfo) {
            if ($eventID == "repeat_instances") {
                $fields["repeat"] = true;
                foreach ($eventInfo[$this->event_id] as $formName => $formData) {
                    foreach ($formData[$repeat_instance] as $fieldname => $fieldValue) {
                        if (isset($fieldValue) && ($fieldValue !== '')) {
                            $thisField = $this->Proj->metadata[$fieldname];
                            $eachField = array();
                            $eachField["fieldLabel"] = $thisField["element_label"];
                            $eachField["value"] = $this->getLabel($thisField, $fieldname, $fieldValue);
                            if (!empty($eachField["value"])) {
                                $fields[$fieldname] = $eachField;
                            }
                        }
                    }
                }
            } else {
                $fields["repeat"] = false;
                foreach(array_keys($this->all_fields) as $fieldkey => $fieldname) {
                    $thisField = $this->Proj->metadata[$fieldname];
                    $eachField = array();
                    $eachField["fieldLabel"] = $thisField["element_label"];
                    $eachField["value"] = $this->getLabel($thisField, $fieldname, $eventInfo[$fieldname]);
                    if (!empty($eachField["value"])) {
                        $fields[$fieldname] = $eachField;
                    }
                }
            }
        }

        return $fields;
    }

    private function createSummarizeBlock($display_data) {

        $html = "<div style='background-color: #fefefe; padding:5px;'>";
        if (!empty($this->title)) {
            $html .= "<h6 style='text-align:center'><b>$this->title</b></h6>";
        }
        $html .= "<table style='border: 1px solid #fefefe; border-spacing:0px;width:100%;'>";

        $odd = false;
        foreach ($display_data as $fieldName => $fieldValue) {
            $label = $fieldValue["fieldLabel"];
            $value = $fieldValue["value"];
            $len = strlen($fieldValue);
            $text = str_replace("\n","<br>",$value);
            $color = ($odd ? '#fefefe' : '#fafafa');
            if ($len < 80) {
                $html .= "<tr style='background: $color;'><td style='padding: 5px;' valign='top'>{$label}</td><td style='font-weight:normal;'>{$text}</td></tr>";
            } else {
                $html .= "<tr style='background: $color;'><td style='padding: 5px;' colspan=2>{$label}<div style='font-weight:normal;padding:5px 20px;'>{$text}</div></td></tr>";
            }
            $odd = !$odd;
        }
        $html .= "</table></div>";

        return $html;
    }

    private function getLabel($fieldInfo, $field, $value)
    {
        global $module;

        if (empty($field)) {
            $module->emError("The variable list is undefined so cannot retrieve data dictionary options.");
        }

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

    public function getErrors() {
        return $this->errors;
    }

}