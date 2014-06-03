jQuery.fn.toggleSwitch = function (params) {

    var defaults = {
        toggle: false,
        highlight: true,
        width: 40,
        change: null,
        toggleset: false
    };

    var options = $.extend({}, defaults, params);
    
    if(options.toggleset){
        return $(this)[0].selectedIndex == 2 ? false : true;
    }
        
    $(this).each(function (i, item) {
        generateToggle(item);
    });
        
    function generateToggle(selectObj) {
        if(options.toggle){
           var slideContain = $(selectObj).nextAll(".ui-toggle-switch");
           var index = $(selectObj)[0].selectedIndex;
           if(index.toString() != options.toggle){
                $(slideContain).find("label").eq(options.toggle).addClass("ui-state-active").siblings("label").removeClass("ui-state-active");
                $(slideContain).parent().find("option").not(options.toggle).prop("selected", false);
                $(slideContain).parent().find("option").eq(options.toggle).prop("selected", true);
                $(slideContain).find(".ui-slider").slider("value", options.toggle * 100);
           }
           return; 
        }
        
        // create containing element
        var $contain = $("<div />").addClass("ui-toggle-switch");
        
        var o = new Option("", "", true, true);
        /// jquerify the DOM object 'o' so we can use the html method
        $(selectObj).append(o);
        
        // generate labels
        $(selectObj).find("option").each(function (i, item) {
            if($(item).text().length > 0){ 
                $contain.append("<label>" + $(item).text() + "</label>"); 
            }else{
                $contain.append("<label style='margin:0;'>" + $(item).text() + "</label>");    
            }
        }).end().addClass("ui-toggle-switch");

        // generate slider with established options
        var $slider = $("<div />").slider({
            min: 0,
            step: 50,
            max: 100,
            animate: "fast",
            change: options.change,
            stop: function (e, ui) {
                var roundedVal = ui.value / 100;
                var self = this;
                window.setTimeout(function () {
                    toggleValue(self.parentNode, roundedVal);
                }, 11);
            },
            range: (options.highlight && !$(selectObj).data("hideHighlight")) ? "max" : null
        }).width(options.width);

        // put slider in the middle
        $slider.insertAfter(
            $contain.children().eq(0)
		);

        // bind interaction
        $contain.delegate("label", "click", function () {
            if ($(this).hasClass("ui-state-active")) {
                return;
            }
            var labelIndex = ($(this).is(":first-child")) ? 0 : ($(this).is(":last-child")) ? .5 : 1;
            toggleValue(this.parentNode, labelIndex);
        });
        
        function toggleValue(slideContain, index) {
            if(index != ".5"){
                $(slideContain).find("label").eq(index).addClass("ui-state-active").siblings("label").removeClass("ui-state-active");
                $(slideContain).parent().find("option").not(index).prop("selected", false);
                $(slideContain).parent().find("option").eq(index).prop("selected", true);
                $(slideContain).find(".ui-slider").slider("value", index * 100);               
            }else{
                $(slideContain).find("label").eq(0).removeClass("ui-state-active");
                $(slideContain).find("label").eq(1).removeClass("ui-state-active");
                $(slideContain).parent().find("option").not(2).prop("selected", false);
                $(slideContain).parent().find("option").eq(2).prop("selected", true);
                $(slideContain).find(".ui-slider").slider("value", index * 100);
            }
        }
        
        // initialise selected option
        $contain.find("label").eq(selectObj.selectedIndex).click();

        // add to DOM
        $(selectObj).parent().append($contain);

    }
};