$(window).resize(function() {
    fill_height_width();
    $(".scroll-pane").sbscroller("refresh");
    $(".textfill").textfill();
    smart_scrollbars();
});

var scriptTimer = {
    scriptRunTime: 0,
    startTime: 0,
    stopTime: 0,
    startTimer: function() {
        time = new Date();
        this.startTime = time.getTime();
    },
    stopTimer: function() {
        time = new Date();
        this.stopTime = time.getTime();
        this.scriptRunTime = (this.stopTime - this.startTime) / 1000;
    }
}

function smart_scrollbars() {
    $('.scroll-content:only-child').css("padding-left", "0");
    $('.slider-wrap').prev('.scroll-content').css("padding-left", "46px");
}

function refresh_all() {
    //scriptTimer.startTimer();
    // Make jQuery UI buttons of all buttons and submits and .button that are not alreayd "buttonized"
    $("input:submit, button, .button").not('.ui-button').button();
    //scriptTimer.stopTimer(); alert(scriptTimer.scriptRunTime);

    $(".button").off("click");

    $("button").click(function() {
        this.blur();
    });

    $(".toggleswitch").toggleSwitch(); // Make toggle switches
    $('.flexsection').off("click").click(function() {
        $(this).next().toggle('blind', function() {
           $(".scroll-pane").sbscroller("refresh");
        });

        $(this).find(".area_toggler svg").toggleClass("flexsection_open");

        setTimeout(function() {
            smart_scrollbars();
        },
        500);
        return false;
    }).next().hide();

    $(".selectable").selectable({
        stop: function() {
            var func = $(".ui-selected", this).attr("rel");
            eval(func);
        }
    });

    if ($('#admin_display').length) {
        $('.admin_button').hide();
        $('.employee_button').hide();
    } else {
        $('.admin_button').show();
    }

    if ($(".ui-dialog-content").length) {
        $(".ui-dialog-content").dialog("destroy").remove();
    }

    if ($.isFunction($('.nyroModal').nyroModal)) {
        $('.nyroModal').nyroModal();
    }

    $('.keyboard').keyboard({
        change: function(e, keyboard, el) {
            var last = $('.keyboard').last();
            var cp_value = ucwords(last.val(), true);
            last.val(cp_value);
        },
        stickyShift: false,
        autoAccept: true,
        position: {
            of: $('#display_level'), // optional - null (attach to input/textarea) or a jQuery object (attach elsewhere)
            at2: 'center middle'
        },
        usePreview: true,
        lockInput: true,
        visible: function(e, keyboard, el) {
            keyboard.$preview[0].select();
        }
    });

    $(".textfill").textfill();

    refresh_tags_editor();

    // Delete old
    $('.colorPicker-picker').remove();
    $('.colorpicker').colorPicker({
        onColorChange: function(id, newValue) {
            var cp = $('#' + id + '_field');
            cp.val(newValue);
            cp.keyup()
        }
    });

    $('.autocapitalizewords').keyup(function(evt) {
        // to capitalize all words
        var cp_value = ucwords($(this).val(), true);
        $(this).val(cp_value);
    });

    $('.autocapitalizefirst').keyup(function(evt) {
        // force: true to lower case all letter except first
        var cp_value = ucfirst($(this).val(), false);
        $(this).val(cp_value);
    });

    setTimeout(function() {
        fill_height_width();
        fill_height_width_once();
        $(".scroll-pane").not(":has(.scroll-content)").sbscroller();
        $(".scroll-pane").sbscroller("refresh");
        smart_scrollbars();
    },
    100);
}

function refresh_tags_editor() {
    $('.tags_editor').each(function () {
        var $input = $(this);
        if ($input.hasClass('ui-autocomplete-input')) {
            $input.autocomplete({
                source: $input.siblings('.tags_list').val().split(","),
                minLength: 0,
                appendTo: $input.closest("form"),
            });
            $input.next('.tags_select')
                .on("click", function(event) {
                    // close if already visible
                    event.preventDefault();
                    if ($input.autocomplete("widget").is(":visible")) {
                        return;
                    }
                    $(this).blur();
                    $input.focus();
                    $('#' + $input.attr("id")).autocomplete("search", "");
                    return;
                });
        } else {
            var $input = $(this);
            $input.autocomplete({
                source: $input.siblings('.tags_list').val().split(","),
                minLength: 0,
                appendTo: $input.closest("form"),
            }).addClass("ui-widget ui-widget-content ui-corner-left");

            $("<button id='tags_list_button_" + $input.attr("id") + "' type='button'>&nbsp;</button>")
                .attr("tabIndex", -1)
                .attr("title", "Show All Items")
                .insertAfter($input)
                .button({
                    icon: "ui-icon-triangle-1-s",
                    showLabel: false
                })
                .removeClass("ui-corner-all")
                .addClass("tags_select ui-corner-right ui-button-icon")
                .on("click", function(event) {
                    // close if already visible
                    event.preventDefault();
                    if ($input.autocomplete("widget").is(":visible")) {
                        $input.autocomplete("close");
                        return;
                    }
                    $('#' + $input.attr("id")).autocomplete("search", "");
                });
        }
    });
    return;
}

function fill_height_width() {
    $('.fill_height').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var pmargins = $(this).parent().outerHeight(true) - $(this).parent().height();
        var margins = $(this).outerHeight(true) - $(this).height();
        $(this).height((poffset.top + $(this).parent().height() - pmargins) - offset.top - margins);
    });
    $('.fill_width').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var margins = $(this).outerWidth(true) - $(this).width();
        $(this).width((poffset.left + $(this).parent().width()) - offset.left);
    });
    $('.fill_height_middle').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var pmargins = $(this).parent().outerHeight(true) - $(this).parent().height();
        var margins = $(this).outerHeight(true) - $(this).height();
        $(this).height((poffset.top + $(this).parent().height() - pmargins) - offset.top - margins - $('.bottom').height());
    });
    $('.fill_width_middle').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var margins = $(this).outerWidth(true) - $(this).width();
        $(this).width((poffset.left + $(this).parent().width()) - offset.left - $('.side').width());
    });
}

