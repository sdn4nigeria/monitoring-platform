#!/usr/bin/env python
# -*- coding: utf-8 -*-
# -------------------------------------------
# Script to process NOSDRA oil spill database
# -------------------------------------------
# Requirements:
#   - Python 2.6+
#   - Shapely
#   - GDAL

import csv, math
from datetime import datetime, date, time
import itertools
import simplejson as json
from shapely.geometry import Polygon
from shapely.geometry import Point
from shapely.geometry import MultiPolygon
from osgeo import gdal,osr

file_names = { "lga_file": "nigeria-lga-reduced.geojson",
               "input": "nosdra_out.csv",
               "output_full": "nosdra_full_data.csv",
               "output_google_docs": "nosdra_map_data.csv",
               "output_json": "nosdra_map_data.json"
               }
import sys
def progress(message):
    sys.stderr.write(message + '\r')
def info(message):
    sys.stderr.write(message + '\n')
def warn(message):
    sys.stderr.write("Warning: " + message + '\n')
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
  --google=<file> Output CSV for loading in Google Docs
                  The default is "'''+file_names["output_google_docs"]+'''"
  --json=<file>   Output JSON for the web application
                  The default is "'''+file_names["output_json"]+'''"
  --gzip          Compress the JSON output with GZIP
If any of the output file names is the empty string, that file
is not produced.

