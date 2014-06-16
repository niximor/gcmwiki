function addEvent(element, event, callback) {
    if (!element.addEventListener) {
        element.attachEvent("on" + event, callback);
    } else if (element.addEventListener) {
        element.addEventListener(event, callback);
    }
}

function fireEvent(element, event) {
    if (document.createEvent && element.dispatchEvent) {
        var evt = document.createEvent("HTMLEvents");
        evt.initEvent(event, true, true);
        element.dispatchEvent(evt); // for DOM-compliant browsers
    } else if (element.fireEvent) {
        element.fireEvent("on" + event); // for IE
    }
}

addEvent(window, "load", function(){
    var sel = document.getElementsByTagName("select");
    for (var i = 0; i < sel.length; ++i) {
        if (sel[i].className.indexOf("checkbox") >= 0) {
            // Install graphical checkbox instead of select.
            var span = document.createElement("span");
            span.className = "checkbox";

            sel[i].parentNode.insertBefore(span, sel[i]);
            sel[i].style.display = "none";

            var change = (function(select, span) { return function() {
                if (select.selectedOptions.length > 0) {
                    var selection = select.selectedOptions[0];
                    if (selection.value == 1) {
                        span.className = "checkbox yes";
                    } else if (selection.value == 0) {
                        span.className = "checkbox no";
                    } else {
                        span.className = "checkbox none";
                    }
                    span.title = selection.text;
                }
            }})(sel[i], span);

            change();
            addEvent(sel[i], "change", change);

            addEvent(span, "click", (function(select, span) { return function() {
                var idx = select.selectedIndex;
                idx = (idx + 1) % select.options.length;
                select.selectedIndex = idx;
                fireEvent(select, "change");
            }})(sel[i], span));
        }
    }
});