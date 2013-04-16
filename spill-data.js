// Author: Alberto González Palomo <http://sentido-labs.com>
// ©2013 Alberto González Palomo <http://sentido-labs.com>
// The author grants Stakeholder Democracy Network Ltd.
// a sublicensable, assignable, royalty free, including the rights
// to create and distribute derivative works, non-exclusive license
// to this software. 

function $(path) { return document.querySelector(path); }

var table = $("table");
function filter(test)
{
    table.style.display = "none";
    var count = 0;
    var rows = table.rows;
    for (var i = table.rows.length-1; i > 0; --i) {
        var row = table.rows[i];
        if (!test || test(row.cells)) {
            row.style.display = "";
            ++count;
        }
        else {
            row.style.display = "none";
        }
    }
    $("#shown-row-count-display").textContent = count;
    $("#total-row-count-display").textContent = table.rows.length-1;
    table.style.display = "";
}

var filters = {
    "no_location": function (cells) {
        return (!(cells[6].textContent) || !(cells[7].textContent))
            && (!(cells[8].textContent) || !(cells[9].textContent));
    },
    "misplaced_location": function (cells) {
        return (!(cells[6].textContent) || !(cells[7].textContent))
            && (!(cells[8].textContent) || !(cells[9].textContent))
            && (regexp_degree_symbol.test(cells[5].textContent));
    },
    "no_incident_number": function (cells) {
        return !(cells[2].textContent);
    }
}

var regexp_degree_symbol = /°|\u02DA/;// 02DA:˚

function search(pattern)
{
    if (pattern) {
        try {
            var regexp = new RegExp(pattern, "i");
            filter (function (cells) {
                for (var i = cells.length-1; i >= 0; --i) {
                    if (regexp.test(cells[i].textContent)) return true;
                }
                
                return false;
            });
        }
        catch (e) {
            filter (function (cells) {
                for (var i = cells.length-1; i >= 0; --i) {
                    if (cells[i].textContent.indexOf(pattern) >= 0) return true;
                }
                
                return false;
            });
        }
    }
    else filter();
}

function count_matches(test)
{
    var count = 0;
    var rows = table.rows;
    if (!test) return table.rows.length;
    for (var i = table.rows.length-1; i > 0; --i) {
        var row = table.rows[i];
        if (!test || test(row.cells)) ++count;
    }
    
    return count;
}

$("#shown-row-count-display").textContent = table.rows.length-1;
$("#total-row-count-display").textContent = table.rows.length-1;

if (window.location.hash.length > 1) {
    $("#field-filter-search").value = window.location.hash.substr(1);
    search($("#field-filter-search").value);
}
