// ===== Agent documents (paste into the office Apps Script, then Deploy > Manage deployments > New version) =====
// 1) Inside your existing doPost(e) (after the key check), add:
//      var p = JSON.parse(e.postData.contents);
//      if (p.op === 'agentdocs')      return agdocsJson_(agdocsList_(p.agent_id));
//      if (p.op === 'agentdocupload') return agdocsJson_(agdocsUpload_(p));
// 2) Paste the functions below anywhere in the script.
var AGDOCS_ACTIVE_FOLDER_ID = '1KmSmoYHNJrBuy2vR1X3DcqpmIfAvjkXO';

function agdocsJson_(o) {
  return ContentService.createTextOutput(JSON.stringify(o)).setMimeType(ContentService.MimeType.JSON);
}
function agdocsAgentFolder_(agentId) {
  var id = String(agentId || '').trim();
  if (!/^[A-Za-z]-?\d+$/.test(id)) return null;
  var it = DriveApp.getFolderById(AGDOCS_ACTIVE_FOLDER_ID).getFolders();
  while (it.hasNext()) { var f = it.next(); if (f.getName().indexOf(id + ' ') === 0) return f; }
  return null;
}
function agdocsAgreements_(agent, create) {
  var it = agent.getFoldersByName('Agreements');
  if (it.hasNext()) return it.next();
  return create ? agent.createFolder('Agreements') : null;
}
function agdocsList_(agentId) {
  var agent = agdocsAgentFolder_(agentId);
  if (!agent) return { ok: false, msg: 'No Drive folder found for agent ' + agentId + ' in the Active folder.' };
  var ag = agdocsAgreements_(agent, false);
  var out = { ok: true, agent_id: agentId, agent_folder_url: agent.getUrl(), folder_url: ag ? ag.getUrl() : '', files: [] };
  if (ag) {
    var fs = ag.getFiles();
    while (fs.hasNext()) {
      var f = fs.next();
      out.files.push({ id: f.getId(), name: f.getName(), url: f.getUrl(),
        modified: Utilities.formatDate(f.getLastUpdated(), 'America/New_York', 'yyyy-MM-dd'), size: f.getSize() });
    }
    out.files.sort(function (a, b) { return a.name < b.name ? -1 : 1; });
  }
  return out;
}
function agdocsUpload_(p) {
  var agent = agdocsAgentFolder_(p.agent_id);
  if (!agent) return { ok: false, msg: 'No Drive folder found for agent ' + p.agent_id + ' in the Active folder.' };
  if (!p.name || !p.data) return { ok: false, msg: 'Missing file name or data.' };
  var ag = agdocsAgreements_(agent, true);
  var blob = Utilities.newBlob(Utilities.base64Decode(p.data), p.mime || 'application/octet-stream', p.name);
  var f = ag.createFile(blob);
  return { ok: true, id: f.getId(), name: f.getName(), url: f.getUrl() };
}
