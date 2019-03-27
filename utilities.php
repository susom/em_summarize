<?php
/**
 * Created by PhpStorm.
 * User: LeeAnnY
 * Date: 2019-03-20
 * Time: 10:43
 */
namespace Stanford\Summarize;
/** @var \Stanford\Summarize\Summarize $module */

use \Project;
use \Exception;

function parseConfigList($list) {
    global $module;

    $listArray = array();

    $lists = preg_split('/\W/', $list, 0, PREG_SPLIT_NO_EMPTY);
    foreach ($lists as $oneEntry) {
        $listArray[] = trim($oneEntry);
    }

    return $listArray;

}

function checkSummarizeField($proj, $descriptiveField) {
    global $module;

    $errorMsg = "";
    $fieldType = $proj->metadata[$descriptiveField]["element_type"];
    $module->emLog("Field $descriptiveField is of type $fieldType");
    if ($fieldType == "") {
        $errorMsg = "Cannot find the Summarize Field [$descriptiveField] on a form.";
    } else if (($fieldType !== 'textarea') and ($fieldType !== 'text')) {
        $errorMsg = "The Summarize Field [$descriptiveField] must be a 'Text Box' or 'Notes Box' field type";
    }
    return $errorMsg;
}

function getDDFieldsAndForms($proj) {
    global $module;

    $fieldsWithFormNames = array();
    foreach ($proj->forms as $formName => $formMetaData) {

        foreach ($formMetaData["fields"] as $field => $fieldLabel) {
            $fieldsWithFormNames[$field] = $formName;
        }
    }

    return $fieldsWithFormNames;
}

function getFieldsInForms($proj, $form) {
    global $module;

    $fieldsInForms = array_keys($proj->forms[$form]["fields"]);

    return $fieldsInForms;
}

function removeExcludeFields($totalFields, $fields) {
    global $module;

    foreach ($fields as $field => $fieldname) {
        $index = array_search($fieldname, $totalFields);
        unset($totalFields[$index]);
    }

    return $totalFields;
}

function getFormsFromFields($fieldsWithFormNames, $fieldList) {

    global $module;

    $formList = array();
    $canNotFindField = array();
    $error = "";
    foreach($fieldList as $field => $fieldInfo) {
        if (!empty($fieldsWithFormNames[$fieldInfo])) {
            $formList[] = $fieldsWithFormNames[$fieldInfo];
        } else {
            $canNotFindField[] = $fieldInfo;
        }
    }

    $uniqueFormList = array_unique($formList, SORT_STRING);

    if (!empty($canNotFindField)) {
        $error = "These fields cannot be found: " . implode(',', $canNotFindField);
        $module->emError($error);
    }

    return array("formList" => $formList, "errorMsg" => $error);
}


function checkFormsInEvents($proj, $totalForms, $eventId) {
    global $module;

    $error = "";
    if ($proj->RepeatingFormsEvents[$eventId] == 'WHOLE') {
        // Check to see if all the forms are in this event
        $formList = $proj->eventsForms[$eventId];

        // Make sure all forms are in this event
        $arrayMissing = array_diff($totalForms, $formList);
        if (!empty($arrayMissing)) {
            $error = "This following forms are not in event $eventId: " . implode(',', $arrayMissing);
        }

    } else if (!empty($proj->RepeatingFormsEvents[$eventId])) {
        // If this is a repeating form, make sure there is only form in the list
        $array_intersection = array_intersect($totalForms, array_keys($proj->RepeatingFormsEvents[$eventId]));
        if ((count($totalForms) > 1) and (count($array_intersection) > 0)) {
            $error = "When using repeating forms, you can only use fields from a repeating form: " . implode(",", $totalForms);
        }

    } else {
        // These forms are not repeating.  Make sure they are in the same event
        $formsInEvent = $proj->eventsForms[$eventId];

        $arrayMissing = array_diff($totalForms, $formsInEvent);

        // If all the forms are not in this event, send an error
        if (!empty($arrayMissing)) {
            $error = "All forms must be in the same event - remove fields in form " . implode(',', array_values($arrayMissing));
        }
    }

    return $error;
}

function getLabel($fieldInfo, $field, $value)
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

function getSummarizeData($totalFields, $data, $eventId, $proj, $instanceId) {

    global $module;

    // Retrieve we want from the return data from REDCap.  We are adding a repeat entry if this
    // data is from a repeating form/event so we know how to send back the data to REDCap.
    $fields = array();
    foreach($data as $eventID => $eventInfo) {
        if ($eventID == "repeat_instances") {
            $fields["repeat"] = true;
            foreach ($eventInfo[$eventId] as $formName => $formData) {
                foreach ($formData[$instanceId] as $fieldname => $fieldValue) {
                    $thisField = $proj->metadata[$fieldname];
                    $eachField = array();
                    $eachField["fieldLabel"] = $thisField["element_label"];
                    $eachField["value"] = getLabel($thisField, $fieldname, $fieldValue);
                    $fields[$fieldname] = $eachField;
                }
             }
        } else {
            $fields["repeat"] = false;
            foreach($totalFields as $fieldkey => $fieldname) {
                $thisField = $proj->metadata[$fieldname];
                $eachField = array();
                $eachField["fieldLabel"] = $thisField["element_label"];
                $eachField["value"] = getLabel($thisField, $fieldname, $eventInfo[$fieldname]);
                $fields[$fieldname] = $eachField;
            }
        }
    }

    return $fields;
}

function createSummarizeBlock($totalFields, $title) {

    $html = "<div style='background-color: #fefefe; padding:5px;'><table style='border: 1px solid #fefefe; border-spacing:0px;width:100%;'>";
    if (!empty($title)) {
        $html .= "<caption>$title</caption>";
    }
    $odd = false;
    foreach ($totalFields as $fieldName => $fieldValue) {
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