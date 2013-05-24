#!/usr/bin/env python
# -*- coding: utf-8 -*-
# -------------------------------------------
# Script to process NOSDRA oil spill database
# -------------------------------------------
# Requirements:
#   - Python 2.6+
#   - Shapely
#   - GDAL

# Print messages always in UTF-8:
import sys, codecs
sys.stdout = codecs.getwriter("UTF-8")(sys.stdout)
sys.stderr = codecs.getwriter("UTF-8")(sys.stderr)

import csv, math
from datetime import datetime, date, time
import itertools
import simplejson as json
from shapely.geometry import Polygon
from shapely.geometry import Point
from shapely.geometry import MultiPolygon
from osgeo import gdal,osr

# Parameter defaults:
file_names = { "lga_file": "nigeria-lga-reduced.geojson",
               "input": "nosdra_out.csv",
               "output_full": "nosdra_full_data.csv",
               "output_json": "nosdra_map_data.json"
               }
coordinates = { "latitude":  "",
                "longitude": "",
                "eastings":  "",
                "northings": ""
                }

# Supress all messages except errors:
quiet = False
# Whether to discard rows with no coordinates:
discardWithoutCoordinates = False

import sys
def progress(message):
    if not quiet: sys.stderr.write(message + '\r')
def info(message):
    if not quiet: sys.stderr.write(message + '\n')
def warn(message):
    if not quiet: sys.stderr.write("Warning: " + message + '\n')
def error(message):
    sys.stderr.write("Error: " + message + '\n')
import getopt
def display_help():
    print r'''Usage: coordinates.py [options] [input file]
Without the [input file] parameter, the default is "'''+file_names["input"]+'''"
Options:
  -h, --help      Display this message
  --progress      Show progress indicator
  --lga=<file>    Input LGA GeoJSON (http://geojson.org) file
                  The default is "'''+file_names["lga_file"]+'''"
  --output=<file> Output CSV file with all data at once
                  The default is "'''+file_names["output_full"]+'''"
  --json=<file>   Output JSON for the web application
                  The default is "'''+file_names["output_json"]+'''"
  --gzip          Compress the JSON output with GZIP
  --latitude=<n>  Latitude of the point to analyze, referring to WGS84,
                  either decimal degrees as used in GPS or "N DD MM SS"
                  which can include degree, minute and second symbols
  --longitude=<n> Longitude, same format as --latitude
  --northings=<n> Alternative to latitude, an integer number referring to
                  Minna/Nigeria Mid Belt EPSG = 26392
  --eastings=<n>  The eastings coordinate used with --northings, same format
If any of the output file names is the empty string, that file
is not produced.

If --latitude/--longitude or --northings/--eastings parameters are given,
the output is the JSON encoding of the computed point data, which includes
latitude/longitude in decimal degrees, northings/eastings, and the
name of the LGA where the point resides or the empty string if none,
such as offshore locations.

Originally written by DevelopmenSeed.org, extended and documented by
Alberto González Palomo <http://sentido-labs.com>
'''

try:
    opts, args = getopt.getopt(sys.argv[1:],
                               'h',
                               ('help','progress', 'gzip',
                                'lga=', 'output=','json=',
                                'latitude=', 'longitude=',
                                'northings=', 'eastings=')
                               )
except getopt.GetoptError, problem:
    print 'Command line option problem: ', problem, '\n'
    display_help()
    exit(1)
show_progress = False
compress_gzip = False
for o, a in opts:
    if             (o == '--progress'): show_progress = True
    if             (o == '--gzip'):     compress_gzip = True
    if             (o == '--lga'):      file_names["lga_file"]           = a
    if             (o == '--output'):   file_names["output_full"]        = a
    if             (o == '--json'):     file_names["output_json"]        = a
    if             (o == '--latitude'): coordinates["latitude"]        = a
    if             (o == '--longitude'):coordinates["longitude"]       = a
    if             (o == '--northings'):coordinates["northings"]       = a
    if             (o == '--eastings'): coordinates["eastings"]        = a
    if (o == '-h')|(o == '--help'):
        display_help()
        exit(0)
