var spill_data_fields = ['spillid', 'updatefor', 'status', 'incidentdate', 'company', 'initialcontainmentmeasures', 'estimatedquantity', 'contaminant', 'cause', 'latitude', 'longitude', 'lga', 'sitelocationname', 'estimatedspillarea', 'spillareahabitat', 'attachments', 'impact', 'descriptionofimpact', 'datejiv', 'datespillstopped', 'datecleanup', 'datecleanupcompleted', 'methodsofcleanup', 'dateofpostcleanupinspection', 'dateofpostimpactassessment', 'furtherremediation', 'datecertificate'];
function fillIncidentReportForm(properties)
{
    if ("string" == typeof properties) {
        // properties is a spillid, so we look for the corresponding feature
        var features = spillsLayer.features();
        for (var i = 0; i < features.length; ++i) {
            var feature = features[i];
            if (properties == feature.properties.spillid) {
                properties = feature.properties;
                break;
            }
        }
        if ("object" != typeof properties) {
            alert("Not found any incident with spill id \"" + properties + "\"");
            properties = {};
        }
    }
    var form = $('#incident-report-form').get(0);
    if (form) {
        for (var i = 0; i < spill_data_fields.length; ++i) {
            var p = spill_data_fields[i];
            var value = (p in properties)?properties[p]:"";
            if ('updatefor' === p && !value) continue;// if empty, use spillid
            if ('spillid' === p) p = 'updatefor';
            var element = form[p];
            if (!element || !(element instanceof Node)) continue;
            FormUtils.setValue(element, value);
        }
    }
}

function loadLoginForm(form)
{
    var container = $('#incident-report-form-container');
    $.ajax({
        type: "POST",
        url: "login.php",
        data: form?($(form).serialize()):null,
        dataType: "html", // This is the *response* type we want
        success: function (responseText, textStatus, XMLHttpRequest) {
            container.html(responseText);
        }
    });
    
    return false;
}

function loadIncidentReportForm(form)
{
    var link = $('#main-nav-report-spill');
    var container = $('#incident-report-form-container');
    if (form && "lga" in form) form["lga"].disabled = false;
    $.ajax({
        type: "POST",
        url: "report-incident.php",
        data: form?($(form).serialize()):null,
        dataType: "html", // This is the *response* type we want
        success: function (responseText, textStatus, XMLHttpRequest) {
            container.html(responseText);
            $('#login-form-container').show();
            if (form && /Spill report (updated|added)/.test(responseText)) {
                loadSpillData();// Reload with changes
                var button = document.createElement("button");
                button.setAttribute("onclick",
                                    "toggleIncidentReportForm(); return false");
                button.textContent = "OK";
                container.append(button);
            }
        }
    });
    
    return false;
}

function toggleIncidentReportForm()
{
    var container = $('#intro-text');
    if (container.hasClass('incident-report')) {
        container.removeClass('incident-report');
        $('#incident-report-form-container').html('');
    }
    else {
        container.addClass('incident-report');
        loadIncidentReportForm();
    }
}

function changeCoordinates(input)
{
    var latitude  = input.form["latitude" ];
    var longitude = input.form["longitude"];
    if (!(latitude.value && longitude.value)) return;
    
    var url = "coordinates.php?";
    var units = document.getElementById('coordinate-units');
    if ("lat-lon" === units.value) {
        url += "latitude=" + encodeURIComponent(latitude.value)
            + "&longitude=" + encodeURIComponent(longitude.value);
    }
    else if ("nor-eas" === units.value) {
        url += "northings=" + encodeURIComponent(latitude.value)
            + "&eastings=" + encodeURIComponent(longitude.value);
    }
    else {
        alert("Unknown coordinate units: " + units.value);
        return;
    }
    var lga = input.form["lga"];
    lga.value = "loading...";
    
    $.getJSON(url, function (data) {
        if ("error" in data) alert("Error: " + data["error"]);
        else {
            FormUtils.setValue(latitude,  data.latitude);
            FormUtils.setValue(longitude, data.longitude);
            lga.value = data.lga;
            if (!lga.value) lga.disabled = false;
            units.value = "lat-lon";
            easey().map(mainMap)
                .to(mainMap.locationCoordinate({lat: data.latitude,
                                                lon: data.longitude
                                               }).zoomTo(mainMap.getZoom()))
                .run(500);
        }
    });
}
