// Synthetic browser fixture. This verifies React interactions and transport shape,
// never business correctness, production readiness, or a real medical workflow.
import { build } from 'esbuild';
import { createServer } from 'node:http';
import { mkdtemp, readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

const output = await mkdtemp(path.join(tmpdir(), 'aio-pharmacy-ui-'));
const entry = `
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from 'antd/es/app';
import ConfigProvider from 'antd/es/config-provider';
import 'antd/dist/reset.css';
import PharmacyManagerPage from './resources/admin/src/modules/pharmacy/pages/PharmacyManagerPage.jsx';
const products = [{ id:'product-a',code:'TEST-A',name:'Hàng thử nghiệm A',base_unit:'viên',strength:'Thuộc tính mẫu',price_minor:'5000',ingredients:[{name:'Thành phần mẫu A',strength:'10',unit:'mg'},{name:'Thành phần mẫu B',strength:'20',unit:'mg'}] },{id:'product-b',code:'TEST-B',name:'Hàng thử nghiệm B',base_unit:'hộp',price_minor:'25000'}];
let record = null;
const permissions=['pharmacy.*'];
const pharmacy={id:'pharmacy-a',name:'Nhà thuốc mô phỏng',code:'DEMO',version:1,settings:{workflow_mode:'single_counter'}};
const bootstrap={id:1,permissions,module:{key:'pharmacy',version:'ui-fixture'},pharmacies:[pharmacy,{...pharmacy,id:'pharmacy-b',name:'Nhà thuốc mô phỏng B'}],current_pharmacy:pharmacy,capabilities:{controlled:{ready:false,reason:'Chưa nghiệm thu fixture'},ecommerce:{ready:false},cold_chain:{ready:false},clinical_knowledge:{ready:false}}};
const client=async(url,options={})=>{
 const resource=new URL(url,location.origin).pathname.replace('/admin/api/pharmacy/','');
 if(options.method==='POST'){
  const body=JSON.parse(options.body);window.__lastPharmacyCommand=body;
  if(body.action==='dispensing.create'){record={id:'dispensing-demo',code:'HS-DEMO',status:'draft',version:1,mode:body.mode,total_minor:'5000',review_status:'pending',inventory_status:'unreserved',payment_status:'unpaid',handover_status:'pending',lines:body.lines.map((line,index)=>({...line,id:'line-'+index,name:products.find(p=>p.id===line.product_id)?.name,total_minor:'5000',allocations:[]}))};}
  if(body.action==='dispensing.prepare'){record={...record,status:'prepared',version:2,inventory_status:'reserved',lines:record.lines.map(line=>({...line,allocations:body.allocations.filter(a=>a.line_id===line.id)}))};}
  if(body.action==='dispensing.review'){record={...record,status:'reviewed',version:3,review_status:'approved'};}
  if(body.action==='dispensing.finalize'){record={...record,status:'finalized',version:4,payment_status:'paid',inventory_status:'committed'};}
  if(body.action==='dispensing.handover'){record={...record,status:'handed_over',version:5,handover_status:'handed_over'};}
  return {data:record??{id:'created-demo'}};
 }
 if(resource==='integrations')return {data:{records:[],internal_adapters:{customers:{available:true,mappings:[],clinical_sharing:false},accounting:{available:true,mappings:[],draft_only:true}}}};
 if(resource==='customer-books')return {data:{records:[{id:'book-a',name:'Nhóm thương mại mẫu',website_key:'commercial-scope'}]}};
 if(resource==='accounting-organizations')return {data:{records:[{id:11,name:'Pháp nhân mẫu'}]}};
 if(resource==='accounting-items')return {data:{records:[{id:17,name:'Hàng kế toán A'}]}};
 if(resource==='bootstrap')return {data:bootstrap};
 if(resource==='scan')return {data:{gtin:'04006381333931',batch_code:'LOT-DEMO',expires_at:'2030-01-01',allocation_confirmed:false,records:[{...products[0],packaging_id:9,packaging_name:'Hộp 10',packaging_unit:'hop',quantity_multiplier:'10'}]}};
 if(resource==='products')return {data:{records:products,meta:{total:products.length}}};
 if(resource==='inventory')return {data:{records:[{id:7,batch_id:7,batch_code:'LOT-DEMO',name:'Hàng thử nghiệm A',available:'20',expires_at:'2030-01-01'}],meta:{total:1}}};
 if(resource==='shifts')return {data:{records:[{id:'shift-demo',name:'Ca mô phỏng',status:'open'}],meta:{total:1}}};
 if(resource==='dispensings')return {data:{records:record?[record]:[],meta:{total:record?1:0}}};
 if(resource.startsWith('dispensings/'))return {data:record};
 return {data:{records:[],meta:{total:0}}};
};
createRoot(document.getElementById('root')).render(<ConfigProvider theme={{token:{colorPrimary:'#177567',borderRadius:10,fontFamily:'Arial, sans-serif'}}}><App><BrowserRouter basename='/admin'><PharmacyManagerPage callAdminApi={client} currentAdmin={{id:1}} currentPermissions={permissions}/></BrowserRouter></App></ConfigProvider>);
`;
await build({ stdin: { contents: entry, resolveDir: process.cwd(), sourcefile: 'pharmacy-ui-fixture.jsx', loader: 'jsx' }, bundle: true, format: 'esm', outdir: output, entryNames: 'app', jsx: 'automatic', define: { 'process.env.NODE_ENV': '"test"' }, logLevel: 'error' });
const html = '<!doctype html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"><title>Pharmacy synthetic UI test</title><style>body{background:#f3f7f4;padding:28px} @media(max-width:600px){body{padding:14px}}</style></head><body><div id="root"></div><script type="module" src="/app.js"></script></body></html>';
const server = createServer(async (req, res) => {
    if (req.url === '/app.js' || req.url === '/app.css') {
        res.setHeader('Content-Type', req.url.endsWith('.css') ? 'text/css' : 'text/javascript');
        res.end(await readFile(path.join(output, req.url.slice(1))));
    } else { res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end(html); }
});
server.listen(Number(process.env.PHARMACY_UI_TEST_PORT ?? 8128), '127.0.0.1', () => process.stdout.write('Pharmacy synthetic UI fixture listening\n'));
