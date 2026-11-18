// Finds out which forms are inside a PDF by reading its text (pdf.js), so signed documents can be matched to required documents.
// Page numbers are 1-based. Used by sign_packet.php (contract packet) and seller_docs.php (seller disclosures).
var FLRDetect=(function(){
  var CONTRACT=[["contract_res",/residential\s+contract\s+for\s+sale\s+and\s+purchase/i],["contract_land",/vacant\s+land\s+contract/i],["ff_credit",/credit\s+related\s+to\s+buyer/i]];
  var SELLER=[["flood",/flood\s+disclosure/i],["hoa",/(hoa|homeowners?['’]?s?\s+association|community)\s+(rider|disclosure)|disclosure\s+summary\s+for/i],["lead",/lead.?based\s+paint/i],["cdd",/community\s+development\s+district|\bcdd\b/i],["condo",/condominium\s+rider/i]];
  async function texts(bytes){
    var doc=await pdfjsLib.getDocument({data:new Uint8Array(bytes).slice()}).promise,out=[];
    for(var n=1;n<=doc.numPages;n++){var tc=await (await doc.getPage(n)).getTextContent();out.push(tc.items.map(function(i){return i.str;}).join(" ").replace(/\s+/g," "));}
    return out;
  }
  function scan(pages,rules){var r={};pages.forEach(function(t,i){rules.forEach(function(x){if(r[x[0]]==null&&x[1].test(t))r[x[0]]=i+1;});});return r;}
  function contract(pages){var r=scan(pages,CONTRACT);if(r.contract_res&&r.contract_land){if(r.contract_res<=r.contract_land)delete r.contract_land;else delete r.contract_res;}return r;}
  return {texts:texts,contract:contract,seller:function(p){return scan(p,SELLER);},
    SELLER_LABELS:{flood:"Flood Disclosure",hoa:"HOA / Community",lead:"Lead paint",cdd:"CDD",condo:"Condo rider"}};
})();