compute_point = False
if args:
    if len(args) == 1: file_names["input"] = args[0]
    else:
        sys.stderr.write("Error: only one input file can be specified")
        display_help()
        exit(1)
elif len(args) == 0 and (coordinates["latitude"] or
                         coordinates["northings"]):
    compute_point = True
    quiet = True

import time as timer
time_start = timer.time()
time_pointInPoly = 0
time_transformTo = 0

# ------------------------------------------------------------------
# Process LGA Shapefile to determine which LGA the spill happened in
# ------------------------------------------------------------------

lga_data = json.loads(open(file_names["lga_file"], "rb").read())
info("Pre-processing LGA data")
lga_count = 0
for d in lga_data['features']:
    lga_count += 1
    if d['geometry']['type'] == 'Polygon':
        # The schema was in the original code, but is not used
        #d['geometry']['schema'] = { 'geometry': 'Polygon', 'properties': { 'name': 'str' } }
        d['poly'] = Polygon(d['geometry']['coordinates'][0])
    elif d['geometry']['type'] == 'MultiPolygon':
        # The schema was in the original code, but is not used
        #d['geometry']['schema'] = { 'geometry': 'MultiPolygon', 'properties': { 'name': 'str' } }
        poly1 = d['geometry']['coordinates'][0]
        poly2 = d['geometry']['coordinates'][0]
        d['poly'] = MultiPolygon(poly1, poly2)
info("Finished pre-processing data for " + str(lga_count) + " LGAs")

def pointInPoly(pointLon, pointLat):
    global time_pointInPoly
    t = timer.time()
    point = Point(pointLon, pointLat)
    for lga in lga_data['features']:
        if point.within(lga['poly']) is True:
            time_pointInPoly += timer.time() - t
            return lga['properties']['Name']
    time_pointInPoly += timer.time() - t
    return ""
    # For debugging: warn("point " + str(pointLon) + " " + str(pointLat) + " not found in LGA map")

# ------------------------------------------------
# Transform from Minna / Nigeria Mid Belt to WGS84
#   http://www.spatialreference.org/ref/epsg/26392/
#   Minna/Nigeria Mid Belt EPSG = 26392
#   WGS84 EPSG = 4326
# --------------------

inSRS = 26392
outSRS = 4326

def transformTo(easting, northing, inSRS, outSRS):
    global time_transformTo
    t = timer.time()

    inproj = osr.SpatialReference()
    outproj = osr.SpatialReference()

    inproj.ImportFromEPSG(inSRS)
    outproj.ImportFromEPSG(outSRS)

    transForm = osr.CoordinateTransformation(inproj, outproj)

    transformed = transForm.TransformPoint(easting,northing)

    time_transformTo += timer.time() - t
    return transformed

import re
re_latlon_label = re.compile(r"N|E")
re_not_number = re.compile(r"[^0-9.]+")
def decimalDegrees(value):
    parts = value.split()
    # The algorithm below assumes that the N|E label is at the front:
    if re_latlon_label.match(parts[-1]):
        parts = [parts[-1]]+parts[:-1]
    parts = [re_not_number.sub("", x) for x in parts]
    if len(parts) == 2:          # if only degree values
        clean = [parts[0],float(parts[1])]
        return clean[1]
    elif len(parts) == 3:        # only degrees and minutes
        clean = [parts[0],float(parts[1]),float(parts[2])]
        return clean[1] + (clean[2]/60)
    elif len(parts) == 4:        # degrees, minutes, and seconds
        clean = [parts[0],float(parts[1]),float(parts[2]),float(parts[3])]
        return clean[1] + (clean[2]/60) + (clean[3]/3600)
    else:  # already in decimal degrees
        return float(value)

