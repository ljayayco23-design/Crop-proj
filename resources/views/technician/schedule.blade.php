@extends('layouts.technician')

@section('title', 'RICEGUARD AI • Schedule')

@section('content')
@php
    // ============================================================
    // This page is ALWAYS-ONLINE. Unlike farmer.schedule, there is no
    // IndexedDB cache, no offline queue, and no service-worker
    // registration here every add/edit/delete goes straight to the
    // server via S.urls.* below and re-renders from the response.
    // ScheduleController@technician* only ever does plain, synchronous CRUD.
    //
    // Page chrome (sidebar / top nav / notifications / quick search /
    // profile panel) now comes entirely from layouts.technician, the
    // same layout every other technician page (e.g. announcements)
    // uses  this page used to duplicate all of that itself with a
    // second, out-of-sync mobile sidebar/burger implementation, which
    // is what caused the nav icons to behave differently on phones.
    // ============================================================
    $cfg = [
        'uid'  => $uid,
        'csrf' => csrf_token(),
        'urls' => [
            'events'  => route('technician.schedule.events', [], false),
            'store'   => route('technician.schedule.store', [], false),
            // '__UUID__' is swapped for the real uuid client-side (urlFor()).
            'update'  => route('technician.schedule.update', ['uuid' => '__UUID__'], false),
            'destroy' => route('technician.schedule.destroy', ['uuid' => '__UUID__'], false),
        ],
    ];
@endphp

