<style>
    .xd-landing-block{position:relative}
    .xd-edit-block{position:absolute;top:18px;right:18px;z-index:25;width:auto!important;border:1px solid rgba(255,255,255,.55)!important;border-radius:999px!important;padding:10px 16px!important;background:#dc2626!important;color:#fff!important;font:700 13px/1 'Be Vietnam Pro',sans-serif!important;box-shadow:0 10px 28px rgba(0,0,0,.3)!important;cursor:pointer;transition:.2s}
    .xd-edit-block:hover{background:#b91c1c!important;transform:translateY(-2px)}
    .xd-editor[hidden],.xd-item-modal[hidden],.xd-editor-items[hidden],.xd-editor-source[hidden],.xd-editor-source label[hidden],.xd-item-upload[hidden]{display:none!important}
    .xd-editor,.xd-item-modal{position:fixed;inset:0;z-index:120;display:grid;place-items:center;padding:20px;background:rgba(3,16,15,.72);color:#20302d;font-family:'Be Vietnam Pro',sans-serif}
    .xd-item-modal{z-index:140}
    .xd-editor-card,.xd-item-card{width:min(940px,100%);max-height:92vh;overflow:auto;border:1px solid #e1e8e5;border-radius:20px;padding:22px;background:#fff;color:#20302d;box-shadow:0 30px 90px rgba(0,0,0,.35)}
    .xd-item-card{width:min(680px,100%)}
    .xd-editor-head,.xd-item-card-head,.xd-editor-items-head,.xd-editor-items-actions,.xd-editor-actions,.xd-item-actions,.xd-editor-item-actions{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .xd-editor-head{position:sticky;top:-22px;z-index:8;margin:-22px -22px 16px;padding:18px 22px 14px;border-bottom:1px solid #e8eeeb;background:rgba(255,255,255,.98)}
    .xd-editor-head h3,.xd-item-card-head h3,.xd-editor-items-head h4,.xd-editor-source h4{margin:0;color:#173b34}
    .xd-editor-close,.xd-item-close{display:grid;place-items:center;width:38px!important;height:38px;border:0;border-radius:50%;background:#eef3f1;color:#173b34;font-size:22px;cursor:pointer}
    .xd-editor-locale-tabs{display:flex;flex-wrap:wrap;gap:7px;width:max-content;max-width:100%;margin-bottom:15px;padding:4px;border:1px solid #e1e8e5;border-radius:999px;background:#f5f8f7}
    .xd-editor-locale-tab{min-height:34px;border:0;border-radius:999px;padding:0 14px;background:transparent;color:#40534f;font:700 13px inherit;cursor:pointer}
    .xd-editor-locale-tab.is-active{background:#0d3b35;color:#fff}
    .xd-editor-grid,.xd-item-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .xd-editor-field,.xd-item-form label,.xd-editor-source label{display:grid;gap:6px;color:#344b46;font-size:12px;font-weight:700}
    .xd-editor-field.is-wide,.xd-item-form .is-wide,.xd-editor-items,.xd-editor-source{grid-column:1/-1}
    .xd-editor-field input,.xd-editor-field textarea,.xd-item-form input,.xd-item-form textarea,.xd-editor-source select,.xd-editor-source input{width:100%;min-height:42px;border:1px solid #d9e2df;border-radius:10px;padding:9px 12px;background:#fff;color:#20302d;font:400 14px 'Be Vietnam Pro',sans-serif}
    .xd-editor-field textarea,.xd-item-form textarea{min-height:90px;resize:vertical}
    .xd-editor-field--visibility{grid-column:1/-1;padding:11px 13px;border:1px solid #e1e8e5;border-radius:12px;background:#f8faf9}.xd-editor-switch{display:flex;align-items:center;gap:10px;width:max-content;cursor:pointer}.xd-editor-switch input{position:absolute;width:1px!important;height:1px!important;min-height:0!important;opacity:0}.xd-editor-switch i{position:relative;width:42px;height:24px;border-radius:999px;background:#cbd5d1;transition:.18s}.xd-editor-switch i:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2);transition:.18s}.xd-editor-switch input:checked+i{background:#0d3b35}.xd-editor-switch input:checked+i:after{transform:translateX(18px)}
    .xd-editor-hidden-json{display:none!important}
    .xd-editor-source,.xd-editor-items{display:grid;gap:12px;padding:14px;border:1px solid #e1e8e5;border-radius:14px;background:#f8faf9}
    .xd-editor-source-grid{display:grid;grid-template-columns:1.15fr 1fr 110px .85fr auto;gap:10px;align-items:end}
    .xd-editor-source-check{display:flex!important;align-items:center;gap:8px;padding-bottom:10px;white-space:nowrap}.xd-editor-source-check input{width:18px;min-height:18px}
    .xd-editor-source-note,.xd-editor-help{margin:0;color:#667a75;font-size:12px;line-height:1.5}
    .xd-editor-item-list{display:grid;gap:10px}
    .xd-editor-item{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px;padding:12px;border:1px solid #e1e8e5;border-radius:12px;background:#fff}
    .xd-editor-item-main{display:grid;grid-template-columns:72px minmax(0,1fr);align-items:center;gap:12px;min-width:0}.xd-editor-item-thumb{display:grid;place-items:center;width:72px;height:54px;overflow:hidden;border-radius:9px;background:#eef3f1;font-size:10px}.xd-editor-item-thumb img{width:100%;height:100%;object-fit:cover}
    .xd-editor-item-summary{min-width:0}.xd-editor-item-summary small,.xd-editor-item-summary strong,.xd-editor-item-summary span{display:block}.xd-editor-item-summary strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.xd-editor-item-summary span{color:#667a75;font-size:12px}
    .xd-editor-edit,.xd-editor-remove,.xd-editor-add,.xd-editor-manage,.xd-editor-actions button,.xd-item-actions button{min-height:38px;border:1px solid #d9e2df;border-radius:999px;padding:0 14px;background:#fff;color:#173b34;font-weight:700;cursor:pointer;text-decoration:none}
    .xd-item-upload,.xd-image-mode{display:flex;flex-wrap:wrap;align-items:center;gap:9px}.xd-item-image-field{grid-column:1/-1}.xd-image-mode label{display:flex!important;align-items:center;gap:7px;padding:7px 12px;border:1px solid #d9e2df;border-radius:999px}.xd-image-mode input{width:auto!important;min-height:auto}.xd-item-image-field.is-upload-mode>[data-xd-item-modal-field="image"]{display:none}
    .xd-editor-add,.xd-editor-manage{display:inline-flex;align-items:center}.xd-editor-manage{background:#173b34;color:#fff}
    .xd-editor-actions{position:sticky;bottom:-22px;z-index:8;justify-content:flex-end;margin:16px -22px -22px;padding:13px 22px;border-top:1px solid #e8eeeb;background:#fff}.xd-editor-actions button[type=submit],.xd-item-actions button[type=submit]{border-color:#dda149;background:#dda149;color:#fff}
    @media(max-width:760px){.xd-edit-block{top:10px;right:10px;padding:8px 11px!important;font-size:11px!important}.xd-editor,.xd-item-modal{align-items:end;padding:0}.xd-editor-card,.xd-item-card{width:100%;max-height:90vh;border-radius:20px 20px 0 0;padding:16px}.xd-editor-head{top:-16px;margin:-16px -16px 12px;padding:14px 16px 12px}.xd-editor-grid,.xd-item-form,.xd-editor-source-grid{grid-template-columns:1fr}.xd-editor-item{grid-template-columns:1fr}.xd-editor-actions{bottom:-16px;margin:14px -16px -16px;padding:12px 16px}.xd-editor-actions button{flex:1}}
</style>