import json
if compute_point:
    if coordinates["latitude"] and coordinates["longitude"]:
        coordinates["latitude"]  = decimalDegrees(coordinates["latitude"])
        coordinates["longitude"] = decimalDegrees(coordinates["longitude"])
        if isinstance(coordinates["latitude"], str):
            coordinates["latitude"] = float('NaN')
        if isinstance(coordinates["longitude"], str):
            coordinates["longitude"] = float('NaN')
        point = transformTo(coordinates["longitude"],
                            coordinates["latitude"],
                            outSRS, inSRS)
        coordinates["northings"] = int(point[1])
        coordinates["eastings"]  = int(point[0])
    elif coordinates["northings"] and coordinates["eastings"]:
        coordinates["northings"] = int(coordinates["northings"])
        coordinates["eastings"]  = int(coordinates["eastings"])
        point = transformTo(coordinates["eastings"],
                            coordinates["northings"],
                            inSRS, outSRS)
        coordinates["latitude"]  = point[1]
        coordinates["longitude"] = point[0]
    coordinates["lga"] = pointInPoly(coordinates["longitude"],
                                     coordinates["latitude"])
    json.dump(coordinates, sys.stdout)
    exit(0)

patterns = {
    "SPILL AREA":
    { r"la": re.compile(r"[\[\(\{]?\s*[Ll]([AaNn][Dd]?)?\s*[\}\)\]]?"),
      r"ss": re.compile(r"[\[\(\{]?\s*[Ss](seasonal\s*)?[Ss]([Ww][Pp]?|wamp)?\s*[\}\)\]]?"),
      r"sw": re.compile(r"[\[\(\{]?\s*[Ss]([Ww][Pp]?|wamp)?\s*[\}\)\]]?"),
      r"co": re.compile(r"COASTLINE|coastland"),
      r"iw": re.compile(r"[\[\(\{]?\s*([Ii][Ww]|fresh water)\s*[\}\)\]]?"),
      r"ns": re.compile(r"[\[\(\{]?\s*[Nn](ear\s*)?[Ss](hore)?\s*[\}\)\]]?"),
      r"of": re.compile(r"[\[\(\{]?\s*[Oo][Ff][Ff]?\s*[\}\)\]]?"),
      r"other": re.compile(r"[\[\(\{]?\s*[Oo][Tt][Hh]?\s*[\}\)\]]?")
    },
    "CAUSE OF SPILL":
    { r"cor": re.compile(r"\s*[Cc][Oo][Rr]\s*"),
      r"eqf": re.compile(r"\s*[Ee][Qq][Ff]\s*"),
      r"pme": re.compile(r"\s*[Oo][Mm][Ee]\s*"),
      r"sab": re.compile(r"\s*[Ss][Aa][Bb]\s*"),
      r"ytd": re.compile(r"\s*[Yy][Tt][Dd]\s*"),
      r"other:\g<1>": re.compile(r"\s*[Oo][Tt][Hh]\s*[(]?\s*([^)]*)")
    },
    "TYPE OF CONTAMINANT":
    { r"cr": re.compile(r"\s*[Cc][Rr]\s*"),
      r"ch": re.compile(r"\s*[Cc][Hh]\s*"),
      r"pr:gas": re.compile(r"\s*[Gg][Aa][Ss]\s*"),
      r"pr:automotive gas oil": re.compile(r"\s*others.*automotive.*gas.*"),
      r"pr": re.compile(r"\s*[Rr][Ee]\s*"),
      r"other:\g<1>": re.compile(r"\s*[Oo][Tt][Hh]\s*[(]?\s*([^)]*)")
    }
}

def normalize(row):
    normalized = False
    value = row['SPILL AREA']
    if value == "land swamp" or value == "LAND SWAMP":
        row['SPILL AREA'] = "la,sw"
        normalized = True
    for field_name in patterns:
        value = row[field_name]
        for (normalized, pattern) in patterns[field_name].items():
            if pattern.match(value):
                row[field_name] = pattern.sub(normalized, value, 1)
                normalized = True
    return normalized

