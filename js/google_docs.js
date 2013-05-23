//var data_root = 'http://demo.sentido-labs.com/data/';
var data_root = 'spill-data.php';

// The original function to read from Google Docs is at the end of the file.

function mmg_json(id, num, callback) {
    if (typeof reqwest === 'undefined') {
        throw 'CSV: reqwest required for mmg_csv_url';
    }
    
    function response(x) {
        var features = [],
        latfield = '',
        lonfield = '';
        if (!x || !x.length) return features;
        
        if ("geometry" in x[0]) return callback(x);
        for (var i = 0; i < x.length; i++) {
            var entry = x[i];
            if (!entry["latitude"] || !entry["longitude"]) continue;
            features.push({
                geometry: {
                    type: 'Point',
                    coordinates: [entry["longitude"], entry["latitude"]]
                },
                properties: entry
            });
        }
        
        return callback(features);
    }
    
    //var url = data_root + id + '.jsonz?alt=json-in-script&callback=callback';
    var url = data_root + '?alt=json-in-script&callback=callback&format=json&dataset=' + id;
    
    reqwest({
        url: url,
        type: 'jsonp',
        jsonpCallback: 'callback',
        success: response,
        error: response
    });
}


function mmg_google_docs(id, num, callback) {
    if (typeof reqwest === 'undefined') {
        throw 'CSV: reqwest required for mmg_csv_url';
    }
    
    function response(x) {
        var features = [],
        latfield = '',
        lonfield = '';
        if (!x || !x.feed) return features;
        
        for (var f in x.feed.entry[0]) {
            if (f.match(/\$Lat/i)) latfield = f;
            if (f.match(/\$Lon/i)) lonfield = f;
        }
        
        for (var i = 0; i < x.feed.entry.length; i++) {
            var entry = x.feed.entry[i];
            var feature = {
                geometry: {
                    type: 'Point',
                    coordinates: []
                },
                // This is for the citizen reports, otherwise properties: {}
	            properties: {
					'marker-color':'#840A0A',
					'title': 'Incident: ' + entry['gsx$obsdate'].$t,
					'area': entry['gsx$localname'].$t,
					'verified': entry['gsx$verified'].$t
				}
            };
            for (var y in entry) {
                if (y === latfield) feature.geometry.coordinates[1] = parseFloat(entry[y].$t);
                else if (y === lonfield) feature.geometry.coordinates[0] = parseFloat(entry[y].$t);
                else if (y.indexOf('gsx$') === 0) {
                    feature.properties[y.replace('gsx$', '')] = entry[y].$t;
                }
            }
            if (feature.geometry.coordinates.length == 2) features.push(feature);
        }
        
        return callback(features);
    }
    
    var url = 'https://spreadsheets.google.com/feeds/list/' + id + '/' + num +
        '/public/values?alt=json-in-script&callback=callback';
    
    reqwest({
        url: url,
        type: 'jsonp',
        jsonpCallback: 'callback',
        success: response,
        error: response
    });
}
