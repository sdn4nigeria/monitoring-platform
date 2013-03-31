# -------------------------------------------
# Script to process NOSDRA oil spill database
# -------------------------------------------
# Requirements: 
#   - Python 2.6+
#   - Shapely 
#   - Fiona 
#   - GDAL 

import csv, math
from datetime import datetime, date, time
import itertools
import simplejson as json
from shapely.geometry import Polygon
from shapely.geometry import Point
from shapely.geometry import MultiPolygon
from fiona import collection
from osgeo import gdal,osr

# ------------------------------------------------------------------
# Process LGA Shapefile to determine which LGA the spill happened in 
# ------------------------------------------------------------------

lga_file = open('nigeria-lga-reduced.geojson', "rb").read()
lga_data = json.loads(lga_file)

def pointInPoly(pointLon, pointLat):
    for d in lga_data['features']:                
        if d['geometry']['type'] == 'Polygon':
            schema = { 'geometry': 'Polygon', 'properties': { 'name': 'str' } }
            poly = Polygon(d['geometry']['coordinates'][0])
            point = Point(pointLon, pointLat)
            if point.within(poly) is True:
                lga = d['properties']['Name']
                return lga
        elif d['geometry']['type'] == 'MultiPolygon':
            schema = { 'geometry': 'MultiPolygon', 'properties': { 'name': 'str' } }
            poly1 = d['geometry']['coordinates'][0]
            poly2 = d['geometry']['coordinates'][0]
            poly = MultiPolygon(poly1, poly2)
            point = Point(pointLon, pointLat)
            if point.within(poly) is True:
                lga = d['properties']['Name']
                return lga

# ------------------------------------------------
# Transform from Minna / Nigeria Mid Belt to WGS84
#   http://www.spatialreference.org/ref/epsg/26392/
#   Minna/Nigeria Mid Belt EPSG = 26392
#   WGS83 EPSG = 4326
# --------------------

inSRS = 26392
outSRS = 4326

def transformTo(easting, northing, inSRS, outSRS):

    inproj = osr.SpatialReference()
    outproj = osr.SpatialReference()

    inproj.ImportFromEPSG(inSRS)
    outproj.ImportFromEPSG(outSRS)

    transForm = osr.CoordinateTransformation(inproj, outproj)

    transformed = transForm.TransformPoint(easting,northing)

    return transformed 

# ----------------------
# Process NOSDRA CSV 
# ----------------------
with open('nosdra_out.csv', 'rb') as f:
    reader = csv.DictReader(f)
    outputRow = []
    for row in reader:
    	dt = datetime.strptime(row['DATE OF INCIDENT'].rstrip(' 00:00:00'), "%Y-%m-%d")
    	outputDict = {
			'timestamp': "", 
			'spillid': row['INCIDENT NO'],
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
			outputDict['causeofspill'] = "Sabatage"
			outputDict['thirdparty'] = "yes"
        else:
			outputDict['causeofspill'] = "Other"
			outputDict['thirdparty'] = "no"
        if row['LON'] != "":				# check if it has a longitude value 
        	lonSplit = row['LON'].split()
        	if len(lonSplit) == 2:			# if only degree values 
        		lonSplit = [x.replace('\xc2\xb0', "") for x in lonSplit]
        		lonSplit = [x.replace('\'', "") for x in lonSplit]
        		lonSplit = [x.replace('\xcb\x9d', "") for x in lonSplit]
        		lonClean = []
        		outputDict['longitude'] = ""
        	elif len(lonSplit) == 3:		# only degrees and minutes
        		lonSplit = [x.replace('\xc2\xb0', "") for x in lonSplit]
        		lonSplit = [x.replace('\'', "") for x in lonSplit]
        		lonSplit = [x.replace('\xcb\x9d', "") for x in lonSplit]
        		lonClean = [lonSplit[0],float(lonSplit[1]),float(lonSplit[2])]
        		lonDD = lonClean[1] + (lonClean[2]/60)
        		#outputRow.append(lonDD)
        		outputDict['longitude'] = lonDD 
        	elif len(lonSplit) == 4:		# degrees, minutes, and seconds 
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
        if row['LAT'] != "":				# check if it has a latitude value 
        	latSplit = row['LAT'].split()
        	if len(latSplit) == 2:			# if only degree values 
        		latSplit = [x.replace('\xc2\xb0', "") for x in latSplit]
        		latSplit = [x.replace('\'', "") for x in latSplit]
        		latSplit = [x.replace('\xcb\x9d', "") for x in latSplit]
        		latClean = []
        		outputDict['latitude'] = ""
        	elif len(latSplit) == 3:		# only degrees and minutes
        		latSplit = [x.replace('\xc2\xb0', "") for x in latSplit]
        		latSplit = [x.replace('\'', "") for x in latSplit]
        		latSplit = [x.replace('\xcb\x9d', "") for x in latSplit]
        		latClean = [latSplit[0],float(latSplit[1]),float(latSplit[2])]
        		latDD = latClean[1] + (latClean[2]/60)
        		#outputRow.append(latDD)
        		outputDict['latitude'] = latDD
        	elif len(latSplit) == 4:		# degrees, minutes, and seconds 
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
            outlga = pointInPoly(lonDD, latDD)
            outputDict['lga'] = outlga
            outputRow.append(outputDict)
        else: 
        	pass

# Hardcoded fieldnames to create columns according to this order 
fieldnames = ['timestamp','spillid','companyname','incidentdate','datespillstopped','initialcontainmentmeasures','estimatedquantity','causeofspill','sitelocationname','gpslatdeg','gpslatmin','gpslatsec','gpslongdeg','gpslongmin','gpslongsec','latitude','longitude','estimatedspillarea','typeoffacility','datejiv','spillareahabitat','impact','descriptionofimpact','image','datecleanup','datecleanupcompleted','methodsofcleanup','dateofpostcleanupinspection','dateofpostimpactassessment','furtherremediation','datecertificate','thirdparty','month','year','monthNum','lga']

out = open('nosdra_full_data.csv', 'wb')
csvwriter = csv.DictWriter(out, delimiter=',', fieldnames=fieldnames, quoting=csv.QUOTE_NONNUMERIC)
csvwriter.writerow(dict((fn,fn) for fn in fieldnames))
for row in outputRow:
     csvwriter.writerow(row)
out.close()

mapdataFieldnames = ['spillid','companyname','incidentdate','datespillstopped','initialcontainmentmeasures','estimatedquantity','causeofspill','sitelocationname','latitude','longitude','estimatedspillarea','thirdparty','month','year','monthNum','lga']

out = open('nosdra_map_data.csv', 'wb')
csvwriter = csv.DictWriter(out, delimiter=',', fieldnames=mapdataFieldnames, quoting=csv.QUOTE_NONNUMERIC)
csvwriter.writerow(dict((fn,fn) for fn in mapdataFieldnames))
for row in outputRow:
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
    csvwriter.writerow(row)
out.close()
