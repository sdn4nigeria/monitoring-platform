---
---
var monthLabels = ["-",
                   "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                   "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
var mapLayers = {
    satellite:   "nigeriaoil.map-g3s2rdj8",
    flat:        "nigeriaoil.map-5ustxk97",
    lga:         "nigeriaoil.nigeria-lga",
    wetlands:    "nigeriaoil.NGWetlands",
    settlements: "nigeriaoil.NGSettlement"
};
var mainMap, spillsLayer;

var browser =   $.browser.version;

// via https://developer.mozilla.org/en-US/docs/JavaScript/Reference/Global_Objects/Array/indexOf
if (!Array.prototype.indexOf) {
    Array.prototype.indexOf = function (searchElement /*, fromIndex */ ) {
        "use strict";
        if (this == null) {
            throw new TypeError();
        }
        var t = Object(this);
        var len = t.length >>> 0;
        if (len === 0) {
            return -1;
        }
        var n = 0;
        if (arguments.length > 1) {
            n = Number(arguments[1]);
            if (n != n) { // shortcut for verifying if it's NaN
                n = 0;
            } else if (n != 0 && n != Infinity && n != -Infinity) {
                n = (n > 0 || -1) * Math.floor(Math.abs(n));
            }
        }
        if (n >= len) {
            return -1;
        }
        var k = n >= 0 ? n : Math.max(len - Math.abs(n), 0);
        for (; k < len; k++) {
            if (k in t && t[k] === searchElement) {
                return k;
            }
        }
        return -1;
    }
}

var MB = {};
MB.maps = {};

var apiUrl,
embedUrl,
basemap;

MB.api = function (l) {
    if (l.base != '') {
        basemap = l.base;
    }
    if (l.id == '') {
        return 'http://api.tiles.mapbox.com/v3/' + basemap + '.jsonp';
    } else {
        return 'http://api.tiles.mapbox.com/v3/' + basemap + ',' + l.id + '.jsonp';
    }
};

MB.map = function (el, l) {
    wax.tilejson(MB.api(l), function (t) {
        var h = [
            new MM.DragHandler(),
            new MM.DoubleClickHandler(),
            new MM.TouchHandler()];
        if ($.inArray('zoomwheel', l.features) >= 0) h.push(new MM.MouseWheelHandler());

        MB.maps[el] = new MM.Map(el, new wax.mm.connector(t), null, h);
        MB.maps[el].setCenterZoom({
            lat: (l.center) ? l.center.lat : t.center[1],
            lon: (l.center) ? l.center.lon : t.center[0]
        }, (l.center) ? l.center.zoom : t.center[2]);

        if (l.zoomrange) {
            MB.maps[el].setZoomRange(l.zoomrange[0], l.zoomrange[1]);
        } else {
            MB.maps[el].setZoomRange(t.minzoom, t.maxzoom);
        }

        if ($.inArray('zoompan', l.features) >= 0) wax.mm.zoomer(MB.maps[el]).appendTo(MB.maps[el].parent);
        if ($.inArray('zoombox', l.features) >= 0) wax.mm.zoombox(MB.maps[el]);
        if ($.inArray('bwdetect', l.features) >= 0) wax.mm.bwdetect(MB.maps[el]);
        if ($.inArray('share', l.features) >= 0) wax.mm.share(MB.maps[el], t).appendTo($('body')[0]);

        if ($.inArray('legend', l.features) >= 0) {
            MB.maps[el].legend = wax.mm.legend(MB.maps[el], t).appendTo(MB.maps[el].parent);
            $('.wax-legends').appendTo(MB.maps[el].parent);
        }
        if ($.inArray('tooltips', l.features) >= 0) {
            MB.maps[el].interaction = wax.mm.interaction().map(MB.maps[el]).tilejson(t).on(wax.tooltip().parent(MB.maps[el].parent).events());
        } else if ($.inArray('movetips', l.features) >= 0) {
            MB.maps[el].interaction = wax.mm.interaction().map(MB.maps[el]).tilejson(t).on(wax.movetip().parent(MB.maps[el].parent).events());
        }
    });
    apiUrl = MB.api(l);
};

MB.refresh = function (m, l) {
    wax.tilejson(MB.api(l), function (t) {
        var layer = l.layer || 0;
        try {
            MB.maps[m].setLayerAt(layer, new wax.mm.connector(t));
        } catch (e) {
            MB.maps[m].insertLayerAt(layer, new wax.mm.connector(t));
        }
        if (MB.maps[m].interaction) MB.maps[m].interaction.tilejson(t);
        if (MB.maps[m].legend) MB.maps[m].legend.content(t);
    });
    if (l.center) {
        var lat = l.center.lat || MB.maps[m].getCenter().lat,
        lon = l.center.lon || MB.maps[m].getCenter().lon,
        zoom = l.center.zoom || MB.maps[m].getZoom();

        if (l.center.ease > 0) {
            MB.maps[m].easey = easey().map(MB.maps[m]).to(MB.maps[m].locationCoordinate({
                lat: lat,
                lon: lon
            }).zoomTo(zoom)).run(l.center.ease);
        } else {
            MB.maps[m].setCenterZoom({
                lat: lat,
                lon: lon
            }, zoom);
        }
    }
    apiUrl = MB.api(l);
};

function commaSeparateNumber(val){
    while (/(\d+)(\d{3})/.test(val.toString())){
        val = val.toString().replace(/(\d+)(\d{3})/, '$1'+','+'$2');
    }
    return val;
}

function updateEmbedApi() {
    center = MB.maps['map'].pointLocation(new MM.Point(MB.maps['map'].dimensions.x / 2, MB.maps['map'].dimensions.y / 2));
    embedUrl = '<iframe src="' + apiUrl.replace(".jsonp", "") + '/mm/zoompan,tooltips,legend,bwdetect.html#' + MB.maps['map'].coordinate.zoom + '/' + center.lat + '/' + center.lon + '"' + ' frameborder="0" width="500" height="400"></iframe>';
    $('textarea.embed-code').text(embedUrl);
    $('textarea.api-code').text(apiUrl);
}

var spillDataIncludesUnverifiedReports;
function loadSpillData() {
    var dataset = "nosdra";
    // With the following line instead, take it from the url .../#nosdra
    // var dataset = window.location.hash.substring(1);
    if (!dataset) {
        alert("The spill data display requires a dataset ID");
        return;
    }
    function isThirdParty(properties) {
        // properties.thirdparty is from the old Google Docs schema,
        // not used any more [2013-04-28]
        return properties.cause == 'sab' || properties.thirdparty == 'yes';
    }
    function isCleanedUp(properties) {
        return properties.datecleanup || properties.datecleanupcompleted;
    }
    function isVerified(properties) {
        return properties.status == "verified";
    }
    
    function createSpillPoint(x) {
        var identifier;
        var popupNosdra = _.template(document.getElementById('popupNosdra').innerHTML);
        var events = x.properties.estimatedquantity;
        if (isVerified(x.properties)) {
            identifier = x.properties.spillid;
            var y, z;
            var d = document.createElement('div');
            //added to point div
            if (isNaN(events) || "" == events || "undefined" == events) {
                d.className = 'point blue';
            } else if (isCleanedUp(x.properties)) {
                d.className = 'point cleanedup';
            } else if (isThirdParty(x.properties)) {
                // Third party spill, typically sabotage
                d.className = 'point';
            } else {
                // Company spill
                d.className = 'point black';
            }
            
            if (events >= 0 && events < 10) {
                y = 14;
                z = 406;
            } else if (events > 10 && events <= 50) {
                y = 20;
                z = 384;
            } else if (events > 50 && events <= 250) {
                y = 28;
                z = 359;
            } else if (events > 250 && events < 500) {
                y = 43;
                z = 313;
            } else if (events > 500) {
                y = 54;
                z = 260;
            } else {
                // There is no amount reported: make it medium size
                y = 20;
                z = 384;
            }
            // The point on the map
            $(d).css("height", y + "px");
            $(d).css("width", y + "px");
            $(d).css("background-position",  -z + 'px ' + (y - 103) + 'px');
            $(d).css("margin-left", -(y / 2) + 'px');
            $(d).css("margin-top",-(y / 2) + 'px');
        }
        else {
            identifier = "<span class='spillid-unverified'>" + x.properties.spillid + "</span>";
            d = document.createElement("img");
            d.className = "point marker-unverified-report";
            d.setAttribute("src", "img/exclamation.png");
        }
        
        var quantity = x.properties.estimatedquantity;
        if(x.properties.estimatedquantity == 0){
            quantity = "Less than 1 ";
        }
        else if (!x.properties.estimatedquantity) {
            // estimatedquantity is an empty string
            quantity = "Unknown amount of ";
        }
        var cause = "";
        if ("cause" in x.properties) {
            cause = x.properties.cause;
            switch (cause.substr(0,3)) {
            case "cor": cause = "corrosion"; break;
            case "eqf": cause = "equipment failure"; break;
            case "pme": cause = "production/maintenance error"; break;
            case "sab": cause = "sabotage"; break;
            case "mys": cause = "unknown causes"; break;
            default:    cause = "["+x.properties.cause+"]";
            }
        }
        var contaminant = "";
        if ("contaminant" in x.properties) {
            contaminant = x.properties.contaminant;
            switch (contaminant.substr(0,2)) {
            case "cr": contaminant = "crude oil"; break;
            case "pr": contaminant = "refined oil product"; break;
            case "ch": contaminant = "drilling mud / chemicals"; break;
            case "fi": contaminant = "fire"; break;
            default:   contaminant = "["+contaminant+"]";
            }
        }
        var habitat = "";
        if ("spillareahabitat" in x.properties) {
            habitat = x.properties.spillareahabitat;
            switch (habitat.substr(0,3)) {
            case "la": habitat = "land"; break;
            case "ss": habitat = "seasonal swamp"; break;
            case "sw": habitat = "swamp"; break;
            case "co": habitat = "coastland"; break;
            case "iw": habitat = "inland waters"; break;
            case "ns": habitat = "near shore"; break;
            case "of": habitat = "offshore"; break;
            default:   habitat = "["+x.properties.spillareahabitat+"]";
            }
        }
        
        $(d).click(function() {
            var point = {lat: (x.geometry.coordinates[1]),
                         lon: (x.geometry.coordinates[0])
                        };
            var zoom = 10;
            easey().map(mainMap)
                .to(mainMap.locationCoordinate(point)
                    .zoomTo(zoom))
                .run(500);
            if ($('#copy-data-from-map').is(':checked')) fillIncidentReportForm(x.properties);
        });
        
        function property(name) { return x.properties[name] || ""; };
        // A points popup
        var contentNosdra = document.createElement('div');
        contentNosdra.className = 'popupNosdra clearfix';
        contentNosdra.innerHTML = popupNosdra({
            db: property,// fields from the database
            identifier: identifier,
            quantity: quantity,
            habitat: habitat,
            cause: cause,
            contaminant: contaminant
        });
        
        d.appendChild(contentNosdra);
        $(d).hover(function () {
            var tooltip = $('#tooltip');
            tooltip.empty();
            var contentNosdra = $(this).find('.popupNosdra').html();
            tooltip.html(contentNosdra);
        });
        
        return d;
    }

    var updateDisplay;
    function updateSpillData(features) {
        var companies = {};
        var years = {};
        var months = {};
        
        _(features).each(function(f) {
            var match = /^([0-9]+)-([0-9]+)-/.exec(f.properties.incidentdate);
            f.properties.year  = match[1];
            f.properties.month = match[2];
            f.properties.monthLabel = monthLabels[Number(match[2])];
            years[f.properties.year] = true;
            months[f.properties.monthLabel] = true;
            companies[f.properties.company] = true;
            
            return f;
        });
        
        var yearsAvailable = _(years).keys().sort(function(a,b){return a - b})
        var monthsAvailable = _(months).chain().keys().sortBy(function(v){
            return ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"].indexOf(v.toLowerCase());
        });
        var active = {
            company: _(companies).keys(),
            year: [yearsAvailable[yearsAvailable.length -1]],
            month: [monthsAvailable._wrapped[0]],
        };
        if (!active.company) alert("No company selected");
        if (!active.year)    alert("No year selected");
        if (!active.month)   alert("No month selected");
        
        if (!spillsLayer) {
            spillsLayer = mapbox.markers.layer();
            mainMap.addLayer(spillsLayer);
            mapbox.markers.interaction(spillsLayer);
        }
        spillsLayer.factory(createSpillPoint).features(features);
        
        updateDisplay = function () {
            _(active).each(function (v, k) {
                if (k == "month") return;
                if (k == "year" || k == "company") {
                    var label = v[0];
                    if (v.length > 1) label = "All";
                    $('#' + k + '-count').text(label);
                }
                else $('#' + k + '-count').text(v.length);
            });
            var showUnverified = $('#show-unverified-reports').is(':checked');
            var showCleanedUp  = $('#show-cleaned-up-spills') .is(':checked');
            var count = 0;
            spillsLayer.filter(function(f) {
                var test = active.company.indexOf(f.properties.company) !== -1 &&
                    active.year.indexOf(f.properties.year) !== -1 &&
                    active.month.indexOf(f.properties.monthLabel) !== -1;
                if (!isVerified(f.properties) && !showUnverified) test = false;
                if (isCleanedUp(f.properties) && !showCleanedUp ) test = false;
                if (test) ++count;
                
                return test;
            });
            $('#spill-number').text(commaSeparateNumber(count));
        }
        
        function setupInterface() {
            var clickHandle = function(type) {
                return function(ev) {
                    var elem = $(ev.currentTarget);
                    var v = elem.attr('id');
                    var i = active[type].indexOf(v);
                    elem.toggleClass('switch-active', i === -1);
                    if (i === -1) {
                        active[type].push(v);
                    } else {
                        active[type].splice(i, 1);
                    }
                    updateDisplay();
                    return false;
                };
            };
            // Variant that allows only one element selected
            var clickHandleUnique = function(type) {
                return function(ev) {
                    var elem = $(ev.currentTarget);
                    var v = elem.attr('id');
                    var allElements = elem.closest("ul").find("a");
                    if (/^all-/.test(v)) {
                        allElements.addClass("switch-active");
                        var all = [];
                        allElements.each(function (i) {
                            var id = $(this).attr('id');
                            if (!/^all-/.test(id)) all.push(id);
                        });
                        active[type] = all;
                    }
                    else {
                        allElements.removeClass("switch-active");
                        elem.addClass('switch-active');
                        active[type] = [v];
                    }
                    elem.closest(".dropdown").click();
                    updateDisplay();
                    return false;
                };
            };
            
            $('#options a').remove();
            _(monthsAvailable._wrapped).chain().sortBy(function(v){
                var activeClass = (active.month.indexOf(v) == -1 ? '' : ' switch-active');
                $('#options').append('<a href="#" class="time-switch clearfix' + activeClass + '" id="'+v+'">'+v+'</a>');
            });
            $('#options a').click(clickHandle('month'));
            
            $('#company-menu').html('');
            $('#company-menu').append('<li><a href="#" class="time-switch clearfix switch-active" id="all-companies">All companies</a>');
            _(companies).chain().keys().each(function(v) {
                $('#company-menu').append('<li><a href="#" class="time-switch clearfix switch-active" id="'+v+'">'+v+'</a>');
            });
            $('#company-menu a').click(clickHandleUnique('company'));
            
            $('#year-menu').html('');
            _(yearsAvailable).each(function(v) {
                var activeClass = (active.year.indexOf(v) == -1 ? '' : ' switch-active');
                $('#year-menu').append('<li><a href="#" class="time-switch clearfix' + activeClass + '" id="'+v+'">'+v+'</a>');
            });
            $('#year-menu a').click(clickHandleUnique('year'));
            
            $('#show-unverified-reports').change(function () {
                if (!spillDataIncludesUnverifiedReports) loadSpillData();
                else                                     updateDisplay();
            });
            $('#show-cleaned-up-spills').change(updateDisplay);
            
            //changing maps for baselayer
            $('ul.bottomlayer li a').click(function(e) {
                e.preventDefault();
                if (!$(this).hasClass('active')) {
                    mainMap.removeLayerAt(0);
                    mainMap.insertLayerAt(0, mapbox.layer().id(this.id));
                    $('ul.bottomlayer li a').removeClass('active');
                    $(this).addClass('active');
                }
                /* This if statement determines the zoom limits
                   based on which base map is shown, since the satellite
                   map is fuzzy when zoomed in too far  */
                if (this.id == mapLayers.satellite) {
                    mainMap.setZoomRange(4, 12);
                }
                else {
                    mainMap.setZoomRange(4, 21);
                }
            });
        }
        setupInterface();
    }

    function loading(state) {
        var opts = {
            lines: 15,
            length: 5,
            width: 5,
            radius: 20,
            color: '#000',
            speed: 2,
            trail: 100,
            top: 'auto',
            left: 'auto'
        };
        
        var target = $('#load');
        if (!this.spinner) this.spinner = new Spinner();
        if (!this.active) this.active = false;
        
        switch(state) {
        case 'start':
            if(!this.active) {
                this.spinner = Spinner(opts).spin();
                target
                    .append(this.spinner.el)
                    .addClass('active');
                this.active = true;
            }
            break;
        case 'stop':
            if (this.spinner) {
                target.removeClass('active');
                this.spinner.stop();
                this.active = false;
            }
            break;
        }
    }
    
    function featuresLoaded(features) {
        loading('stop');
        if (!features) return; // throw error?
        
        updateSpillData(features);
        
        updateDisplay();
    }
    loading('start');
    var key = dataset, number = 2;
    var mmg_f = mmg_json;
    if (44 == key.length) mmg_f = mmg_google_docs;
    mmg_f(key, number, function (features) {
        if (!$("#show-unverified-reports").is(":checked")) {
            spillDataIncludesUnverifiedReports = false;
            featuresLoaded(features);
        }
        else {
            var verifiedFeatures = features;
            mmg_f(key + "-unverified", number, function (features) {
                features = verifiedFeatures.concat(features);
                spillDataIncludesUnverifiedReports = true;
                featuresLoaded(features);
            });
        }
    });
}


function frontpageSetup() {
    var base = mapLayers.satellite; // 0
    var layerIDs = [
        mapLayers.lga,        // 1 state - LGA
        mapLayers.wetlands,   // 2 state - wetlands
        mapLayers.settlements // 3 county - settlements
    ];
    var allLayers = [
        base,
        layerIDs[0],
        layerIDs[1],
        layerIDs[2]
    ];
    
    function setupMap(m) {
        mainMap = m;
        m.setZoomRange(4, 21);
        m.smooth(true);
        
        m.interaction.auto();
        m.ui.hash;
        m.ui.zoomer.add();
        m.centerzoom({lat: 5.0000, lon: 6.6000 },8);
        m.getLayerAt(0).composite(false); // descriptions go here
        m.getLayerAt(1).composite(false); // descriptions go here
        m.getLayerAt(2).composite(false); // descriptions go here
        m.getLayerAt(3).composite(false); // descriptions go here
        
        var currentLayer = 6; //state-medicaid
        
        var displayCurrent = function(){
            for (var i = 1; i < allLayers.length; i++) {
                m.disableLayerAt(i);
            }
            m.enableLayerAt(currentLayer);
            m.ui.refresh();
            m.interaction.refresh();
        };
        
        displayCurrent();//initial load
        
        $('#context-layers li a').click(function(e) {
            e.preventDefault();
            $('#context-layers li a').removeClass("active");
            m.ui.legend.remove();
            switch ($(this).attr("id")) {
            case "lgas":
                if (currentLayer != 1) currentLayer = 1;
                else                   currentLayer = 0;
                break;
            case "wetlands":
                if (currentLayer != 2) currentLayer = 2;
                else                   currentLayer = 0;
                break;
            case "settlements":
                if (currentLayer != 3) currentLayer = 3;
                else                   currentLayer = 0;
                break;
            default:
                // Ignore clicks on other elements
                return;
            }
            if (currentLayer != 0) $(this).addClass("active");
            displayCurrent();
        });
        
        loadSpillData();
    }
    
    mapbox.auto("map", allLayers, setupMap);
}

$(function () {
    if ($('body').hasClass('frontpage')) frontpageSetup();
    else {
        $(window).load(function () {
            var layersParameter = window.location.hash.substring(1);
            if (layersParameter) setLayerCheckboxes(layersParameter);
        });
    }
    
    // Draggable data list
    $('#sortable').sortable({
        axis: 'y',
        containment: 'parent',
        tolerance: 'pointer',
        update: function () {
            updateLayers();
            buildRequest(el, layer);
            updateEmbedApi();
        }
    });
    $('#sortable').disableSelection();
    
    // Update layer order
    function updateLayers() {
        layer = '';
        $('#sortable li .layer-link').each(function (i) {
            if ($(this).hasClass('active') && this.id != '') {
                if (layer == '') {
                    layer = 'nigeriaoil.' + this.id;
                } else {
                    layer = ['nigeriaoil.' + this.id,
                             layer].join(',');
                }
            }
        });
    }
    function setLayerCheckboxes(ids) {
        // ids is a string with all the layer identifiers separated by commas
        // if the value is not a string, but evaluates to false the result
        // is the same as if it was an empty string, that is, clear all layers
        ids = (ids?ids:'').split(",");
        $('#sortable li .layer-link').each(function (j) {
            el = $(this);
            for (var i = 0; i < ids.length; ++i) {
                if (ids[i] === this.id) {
                    el = $(this);
                    el.click();
                    break;
                }
            }
        });
    }
    
    // Data layerswitcher
    $('.datalist .layer-link').change(function (e) {
        if ($(this).is(':checked')) {
            el = $(this);
            $(this).addClass('active');
        } else {
            $(this).removeClass('active');
        }
        updateLayers();
        if (el) buildRequest(el, layer);
        updateEmbedApi();
    });
    
    // Primary Navigation
    $('#nav a').click(function (e) {
        //e.preventDefault();
        $('#nav a').removeClass('active');
        $(this).addClass('active');
    });
    
    var buildRequest = function (el, l) {
        var options = {}
        options.base = el.attr('data-basemap') || '';
        options.id = l;
        options.center = {};
        options.center.lon = el.attr('data-lon') || undefined;
        options.center.lat = el.attr('data-lat') || undefined;
        options.center.zoom = el.attr('data-zoom') || undefined;
        options.center.ease = el.attr('data-ease') || 0;
        MB.refresh('map', options);
    }
    
    if (isTouchDevice()) {
        $('body').removeClass('no-touch');
    }
});

{% include js/FormUtils.js %}

{% include js/incident-report-form.js %}
