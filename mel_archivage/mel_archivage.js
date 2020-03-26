if (window.rcmail) {
    rcmail.addEventListener('init', function (evt) {
        // register command (directly enable in message view mode)

        rcmail.enable_command('plugin_archiver', true);

        var datepicker_settings = {
            // translate from PHP format to datepicker format

            onChange: function () {
                getDate(this.value);
            }
        };

        $('#archivage_date').datepicker({ maxDate: 0,  dateFormat: 'dd/mm/yy' })
            .change(function () {
                changeInput(this.value);
            });

        $('#nb_jours').on('keyup', function () {
            changeDatepicker(this.value);
        })
        $('#nb_jours').on('change', function () {
            changeDatepicker(this.value);
        })

    })
}

function changeInput(datepicker) {
    let start_date = $.datepicker.parseDate("dd/mm/yy", datepicker);
    let today = new Date();
    
    let TimeJours = today.getTime() - start_date.getTime();
    let nbJours = TimeJours / (1000 * 3600 * 24);
    $('#nb_jours').val(Math.floor(nbJours));
}

function changeDatepicker(nbJours) {
    let today = new Date();
    let datepicker = new Date(new Date().setDate(today.getDate() - nbJours));

    $('#archivage_date').datepicker('setDate', datepicker);
}

rcube_webmail.prototype.plugin_archiver = function () {
    var frame = $('<iframe>').attr('id', 'managelabelsframe')
        .attr('src', rcmail.url('settings/plugin.mel_archivage') + '&_framed=1')
        .attr('frameborder', '0')
        .appendTo(document.body);

    var buttons = {};

    frame.dialog({
        modal: true,
        resizable: false,
        closeOnEscape: true,
        title: '',
        close: function () {
            frame.dialog('destroy').remove();
            window.location.reload();
        },
        buttons: buttons,
        width: 290,
        height: 400,
        rcmail: rcmail
    }).width(260);
};


function button_archivage() {
    alert('hello');
}