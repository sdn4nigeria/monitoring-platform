---
---
var mapLayers = {
    satellite:   "nigeriaoil.map-g3s2rdj8",
    flat:        "nigeriaoil.map-5ustxk97",
    lga:         "nigeriaoil.nigeria-lga",
    wetlands:    "nigeriaoil.NGWetlands",
    settlements: "nigeriaoil.NGSettlement"
};

var googleDocsIdentifier = window.location.hash.substring(1);

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
    mapbox.auto("map", allLayers, function(m) {
        m.setZoomRange(4, 21);
        m.smooth(true);
        
        m.interaction.auto();
        m.ui.hash;
        m.ui.zoomer.add();
        m.centerzoom({lat: 4.8470, lon: 5.9222 },8);
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
        };
        
        var totalspills = {};
        var totalbarrels = {};
        var totalid;
        var totallist = [];
        var totalcontent = '';
        
        if (googleDocsIdentifier) (function loadMap(key, number) {
            loading('start');
            mmg_google_docs(key, number, function(features) {
                
                var companies = {};
                var years = {};
                var months = {};
                
                _(features).each(function(f) {
                    years[f.properties.year] = true;
                    months[f.properties.month] = true;
                    companies[f.properties.companyname] = true;
                    totalspills[f.properties.month] = f.properties.estimatedquantity;
                    totalbarrels[f.properties.month] = f.properties.sitelocationname;
                    return f;
                });
                
                var yearsAvailable = _(years).keys().sort(function(a,b){return a - b})
                var monthsAvailable = _(months).chain().keys().sortBy(function(v){
                    return ["jan","feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"].indexOf(v.toLowerCase());
                });
                var active = {
                    company: _(companies).keys(),
                    year: [yearsAvailable[yearsAvailable.length -1]],
                    month: [monthsAvailable._wrapped[0]],
                };
                var ml = mapbox.markers.layer().factory(function(x) {
                    var popupNosdra = _.template(document.getElementById('popupNosdra').innerHTML);
                    var events = x.properties.estimatedquantity;
                    var y, z;
                    var d = document.createElement('div');
                    //added to point div
                    if (x.properties.thirdparty == 'yes') {
                        d.className = 'point';
                    } else {
                        d.className = 'point blue';
                    }
                    d.zIndex = 999999;
                    d.pointerEvents = 'all';
                    
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
                    }
                    // The point on the map
                    $(d).css("height", y + "px");
                    $(d).css("width", y + "px");
                    $(d).css("background-position",  -z + 'px ' + (y - 103) + 'px');
                    $(d).css("margin-left", -(y / 2) + 'px');
                    $(d).css("margin-top",-(y / 2) + 'px');
                    
                    //Account for 0 barrels lost
                    var quantity = x.properties.estimatedquantity;
                    if(x.properties.estimatedquantity == 0){
                        quantity = "Less than 1 ";
                    }
                    
                    $(d).click(function() {
                        var point = {lat: (x.geometry.coordinates[1] - .03), lon: (x.geometry.coordinates[0] - .2)};
                        var zoom = 10;
                        easey().map(m)
                            .to(m.locationCoordinate(point)
                                .zoomTo(zoom))
                            .run(500);
                    });
                    
                    // A points popup
                    var contentNosdra = document.createElement('div');
                    contentNosdra.className = 'popupNosdra clearfix';
                    contentNosdra.innerHTML = popupNosdra({
                        location: '<h3 class="int-location">' + x.properties.sitelocationname + '</h3><b>LGA:</b> ' + x.properties.lga +'</br>',
                        spill: '<h3 class="int-spillamount">' +  quantity  + '</h4><h4 class="int-spillamount"> barrels spilled due to ' + x.properties.causeofspill  + '</h4>',
                        date: '</br><b>Company Name: </b>' + x.properties.companyname + '</br><b>Date: </b>' + x.properties.incidentdate + '</br><b>Date spill stopped: </b>' + x.properties.datespillstopped,
                        contaiment: '</br><b>Initial containment measures: </b>' + x.properties.initialcontainmentmeasures + '</br><b>Cause of spill: </b>' + x.properties.causeofspill + '</br><b>Estimated spill area: </b>' + x.properties.estimatedspillarea,
                        facility: '</br><b>Type of facility: </b>' + x.properties.typeoffacility + '</br><b>Datejiv: </b>' + x.properties.datejiv + '</br><b>Spill area habitat: </b>' + x.properties.spillareahabitat,
                        impact: '</br><b>Impact: </b>' + x.properties.impact + '</br><b>Description of impact: </b>' + x.properties.descriptionofimpact,
                        cleanup: '</br><b>Date clean up: </b>' + x.properties.datecleanup + '</br><b>Date clean up completed: </b>' + x.properties.datecleanupcompleted + '</br><b>Method used: </b>' + x.properties.methodsofcleanup + '</br><b>Date of post clean up inspection: </b>' + x.properties.dateofpostcleanupinspection + '</br><b>Date of post impact assessment: </b>' +  x.properties.dateofpostimpactassessment,
                        mediation: '</br><b>Furter mediation: </b>' + x.properties.furtherremediation + '</br><b>Date of certificate: </b>' + x.properties.datecertificate,
                        image: '<img src=' + x.properties.image + '></img>'
                    });
                    
                    d.appendChild(contentNosdra);
                    $(d).hover(function () {
                        var tooltip = $('.about-text');
                        tooltip.empty();
                        var contentNosdra = $(this).find('.popupNosdra').html();
                        tooltip.html(contentNosdra);
                    });
                    //changing maps for baselayer
                    $('ul.bottomlayer li a').click(function(e) {
                        e.preventDefault();
                        if (!$(this).hasClass('active')) {
                            m.removeLayerAt(0);
                            m.insertLayerAt(0, mapbox.layer().id(this.id));
                            $('ul.bottomlayer li a').removeClass('active');
                            $(this).addClass('active');
                        }
                        /* This if statement determines the zoom limits
                           based on which base map is shown, since the satellite
                           map is fuzzy when zoomed in too far  */
                        if (this.id == mapLayers.satellite) {
                            m.setZoomRange(4, 12);
                        }
                        else {
                            m.setZoomRange(4, 21);
                        }
                    });
                    
                    return d;
                }).features(features);
                
                function updateDisplay() {
                    _(active).each(function (v, k) {
                        if (k == "month") return;
                        if (k == "year" || k == "company") {
                            var label = v[0];
                            if (v.length > 1) label = "All";
                            $('#' + k + '-count').text(label);
                        }
                        else $('#' + k + '-count').text(v.length);
                    });
                    var count = 0;
                    ml.filter(function(f) {
                        var test = active.company.indexOf(f.properties.companyname) !== -1 &&
                            active.year.indexOf(f.properties.year) !== -1 &&
                            active.month.indexOf(f.properties.month) !== -1;
                        count += test;
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
                    
                    $('#company-menu').append('<li><a href="#" class="time-switch clearfix switch-active" id="all-companies">All companies</a>');
                    _(companies).chain().keys().each(function(v) {
                        $('#company-menu').append('<li><a href="#" class="time-switch clearfix switch-active" id="'+v+'">'+v+'</a>');
                    });
                    $('#company-menu a').click(clickHandleUnique('company'));
                    
                    _(yearsAvailable).each(function(v) {
                        var activeClass = (active.year.indexOf(v) == -1 ? '' : ' switch-active');
                        $('#year-menu').append('<li><a href="#" class="time-switch clearfix' + activeClass + '" id="'+v+'">'+v+'</a>');
                    });
                    $('#year-menu a').click(clickHandleUnique('year'));
                }
                
                loading('stop');
                if (!features) return; // throw error?
                
                m.addLayer(ml);
                mapbox.markers.interaction(ml);
                setupInterface();
                updateDisplay();
            });
        })(googleDocsIdentifier, 2);
        else alert("The spill data display requires a dataset ID");
    });
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