lat_pattern = u"0*(?P<latdeg>[0-9]{1,2}([.][0-9]+)?[°˚])\s*(?P<latmin>0*[0-9]{1,2}([.][0-9]+)?['´ °˚])?\s*(?P<latsec>0*[0-9]{1,2}([.][0-9]+)?[\"˝]*)?"
lon_pattern = u"0*(?P<londeg>[0-9]{1,2}([.][0-9]+)?[°˚])\s*(?P<lonmin>0*[0-9]{1,2}([.][0-9]+)?['´ °˚])?\s*(?P<lonsec>0*[0-9]{1,2}([.][0-9]+)?[\"˝]*)?"
re_latlon_format = [
    re.compile(u"(\(?[Nn]\)?\s*"+lat_pattern+")[ ,]*\(?[Ee]\)?("+lon_pattern+")"),
    re.compile(u"(\(?[Ee]\)?\s*"+lon_pattern+")[ ,]*\(?[Nn]\)?("+lat_pattern+")"),
    re.compile(u"("+lat_pattern+")\s*\(?[Nn]\)?[ ,]*("+lon_pattern+")\s*\(?[Ee]\)?"),
    re.compile(u"("+lon_pattern+")\s*\(?[Ee]\)?[ ,]*("+lat_pattern+")\s*\(?[Nn]\)?")
    ]

re_habitat_in_location = re.compile(u"([\[\{\(][a-zA-z]{1,3}[\)\}\]])\s*$")
latlon_extraction_count = 0
habitat_extraction_count = 0
def extract_fields_from_location(row):
    global latlon_extraction_count
    global habitat_extraction_count
    value = unicode(row['LOCATION'], "utf8")
    changed = False
    for pattern in re_latlon_format:
        match = pattern.search(value)
        if match:
            info("Found misplaced coordinates in LOCATION: " + match.group(0))
            if row['LAT'] or row['LON']:
                info("Coordinates already entered, so not modified: "
                     + row['LAT'].decode("utf8")
                     + ", " + row['LON'].decode("utf8"))
                break
            latitude  = match.group("latdeg")
            found = match.group("latmin")
            if found:
                latitude += " " + found
                # Inside to avoid putting seconds without minutes if corrupted
                found = match.group("latsec")
                if found: latitude += " " + found
            latitude += " N"
            longitude = match.group("londeg")
            found = match.group("lonmin")
            if found:
                longitude += " " + found
                # Inside to avoid putting seconds without minutes if corrupted
                found = match.group("lonsec")
                if found: longitude += " " + found
            longitude += " E"
            row['LAT'] = latitude .encode("utf8")
            row['LON'] = longitude.encode("utf8")
            # Leave the coordinates in place to keep location descriptions
            # sensible because some of them give extra explanations about the
            # coordinates:
            #value = pattern.sub("", value, 1)
            changed = True
            latlon_extraction_count += 1
            break
    if row['SPILL AREA']: match = False # Don't try if we already got it
    else:                 match = re_habitat_in_location.search(value)
    match = re_habitat_in_location.search(value)
    if match:
        info("Found misplaced SPILL AREA in LOCATION: " + match.group(1))
        if row['SPILL AREA']:
            info("SPILL AREA already entered, so not modified: "
                 + row['SPILL AREA'].encode("utf8"))
        else:
            row['SPILL AREA'] = match.group(1).encode("utf8")
            value = re_habitat_in_location.sub("", value, 1)
            changed = True
            habitat_extraction_count += 1
    if changed: row['LOCATION'] = value.strip().encode("utf8")

