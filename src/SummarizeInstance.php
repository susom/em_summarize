<?php
namespace Stanford\Summarize;



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
        if (in_array($this->destination_field, all_fields)) unset($all_fields[$this->destination_field]);

        $this->all_fields = $all_fields;
        $this->all_forms = $all_forms;

        return $all_fields;
    }


    public function validateConfig() {
        // Ensure forms exist in project
        foreach ($this->include_forms as $form_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->forms[$form_name])) {
                $this->errors[] = "Form $form_name is not found in project";
            }
        }

        // Ensure field exist in project
        foreach ($this->include_fields as $field_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->metadata[$field_name])) {
                $this->errors[] = "Field $field_name is not found in project";
            }
        }

        // Ensure field exist in project
        foreach ($this->exclude_fields as $field_name) {
            // A key value array of fields name and field label
            if (!isset($this->Proj->metadata[$field_name])) {
                $this->errors[] = "Excluded field $field_name is not found in project";
            }
        }

        // Ensure destination field exists
        if (!isset($this->Proj->metadata[$this->destination_field]))
        {
            $this->errors[] = "Destination field $this->destination_field is not found in project";
        } else {
            // Verify it is a text/text-area field
            if (!in_array($this->Proj->metadata[$this->destination_field]['element_type'], array("text","textarea"))) {
                $this->errors[] = "Destination field $this->destination_field is not of type text or textarea";
            }
        }

        // Ensure forms exist in event
        foreach ($this->all_forms as $form_name) {
            // A key value array of fields name and field label
            if (!in_array($form_name, $this->Proj->eventsForms[$this->event_id])) {
                $event_name = $this->Proj->eventInfo[$this->event_id]['name'];
                $this->errors[] = "Form $form_name is not found/enabled in $event_name";
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






        // // Retrieve fields that are in each form
        // $ddFieldsWithFormsNames = getDDFieldsAndForms($proj);
        //
        // $allConfigErrors = "";
        //
        // // Loop over each configuration to see if it valid
        // for ($ncnt=0; $ncnt < count($settings["informs"]); $ncnt++) {
        //
        //     $configErrors = "";
        //     $inForms = $settings["informs"][$ncnt];
        //     $destinationField = $settings["destField"][$ncnt];
        //     $inFields = $settings["infields"][$ncnt];
        //     //$exFields = $settings["exfields"][$ncnt];
        //     $eventId = $settings["eventId"][$ncnt];
        //
        //     // Check the destination field and make sure we can find it
        //     $destError = checkSummarizeField($proj, $destinationField[0]);
        //
        //     // Add all individual fields together
        //     $allIndividualFields = array_merge($destinationField, $inFields);
        //
        //     // If individual fields are included, retrieve the forms they belong to
        //     $fieldsForms = getFormsFromFields($ddFieldsWithFormsNames, $allIndividualFields);
        //     $fieldsErrors = $fieldsForms["errorMsg"];
        //
        //     // Merge all the forms together
        //     $formsTotal = array_unique(array_merge($inForms, $fieldsForms["formList"]));
        //
        //     // We now have all the forms where are fields are located
        //     // Now check events. There are 3 scenarios:
        //     // 1) If this is a repeating event, make sure all the forms are in the same event
        //     // 2) If this is a repeating form, make sure all fields are only on that one form
        //     // 3) If none of the forms are repeating (forms or events), make sure all the fields as
        //     //      well as the destination field is in the same event
        //     $eventErrors = checkFormsInEvents($proj, $formsTotal, $eventId);
        //     if (!empty($eventErrors) or (!empty($fieldsErrors)) or (!empty($destError))) {
        //         $configErrors = "<div><p>For summarize destination field $destinationField[0]:</p>";
        //         if (!empty($destError)) {
        //             $configErrors .= "<p>   $destError</p>";
        //         }
        //         if (!empty($fieldsErrors)) {
        //             $configErrors .= "<p>   $fieldsErrors</p>";
        //         }
        //         if (!empty($eventErrors)) {
        //             $configErrors .= "<p>   $eventErrors</p>";
        //         }
        //         $configErrors .= "</div><br>";
        //      }
        //
        //     // These are the errors that I want to display when the configurations are not validgit status
        //     if (!empty($configErrors)) {
        //         $module->emLog("This is the error message: " . $configErrors);
        //         // Look for class modal-body and add a <div> before the table to display errors
        //         $allConfigErrors .= $configErrors;
        //     } else {
        //         // These are configuations that are valid.  For now, go create the table so I can see what it looks like.
        //         // Just using this to test
        //         $this->redcap_save_data($project_id, 1, null, $eventId, null, null, null, 1);
        //     }
        // }






        // Validate that all is right with this config
        $result = empty($this->errors);

        return $result;
    }


    public function getErrors() {
        return $this->errors;
    }



}