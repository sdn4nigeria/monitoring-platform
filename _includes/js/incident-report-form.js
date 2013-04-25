function fillIncidentReportForm(properties)
{
    var form = $('#incident-report-form').get(0);
    if (form) for (var p in properties) {
        var element = form[p];
        if (!element || !(element instanceof Node)) continue;
        var value = properties[p];
        FormUtils.setValue(element, value);
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