Originally written by DevelopmenSeed.org, extended and documented by
Alberto González Palomo <http://sentido-labs.com>
'''
try:
    opts, args = getopt.getopt(sys.argv[1:],
                               'h',
                               ('help','progress', 'gzip',
                                'lga=', 'output=','google=','json='))
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
    if             (o == '--google'):   file_names["output_google_docs"] = a
    if             (o == '--json'):     file_names["output_json"]        = a
    if (o == '-h')|(o == '--help'):
        display_help()
        exit(0)
if args:
    if len(args) == 1: file_names["input"] = args[0]
    else:
        sys.stderr.write("Error: only one input file can be specified")
        display_help()
        exit(1)

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
#   WGS83 EPSG = 4326
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
        dt = datetime.strptime(row['DATE OF INCIDENT'].rstrip(' 00:00:00'), "%Y-%m-%d")
        if not row['COMPANY'] in companies:
            companies[row['COMPANY']] = {"processed":0, "discarded":0};
        spillid = row['ID']
        if row['INCIDENT NO']: spillid += "." + row['INCIDENT NO']
        outputDict = {
            'timestamp': "",
            'spillid': spillid,
            'companyname': row['COMPANY'],
            'incidentdate': row['DATE OF INCIDENT'].rstrip(' 00:00:00'),
            'datespillstopped': "",
            'initialcontainmentmeasures': "",
            'estimatedquantity': row['QUANTITY OF SPILL (bbl)'],
            'causeofspill': row['CAUSE OF SPILL'],
            'sitelocationname': row['LOCATION'].replace('     ',''),
            'gpslatdeg': "",
            'gpslatmin': "",
            'gpslatsec': "",
            'gpslongdeg': "",
            'gpslongmin': "",
            'gpslongsec': "",
            'latitude': "",
            'longitude': "",
            'estimatedspillarea': row['SPILL AREA'],
            'typeoffacility': "",
            'datejiv': "",
            'spillareahabitat': "",
            'impact': "",
            'descriptionofimpact': "",
            'image': "",
            'datecleanup': "",
            'datecleanupcompleted': "",
            'methodsofcleanup': "",
            'dateofpostcleanupinspection': "",
            'dateofpostimpactassessment': "",
            'furtherremediation': "",
            'datecertificate': row['CERT_DATE'],
            'thirdparty': "",
            'month': dt.strftime("%b"),
            'year': dt.strftime("%Y"),
            'monthNum': dt.strftime("%m"),
            'lga': ""
            }
        if row['CAUSE OF SPILL'].lower() == "cor":
            outputDict['causeofspill'] = "Corrosion"
            outputDict['thirdparty'] = "no"
        elif row['CAUSE OF SPILL'].lower() == "eqf":
            outputDict['causeofspill'] = "Equipment Failure"
            outputDict['thirdparty'] = "no"
        elif row['CAUSE OF SPILL'].lower() == "ome":
            outputDict['causeofspill'] = "Operational Error"
            outputDict['thirdparty'] = "no"
        elif row['CAUSE OF SPILL'].lower() == "oth":
            outputDict['causeofspill'] = "Other"
            outputDict['thirdparty'] = "no"
        elif row['CAUSE OF SPILL'].lower() == "sab":
            outputDict['causeofspill'] = "Sabotage"
            outputDict['thirdparty'] = "yes"
        else:
            outputDict['causeofspill'] = "Other"
            outputDict['thirdparty'] = "no"
        if row['LON'] != "":                # check if it has a longitude value
            lonSplit = row['LON'].split()
            if len(lonSplit) == 2:          # if only degree values
                lonSplit = [x.replace('\xc2\xb0', "") for x in lonSplit]
                lonSplit = [x.replace('\'', "") for x in lonSplit]
                lonSplit = [x.replace('\xcb\x9d', "") for x in lonSplit]
                lonClean = []
                outputDict['longitude'] = ""
            elif len(lonSplit) == 3:        # only degrees and minutes
                lonSplit = [x.replace('\xc2\xb0', "") for x in lonSplit]
                lonSplit = [x.replace('\'', "") for x in lonSplit]
                lonSplit = [x.replace('\xcb\x9d', "") for x in lonSplit]
                lonClean = [lonSplit[0],float(lonSplit[1]),float(lonSplit[2])]
                lonDD = lonClean[1] + (lonClean[2]/60)
                #outputRow.append(lonDD)
                outputDict['longitude'] = lonDD
            elif len(lonSplit) == 4:        # degrees, minutes, and seconds
                lonSplit = [x.replace('\xc2\xb0', "") for x in lonSplit]
                lonSplit = [x.replace('\'', "") for x in lonSplit]
                lonSplit = [x.replace('\xcb\x9d', "") for x in lonSplit]
                lonClean = [lonSplit[0],float(lonSplit[1]),float(lonSplit[2]),float(lonSplit[3])]
                lonDD = lonClean[1] + (lonClean[2]/60) + (lonClean[3]/3600)
                outputDict['longitude'] = lonDD
        else:
            if row['EASTINGS'] != "":
                lonNorth = float(row['EASTINGS'])
                latNorth = float(row['NORTHINGS'])
                lonDD = transformTo(lonNorth, latNorth, inSRS, outSRS)
                outputDict['longitude'] = lonDD[0]
        if row['LAT'] != "":                # check if it has a latitude value
            latSplit = row['LAT'].split()
            if len(latSplit) == 2:          # if only degree values
                latSplit = [x.replace('\xc2\xb0', "") for x in latSplit]
                latSplit = [x.replace('\'', "") for x in latSplit]
                latSplit = [x.replace('\xcb\x9d', "") for x in latSplit]
                latClean = []
                outputDict['latitude'] = ""
            elif len(latSplit) == 3:        # only degrees and minutes
                latSplit = [x.replace('\xc2\xb0', "") for x in latSplit]
                latSplit = [x.replace('\'', "") for x in latSplit]
                latSplit = [x.replace('\xcb\x9d', "") for x in latSplit]
                latClean = [latSplit[0],float(latSplit[1]),float(latSplit[2])]
                latDD = latClean[1] + (latClean[2]/60)
                #outputRow.append(latDD)
                outputDict['latitude'] = latDD
            elif len(latSplit) == 4:        # degrees, minutes, and seconds
                latSplit = [x.replace('\xc2\xb0', "") for x in latSplit]
                latSplit = [x.replace('\'', "") for x in latSplit]
                latSplit = [x.replace('\xcb\x9d', "") for x in latSplit]
                latClean = [latSplit[0],float(latSplit[1]),float(latSplit[2]),float(latSplit[3])]
                latDD = latClean[1] + (latClean[2]/60) + (latClean[3]/3600)
                outputDict['latitude'] = latDD
        else:
            if row['NORTHINGS'] != "":
                latNorth = float(row['NORTHINGS'])
                lonNorth = float(row['EASTINGS'])
                latDD = transformTo(lonNorth, latNorth, inSRS, outSRS)
                outputDict['latitude'] = latDD[1]
        if outputDict['latitude'] != "":
            outputDict['lga'] = pointInPoly(lonDD, latDD)
            outputRow.append(outputDict)
            companies[row['COMPANY']]['processed'] += 1
        else:
            companies[row['COMPANY']]['discarded'] += 1
            pass
    info("Processed " + str(reader.line_num) + " rows")
    info("Seconds for pointInPoly(): " + str(time_pointInPoly))
    info("Seconds for transformTo(): " + str(time_transformTo))
    info("Total time in seconds: " + str(timer.time() - time_start));
    for c in companies.keys():
        info("Company: " + c + "\n"
             + "  processed " + str(companies[c]['processed']) + " reports\n"
             + "  discarded " + str(companies[c]['discarded']) + " reports"
             + " for lack of location data")

def cleanup(rows):
    for row in rows:
        del row['timestamp']
        del row['gpslatdeg']
        del row['gpslatmin']
        del row['gpslatsec']
        del row['gpslongdeg']
        del row['gpslongmin']
        del row['gpslongsec']
        del row['typeoffacility']
        del row['datejiv']
        del row['spillareahabitat']
        del row['impact']
        del row['descriptionofimpact']
        del row['image']
        del row['datecleanup']
        del row['datecleanupcompleted']
        del row['methodsofcleanup']
        del row['dateofpostcleanupinspection']
        del row['dateofpostimpactassessment']
        del row['furtherremediation']
        del row['datecertificate']

def write_full_data(file_name):
    # Hardcoded fieldnames to create columns according to this order
    fieldnames = ['timestamp','spillid','companyname','incidentdate','datespillstopped','initialcontainmentmeasures','estimatedquantity','causeofspill','sitelocationname','gpslatdeg','gpslatmin','gpslatsec','gpslongdeg','gpslongmin','gpslongsec','latitude','longitude','estimatedspillarea','typeoffacility','datejiv','spillareahabitat','impact','descriptionofimpact','image','datecleanup','datecleanupcompleted','methodsofcleanup','dateofpostcleanupinspection','dateofpostimpactassessment','furtherremediation','datecertificate','thirdparty','month','year','monthNum','lga']
    out = open(file_name, 'wb')
    csvwriter = csv.DictWriter(out, delimiter=',', fieldnames=fieldnames, quoting=csv.QUOTE_NONNUMERIC)
    csvwriter.writerow(dict((fn,fn) for fn in fieldnames))
    for row in outputRow:
        csvwriter.writerow(row)
    out.close()
    info("Written full data into " + file_name)

import json
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

def write_google_data(file_name):
    mapdataFieldnames = ['spillid','companyname','incidentdate','datespillstopped','initialcontainmentmeasures','estimatedquantity','causeofspill','sitelocationname','latitude','longitude','estimatedspillarea','thirdparty','month','year','monthNum','lga']
    out = open(file_name, 'wb')
    csvwriter = csv.DictWriter(out, delimiter=',', fieldnames=mapdataFieldnames, quoting=csv.QUOTE_NONNUMERIC)
    csvwriter.writerow(dict((fn,fn) for fn in mapdataFieldnames))
    for row in outputRow:
        csvwriter.writerow(row)
    out.close()
    info("Written CSV map data for Google Docs at " + file_name)

if (file_names["output_full"]):
    write_full_data(file_names["output_full"])
cleanup(outputRow)
if (file_names["output_json"]):
    write_json_data(file_names["output_json"])
if (file_names["output_google_docs"]):
    write_google_data(file_names["output_google_docs"])

info("OK");