# ----------------------
# Process NOSDRA CSV
# ----------------------
info("Processing input data from " + file_names["input"])
companies = {}
with open(file_names["input"], 'rb') as f:
    reader = csv.DictReader(f)
    outputRow = []
    for row in reader:
        if show_progress: progress("Line " + str(reader.line_num) + "  ")
        extract_fields_from_location(row)
        normalize(row)
        dt = datetime.strptime(row['DATE OF INCIDENT'].rstrip(' 00:00:00'), "%Y-%m-%d")
        if not row['COMPANY'] in companies:
            companies[row['COMPANY']] = {"processed":0, "no-location":0};
        spillid = row['ID']
        if row['INCIDENT NO']: spillid += "." + row['INCIDENT NO']
        outputDict = {
            'spillid': spillid,
            'updatefor': "",
            # only administrators can upload data, so we assume it's verified
            'status': "verified",
            'incidentdate': row['DATE OF INCIDENT'].rstrip(' 00:00:00'),
            'datespillstopped': "",
            'company': row['COMPANY'],
            'initialcontainmentmeasures': "",
            'estimatedquantity': row['QUANTITY OF SPILL (bbl)'],
            'contaminant': row['TYPE OF CONTAMINANT'],
            'cause': row['CAUSE OF SPILL'],
            'latitude': "",
            'longitude': "",
            'lga': "",
            'sitelocationname': row['LOCATION'],
            'estimatedspillarea': "",
            'spillareahabitat': row['SPILL AREA'],
            'attachments': "",
            'impact': "",
            'descriptionofimpact': "",
            'datejiv': "",
            'datecleanup': "",
            'datecleanupcompleted': "",
            'methodsofcleanup': "",
            'dateofpostcleanupinspection': "",
            'dateofpostimpactassessment': "",
            'furtherremediation': "",
            'datecertificate': row['CERT_DATE']
            }
        if row['LON'] != "" and row['LAT'] != "":
            outputDict['latitude']  = decimalDegrees(row['LAT'])
            outputDict['longitude'] = decimalDegrees(row['LON'])
        elif row['NORTHINGS'] != "" and row['EASTINGS'] != "":
            lon_lat = transformTo(float(row['EASTINGS']),
                                  float(row['NORTHINGS']),
                                  inSRS, outSRS)
            outputDict['longitude'] = lon_lat[0]
            outputDict['latitude']  = lon_lat[1]
        companies[row['COMPANY']]['processed'] += 1
        if outputDict['latitude'] != "":
            outputDict['lga'] = pointInPoly(outputDict['longitude'],
                                            outputDict['latitude'])
        else:
            companies[row['COMPANY']]['no-location'] += 1
        if outputDict['latitude'] != "" or not discardWithoutCoordinates:
            outputRow.append(outputDict)
        else:
            pass
    info("Processed " + str(reader.line_num) + " rows")
    info("Seconds for pointInPoly(): " + str(time_pointInPoly))
    info("Seconds for transformTo(): " + str(time_transformTo))
    info("Total time in seconds: " + str(timer.time() - time_start));
    for c in companies.keys():
        info("Company: " + c + "\n"
             + "  processed " + str(companies[c]['processed']) + " reports\n"
             + "  of which  " + str(companies[c]['no-location']) + " reports"
             + " lack location data")

def write_full_data(file_name):
    # Hardcoded fieldnames to create columns according to this order
    fieldnames = ['spillid','updatefor','status','incidentdate','company','initialcontainmentmeasures','estimatedquantity','contaminant','cause','latitude','longitude','lga','sitelocationname','estimatedspillarea','spillareahabitat','attachments','impact','descriptionofimpact','datejiv','datespillstopped','datecleanup','datecleanupcompleted','methodsofcleanup','dateofpostcleanupinspection','dateofpostimpactassessment','furtherremediation','datecertificate']
    out = open(file_name, 'wb')
    csvwriter = csv.DictWriter(out, delimiter=',', fieldnames=fieldnames, quoting=csv.QUOTE_NONNUMERIC)
    csvwriter.writerow(dict((fn,fn) for fn in fieldnames))
    for row in outputRow:
        csvwriter.writerow(row)
    out.close()
    info("Written full data into " + file_name)

import gzip
def write_json_data(file_name):
    if compress_gzip: out = gzip.open(file_name, 'wb')
    else:             out =      open(file_name, 'wb')
    out.write("callback([")
    firstRow = True
    for row in outputRow:
        if not firstRow: out.write(',')
        else:            firstRow = False
        out.write('{"geometry":{"type":"Point","coordinates":['
                  + str(row['longitude']) + ','
                  + str(row['latitude'])  + ']},properties:')
        json.dump(row, out)
        out.write('}')
    out.write("]);")
    out.close();
    if compress_gzip:
        info("Written compressed JSON map data into " + file_name)
    else:
        info("Written JSON map data into " + file_name)

if (file_names["output_full"]):
    write_full_data(file_names["output_full"])

if (file_names["output_json"]):
    write_json_data(file_names["output_json"])

info("extracted " + str(latlon_extraction_count) + " coordinates from LOCATION field")
info("extracted " + str(habitat_extraction_count) + " habitat/area values from LOCATION field")
info("OK")