function fill_height_width_once() {
    $('.fill_height_once').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var pmargins = $(this).parent().outerHeight(true) - $(this).parent().height();
        var margins = $(this).outerHeight(true) - $(this).height();
        $(this).height((poffset.top + $(this).parent().height() - pmargins) - offset.top - margins);
    });
    $('.fill_width_once').each(function() {
        var offset = $(this).offset();
        var poffset = $(this).parent().offset();
        var margins = $(this).outerWidth(true) - $(this).width();
        $(this).width((poffset.left + $(this).parent().width()) - offset.left);
    });
}

function uploader(id, callback, fields) {
    var fd = new FormData();
    fd.append("afile", $('.uploader' + id + ' :file')[0].files[0]);
    //These extra params aren't necessary but show that you can include other data.
    var i = 0;
    $.each(fields, function(i) {
        fd.append('values[' + i + '][name]', this.name);
        fd.append('values[' + i + '][value]', this.value);
        i++;
    });

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'ajax/upload.php', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable && typeof $('.uploader' + id + ' :file')[0].files[0] != "undefined") {
            var percentComplete = (e.loaded / e.total) * 100;
            $('.uploader' + id + ' .progress').css("width", percentComplete + '%');
            if (percentComplete == 100) {
                $('.uploader' + id + ' .progress').html('Upload Finished: Processing...');
            } else {
                $('.uploader' + id + ' .progress').html(percentComplete.toFixed(2) + '%');
            }
        }
    }

    xhr.onload = function() {
        if (this.status == 200) {
            callback(this.response);
        }
    }

    xhr.send(fd);
}

function CreateConfirm(id, messageText, okText, cancelText, okCallback, cancelCallback) {
    $('#' + id + ' label').html(messageText);
    var thisdialog = $('#' + id);
    $('#' + id).dialog({
        dialogClass: "big_confirm",
        draggable: false,
        resizable: false,
        height: 300,
        modal: true,
        buttons: [{
            text: okText,
            click: function() {
                $(thisdialog).dialog("destroy");
                okCallback();
            }
        }, {
            text: cancelText,
            click: function() {
                $(thisdialog).dialog("destroy");
                cancelCallback();
            }
        }]
    });
}

function CreateAlert(id, messageText, cancelText, cancelCallback) {
    $('#' + id + ' label').html(messageText);
    var thisdialog = $('#' + id);
    $('#' + id).dialog({
        dialogClass: "big_confirm",
        draggable: false,
        resizable: false,
        height: 300,
        modal: true,
        buttons: [{
            text: cancelText,
            click: function() {
                $(thisdialog).dialog("destroy");
                cancelCallback();
            }
        }]
    });
}

function CreateDialog(id, height, width, classes) {
    $('#' + id).dialog({
        dialogClass: classes,
        draggable: false,
        resizable: false,
        height: height,
        width: width,
        modal: true,
        open: function() {
            this.focus();
        },
        focus: function() {
            refresh_tags_editor();
        },
        close: function(event, ui) {
            $('#' + id + ' > form').each(function() {
                this.reset();
            });
        }
    });
}

function numpad(id) {
    var thisdialog = $('#' + id);
    $('#' + id).dialog({
        height: 555,
        width: 290,
        resizable: false,
        draggable: false,
        modal: true,
        open: function() {
            $(':input', thisdialog).val('');
            var kpad = $('.' + id + 'keypad_submit');
            kpad.button('destroy');
            kpad.children().removeClass("ui-button-text");
            kpad.button();
            kpad.button('option', 'disabled', true);
        }
    });
}

function close_modal() {
    $.nmTop().close();
}

function resize_modal() {
    var contentWidth = $('.printthis').width() + 30;
    var contentHeight = $('.printthis').height() + 30;

    var maxWidth = $(window).width();
    var maxHeight = $(window).height();

    var width = contentWidth > maxWidth ? maxWidth : contentWidth;
    var height = contentHeight > maxHeight ? maxHeight : contentHeight;


    $('.printthis .doc').each(function() { //reduce the size of an image if it is still too big
        var w = $(this).width();
        $(this).width(w > maxWidth ? maxWidth - 100 : w);
    });

    $.nmTop().sizes.initW = width;
    $.nmTop().sizes.initH = height;

    $.nmTop().resize();
}

function SelectSelectableElements(selectableContainer, elementsToSelect) {
    $(".ui-selected", selectableContainer).not(elementsToSelect).removeClass("ui-selected").addClass("ui-unselecting");
    $(elementsToSelect).not(".ui-selected").addClass("ui-selecting");
    //selectableContainer.data("selectable")._mouseStop(null);
}

function ucfirst(str, force) {
    str = force ? str.toLowerCase() : str;
    return str.replace(/(\b)([a-zA-Z])/,
        function(firstLetter) {
            return firstLetter.toUpperCase();
        });
}

function ucwords(str, force) {
    str = force ? str.toLowerCase() : str;
    return str.replace(/(\b)([a-zA-Z])/g,
        function(firstLetter) {
            return firstLetter.toUpperCase();
        });
}

refresh_all();
smart_scrollbars();

$(document).bind("ajaxSend", function() {
    $(".loadingscreen").show();
}).bind("ajaxComplete", function() {
    $(".loadingscreen").hide();
});