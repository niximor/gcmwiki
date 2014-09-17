"use strict";

function addEvent(element, event, callback) {
    if (element.addEventListener) {
        element.addEventListener(event, callback);
    } else if (element.attachEvent) {
        element.attachEvent("on" + event, callback);
    } else {
        throw new Error("Your browser does not seem to support events.");
    }

    return callback;
}

function removeEvent(element, event, callback) {
    if (element.removeEventListener) {
        element.removeEventListener(event, callback);
    } else if (element.detachEvent) {
        element.detachEvent("on" + event, callback);
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

function installCheckbox(element) {
    // Install graphical checkbox instead of select.
    var span = document.createElement("span");
    span.className = "checkbox";

    element.parentNode.insertBefore(span, element);
    element.style.display = "none";

    var change = (function(select, span) { return function() {
        var firstSelected = null;
        for (var i = 0; i < select.options.length; ++i) {
            if (select.options[i].selected && firstSelected == null) {
                firstSelected = select.options[i];
                break;
            }
        }

        if (firstSelected != null) {
            if (firstSelected.value == 1) {
                span.className = "checkbox yes";
            } else if (firstSelected.value == 0) {
                span.className = "checkbox no";
            } else {
                span.className = "checkbox none";
            }
            span.title = firstSelected.text;
        }
    }})(element, span);

    change();
    addEvent(element, "change", change);

    addEvent(span, "click", (function(select, span) { return function() {
        var idx = select.selectedIndex;
        idx = (idx + 1) % select.options.length;
        select.selectedIndex = idx;
        fireEvent(select, "change");
    }})(element, span));
}

function setCollapsibles() {
    var list = document.querySelectorAll(".collapsible");
    for (var i = 0; i < list.length; ++i) {
        var col = list[i];

        if (col.dataset.state == undefined) {
            col.dataset.state = "expanded";
        } else if (col.dataset.state == "collapsed") {
            var toProcess = document.querySelectorAll(col.dataset.for);
            for (var j = 0; j < toProcess.length; ++j) {
                var el = toProcess[j];
                el.style.display = "none";
            }
        }

        addEvent(col, "click", (function(col){ return function() {
            var toProcess = document.querySelectorAll(col.dataset.for);
            var display = "";

            if (col.dataset.state == "expanded") {
                col.dataset.state = "collapsed";
                display = "none";
            } else {
                col.dataset.state = "expanded";
            }

            for (var j = 0; j < toProcess.length; ++j) {
                var el = toProcess[j];
                el.style.display = display;
            }
        }})(col));
    }
}

function ModalWindow(name, container) {
    this.container = container;
    this.container.modalWindow = this;

    var title = document.createElement("h1");
    title.innerHTML = name;

    this.moving = false;
    this.lastX = null;
    this.lastY = null;

    this.evmove = null;
    this.evup = null;

    var wnd = this;
    addEvent(title, "mousedown", function(e){
        wnd.moving = true;
        wnd.lastX = e.clientX;
        wnd.lastY = e.clientY;

        if (e.preventDefault) {
            e.preventDefault();
        }

        wnd.evmove = addEvent(window, "mousemove", function(e){
            if (!wnd.moving) {
                return;
            }

            if (e.preventDefault) {
                e.preventDefault();
            }

            var diffX = wnd.lastX - e.clientX;
            var diffY = wnd.lastY - e.clientY;

            var newX = Math.max(-wnd.window.clientWidth + 16, parseInt(wnd.window.style.left) - diffX);
            var newY = Math.max(-title.clientHeight, parseInt(wnd.window.style.top) - diffY);

            wnd.window.style.left = newX.toString() + "px";
            wnd.window.style.top = newY.toString() + "px";

            wnd.lastX = e.clientX;
            wnd.lastY = e.clientY;

            return false;
        });

        wnd.evup = addEvent(window, "mouseup", function(e){
            wnd.moving = false;
            removeEvent(window, "mousemove", wnd.evmove);
            removeEvent(window, "mouseup", wnd.evup);
        });

        return false;
    });

    var content = document.createElement("div");
    content.className = "content";
    content.appendChild(this.container);

    this.window = document.createElement("div");
    this.window.className = "window";

    var wnd2 = document.createElement("div");
    wnd2.appendChild(title);
    wnd2.appendChild(content);

    this.window.appendChild(wnd2);

    this.cancelable = true;
}

ModalWindow.cover = null;
ModalWindow.stack = [];

ModalWindow.prototype.setCancelable = function(cancelable) {
    this.cancelable = cancelable;
}

ModalWindow.prototype._createCover = function() {
    var cover = ModalWindow.cover = document.createElement("div");
    var container = document.createElement("div");
    cover.appendChild(container);

    cover.id = "modalCover";
    document.body.appendChild(cover);

    var cancelHandler = function() {
        var topmost = ModalWindow.stack.pop();
        if (topmost.modalWindow.cancelable) {
            topmost.modalWindow.hide();
        }
    };

    ModalWindow.globalEvent = addEvent(window, "keypress", function(e) {
        if (!e) {
            e = window.event;
        }

        if (e.keyCode == 27) {
            cancelHandler()
        }
    });

    addEvent(cover, "click", function(e){
        if ((e.srcElement && e.srcElement == ModalWindow.cover) || (e.target && e.target == ModalWindow.cover)) {
            cancelHandler();
        }
    });
}

ModalWindow.prototype._removeCover = function() {
    removeEvent(window, "keypress", ModalWindow.globalEvent);
    ModalWindow.globalEvent = null;

    ModalWindow.cover.parentNode.removeChild(ModalWindow.cover);
    ModalWindow.cover = null;
}

ModalWindow.prototype.show = function() {
    if (!ModalWindow.cover) {
        this._createCover();
    }

    ModalWindow.cover.firstChild.appendChild(this.window);
    ModalWindow.stack.push(this.container);

    // Center window in the container.
    var cw = ModalWindow.cover.clientWidth;
    var ch = ModalWindow.cover.clientHeight;
    var ww = this.window.clientWidth;
    var wh = this.window.clientHeight;

    this.window.style.left = (cw / 2 - ww / 2).toString() + "px";
    this.window.style.top = (ch / 2 - wh / 2).toString() + "px";
}

ModalWindow.prototype.hide = function() {
    this.window.parentNode.removeChild(this.window);

    // Find window on stack and remove it.
    for (var i = 0; i < ModalWindow.stack.length; ++i) {
        if (ModalWindow.stack[i] == this.container) {
            ModalWindow.stack.splice(i, 1);
            break;
        }
    }

    // If that was last window on stack...
    if (ModalWindow.stack.length == 0) {
        this._removeCover();
    }
}

function WikiEditor() {
    this.initialized = false;

    this.form = document.querySelector("form.editPage");
    if (!this.form) {
        return false;
    }

    this.textarea = this.form.querySelector("textarea[name=body]");
    this.inputs = this.form.querySelectorAll(".toolbar input[type=button]:not([disabled])");

    for (var i = 0; i < this.inputs.length; ++i) {
        addEvent(this.inputs[i], "click", (function(editor, button){ return function() {
            editor.handleButton(button);
        }})(this, this.inputs[i]));
    }

    this.initialized = true;
}

WikiEditor.prototype.handleButton = function(button) {
    if (!this.initialized) {
        return;
    }

    switch (button.name) {
        case "bold":
            this.wrapSelection("**", "**");
            this.textarea.focus();
            break;

        case "italic":
            this.wrapSelection("//", "//");
            this.textarea.focus();
            break;

        case "underline":
            this.wrapSelection("__", "__");
            this.textarea.focus();
            break;

        case "strikethrough":
            this.wrapSelection("--", "--");
            this.textarea.focus();
            break;

        case "code":
            this.wrapSelection("``", "``");
            this.textarea.focus();
            break;

        case "newline":
            this.beforeCursor("\\\\\\\\");
            this.textarea.focus();
            break;

        case "ulist":
            this.wrapLines("* ");
            this.textarea.focus();
            break;

        case "olist":
            this.wrapLines("- ");
            this.textarea.focus();
            break;

        case "quote":
            this.wrapLines("> ");
            this.textarea.focus();
            break;

        case "line":
            this.beforeCursor("\n----\n");
            this.textarea.focus();
            break;

        case "link":
            this.linkWindow();
            break;

        case "image":
            this.imageWindow();
            break;

        case "table":
            this.tableWindow();
            break;

        case "special":
            if (button.className.indexOf("down") < 0) {
                button.className += " down";
            } else {
                button.className = this.strSplice(button.className, button.className.indexOf("down"), 4);
            }
            break;

        case "toc":
            this.beforeCursor("{{{toc}}}");

            // close the special menu
            var spec = document.querySelector("input[name=special]");
            spec.className = this.strSplice(spec.className, spec.className.indexOf("down"), 4);
            break;

        case "category":
            this.beforeCursor("{{{category}}}");

            // close the special menu
            var spec = document.querySelector("input[name=special]");
            spec.className = this.strSplice(spec.className, spec.className.indexOf("down"), 4);
            break;

        case "template":
            this.templateWindow();

            // close the special menu
            var spec = document.querySelector("input[name=special]");
            spec.className = this.strSplice(spec.className, spec.className.indexOf("down"), 4);
            break;
    }

    this.textarea.focus();
}

WikiEditor.prototype.strSplice = function(str, start, delCount, newSubStr) {
    return str.slice(0, start) + newSubStr + str.slice(start + Math.abs(delCount));
}

WikiEditor.prototype.wrapSelection = function(begin, end) {
    if (!this.initialized) {
        return false;
    }

    var selStart = this.textarea.selectionStart;
    var selEnd = this.textarea.selectionEnd;

    if (!selEnd) {
        selEnd = selStart;
    }

    this.textarea.value = this.strSplice(this.textarea.value, selStart, 0, begin);
    selStart += begin.length;
    selEnd += begin.length;
    this.textarea.value = this.strSplice(this.textarea.value, selEnd, 0, end);

    this.textarea.setSelectionRange(selStart, selEnd);
}

WikiEditor.prototype.wrapLines = function(before) {
    var selStart = this.textarea.selectionStart;
    var selEnd = this.textarea.selectionEnd;

    // Find begining of line
    if (selStart > 0) {
        selStart = this.textarea.value.lastIndexOf("\n", selStart) + 1;
    }

    while (selStart <= selEnd && selStart > 0) {
        this.textarea.value = this.strSplice(this.textarea.value, selStart, 0, before);
        selStart += before.length;
        selEnd += before.length;

        selStart = this.textarea.value.indexOf("\n", selStart) + 1;
    }

    this.textarea.setSelectionRange(selEnd, selEnd);
}

WikiEditor.prototype.beforeCursor = function(text) {
    var selStart = this.textarea.selectionStart;
    var selEnd = this.textarea.selectionEnd;

    this.textarea.value = this.strSplice(this.textarea.value, selStart, selEnd - selStart, text);
    this.textarea.setSelectionRange(selStart + text.length, selStart + text.length);
}

WikiEditor.prototype.linkWindow = function() {
    var div = document.createElement("form");
    div.className = "small";
    div.innerHTML = ""
        + "<div class=\"fullwidth\"><label for=\"target\">Link target:</label> <input type=\"text\" name=\"target\" /></div>"
        + "<div class=\"fullwidth\"><label for=\"description\">Description:</label> <input type=\"text\" name=\"description\" /></div>"
        + "<div class=\"buttons\"><input type=\"button\" name=\"ok\" value=\"OK\" /> <input type=\"button\" name=\"cancel\" value=\"Cancel\" /></div>";

    var wnd = new ModalWindow("Create link", div);
    var editor = this;

    addEvent(div.querySelector("input[name=cancel]"), "click", function(){
        wnd.hide();
        editor.textarea.focus();
    });

    addEvent(div.querySelector("input[name=ok]"), "click", function(){
        var target = div.querySelector("input[name=target]");
        var description = div.querySelector("input[name=description]");

        if (target.value != "") {
            if (description.value != "") {
                editor.beforeCursor("[[" + target.value + "|" + description.value + "]]");
            } else {
                editor.beforeCursor("[[" + target.value + "]]");
            }
        }

        wnd.hide();
        editor.textarea.focus();
    });

    wnd.show();
}

WikiEditor.prototype.imageWindow = function(){
    var div = document.createElement("form");
    div.className = "small";
    div.innerHTML = ""
        + "<div class=\"fullwidth\"><label for=\"url\">Image URL:</label> <div><input type=\"text\" name=\"url\" /></div></div>"
        + "<div><label for=\"align\">Align:</label> "
        + "<label><input type=\"radio\" name=\"align\" value=\"\" checked /> In text</label> "
        + "<label><input type=\"radio\" name=\"align\" value=\"left\" /> Left</label> "
        + "<label><input type=\"radio\" name=\"align\" value=\"right\" /> Right</label></div>"
        + "<div class=\"fullwidth\"><label for=\"size\">Size:</label> <div><input type=\"text\" name=\"size\" /></div> "
        + "<div class=\"comment\">Possible formats: 50%, 128x96, 128pxx96px, 128emx96em, 128pxx96em, 128emx96px.</div></div>"
        + "<div class=\"fullwidth\"><label for=\"caption\">Caption:</label> <div><input type=\"text\" name=\"caption\" /></div></div>"
        + "<div class=\"buttons\"><input type=\"submit\" name=\"ok\" value=\"OK\" /> <input type=\"button\" name=\"cancel\" value=\"Cancel\" /></div>";

    var wnd = new ModalWindow("Create image", div);
    var editor = this;

    addEvent(div.querySelector("input[name=cancel]"), "click", function(){
        wnd.hide();
        editor.textarea.focus();
    });

    addEvent(div.querySelector("input[name=ok]"), "click", function(){
        var url = div.querySelector("input[name=url]");
        var align = div.querySelector("input[name=align]:checked");
        var size = div.querySelector("input[name=size]");
        var caption = div.querySelector("input[name=caption]");

        if (url.value != "") {
            var imgTag = "((" + url.value;

            var params = [];

            if (size.value != "") {
                params.push(size.value);
            }

            if (align.value != "") {
                params.push(align.value);
            }

            if (params.length > 0) {
                imgTag += "|" + params.join();
            }

            if (caption.value != "") {
                imgTag += "|" + caption.value;
            }

            imgTag += "))";
            editor.beforeCursor(imgTag);
        }

        wnd.hide();
        editor.textarea.focus();
    });

    wnd.show();
}

WikiEditor.prototype.tableWindow = function(){
    var div = document.createElement("form");
    div.className = "small";
    div.innerHTML = ""
        + "<div class=\"fullwidth\"><label for=\"columns\">Columns:</label> <div><input type=\"text\" name=\"columns\" value=\"3\" /></div></div>"
        + "<div class=\"fullwidth\"><label for=\"rows\">Rows:</label> <div><input type=\"text\" name=\"rows\" value=\"3\" /></div></div>"
        + "<div class=\"buttons\"><input type=\"button\" name=\"ok\" value=\"OK\" /> <input type=\"button\" name=\"cancel\" value=\"Cancel\" /></div>";

    var wnd = new ModalWindow("Create table", div);
    var editor = this;

    addEvent(div.querySelector("input[name=cancel]"), "click", function(){
        wnd.hide();
        editor.textarea.focus();
    });

    addEvent(div.querySelector("input[name=ok]"), "click", function(){
        var out = []

        var rows = div.querySelector("input[name=rows]").value;
        var columns = div.querySelector("input[name=columns]").value;

        if (rows > 0 && columns > 0) {
            for (var r = 0; r < rows; r++) {
                out.push("||");
                for (var c = 0; c < columns; c++) {
                    out.push(" ||");
                }
                if (r < rows - 1) {
                    out.push("\n||-\n");
                } else {
                    out.push("\n");
                }
            }
        }

        editor.beforeCursor(out.join(""));

        wnd.hide();
        editor.textarea.focus();
    });

    wnd.show();
}

WikiEditor.prototype.templateWindow = function(){
    var div = document.createElement("form");
    div.className = "small";
    div.innerHTML = ""
        + "<div class=\"fullwidth\"><label for=\"name\">Template name:</label> <div><input type=\"text\" name=\"name\" /></div></div>"
        + "<div class=\"buttons\"><input type=\"button\" name=\"ok\" value=\"OK\" /> <input type=\"button\" name=\"cancel\" value=\"Cancel\" /></div>";

    var wnd = new ModalWindow("Insert template", div);
    var editor = this;

    addEvent(div.querySelector("input[name=cancel]"), "click", function(){
        wnd.hide();
        editor.textarea.focus();
    });

    addEvent(div.querySelector("input[name=ok]"), "click", function(){
        var name = div.querySelector("input[name=name]").value;

        editor.beforeCursor("{{{template:" + name + "}}}");

        wnd.hide();
        editor.textarea.focus();
    });

    wnd.show();
}

function startup(callback) {
    addEvent(window, "DOMContentLoaded", callback);
}

startup(function(){
    var sel = document.getElementsByTagName("select");
    for (var i = 0; i < sel.length; ++i) {
        if (sel[i].className.indexOf("checkbox") >= 0) {
            installCheckbox(sel[i]);
        }
    }

    setCollapsibles();
    new WikiEditor();
});