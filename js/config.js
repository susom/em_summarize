// Create a javascript object to hold all of our config features
var SummarizeConfig = SummarizeConfig || {};

Object.assign(SummarizeConfig, {
    "isDev":true
});



// Basic Logging function
SummarizeConfig.log = function() {
    if (!SummarizeConfig.isDev) return;

    // Make console logging more resilient to Redmond
    try {
        console.log.apply(this,arguments);
    } catch(err) {
        // Error trying to apply logs to console (problem with IE11)
        try {
            console.log(arguments);
        } catch (err2) {
            // Can't even do that!  Argh - no logging
        }
    }
};


// In cases where you want to handle a new subsetting with some custom defaults:
SummarizeConfig.initSubSettingHandler = function() {
    // Add an event handler in case someone adds a new 'instance' to a repeating subsetting
    $('.external-modules-add-instance').on("click", function () {
        SummarizeConfig.log("New instance created!");
        if (! undefined(SummarizeConfig.newSubSetting)) setTimeout(SummarizeConfig.newSubSetting(), 1000);
    });
};
SummarizeConfig.newSubSetting = function() {
    this.log("New Subsetting!");
};


SummarizeConfig.config = function() {

    // Set up our ajax url
    var configureModal = $('#external-modules-configure-modal');
    var moduleDirectoryPrefix = configureModal.data('module');
    // var version = ExternalModules.versionsByPrefix[moduleDirectoryPrefix];
    SummarizeConfig.url = app_path_webroot + "ExternalModules/?prefix=" + moduleDirectoryPrefix + "&page=pages%2FConfigAjax&pid="+pid;
    SummarizeConfig.log(SummarizeConfig.url);

    // Clear and Create a DIV to hold our status
    $('#config_status').remove();
    SummarizeConfig.alertWindow = $('<div></div>')
        .attr('id', 'config_status')
        .on('click', '.btn', function () { SummarizeConfig.doAction(this); })
        .prependTo($('.modal-body', '#external-modules-configure-modal'));

    // this.log("starting with this: ", this);

    $(configureModal).on('change', 'input, select, textarea', SummarizeConfig.getStatus);


    // Let's call getStatus once to start us off:
    setTimeout(SummarizeConfig.getStatus, 300);
};


// Do an action -- NOT USED
SummarizeConfig.doAction = function (e) {

    const data = $(e).data();

    // Action MUST be defined or we won't do anything
    if (!data.action) {
        alert ("Invalid Button - missing action");
        return;
    }

    // Do the ajax call
    $.ajax({
        method: "POST",
        url: SummarizeConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            // Data should be in format of:
            // data.result   true/false
            // data.message  (optional)  message to display.
            // data.callback (function to call)
            // data.delay    (delay before callbackup in ms)
            const cls = data.result ? 'alert-success' : 'alert-danger';

            // Render message if we have one
            if (data.message) {
                var alert = $('<div></div>')
                    .addClass('alert')
                    .addClass(cls)
                    .css({"position": "fixed", "top": "5px", "left": "2%", "width": "96%", "display":"none"})
                    .html(data.message)
                    .prepend("<a href='#' class='close' data-dismiss='alert'>&times;</a>")
                    .appendTo('#external-modules-configure-modal')
                    .show(500);

                setTimeout(function(){
                    console.log('Hiding in 500', alert);
                    alert.hide(500);
                }, 5000);
            }

            if (data.callback) {
                const delay = data.delay ? data.delay : 0;

                //since configuration is set, set the defaults for the first configuration
                setTimeout(window[data.callback](), delay);
            }
        })
        .fail(function () {
            alert("error");
        })
        .always(function() {
            SummarizeConfig.getStatus();
        });


    //const event  = $(e).data('event');
    //const form   = $(e).data('form');

    //console.log(e, action, event, form);

    //switch (action) {
    //    case 'create_pi_form':
    //        PortalConfig.insertForm('pi');
    //        break;
    //    case 'create_md_form':
    //        PortalConfig.insertForm('md');
    //        break;
    //    case 'designate_event':
    //        PortalConfig.designateForm(form, event);
    //        break;
    //    default:
    //        alert ("Invalid action received from status button");
    //}

};






// Get all the fields from the config form
SummarizeConfig.getRawForm = function() {
    var configureModal = $('#external-modules-configure-modal');
    var data = {};

    configureModal.find('input, select, textarea').each(function(index, element){
        var element = $(element);
        var name = element.attr('name');
        var type = element[0].type;

        if(!name || (type === 'radio' && !element.is(':checked'))){
            SummarizeConfig.log("Skipping", element)
            return;
        }

        if (type === 'file') {
            SummarizeConfig.log("Skipping File", element)
            return;
        }


        var value;
        if(type === 'checkbox'){
            if(element.prop('checked')){
                value = true;
            } else{
                value = false;
            }
        } else if(element.hasClass('external-modules-rich-text-field')){
            var id = element.attr('id');
            value = tinymce.get(id).getContent();
        } else{
            value = element.val();
        }

        // SummarizeConfig.log("Name: " + name, value);

        data[name] = value;
    });

    SummarizeConfig.log("DATA", data);

    return data;
};


// Get status
SummarizeConfig.getStatus = function () {

    // Assemble data from modal form
    let data = {
        'action'    : 'getStatus',
        'raw'       : SummarizeConfig.getRawForm()
    };

    SummarizeConfig.log("GET STATUS", data);

    var jqxhr = $.ajax({
        method: "POST",
        url: SummarizeConfig.url,
        data: data,
        dataType: "json"
    })
        .done(function (data) {
            //if (data.result === 'success') {
            // all is good
            var configStatus = $('#config_status');
            configStatus.empty();
            configStatus.html('');

            const cls = data.result ? 'alert-success': 'alert-danger';

            $.each(data.message, function (i, alert) {
                $('<div></div>')
                    .addClass('alert')
                    .addClass(cls)
                    .html(alert)
                    .appendTo(configStatus);
            })

        })
        .fail(function () {
            //alert("error");
        })
        .always(function() {
        });
};
