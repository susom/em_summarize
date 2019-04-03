# Summarize

This external module allows you to summarize forms and fields into a single field...
The field must be a text or textarea field.  Only fields which have values will be displayed.


## Options

1. Title (supports HTML and can be used to add custom text above a summary table)
1. Source event_id if longitudinal
1. Include Forms
1. Include Fields
1. Exclude Fields (optional)
1. Display Fieldname, Field Label, or Both on left side (default to Field Label)
1. Field Order (order in data dictionary, order entered as forms then fields,  or order of fields then forms)
1. Checkbox to automatically remove form_status fields
1. Use light/dark row background
1. Width Options: Auto, 1:2, 2:1?
1. Include section headers
1. Include empty fields (normally fields with no value are excluded from summary)

## Assumptions
1. You always display 'labels' and not titles.
1. We strip HTML from labels
1. Colspan=2 for text areas and descriptive fields

## Valid Configuration Checks

1. The destination event/field must be of type text or note and should not have any validation
1. The destination event/field msut be in the same event as the source fields for classical/longitudinal.  
1. If the source form is repeating, then the dest field must be in the same form.
1. If not a repeating event, but repeating forms, there can only be one form in the source event/fields.
1. If a repeting event or longitudinal, all source forms/fields must be enabled for that repeating event
1. 
