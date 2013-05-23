<form id="incident-report-form" action="javascript:void()" onsubmit="return loadIncidentReportForm(this)" method="POST">
  <div>
    <input id="this-is-an-update" type="checkbox" onchange="var u=$(this).parent().next(); if (this.checked) { u.show(); this.form['updatefor'].disabled = false; } else { u.hide(); $('#copy-data-from-map').prop('checked', false); }"/><label for="this-is-an-update">&nbsp;this is an update for a reported spill</label>
  </div>
  <div style="display:none">
    Update for: <div style="display:inline-block;vertical-align:top"><input name="updatefor"/><input type="button" value="Get" onclick="fillIncidentReportForm(this.form['updatefor'].value)"/><br/><input id="copy-data-from-map" name="copy-data-from-map" type="checkbox" style="vertical-align:middle"/><label for="copy-data-from-map">&nbsp;select in map</label>, <a href="spill-data.php?dataset=nosdra" target="_blank">Data Explorer</a></div><br/>
  </div>
  Status: <input name="status" size="8"/><br/>
  Incident date: <input type="date" size="11" name="incidentdate"/><br/>
  Date spill stopped: <input type="date" size="11" name="datespillstopped"/><br/>
  Company: <input name="company" size="10"/><br/>
  Initial containment measures: <textarea name="initialcontainmentmeasures"></textarea><br/>
  Estimated quantity (bbl): <input size="8" name="estimatedquantity" type="number"/><br/>
  Type of contaminant: <input name="contaminant"/><br/>
  Cause of spill: <input name="cause" size=""/><br/>
  <select id="coordinate-units"><option value="lat-lon">Lat / Lon</option><option value="nor-eas">Nort/East</option></select>: <input name="latitude" size="9" type="number" precision="9" onchange="changeCoordinates(this)"/> / <input name="longitude" size="9" type="number" precision="9" onchange="changeCoordinates(this)"/><br/>
  LGA: <input name="lga" disabled="disabled"/><br/>
  Site location name: <input name="sitelocationname" size=""/><br/>
  Estimated spill area: <input name="estimatedspillarea" type="number"/><!-- select><option>Km²</option><option>ha</option><option>Km around</option></select -->Km²<br/>
  Spill area habitat: <input name="spillareahabitat"/><br/>
  <button onclick="event.preventDefault();FormUtils.addAttachmentLink('incident-report-form-attachments')">Attach link URL</button>
  <button onclick="event.preventDefault();FormUtils.addAttachmentFile('incident-report-form-attachments')">Attach file</button>
  <div id="incident-report-form-attachments"></div>
  <!-- Type of facility: <input name="typeoffacility"/><br/> -->
  Impact: <input name="impact"/><br/>
  Description of impact: <textarea name="descriptionofimpact"></textarea><br/>
  Date JIV: <input type="date" size="11" name="datejiv"/><br/>
  <h3>Cleanup</h3>
  Date cleanup: <input type="date" size="11" name="datecleanup"/><br/>
  Date cleanup completed: <input type="date" size="11" name="datecleanupcompleted"/><br/>
  Methods used: <textarea name="methodsofcleanup"></textarea><br/>
  <h3>Post-cleanup</h3>
  Date inspection: <input type="date" size="11" name="dateofpostcleanupinspection"/><br/>
  Date impact asessment: <input type="date" size="11" name="dateofpostimpactassessment"/><br/>
  Further remediation: <textarea name="furtherremediation"></textarea><br/>
  Date certificate: <input type="date" size="11" name="datecertificate"/><br/>
  <input type="hidden" />
  <hr/>
  <button type="submit">Send report</button>
  <button style="float:right" onclick="toggleIncidentReportForm(); return false">Cancel</button>
</form>
<script>
var form = document.getElementById('incident-report-form');
FormUtils.combobox(form["status"],
                   ["new", "unverifiable", "investigation", "verified"],
                   false /* this is a simple menu without "other" option */);
FormUtils.combobox(form["company"],
                   ["ADDAX", "AENR", "CHEVRON", "ESSO", "MPN",
                    "NAOC", "NDPR", "NPDC", "POOCN", "PPMC",
                    "SEEPCO", "SEPLAT", "SNEPCO", "SPDC",
                    "TOTAL", "WRPC"
                   ], "");
FormUtils.combobox(form["spillareahabitat"],
                   [["la", "land"],
                    ["ss", "seasonal swamp"], ["sw", "swamp"],
                    ["co", "coastland"],
                    ["iw", "inland waters"],
                    ["ns", "near shore"],
                    ["of", "offshore"]
                   ], "other");
FormUtils.combobox(form["contaminant"],
                   [["cr", "Crude oil"], ["pr", "Refined oil product"],
                    ["ch", "Drilling mud / chemicals"],
                    ["fi", "Fire"]
                   ], "other");
FormUtils.combobox(form["cause"],
                   [["cor", "Corrosion"], ["eqf", "Equipment failure"],
                    ["pme", "Production/maintenance/human error"],
                    ["sab", "Sabotage"], ["mys", "Mystery spill"],
                    ["ome", "OME"], ["ytd", "YTD"]
                   ], "oth");
FormUtils.date(form["incidentdate"]);
</script>
