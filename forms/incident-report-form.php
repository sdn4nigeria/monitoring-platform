<form id="incident-report-form" action="javascript:void(alert('not done yet'))" method="POST">
  <hr/>
  <div>
    <input id="this-is-an-update" type="checkbox" onchange="$(this).parent().next().show()"/><label for="this-is-an-update">This is an update on a previous spill</label>
  </div>
  <div style="display:none">
    Update for: <div style="display:inline-block;vertical-align:top"><input name="spillid" onchange="if (!this.value) $('#this-is-an-update').click()"/><br/><input id="copy-data-from-map" name="copy-data-from-map" type="checkbox" style="vertical-align:middle"/><label for="copy-data-from-map">&nbsp;Select in map</label></div><br/>
  </div>
  Date: <input type="date" size="11" name="incidentdate"/><br/>
  Company: <select onchange="FormUtils.combobox(this, 'companyname', '')"><option value="">Other</option><option value="ADDAX">ADDAX</option><option value="AENR">AENR</option><option value="CHEVRON">CHEVRON</option><option value="ESSO">ESSO</option><option value="MPN">MPN</option><option value="NAOC">NAOC</option><option value="NDPR">NDPR</option><option value="NPDC">NPDC</option><option value="POOCN">POOCN</option><option value="PPMC">PPMC</option><option value="SEEPCO">SEEPCO</option><option value="SEPLAT">SEPLAT</option><option value="SNEPCO">SNEPCO</option><option value="SPDC">SPDC</option><option value="TOTAL">TOTAL</option><option value="WRPC">WRPC</option></select><input name="companyname" size="10"/><br/>
  Initial containment measures: <textarea name="initialcontainmentmeasures"></textarea><br/>
  Estimated quantity (bbl): <input size="8" name="estimatedquantity" type="number"/><br/>
  Cause of spill: <select onchange="FormUtils.combobox(this, 'causeofspill', 'oth')"><option value="oth">Other</option><option value="cor">Corrosion</option><option value="eqf">Equipment failure</option><option value="pme">Production/maintenance/human error</option><option value="sab">Sabotage</option><option value="mys">Mystery spill</option><option value="ome">OME</option><option value="ytd">YTD</option></select><input name="causeofspill" value="oth:" size=""/><br/>
  Site location name: <input name="sitelocationname" size=""/><br/>
  Latitude: <input name="latitude"/><br/>
  Longitude: <input name="longitude"/><br/>
  LGA: <span id="form-lga">-</span><br/>
  Estimated spill area: <input name="estimatedspillarea" type="number"/><select><option>Km²</option><option>ha</option><option>Km around</option></select><br/>
  <button onclick="event.preventDefault();FormUtils.addAttachmentLink($('#incident-report-form-attachments'))">Attach link URL</button>
  <button onclick="event.preventDefault();FormUtils.addAttachmentFile($('#incident-report-form-attachments'))">Attach file</button>
  <div id="incident-report-form-attachments">
  </div>
  Spill area habitat: <select name="spillareahabitat"><option value="land">Land</option><option value="seasonal-swamp">Seasonal swamp</option><option value="swamp">Swamp</option><option value="coastland">Coastland</option><option value="inland-waters">Inland waters</option><option value="near-shore">Near shore</option><option value="offshore">Offshore</option></select><br/>
  Type of facility: <input name="typeoffacility"/><br/>
  Impact: <input name="impact"/><br/>
  Description of impact: <textarea name="descriptionofimpact"></textarea><br/>
  Date JIV: <input type="date" size="11" name="datejiv"/><br/>
  Date spill stopped: <input type="date" size="11" name="datespillstopped"/><br/>
  <h3>Cleanup</h3>
  Date cleanup: <input type="date" size="11" name="datecleanup"/><br/>
  Date cleanup completed: <input type="date" size="11" name="datecleanupcompleted"/><br/>
  Methods of cleanup: <textarea name="methodsofcleanup"></textarea><br/>
  <h3>Post-cleanup</h3>
  Date inspection: <input type="date" size="11" name="dateofpostcleanupinspection"/><br/>
  Date impact asessment: <input type="date" size="11" name="dateofpostimpactassessment"/><br/>
  Further remediation: <textarea name="furtherremediation"></textarea><br/>
  Date certificate: <input type="date" size="11" name="datecertificate"/><br/>
  <input type="hidden" />
  <hr/>
  <button type="submit">Report incident</button>
  <button style="float:right" onclick="toggleIncidentReportForm(); return false">Cancel</button>
</form>
<script>var today = new Date(); document.getElementById('incident-report-form')["incidentdate"].value = today.getFullYear()+"-"+("0"+today.getMonth()).substr(-2)+"-"+("0"+today.getDate()).substr(-2)</script>
