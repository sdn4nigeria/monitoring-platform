var FormUtils = {
    setValue: function (target, value)
    {
        var menu;
        var type = target.tagName.toLowerCase();
        if (target.hasAttribute("type")) type += "." + target.getAttribute("type");
        switch (type) {
        case "input.date":
            if ("today" === value) {
                var today = new Date();
                value = today.getFullYear() + "-" +
                    ("0"+(1+today.getMonth())).substr(-2) + "-" +
                    ("0"+today.getDate ()).substr(-2);
            }
            target.value = value;
            break;
        case "input.combobox":
            menu = target.previousSibling;
            if (menu && menu.nodeName.toLowerCase() === "select") {
                var otherValue = target.getAttribute("otherValue");
                menu.value = value;
                if (menu.value !== value) menu.value = otherValue;
                if (menu.value !== otherValue) target.style.display = "none";
                else                           target.style.display = "";
            }
            if (value === otherValue && otherValue) target.value = otherValue + ":";
            else if (target.value !== value) target.value = value;
            break;
        case "select.combobox":
            menu = target;
            target = menu.nextSibling;
            if (target && target.nodeName.toLowerCase() === "input") {
                FormUtils.setValue(target, value);
            }
            else if (menu.value != value) menu.value = value;
            break;
        case "textarea":
            target.textContent = value;
            break;
        case "input.number":
            if ("number" === typeof value) {
                var precision = 10;
                if (target.hasAttribute("precision")) {
                    precision = Number(target.getAttribute("precision"));
                }
                value = value.toPrecision(precision);
            }
            target.value = value;
            break;
        default:
            target.value = value;
        }
    },
    combobox: function (target, options, otherValue)
    {
        target.setAttribute("otherValue", otherValue);
        target.setAttribute("type", "combobox");
        var menu = document.createElement("select");
        menu.setAttribute("onchange",
                          "FormUtils.setValue(this, this.value)"
                         );
        menu.setAttribute("type", "combobox");
        var option, value = otherValue, label = "Other";
        if (otherValue !== false) {
            option = document.createElement("option");
            option.setAttribute("value", value);
            option.textContent         = label;
            menu.appendChild(option);
        }
        for (var i = 0; i < options.length; ++i) {
            var optionData = options[i];
            option = document.createElement("option");
            if ("string" === typeof optionData) {
                value = optionData;
                label = optionData;
            }
            else {
                value = optionData[0];
                label = optionData[1];
            }
            option.setAttribute("value", value);
            option.textContent         = label;
            menu.appendChild(option);
        }
        target.parentNode.insertBefore(menu, target);
        target.setAttribute("onchange",
                            "FormUtils.setValue(this, this.value)");
        FormUtils.setValue(target, otherValue);
    },
    date: function (target)
    {
        var button = document.createElement("button");
        button.textContent = "Today";
        button.setAttribute("onclick",
                            "event.preventDefault(); FormUtils.setValue(this.previousSibling, 'today')");
        if (target.nextSibling) target.parentNode.insertBefore(button, target.nextSibling);
        else                    target.parentNode.appendChild(button);
    },
    addAttachmentLink: function (container, id)
    {
        if ("string" == typeof container) container = document.getElementById(container);
        var input;
        input = document.createElement("input");
        input.setAttribute("size", "");
        input.setAttribute("type", "url");
        input.setAttribute("name", "attachmentLink[]");
        if (id) input.setAttribute("id", id);
        container.appendChild(input);
        input.focus();
        input = document.createElement("textarea");
        input.setAttribute("placeholder", "caption");
        input.setAttribute("name", "attachmentLinkCaption[]");
        container.appendChild(input);
    },
    addAttachmentFile: function (container)
    {
        if ("string" == typeof container) container = document.getElementById(container);
        var serial = container.childNodes.length;// Just a unique number as the number of widgets increases
        var form = document.createElement("form");
        form.setAttribute("action", "upload-attachment.php");
        form.setAttribute("method", "POST");
        form.setAttribute("enctype", "multipart/form-data");
        form.setAttribute("target", "file-upload-frame-" + serial);
        form.setAttribute("onsubmit", "return !!this['attachmentFile'].value");
        var iframe = document.createElement("iframe");
        iframe.style.display = "none";
        iframe.setAttribute("src", "");
        iframe.setAttribute("name", "file-upload-frame-" + serial);
        form.appendChild(iframe);
        input = document.createElement("input");
        input.setAttribute("size", "8");
        input.setAttribute("type", "file");
        input.setAttribute("name", "attachmentFile");
        form.appendChild(input);
        input = document.createElement("button");
        input.setAttribute("type", "submit");
        input.textContent = "Upload";
        form.appendChild(input);
        input = document.createElement("button");
        input.setAttribute("onclick", "event.preventDefault(); var form = this.parentNode; form.parentNode.removeChild(form.nextSibling); form.parentNode.removeChild(form)");
        input.textContent = "Cancel";
        form.appendChild(input);
        container.appendChild(form);
        var c = document.createElement("div");
        c.style.display = "none";
        container.appendChild(c);
        FormUtils.addAttachmentLink(c, "file-upload-field-" + serial);
        iframe.setAttribute("onload", "var url = this.contentDocument.body.textContent; if (url) { var link = document.getElementById('file-upload-field-"+serial+"'); link.value = url; link.parentNode.style.display = ''; var form = this.parentNode; form.parentNode.removeChild(form); }");
    }
};
