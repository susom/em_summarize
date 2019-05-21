# Summarize

This external module allows you to display summary data of forms and/or fields into a nicely formatted display.
The summary data is kept in a text or textarea field but it can be piped onto forms in the REDCap project or on surveys, emails, etc.

Only fields which contain values will be displayed.


## Options

1. Title (supports HTML and can be used to add custom text above a summary table)
1. Source event_id if longitudinal
1. Comma-separated list of forms to include
1. Comma-separated list of fields to include
1. Comma-separated list of fields to exclude (optional)
1. Option to exclude all form status values
1. Option to display field value under the field label (default is two column display)
1. Option to specify field label width (in percentage). 
1. Option to specify the maximum length of a field value to display it in the second column.  Once the value is larger than this maximum amount, it will be placed on a second line under the field label.

## Assumptions
1. You always display 'labels' and not titles.
1. We filter HTML tags from labels that might be harmful embedding them into a form

## Valid Configuration Checks

1. The destination field must be of type text or textarea and should not have any validation
1. The destination field must be in the same event as the source fields for classical/longitudinal.  
1. If the source form is repeating, then the destination field must be in the same form.
1. If the source forms/fields are in a repeating event, the destination field must be in that repeating event.

## Future Enhancements
1. Use light/dark row background
1. Width Options: Auto, 1:2, 2:1?
1. Include section headers
1. Include empty fields (normally fields with no value are excluded from summary)