<style>
:root{color-scheme:dark;--bg:#050d1a;--pn:#0b192b;--ln:#182b45;--ln2:#12233a;--tx:#e6edf7;--mu:#8da0ba}
*{box-sizing:border-box}
[hidden]{display:none!important}
html,body{overflow-x:hidden}
button{font-family:inherit}


.s-wrap{background:#07121f;border:1px solid var(--ln);border-radius:18px;padding:14px 16px 16px}
.s-head{display:flex;align-items:center;gap:12px;padding:4px 6px 14px}
.s-head h1{font-size:26px;font-weight:600;margin:0;line-height:1.1}
.s-head p{margin:2px 0 0;color:#b6c4d8;font-size:14px}
.s-hic{font-size:26px}
.s-hr{margin-left:auto;display:flex;align-items:center;gap:12px}
.s-ib{background:none;border:0;color:#dbe6f5;font-size:18px;padding:6px 8px;cursor:pointer}
.s-add{background:linear-gradient(#0ea5e9,#0284c7);border:1px solid #38bdf8;color:#fff;font-weight:600;border-radius:9px;padding:9px 18px;cursor:pointer;display:inline-flex;gap:8px;align-items:center;font-size:15px}
.s-add:hover{filter:brightness(1.1)}
.s-add:disabled{opacity:.6;cursor:default}
.s-q{background:#0a1a2e;border:1px solid var(--ln);border-radius:8px;color:var(--tx);padding:7px 10px;width:220px}
.s-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--mu);border:1px solid var(--ln);border-radius:999px;padding:4px 10px;white-space:nowrap}
.s-pill i{width:8px;height:8px;border-radius:50%;background:#3b82f6;display:inline-block}
.s-pill em{font-style:normal}
.s-pill.off i{background:#f59e0b}

.s-cols{display:grid;grid-template-columns:320px minmax(0,1fr);gap:14px;align-items:start}
.s-card{background:var(--pn);border:1px solid var(--ln);border-radius:14px}

/* mini calendar */
.s-mini{padding:14px 12px 12px;margin-bottom:12px}
.s-mnav{display:flex;align-items:center;gap:22px;margin:0 0 10px 6px}
.s-pair{display:flex;border:1px solid var(--ln);border-radius:9px;overflow:hidden;background:#0a1a2e}
.s-pair button{background:none;border:0;color:#dbe6f5;width:38px;height:34px;cursor:pointer}
.s-pair button+button{border-left:1px solid var(--ln)}
.s-pair button:hover{background:#12294a}
.s-lbl{background:none;border:0;color:var(--tx);font-size:19px;font-weight:600;cursor:pointer;padding:0}
.s-lbl i{font-size:11px;margin-left:6px}
.s-mg{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));text-align:center;row-gap:2px}
.s-mg .dh{color:#c9d6e8;font-size:13px;padding:8px 0}
.s-mg .d{height:37px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#dbe6f5;font-size:14px}
.s-mg .d span{width:33px;height:33px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.s-mg .d.o{color:#5d6f88}
.s-mg .d:hover span{background:#132a45}
.s-mg .d.a span{box-shadow:inset 0 0 0 1.5px #2b4468}
.s-mg .d.t span,.s-mg .d.t:hover span{background:#0ea5e9;color:#fff;font-weight:600}

/* calendars list */
.s-cals{padding:16px}
.s-cals h3{font-size:17px;font-weight:600;margin:0 0 10px}
.s-cr{display:flex;align-items:center;gap:12px;height:37px;cursor:pointer;color:#dbe6f5;margin:0}
.s-cr input{display:none}
.s-cr b{width:22px;height:22px;border-radius:5px;border:2px solid var(--c);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700}
.s-cr input:checked+b{background:var(--c)}
.s-cr input:checked+b::after{content:"\2713"}
.s-addcal{margin-top:8px;padding:12px 0 0;border:0;border-top:1px solid var(--ln);background:none;color:#38bdf8;display:flex;gap:10px;width:100%;cursor:pointer;font-size:14px;align-items:center}

/* main calendar */
.s-mc{padding:12px}
.s-bar{display:flex;align-items:center;gap:12px;padding:4px 4px 12px;flex-wrap:wrap}
.s-tl{background:#0a1a2e;border-radius:9px;padding:9px 16px;font-size:19px;font-weight:600;color:var(--tx);border:0;cursor:pointer}
.s-tl i{font-size:11px;margin-left:6px}
.s-sp{flex:1}
.s-bar label{color:var(--mu);font-size:13px;margin:0 0 0 6px}
.s-sel{background:#0a1a2e;border:1px solid var(--ln);color:var(--tx);border-radius:8px;padding:7px 12px;min-width:110px}
.s-gw{border:1px solid var(--ln);border-radius:8px;overflow-x:auto;background:#081524}
.s-gin{min-width:720px}
.s-row{display:grid;grid-template-columns:100px repeat(var(--n),minmax(0,1fr))}
.s-hd>div{height:40px;display:flex;align-items:center;justify-content:center;border-bottom:1px solid var(--ln);border-left:1px solid var(--ln2);color:#cfdbee;font-size:15px;gap:6px}
.s-hd>div:first-child{border-left:0}
.tn{background:#0ea5e9;color:#fff;border-radius:50%;min-width:26px;height:26px;display:inline-flex;align-items:center;justify-content:center;font-weight:600}
.s-ad>div{min-height:34px;border-bottom:1px solid var(--ln2);border-left:1px solid var(--ln2)}
.s-ad>.lb{display:flex;align-items:center;justify-content:center;color:#a9b9d0;font-size:13px;border-left:0}
.s-bd{max-height:400px;overflow-y:auto}
.s-tc>div{height:33px;display:flex;align-items:center;justify-content:center;color:#a9b9d0;font-size:13px}
.s-dc{position:relative;border-left:1px solid var(--ln2);background-image:repeating-linear-gradient(to bottom,transparent 0,transparent 32px,var(--ln2) 32px,var(--ln2) 33px)}
.s-ev{border-radius:5px;border:1px solid var(--bd);background:var(--bg2);padding:4px 7px;overflow:hidden;cursor:pointer;font-size:11.5px;line-height:1.25;color:#eaf1ff}
.s-dc .s-ev{position:absolute}
.s-ad .s-ev{margin:2px}
.s-ev b{display:block;font-weight:600;font-size:12px}
.s-ev span{display:block}
.s-ev:hover{filter:brightness(1.15)}
.s-leg{display:flex;gap:30px;align-items:center;flex-wrap:wrap;padding:12px 18px;margin-top:12px;border:1px solid var(--ln);border-radius:8px;color:#c5d3e8;font-size:13px}
.s-leg i{display:inline-block;width:11px;height:11px;border-radius:3px;background:var(--dot);margin-right:8px}
.s-month{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));min-width:720px}
.s-month .mh{padding:10px;text-align:center;border-bottom:1px solid var(--ln);color:#cfdbee}
.s-month .mc{min-height:96px;border-left:1px solid var(--ln2);border-top:1px solid var(--ln2);padding:4px}
.s-month .mc.o{opacity:.5}
.s-month .mn{text-decoration:none;display:inline-flex;min-width:24px;height:24px;align-items:center;justify-content:center;color:#cfdbee;cursor:pointer;border-radius:50%;margin-bottom:2px}
.s-month .s-ev{margin-top:3px;padding:2px 6px}
.s-month small{color:var(--mu)}
.c-blue{--bg2:#0b3a8c;--bd:#3b6fe0;--dot:#4f6df0}
.c-green{--bg2:#0a6a3a;--bd:#22a65a;--dot:#22c55e}
.c-amber{--bg2:#7a4a08;--bd:#d99a1f;--dot:#f59e0b}
.c-purple{--bg2:#4a1d96;--bd:#7c4ddb;--dot:#8b5cf6}
.c-red{--bg2:#7a2434;--bd:#c73c52;--dot:#ef4444}
.c-teal{--bg2:#0b6660;--bd:#1fb5a8;--dot:#14b8a6}
.c-gray{--bg2:#334155;--bd:#64748b;--dot:#94a3b8}

/* upcoming table */
.s-up{margin-top:12px;padding:16px 18px 18px}
.s-uph{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.s-uph h2{font-size:18px;font-weight:600;margin:0}
.s-out{background:#0a1a2e;border:1px solid var(--ln);color:var(--tx);border-radius:8px;padding:8px 16px;cursor:pointer;display:inline-flex;gap:8px;align-items:center}
.s-out:hover{background:#12294a}
.s-tw{overflow-x:auto;border:1px solid var(--ln);border-radius:8px}
.s-tbl{width:100%;border-collapse:collapse;min-width:820px}
.s-tbl th{background:#0a1a2e;color:#c0cee2;font-weight:500;font-size:13px;padding:10px 14px;text-align:left}
.s-tbl td{padding:0 14px;height:37px;border-top:1px solid var(--ln2);font-size:13px;color:#dbe6f5;white-space:nowrap}
.s-tbl td i.fa-regular,.s-tbl td i.fa-solid{color:#b6c4d8;margin-right:8px;width:14px;text-align:center}
.s-tbl .r{text-align:center;width:90px}
.s-bg{display:inline-block;padding:2px 12px;border-radius:5px;border:1px solid var(--bd);background:var(--bg2);font-size:13px;color:#fff}
.s-st{display:inline-block;padding:2px 12px;border-radius:5px;border:1px solid;font-size:13px}
.st-approved{background:#0c3a26;border-color:#1f8a4c;color:#8ee6ad}
.st-pending{background:#3a2c0a;border-color:#a37a12;color:#f5c451}
.st-completed{background:#12304a;border-color:#2c6a9e;color:#8cc4f0}
.s-dots{background:none;border:0;color:#dbe6f5;cursor:pointer;padding:4px 10px}
.s-empty{text-align:center;color:var(--mu);height:72px!important;white-space:normal!important}
.s-empty button{background:none;border:0;color:#38bdf8;cursor:pointer;padding:0;text-decoration:underline}
.s-menu{position:absolute;z-index:120;background:#0c1b30;border:1px solid var(--ln);border-radius:8px;padding:4px;min-width:120px;box-shadow:0 8px 24px rgba(0,0,0,.4)}
.s-menu button{display:block;width:100%;text-align:left;background:none;border:0;color:var(--tx);padding:7px 10px;border-radius:6px;cursor:pointer}
.s-menu button:hover{background:#12294a}

/* add / edit dialog */
.s-mbg{position:fixed;inset:0;background:rgba(2,8,18,.72);display:flex;align-items:center;justify-content:center;z-index:100;padding:16px}
.s-md{background:#0c1b30;border:1px solid var(--ln);border-radius:14px;width:560px;max-width:100%;max-height:92vh;overflow:auto;padding:20px}
.s-md h3{margin:0 0 14px;font-size:20px;font-weight:600}
.s-g2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.s-g2 label,.s-g2 .fld{display:flex;flex-direction:column;gap:5px;color:var(--mu);font-size:13px;margin:0}
.s-g2 .full{grid-column:1/-1}
.s-g2 input,.s-g2 select,.s-g2 textarea{background:#08162a;border:1px solid var(--ln);border-radius:8px;color:var(--tx);padding:8px 10px;font-size:14px;width:100%}
.s-g2 input:disabled{opacity:.4}
.s-g2 .chk{flex-direction:row;align-items:center;gap:8px;align-self:end;color:var(--tx);height:38px}
.s-g2 .chk input{width:auto}
.s-sw{display:flex;gap:10px;align-items:center}
.s-sw button{width:24px;height:24px;border-radius:50%;border:2px solid transparent;background:var(--dot);cursor:pointer;padding:0}
.s-sw button[data-c=""]{background:conic-gradient(#3b6fe0,#22a65a,#f59e0b,#ef4444,#8b5cf6,#3b6fe0)}
.s-sw button.on{border-color:#fff}
.s-err{color:#fca5a5;min-height:20px;margin-top:8px;font-size:13px}
.s-act{display:flex;gap:10px;align-items:center;margin-top:8px}
.s-act .grow{flex:1}
.s-ghost{background:#0a1a2e;border:1px solid var(--ln);color:var(--tx);border-radius:9px;padding:9px 16px;cursor:pointer}
.s-del{background:none;border:1px solid #7a2434;color:#fca5a5;border-radius:9px;padding:9px 16px;cursor:pointer}
.s-del:disabled{opacity:.5;cursor:default}

.s-strip{display:none}

/* day agenda (phone) */
.ag{display:flex;flex-direction:column;gap:10px}
.ag-item{display:flex;gap:14px;background:linear-gradient(100deg,var(--bg2) -60%,#0a1a2e 60%);border:1px solid var(--ln);border-left:4px solid var(--bd);border-radius:14px;padding:12px 14px;cursor:pointer}
.ag-t{flex:0 0 68px;font-size:13px}
.ag-t b{display:block;font-size:14px;font-weight:600;color:#eaf1ff}
.ag-t span{color:var(--mu)}
.ag-b{flex:1;min-width:0;overflow-wrap:anywhere}
.ag-b strong{display:block;font-size:15px;font-weight:600;margin-bottom:3px}
.ag-b span{display:block;color:#a9b9d0;font-size:13px}
.ag-b span i{width:14px;margin-right:4px;text-align:center}
.ag-b span.s-st{display:inline-block;margin-top:8px;font-size:12px;padding:1px 10px;color:inherit}
.ag-b span.st-approved{color:#8ee6ad}.ag-b span.st-pending{color:#f5c451}.ag-b span.st-completed{color:#8cc4f0}
.ag-empty{text-align:center;padding:36px 16px;color:var(--mu);border:1px dashed var(--ln);border-radius:16px}
.ag-empty i{font-size:34px;color:#2b4468;margin-bottom:10px}
.ag-empty p{margin:0}

@media(max-width:1100px){.s-cols{grid-template-columns:minmax(0,1fr)}}

/* ===== phones ===== */
@media(max-width:767px){
  .s-wrap{background:transparent;border:0;border-radius:0;padding:8px 14px calc(96px + env(safe-area-inset-bottom))}
  .s-head{flex-wrap:wrap;gap:8px;padding:6px 0 12px}
  .s-hic,.s-head p{display:none}
  .s-head h1{font-size:22px}
  .s-hr{display:contents}
  .s-pill{margin-left:auto;font-size:11px;padding:4px 9px}
  .s-ib{font-size:16px;width:40px;height:40px;padding:0;background:#0a1a2e;border:1px solid var(--ln);border-radius:12px}
  .s-q{order:10;flex:1 0 100%;width:100%;font-size:16px;padding:10px 12px}
  #btnAdd{position:fixed;right:16px;bottom:calc(18px + env(safe-area-inset-bottom));z-index:50;width:58px;height:58px;padding:0;border-radius:50%;justify-content:center;gap:0;font-size:0;box-shadow:0 10px 24px rgba(0,0,0,.55),0 0 0 5px rgba(14,165,233,.18)}
  #btnAdd i{font-size:22px}

  .s-cols{grid-template-columns:minmax(0,1fr);gap:0}
  .s-cols>*{min-width:0}

  /* week strip */
  .s-strip{display:block;margin-bottom:12px}
  .st-top{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .st-month{background:none;border:0;color:var(--tx);font-size:18px;font-weight:600;padding:0;margin-right:auto}
  .st-month i{font-size:11px;margin-left:6px}
  .st-today{background:#0a1a2e;border:1px solid var(--ln);color:var(--tx);border-radius:999px;padding:6px 14px;font-size:13px}
  .st-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}
  .st-d{background:#0a1a2e;border:1px solid var(--ln);border-radius:14px;padding:8px 0 7px;color:#dbe6f5;display:flex;flex-direction:column;align-items:center;gap:2px;min-width:0}
  .st-d small{font-size:11px;color:var(--mu)}
  .st-d b{font-size:17px;font-weight:600}
  .st-d .dot{width:5px;height:5px;border-radius:50%;background:transparent;display:block}
  .st-d.has .dot{background:#0ea5e9}
  .st-d.t{border-color:#0ea5e9}
  .st-d.a{background:#0ea5e9;border-color:#0ea5e9;color:#fff}
  .st-d.a small{color:#e0f2fe}
  .st-d.a.has .dot{background:#fff}
  .s-mini{display:none;margin-bottom:12px}
  #asideCol.mopen .s-mini{display:block}

  /* calendar filters become chips */
  .s-cals{padding:0;background:none;border:0;margin-bottom:12px}
  .s-cals h3,.s-addcal{display:none}
  #calList{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;margin:0 -14px;padding:2px 14px}
  #calList::-webkit-scrollbar{display:none}
  .s-cr{flex:0 0 auto;height:36px;gap:8px;padding:0 14px 0 12px;border:1.5px solid var(--ln);border-radius:999px;font-size:13px;white-space:nowrap;background:#0a1a2e}
  .s-cr b{width:10px;height:10px;border-radius:50%;border:2px solid var(--c);font-size:0}
  .s-cr input:checked+b{background:var(--c)}
  .s-cr input:checked+b::after{content:none}
  .s-cr:has(input:checked){border-color:var(--c)}
  .s-cr:has(input:not(:checked)){opacity:.5}

  /* agenda header */
  .s-mc{padding:0;background:none;border:0}
  .s-bar{padding:0 0 12px;gap:8px;flex-wrap:nowrap}
  .s-bar>label,#selView,.s-bar .s-pair,.s-tl i{display:none}
  .s-tl{font-size:16px;padding:0;background:none;white-space:nowrap}
  .s-bar .s-sp{flex:1}
  #selTech{min-width:0;flex:0 1 170px;font-size:14px;padding:8px 10px}
  .s-leg{display:none}

  /* upcoming: table rows become cards */
  .s-up{padding:0;background:none;border:0;margin-top:22px}
  .s-tw{border:0;overflow:visible}
  .s-tbl{min-width:0;display:block}
  .s-tbl thead{display:none}
  .s-tbl tbody{display:block}
  .s-tbl tr{display:grid;grid-template-columns:auto auto 1fr auto;gap:8px 8px;align-items:center;background:#0a1a2e;border:1px solid var(--ln);border-radius:14px;padding:12px 8px 12px 14px;margin-bottom:8px}
  .s-tbl td{display:block;border:0;padding:0;height:auto;white-space:normal;overflow-wrap:anywhere}
  .s-tbl td:nth-child(1){grid-column:1/4;grid-row:1;font-weight:600;color:#eaf1ff}
  .s-tbl td:nth-child(6){grid-column:4;grid-row:1;width:auto}
  .s-tbl td:nth-child(2){grid-column:1;grid-row:2}
  .s-tbl td:nth-child(5){grid-column:2;grid-row:2}
  .s-tbl td:nth-child(3){grid-column:1/4;grid-row:3;color:#a9b9d0}
  .s-tbl td:nth-child(4){grid-column:1/4;grid-row:4;color:#a9b9d0}
  .s-tbl td:nth-child(4)::before{content:"\f007";font:400 12px "Font Awesome 6 Free";margin-right:8px;color:#b6c4d8}
  .s-tbl td.s-empty{grid-column:1/-1;grid-row:auto;height:auto!important;padding:24px 8px}
  .s-tbl td.s-empty::before{content:none}

  /* add / edit becomes a bottom sheet */
  .s-mbg{align-items:flex-end;padding:0}
  .s-md{width:100%;max-width:100%;border-radius:20px 20px 0 0;border-bottom:0;max-height:94vh;padding:18px 16px calc(16px + env(safe-area-inset-bottom))}
  .s-g2{gap:10px}
  .s-g2 .mf{grid-column:1/-1}
  .s-g2 input,.s-g2 select,.s-g2 textarea{font-size:16px;padding:10px 12px}
}
</style>

    <div class="s-wrap">
        <header class="s-head">
            <i class="fa-regular fa-calendar-days s-hic"></i>
            <div><h1>Schedule</h1><p>View and manage your field schedule</p></div>
            <div class="s-hr">
                <span class="s-pill" id="pill" hidden><i></i><em></em></span>
                <input class="s-q" id="q" placeholder="Search schedules" hidden>
                <button class="s-ib" id="btnSearch" title="Search"><i class="fas fa-magnifying-glass"></i></button>
                <button class="s-add" id="btnAdd"><i class="fas fa-plus"></i> Add Schedule</button>
            </div>
        </header>

        <div class="s-cols">
            <aside id="asideCol">
                <div class="s-strip" id="strip"></div>
                <section class="s-card s-mini">
                    <div class="s-mnav">
                        <div class="s-pair"><button id="mPrev" aria-label="Previous month"><i class="fas fa-chevron-left"></i></button><button id="mNext" aria-label="Next month"><i class="fas fa-chevron-right"></i></button></div>
                        <button class="s-lbl" id="mLabel"></button>
                    </div>
                    <div class="s-mg" id="miniGrid"></div>
                </section>
                <section class="s-card s-cals">
                    <h3>Calendars</h3>
                    <div id="calList"></div>
                    <button class="s-addcal" id="addCal"><i class="fas fa-plus"></i> Add Calendar</button>
                </section>
            </aside>

            <section>
                <div class="s-card s-mc">
                    <div class="s-bar">
                        <div class="s-pair"><button id="tPrev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button><button id="tNext" aria-label="Next"><i class="fas fa-chevron-right"></i></button></div>
                        <button class="s-tl" id="tLabel"></button>
                        <span class="s-sp"></span>
                        <label for="selView">View:</label>
                        <select class="s-sel" id="selView"><option value="week">Week</option><option value="day">Day</option><option value="month">Month</option></select>
                        <label for="selTech">Filter:</label>
                        <select class="s-sel" id="selTech"></select>
                    </div>
                    <div id="cal"></div>
                    <div class="s-leg" id="legend"></div>
                </div>

                <div class="s-card s-up">
                    <div class="s-uph"><h2>Upcoming Schedules</h2><button class="s-out" id="btnExport"><i class="fas fa-download"></i> Export</button></div>
                    <div class="s-tw">
                        <table class="s-tbl">
                            <thead><tr><th>Date &amp; Time</th><th>Type</th><th>Location</th><th>Farmer / Contact</th><th>Status</th><th class="r">Actions</th></tr></thead>
                            <tbody id="upBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>

<div class="s-menu" id="menu" hidden><button data-act="edit"><i class="fas fa-pen me-2"></i>Edit</button><button data-act="del"><i class="fas fa-trash me-2"></i>Delete</button></div>


<div class="s-mbg" id="modal" hidden>
    <form class="s-md" id="form" autocomplete="off" novalidate>
        <h3 id="mTitle">Add Schedule</h3>
        <div class="s-g2">
            <label class="full">Title<input name="title" maxlength="150" placeholder="e.g. Field Inspection"></label>
            <label>Type<select name="type"><option value="inspection">Inspection</option><option value="treatment">Treatment</option><option value="maintenance">Maintenance</option><option value="followup">Follow-up</option><option value="other">Other</option></select></label>
            <label>Calendar<select name="calendar"><option value="my">My Schedule</option><option value="team">Team Schedule</option></select></label>
            <label>Date<input type="date" name="date"></label>
            <label class="chk"><input type="checkbox" name="all_day"> All-day</label>
            <label>Start<input type="time" name="start"></label>
            <label>End<input type="time" name="end"></label>
            <label class="mf">Location<input name="location" maxlength="150" placeholder="e.g. North Field 3"></label>
            <label class="mf">Farmer / Contact<input name="technician" maxlength="120" list="techList"></label>
            <datalist id="techList"></datalist>
            <label>Status<select name="status"><option value="approved">Approved</option><option value="pending">Pending Review</option><option value="completed">Completed</option></select></label>
            <div class="fld full">Color
                <div class="s-sw" id="sw">
                    <button type="button" data-c="" title="Automatic (by type)"></button>
                    <button type="button" data-c="blue" class="c-blue"></button><button type="button" data-c="green" class="c-green"></button><button type="button" data-c="amber" class="c-amber"></button>
                    <button type="button" data-c="purple" class="c-purple"></button><button type="button" data-c="red" class="c-red"></button><button type="button" data-c="teal" class="c-teal"></button>
                </div>
                <input type="hidden" name="color">
            </div>
            <label class="full">Notes<textarea name="notes" rows="2" maxlength="2000"></textarea></label>
        </div>
        <div class="s-err" id="fErr"></div>
        <div class="s-act">
            <button type="button" class="s-del" id="btnDel">Delete</button>
            <span class="grow"></span>
            <button type="button" class="s-ghost" id="btnCancel">Cancel</button>
            <button type="submit" class="s-add" id="btnSave">Save</button>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>window.SCH = @json($cfg);</script>
@verbatim
<script>
(() => {
'use strict';
const S = window.SCH;
const $ = (s) => document.querySelector(s);

/* ---------- helpers ---------- */
const pad = (n) => String(n).padStart(2, '0');
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
const pd = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
const addD = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const wkStart = (d) => addD(d, -d.getDay());
const mins = (s) => +s.slice(11, 13) * 60 + +s.slice(14, 16);
const day = (s) => s.slice(0, 10);
const hh = (h) => ((h + 11) % 12) + 1;
const fT = (m) => hh(Math.floor(m / 60)) + ':' + pad(m % 60) + ' ' + (m % 1440 < 720 ? 'AM' : 'PM');
const fH = (h) => hh(h) + ' ' + (h % 24 < 12 ? 'AM' : 'PM');
const fD = (d) => d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
const rng = (e) => e.all_day ? 'All day' : fT(mins(e.start_at)) + ' \u2013 ' + fT(mins(e.end_at));
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }));
const MON = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const urlFor = (tpl, id) => tpl.replace('__UUID__', id);

const TYPES = { inspection: ['Inspection', 'blue'], treatment: ['Treatment', 'green'], maintenance: ['Maintenance', 'red'], followup: ['Follow-up', 'teal'], other: ['Other', 'gray'] };
const STATUS = { approved: 'Approved', pending: 'Pending Review', completed: 'Completed' };
const CALS = [['my', 'My Schedule', '#4f46e5'], ['team', 'Team Schedule', '#8b5cf6'], ['inspection', 'Inspections', '#3b6fe0'], ['treatment', 'Treatments', '#22a65a'], ['maintenance', 'Maintenance', '#ef4444'], ['followup', 'Follow-ups', '#14b8a6'], ['other', 'Other', '#94a3b8']];
const tp = (e) => TYPES[e.type] || TYPES.other;
const colorOf = (e) => e.color || tp(e)[1];

const mq = window.matchMedia('(max-width:767px)');
const isM = () => mq.matches;
/* No dirty/synced flags here — every change is written straight to the
   server (see saveEv/removeEv below), so st.events always mirrors what
   the last successful request returned. */
const st = { events: new Map(), view: 'week', anchor: new Date(), mini: new Date(), tech: '', q: '', cal: { my: 1, team: 1, inspection: 1, treatment: 1, maintenance: 1, followup: 1, other: 1 }, showOther: false, ready: false, loading: false, err: '' };

/* ---------- filtering ---------- */
const all = () => [...st.events.values()];
const vis = () => all().filter((e) => st.cal[e.calendar] !== 0 && st.cal[e.type] !== 0 && (!st.tech || e.technician === st.tech) && (!st.q || (e.title + ' ' + (e.location || '') + ' ' + (e.technician || '')).toLowerCase().includes(st.q)));

/* ---------- rendering ---------- */
function renderMini() {
  const m = st.mini, first = new Date(m.getFullYear(), m.getMonth(), 1), nd = new Date(m.getFullYear(), m.getMonth() + 1, 0).getDate();
  const s = wkStart(first), today = ymd(new Date()), sel = ymd(st.anchor), cells = Math.ceil((first.getDay() + nd) / 7) * 7;
  $('#mLabel').innerHTML = MON[m.getMonth()] + ' ' + m.getFullYear() + ' <i class="fas fa-chevron-down"></i>';
  let h = 'Su Mo Tu We Th Fr Sa'.split(' ').map((d) => '<div class="dh">' + d + '</div>').join('');
  for (let i = 0; i < cells; i++) {
    const d = addD(s, i), k = ymd(d);
    h += '<div class="d' + (d.getMonth() !== m.getMonth() ? ' o' : '') + (k === today ? ' t' : '') + (k === sel && k !== today ? ' a' : '') + '" data-d="' + k + '"><span>' + d.getDate() + '</span></div>';
  }
  $('#miniGrid').innerHTML = h;
}

function renderCals() {
  $('#calList').innerHTML = CALS.filter((c) => c[0] !== 'other' || st.showOther).map((c) =>
    '<label class="s-cr" style="--c:' + c[2] + '"><input type="checkbox" data-cal="' + c[0] + '"' + (st.cal[c[0]] ? ' checked' : '') + '><b></b>' + c[1] + '</label>').join('');
  $('#addCal').hidden = st.showOther;
}

const evHtml = (e, style) => '<div class="s-ev c-' + colorOf(e) + '" data-id="' + e.uuid + '" style="' + style + '"><b>' + esc(e.title) + '</b>' +
  (e.location ? '<span>' + esc(e.location) + '</span>' : '') + (e.all_day ? '' : '<span>' + rng(e) + '</span>') + '</div>';

function layout(list) {
  const s = list.map((e) => ({ e, a: mins(e.start_at), b: Math.max(mins(e.end_at), mins(e.start_at) + 30) })).sort((x, y) => x.a - y.a || y.b - x.b);
  const out = []; let cluster = [], end = -1;
  const flush = () => {
    const cols = [];
    cluster.forEach((it) => { let c = cols.findIndex((t) => t <= it.a); if (c < 0) { c = cols.length; cols.push(0); } cols[c] = it.b; it.col = c; });
    cluster.forEach((it) => { it.cols = cols.length; }); out.push(...cluster); cluster = [];
  };
  s.forEach((it) => { if (cluster.length && it.a >= end) { flush(); end = -1; } cluster.push(it); end = Math.max(end, it.b); });
  if (cluster.length) flush();
  return out;
}

function weekHtml(days) {
  const n = days.length, today = ymd(new Date()), map = {}, H = 33;
  days.forEach((d) => { map[ymd(d)] = []; });
  vis().forEach((e) => { const k = day(e.start_at); if (map[k]) map[k].push(e); });
  let h0 = 6, h1 = 18;
  Object.values(map).flat().forEach((e) => { if (e.all_day) return; h0 = Math.min(h0, Math.floor(mins(e.start_at) / 60)); h1 = Math.max(h1, Math.ceil(mins(e.end_at) / 60)); });
  h1 = Math.min(24, h1);
  let hd = '<div></div>', ad = '<div class="lb">All-day</div>', tc = '', cols = '';
  for (let h = h0; h < h1; h++) tc += '<div>' + fH(h) + '</div>';
  days.forEach((d) => {
    const k = ymd(d), list = map[k];
    hd += '<div>' + d.toLocaleDateString('en-US', { weekday: 'short' }) + ' ' + (k === today ? '<span class="tn">' + d.getDate() + '</span>' : d.getDate()) + '</div>';
    ad += '<div>' + list.filter((e) => e.all_day).map((e) => evHtml(e, '')).join('') + '</div>';
    cols += '<div class="s-dc" style="height:' + ((h1 - h0) * H) + 'px">' + layout(list.filter((e) => !e.all_day)).map((o) => {
      const w = 100 / o.cols, top = (o.a - h0 * 60) / 60 * H, ht = Math.max((o.b - o.a) / 60 * H - 2, 20);
      return evHtml(o.e, 'top:' + top + 'px;height:' + ht + 'px;left:calc(' + (w * o.col) + '% + 2px);width:calc(' + w + '% - 4px)');
    }).join('') + '</div>';
  });
  return '<div class="s-gw"><div class="s-gin" style="--n:' + n + '"><div class="s-row s-hd">' + hd + '</div><div class="s-row s-ad">' + ad +
    '</div><div class="s-bd"><div class="s-row"><div class="s-tc">' + tc + '</div>' + cols + '</div></div></div></div>';
}

function monthHtml() {
  const a = st.anchor, first = new Date(a.getFullYear(), a.getMonth(), 1), s = wkStart(first), nd = new Date(a.getFullYear(), a.getMonth() + 1, 0).getDate();
  const rows = Math.ceil((first.getDay() + nd) / 7), today = ymd(new Date()), list = vis();
  let h = 'Sun Mon Tue Wed Thu Fri Sat'.split(' ').map((d) => '<div class="mh">' + d + '</div>').join('');
  for (let i = 0; i < rows * 7; i++) {
    const d = addD(s, i), k = ymd(d), ev = list.filter((e) => day(e.start_at) === k).sort((x, y) => x.start_at.localeCompare(y.start_at));
    h += '<div class="mc' + (d.getMonth() !== a.getMonth() ? ' o' : '') + '"><a class="mn' + (k === today ? ' tn' : '') + '" data-day="' + k + '">' + d.getDate() + '</a>' +
      ev.slice(0, 3).map((e) => '<div class="s-ev c-' + colorOf(e) + '" data-id="' + e.uuid + '"><b>' + esc(e.title) + '</b></div>').join('') +
      (ev.length > 3 ? '<small>+' + (ev.length - 3) + ' more</small>' : '') + '</div>';
  }
  return '<div class="s-gw"><div class="s-month">' + h + '</div></div>';
}

function renderMain() {
  const a = st.anchor;
  if (isM()) {
    $('#tLabel').innerHTML = a.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
    $('#cal').innerHTML = agendaHtml();
    return;
  }
  $('#tLabel').innerHTML = (st.view === 'day' ? a.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : MON[a.getMonth()] + ' ' + a.getFullYear()) + ' <i class="fas fa-chevron-down"></i>';
  if (st.view === 'month') { $('#cal').innerHTML = monthHtml(); return; }
  const days = st.view === 'day' ? [a] : Array.from({ length: 7 }, (_, i) => addD(wkStart(a), i));
  $('#cal').innerHTML = weekHtml(days);
}

function agendaHtml() {
  const k = ymd(st.anchor);
  const list = vis().filter((e) => day(e.start_at) === k).sort((a, b) => (b.all_day - a.all_day) || a.start_at.localeCompare(b.start_at));
  if (!list.length) return '<div class="ag-empty"><i class="fa-regular fa-calendar-check"></i><p>Nothing scheduled for this day.</p></div>';
  return '<div class="ag">' + list.map((e) => '<div class="ag-item c-' + colorOf(e) + '" data-id="' + e.uuid + '"><div class="ag-t">' +
    (e.all_day ? '<b>All day</b>' : '<b>' + fT(mins(e.start_at)) + '</b><span>' + fT(mins(e.end_at)) + '</span>') + '</div><div class="ag-b"><strong>' + esc(e.title) + '</strong>' +
    (e.location ? '<span><i class="fa-solid fa-location-dot"></i>' + esc(e.location) + '</span>' : '') +
    (e.technician ? '<span><i class="fa-regular fa-user"></i>' + esc(e.technician) + '</span>' : '') +
    '<span class="s-st st-' + esc(e.status) + '">' + esc(STATUS[e.status] || e.status) + '</span></div></div>').join('') + '</div>';
}

function renderStrip() {
  const s = wkStart(st.anchor), today = ymd(new Date()), sel = ymd(st.anchor), cnt = {};
  vis().forEach((e) => { const k = day(e.start_at); cnt[k] = (cnt[k] || 0) + 1; });
  let h = '<div class="st-top"><button class="st-month" data-strip="month">' + MON[st.anchor.getMonth()] + ' ' + st.anchor.getFullYear() + ' <i class="fas fa-chevron-down"></i></button>' +
    '<button class="st-today" data-strip="today">Today</button><div class="s-pair"><button data-strip="prev" aria-label="Previous week"><i class="fas fa-chevron-left"></i></button>' +
    '<button data-strip="next" aria-label="Next week"><i class="fas fa-chevron-right"></i></button></div></div><div class="st-days">';
  for (let i = 0; i < 7; i++) {
    const d = addD(s, i), k = ymd(d);
    h += '<button class="st-d' + (k === sel ? ' a' : '') + (k === today ? ' t' : '') + (cnt[k] ? ' has' : '') + '" data-d="' + k + '"><small>' +
      d.toLocaleDateString('en-US', { weekday: 'short' }).slice(0, 3) + '</small><b>' + d.getDate() + '</b><i class="dot"></i></button>';
  }
  $('#strip').innerHTML = h + '</div>';
}

function renderUp() {
  const t0 = ymd(new Date());
  const list = vis().filter((e) => day(e.start_at) >= t0).sort((a, b) => a.start_at.localeCompare(b.start_at)).slice(0, 8);
  if (!list.length) {
    $('#upBody').innerHTML = !st.ready ? '' : '<tr><td colspan="6" class="s-empty">' + (all().length ? 'No upcoming schedules match your filters.' : 'No schedules yet. Use Add Schedule to create one.') + '</td></tr>';
    return;
  }
  $('#upBody').innerHTML = list.map((e) => '<tr><td><i class="fa-regular fa-calendar"></i>' + fD(pd(day(e.start_at))) + ' \u2022 ' + rng(e) + '</td>' +
    '<td><span class="s-bg c-' + tp(e)[1] + '">' + tp(e)[0] + '</span></td>' +
    '<td><i class="fa-solid fa-location-dot"></i>' + esc(e.location || '\u2014') + '</td><td>' + esc(e.technician || '\u2014') + '</td>' +
    '<td><span class="s-st st-' + esc(e.status) + '">' + esc(STATUS[e.status] || e.status) + '</span></td>' +
    '<td class="r"><button class="s-dots" data-id="' + e.uuid + '" aria-label="Actions"><i class="fas fa-ellipsis-vertical"></i></button></td></tr>').join('');
}

function renderTech() {
  const t = [...new Set(all().map((e) => e.technician).filter(Boolean))].sort();
  if (st.tech && !t.includes(st.tech)) st.tech = '';
  $('#selTech').innerHTML = '<option value="">All</option>' + t.map((x) => '<option' + (x === st.tech ? ' selected' : '') + '>' + esc(x) + '</option>').join('');
  $('#techList').innerHTML = t.map((x) => '<option value="' + esc(x) + '">').join('');
}

/* Small status pill — only shown while loading or on a request error.
   There is no "Synced"/"Offline" state here: every write already went
   through by the time this runs. */
function renderPill() {
  const p = $('#pill');
  if (st.loading) { p.hidden = false; p.className = 's-pill'; p.querySelector('em').textContent = 'Loading\u2026'; }
  else if (st.err) { p.hidden = false; p.className = 's-pill off'; p.querySelector('em').textContent = st.err; }
  else { p.hidden = true; }
}

function render() {
  renderTech(); renderMini(); renderStrip(); renderCals(); renderMain(); renderUp(); renderPill();
  $('#legend').innerHTML = Object.values(TYPES).map((t) => '<span><i class="c-' + t[1] + '"></i>' + t[0] + '</span>').join('');
}

/* ---------- server calls (always online — no local queue) ---------- */
async function apiFetch(url, opts) {
  opts = opts || {};
  opts.credentials = 'same-origin';
  opts.headers = Object.assign({ Accept: 'application/json', 'X-CSRF-TOKEN': S.csrf, 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {});
  let r = await fetch(url, opts);
  if (r.status === 419) { // CSRF token expired — refresh it once via a GET, then retry
    const fresh = await fetch(S.urls.events, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (fresh.ok) { S.csrf = (await fresh.json()).csrf; opts.headers['X-CSRF-TOKEN'] = S.csrf; r = await fetch(url, opts); }
  }
  return r;
}

function applyPayload(j) {
  S.csrf = j.csrf || S.csrf;
  st.events = new Map(j.events.map((e) => [e.uuid, e]));
}

async function loadEvents() {
  st.loading = true; st.err = ''; render();
  try {
    const r = await apiFetch(S.urls.events);
    if (!r.ok) throw new Error('http ' + r.status);
    applyPayload(await r.json());
  } catch (e) {
    st.err = 'Could not load your schedule.';
  } finally {
    st.loading = false; st.ready = true; render();
  }
}

const pub = (r) => ({ title: r.title, type: r.type, calendar: r.calendar, location: r.location, technician: r.technician, status: r.status,
  start_at: r.start_at, end_at: r.end_at, all_day: r.all_day ? 1 : 0, color: r.color || null, notes: r.notes || null });

async function saveEv(rec, isNew) {
  const url = isNew ? S.urls.store : urlFor(S.urls.update, rec.uuid);
  const r = await apiFetch(url, { method: isNew ? 'POST' : 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(pub(rec)) });
  if (!r.ok) throw new Error('http ' + r.status);
  applyPayload(await r.json());
  render();
}

async function removeEv(id) {
  if (!confirm('Delete this schedule?')) return;
  try {
    const r = await apiFetch(urlFor(S.urls.destroy, id), { method: 'DELETE' });
    if (!r.ok) throw new Error('http ' + r.status);
    applyPayload(await r.json());
    render();
  } catch (e) { alert('Could not delete this schedule. Please try again.'); }
}

/* ---------- add / edit dialog ---------- */
const f = $('#form'), F = (n) => f.elements[n];
const setColor = (c) => { F('color').value = c; document.querySelectorAll('#sw button').forEach((b) => b.classList.toggle('on', b.dataset.c === c)); };
const toggleAllDay = () => { F('start').disabled = F('end').disabled = F('all_day').checked; };
const closeForm = () => { $('#modal').hidden = true; };

function openForm(id) {
  const e = id ? st.events.get(id) : null, b = e || {};
  f.reset(); $('#fErr').textContent = ''; f.dataset.id = id || '';
  $('#mTitle').textContent = e ? 'Edit Schedule' : 'Add Schedule';
  $('#btnDel').hidden = !e;
  F('title').value = b.title || '';
  F('type').value = b.type || 'inspection';
  F('calendar').value = b.calendar || 'my';
  F('date').value = b.start_at ? day(b.start_at) : ymd(st.anchor);
  F('all_day').checked = !!b.all_day;
  F('start').value = b.start_at && !b.all_day ? b.start_at.slice(11, 16) : '08:00';
  F('end').value = b.end_at && !b.all_day ? b.end_at.slice(11, 16) : '09:30';
  F('location').value = b.location || '';
  F('technician').value = b.technician || '';
  F('status').value = b.status || 'approved';
  F('notes').value = b.notes || '';
  setColor(b.color || ''); toggleAllDay();
  $('#modal').hidden = false; F('title').focus();
}

f.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const err = (m) => { $('#fErr').textContent = m; };
  const id = f.dataset.id, ad = F('all_day').checked, date = F('date').value, s = F('start').value, en = F('end').value, title = F('title').value.trim();
  if (!title) return err('Enter a title for this schedule.');
  if (!date) return err('Pick a date.');
  if (!ad && !(s && en && en > s)) return err('The end time must be after the start time.');
  const rec = { uuid: id || uuid(), title, type: F('type').value, calendar: F('calendar').value, location: F('location').value.trim(), technician: F('technician').value.trim(),
    status: F('status').value, color: F('color').value || null, notes: F('notes').value.trim(), all_day: ad ? 1 : 0,
    start_at: date + ' ' + (ad ? '00:00' : s) + ':00', end_at: date + ' ' + (ad ? '23:59' : en) + ':00' };
  const btn = $('#btnSave'); btn.disabled = true;
  try { await saveEv(rec, !id); closeForm(); }
  catch (e) { err('Could not save. Please try again.'); }
  finally { btn.disabled = false; }
});

/* ---------- export ---------- */
function exportCsv() {
  const q = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
  const rows = all().sort((a, b) => a.start_at.localeCompare(b.start_at));
  const csv = ['Date', 'Start', 'End', 'Title', 'Type', 'Location', 'Farmer/Contact', 'Status'].join(',') + '\n' +
    rows.map((e) => [day(e.start_at), e.all_day ? 'All day' : e.start_at.slice(11, 16), e.all_day ? '' : e.end_at.slice(11, 16), e.title, tp(e)[0], e.location, e.technician, STATUS[e.status] || e.status].map(q).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type: 'text/csv' }));
  a.download = 'schedule-' + ymd(new Date()) + '.csv'; a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ---------- events ---------- */
const step = (d) => {
  const a = st.anchor;
  const v = isM() ? 'day' : st.view;
  st.anchor = v === 'month' ? new Date(a.getFullYear(), a.getMonth() + d, 1) : addD(a, d * (v === 'week' ? 7 : 1));
  st.mini = new Date(st.anchor); render();
};
const goToday = () => { st.anchor = new Date(); st.mini = new Date(); render(); };
const menu = $('#menu'), closeMenu = () => { menu.hidden = true; };

document.addEventListener('click', (e) => {
  const t = e.target;
  const mi = t.closest('#menu button');
  if (mi) { const id = menu.dataset.id; closeMenu(); if (mi.dataset.act === 'edit') openForm(id); else removeEv(id); return; }
  const dots = t.closest('.s-dots');
  if (dots) { const r = dots.getBoundingClientRect(); menu.dataset.id = dots.dataset.id; menu.hidden = false;
    menu.style.left = (window.scrollX + r.right - menu.offsetWidth) + 'px'; menu.style.top = (window.scrollY + r.bottom + 4) + 'px'; return; }
  closeMenu();
  const sa = t.closest('[data-strip]');
  if (sa) {
    const k = sa.dataset.strip;
    if (k === 'prev' || k === 'next') { st.anchor = addD(st.anchor, k === 'prev' ? -7 : 7); st.mini = new Date(st.anchor); render(); }
    else if (k === 'today') goToday();
    else $('#asideCol').classList.toggle('mopen');
    return;
  }
  if (t.closest('[data-add]')) { openForm(); return; }
  const ev = t.closest('.s-ev[data-id], .ag-item[data-id]'); if (ev) { openForm(ev.dataset.id); return; }
  const dd = t.closest('[data-d]'); if (dd) { st.anchor = pd(dd.dataset.d); st.mini = new Date(st.anchor); $('#asideCol').classList.remove('mopen'); render(); return; }
  const md = t.closest('[data-day]'); if (md) { st.anchor = pd(md.dataset.day); st.view = 'day'; $('#selView').value = 'day'; render(); return; }
  const sw = t.closest('#sw button'); if (sw) { setColor(sw.dataset.c); return; }
  if (t === $('#modal')) closeForm();
});
document.addEventListener('change', (e) => {
  const c = e.target.dataset && e.target.dataset.cal;
  if (c) { st.cal[c] = e.target.checked ? 1 : 0; render(); }
});
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeForm(); closeMenu(); } });

$('#btnAdd').onclick = () => openForm();
$('#btnCancel').onclick = closeForm;
$('#btnDel').onclick = () => { const id = f.dataset.id; if (id) { closeForm(); removeEv(id); } };
$('#btnExport').onclick = exportCsv;
$('#btnSearch').onclick = () => { const q = $('#q'); q.hidden = !q.hidden; if (q.hidden) { q.value = ''; st.q = ''; render(); } else q.focus(); };
$('#q').oninput = (e) => { st.q = e.target.value.trim().toLowerCase(); renderMain(); renderUp(); };
$('#mPrev').onclick = () => { st.mini = new Date(st.mini.getFullYear(), st.mini.getMonth() - 1, 1); renderMini(); };
$('#mNext').onclick = () => { st.mini = new Date(st.mini.getFullYear(), st.mini.getMonth() + 1, 1); renderMini(); };
$('#mLabel').onclick = goToday;
$('#tPrev').onclick = () => step(-1);
$('#tNext').onclick = () => step(1);
$('#tLabel').onclick = goToday;
$('#selView').onchange = (e) => { st.view = e.target.value; render(); };
$('#selTech').onchange = (e) => { st.tech = e.target.value; render(); };
$('#addCal').onclick = () => { st.showOther = true; renderCals(); };
F('all_day').onchange = toggleAllDay;

// phones: swipe the day view left / right to change day; re-render when the screen size class changes
let tx = 0, ty = 0;
$('#cal').addEventListener('touchstart', (e) => { tx = e.touches[0].clientX; ty = e.touches[0].clientY; }, { passive: true });
$('#cal').addEventListener('touchend', (e) => {
  if (!isM()) return;
  const dx = e.changedTouches[0].clientX - tx, dy = e.changedTouches[0].clientY - ty;
  if (Math.abs(dx) > 60 && Math.abs(dy) < 40) step(dx < 0 ? 1 : -1);
}, { passive: true });
if (mq.addEventListener) mq.addEventListener('change', render); else mq.addListener(render);

// Always-online refresh: reload from the server when the tab regains focus
// or periodically. NOT an offline queue — there is nothing queued locally.
document.addEventListener('visibilitychange', () => { if (!document.hidden) loadEvents(); });
setInterval(loadEvents, 60000);

/* ---------- start ---------- */
(async () => {
  render();
  await loadEvents();
})();
})();
</script>
@endverbatim
@endsection