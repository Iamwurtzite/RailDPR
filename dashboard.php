<?php
session_start();
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

require_once 'db.php';

$today = date('Y-m-d');
$filter_date    = isset($_GET['date'])    ? $conn->real_escape_string($_GET['date'])    : $today;
$filter_line    = isset($_GET['line'])    ? $conn->real_escape_string($_GET['line'])    : '';
$filter_section = isset($_GET['section']) ? $conn->real_escape_string($_GET['section']) : '';
$filter_shift   = isset($_GET['shift'])   ? $conn->real_escape_string($_GET['shift'])   : '';

$where = "WHERE is_deleted = 0 AND report_date = '$filter_date'";
if ($filter_line)    $where .= " AND line_name = '$filter_line'";
if ($filter_section) $where .= " AND section = '$filter_section'";
if ($filter_shift)   $where .= " AND shift = '$filter_shift'";

// KPI
$kpi = $conn->query("SELECT
    COUNT(DISTINCT report_id) AS total_reports,
    COUNT(DISTINCT line_name) AS total_lines,
    SUM(total_qty)            AS total_production,
    SUM(scrap_qty)            AS total_scrap,
    SUM(repair_qty)           AS total_repair,
    SUM(CASE WHEN is_verified=1 THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN is_verified=0 THEN 1 ELSE 0 END) AS pending
FROM production_records $where")->fetch_assoc();

// Model-wise hourly
$model_data = $conn->query("SELECT
    model_name, line_name, section, shift, MAX(drafter_name) AS drafter_name, MAX(uph) AS uph,
    SUM(h1_qty) AS h1, SUM(h2_qty) AS h2, SUM(h3_qty) AS h3, SUM(h4_qty) AS h4,
    SUM(h5_qty) AS h5, SUM(h6_qty) AS h6, SUM(h7_qty) AS h7, SUM(h8_qty) AS h8,
    SUM(h9_qty) AS h9, SUM(h10_qty) AS h10, SUM(h11_qty) AS h11,
    SUM(total_qty) AS total, SUM(scrap_qty) AS scrap, SUM(repair_qty) AS repair,
    MAX(is_verified) AS is_verified
FROM production_records $where
GROUP BY model_name, line_name, section, shift
ORDER BY line_name, model_name");
$rows_model = [];
while($r = $model_data->fetch_assoc()) $rows_model[] = $r;

// Line-wise
$line_data = $conn->query("SELECT line_name, section,
    SUM(total_qty) AS total, SUM(scrap_qty) AS scrap, SUM(repair_qty) AS repair,
    COUNT(DISTINCT model_name) AS models
FROM production_records $where
GROUP BY line_name, section ORDER BY line_name");
$rows_line = [];
while($r = $line_data->fetch_assoc()) $rows_line[] = $r;

// Hourly grand total
$hourly = $conn->query("SELECT
    SUM(h1_qty) AS h1, SUM(h2_qty) AS h2, SUM(h3_qty) AS h3, SUM(h4_qty) AS h4,
    SUM(h5_qty) AS h5, SUM(h6_qty) AS h6, SUM(h7_qty) AS h7, SUM(h8_qty) AS h8,
    SUM(h9_qty) AS h9, SUM(h10_qty) AS h10, SUM(h11_qty) AS h11,
    SUM(total_qty) AS grand_total
FROM production_records $where")->fetch_assoc();

// Dropdowns
$lines_res    = $conn->query("SELECT DISTINCT line_name FROM lines_list ORDER BY line_name");
$sections_res = $conn->query("SELECT DISTINCT section FROM production_records WHERE section!='' ORDER BY section");

// Chart data
$chart_labels = []; $chart_totals = []; $chart_scrap = [];
foreach($rows_model as $r) {
    $chart_labels[] = $r['model_name'].' ('.$r['line_name'].')';
    $chart_totals[] = (int)$r['total'];
    $chart_scrap[]  = (int)$r['scrap'];
}
$hourly_values = [];
for($i=1;$i<=11;$i++) $hourly_values[] = (int)($hourly["h$i"] ?? 0);
$scrap_pct = ($kpi['total_production'] > 0) ? round(($kpi['total_scrap']/$kpi['total_production'])*100,1) : 0;
$line_labels = array_column($rows_line,'line_name');
$line_totals  = array_map('intval', array_column($rows_line,'total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Live Production Dashboard – ILJIN</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
/* ═══════════════════════════════════════════
   RESET & ROOT
═══════════════════════════════════════════ */
:root{
  --dark:#1a2332; --card:#fff; --accent:#f59e0b;
  --green:#10b981; --red:#ef4444; --blue:#3b82f6;
  --purple:#8b5cf6; --border:#e5e7eb;
  --text:#1f2937; --muted:#6b7280; --bg:#f1f5f9;
  --topbar-h:44px;
  --filter-h:48px;
  --footer-h:28px;
}
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;font-family:'Segoe UI',Arial,sans-serif;background:var(--bg);color:var(--text);font-size:12px;}

/* ═══════════════════════════════════════════
   TOPBAR
═══════════════════════════════════════════ */
.topbar{
  height:var(--topbar-h);
  background:var(--dark);color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  padding:0 16px;flex-shrink:0;
  box-shadow:0 2px 6px rgba(0,0,0,.35);
  position:relative;z-index:10;
}
.topbar-title{font-size:14px;font-weight:700;letter-spacing:.4px;}
.topbar-title span{color:var(--accent);}
.topbar-nav{display:flex;align-items:center;gap:6px;}
.topbar-nav a{color:#fff;text-decoration:none;padding:4px 10px;border-radius:5px;font-size:11px;background:rgba(255,255,255,.1);transition:background .2s;}
.topbar-nav a:hover{background:var(--accent);color:#000;}
.live-badge{display:inline-flex;align-items:center;gap:5px;background:var(--green);color:#fff;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;}
.pulse{width:7px;height:7px;background:#fff;border-radius:50%;animation:pulse 1.4s infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(.7)}}

/* ═══════════════════════════════════════════
   FILTER BAR
═══════════════════════════════════════════ */
.filter-bar{
  height:var(--filter-h);
  background:#fff;
  display:flex;align-items:center;gap:10px;
  padding:0 16px;flex-shrink:0;
  border-bottom:1px solid var(--border);
  box-shadow:0 1px 3px rgba(0,0,0,.06);
}
.filter-bar label{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;white-space:nowrap;}
.fg{display:flex;align-items:center;gap:5px;}
.filter-bar input,.filter-bar select{padding:4px 7px;border:1px solid var(--border);border-radius:5px;font-size:11px;height:28px;outline:none;}
.btn-f{background:var(--blue);color:#fff;border:none;padding:0 14px;border-radius:5px;font-weight:700;cursor:pointer;height:28px;font-size:11px;}
.btn-r{background:#e5e7eb;color:#374151;border:none;padding:0 10px;border-radius:5px;font-weight:600;cursor:pointer;height:28px;font-size:11px;text-decoration:none;display:inline-flex;align-items:center;}
#rt{font-size:10px;color:var(--muted);margin-left:auto;white-space:nowrap;}

/* ═══════════════════════════════════════════
   OUTER WRAPPER  (fills remaining height)
═══════════════════════════════════════════ */
.page-body{
  display:flex;flex-direction:column;
  height:calc(100vh - var(--topbar-h) - var(--filter-h) - var(--footer-h));
  padding:8px 12px;gap:7px;overflow:hidden;
}

/* ═══════════════════════════════════════════
   ROW 1 — KPI cards
═══════════════════════════════════════════ */
.kpi-row{
  display:grid;
  grid-template-columns:repeat(6,1fr);
  gap:7px;flex-shrink:0;
}
.kpi-card{
  background:var(--card);border-radius:8px;border:1px solid var(--border);
  padding:8px 10px;position:relative;overflow:hidden;
  display:flex;flex-direction:column;gap:2px;
}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:3px;height:100%;border-radius:8px 0 0 8px;}
.kpi-card.cl-green::before{background:var(--green);}
.kpi-card.cl-red::before  {background:var(--red);}
.kpi-card.cl-amber::before{background:var(--accent);}
.kpi-card.cl-blue::before {background:var(--blue);}
.kpi-card.cl-purple::before{background:var(--purple);}
.kpi-label{font-size:9px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;}
.kpi-value{font-size:22px;font-weight:800;line-height:1.1;}
.kpi-value.cl-green{color:var(--green);}
.kpi-value.cl-red  {color:var(--red);}
.kpi-value.cl-amber{color:var(--accent);}
.kpi-value.cl-blue {color:var(--blue);}
.kpi-value.cl-purple{color:var(--purple);}
.kpi-sub{font-size:9px;color:var(--muted);}
.kpi-icon{font-size:22px;position:absolute;right:8px;top:8px;opacity:.12;}

/* ═══════════════════════════════════════════
   ROW 2 — Hourly bars + Charts  (side by side)
═══════════════════════════════════════════ */
.mid-row{display:grid;grid-template-columns:1.6fr 1fr 1fr;gap:7px;flex:0 0 auto;height:170px;}
.panel{background:var(--card);border-radius:8px;border:1px solid var(--border);padding:8px 10px;display:flex;flex-direction:column;overflow:hidden;}
.panel-title{font-size:10px;font-weight:700;color:var(--text);margin-bottom:6px;display:flex;align-items:center;gap:5px;flex-shrink:0;}
.panel-title::before{content:'';width:3px;height:12px;border-radius:2px;background:var(--blue);display:inline-block;flex-shrink:0;}
.panel-title.g::before{background:var(--green);}
.panel-title.p::before{background:var(--purple);}

/* hourly bars */
.hourly-visual{display:grid;grid-template-columns:repeat(11,1fr);gap:4px;align-items:flex-end;flex:1;min-height:0;}
.hour-col{text-align:center;display:flex;flex-direction:column;align-items:center;height:100%;}
.hour-bar-wrap{flex:1;display:flex;align-items:flex-end;justify-content:center;width:100%;}
.hour-bar{width:80%;background:linear-gradient(to top,#3b82f6,#93c5fd);border-radius:3px 3px 0 0;min-height:3px;transition:height .5s ease;}
.hour-bar:hover{background:linear-gradient(to top,#1d4ed8,#60a5fa);}
.hour-val{font-size:9px;font-weight:700;color:var(--text);line-height:1.2;}
.hour-lbl{font-size:8px;color:var(--muted);}
.h-grand{font-size:10px;font-weight:800;color:var(--green);text-align:right;margin-top:3px;flex-shrink:0;}

/* chart canvas fills panel */
.panel canvas{flex:1;min-height:0;}

/* ═══════════════════════════════════════════
   ROW 3 — Model table + Line table  (bottom)
═══════════════════════════════════════════ */
.bot-row{display:grid;grid-template-columns:3fr 1.1fr;gap:7px;flex:1;min-height:0;}
.table-panel{background:var(--card);border-radius:8px;border:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden;}
.tp-head{padding:6px 10px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.tp-head h3{font-size:11px;font-weight:700;display:flex;align-items:center;gap:5px;}
.tp-head h3::before{content:'';width:3px;height:12px;border-radius:2px;background:var(--purple);display:inline-block;}
.tp-head h3.g::before{background:var(--green);}
.tp-sub{font-size:10px;color:var(--muted);}
.tbl-wrap{overflow:auto;flex:1;}
.tbl{width:100%;border-collapse:collapse;}
.tbl th{
  background:#f8fafc;padding:5px 6px;text-align:center;
  font-size:9px;font-weight:700;color:var(--muted);
  text-transform:uppercase;letter-spacing:.3px;
  border-bottom:2px solid var(--border);white-space:nowrap;
  position:sticky;top:0;z-index:2;
}
.tbl td{padding:5px 6px;text-align:center;border-bottom:1px solid #f1f5f9;font-size:11px;}
.tbl tbody tr:hover{background:#f8fafc;}
.hc{background:#eff6ff;font-weight:600;color:#1d4ed8;font-size:11px;}
.hc.z{color:#cbd5e1;background:transparent;font-weight:400;}
.tc{background:#f0fdf4;font-weight:800;color:var(--green);}
.sc{color:var(--red);font-weight:600;}
.tbl tfoot td{background:#1a2332;color:#fff;font-weight:700;font-size:11px;position:sticky;bottom:0;z-index:2;}
.badge-p{background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:2px 6px;border-radius:10px;font-size:9px;font-weight:700;}
.badge-a{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;padding:2px 6px;border-radius:10px;font-size:9px;font-weight:700;}
.mn{text-align:left;font-weight:600;}
.lb{display:inline-block;background:#ede9fe;color:#5b21b6;padding:1px 6px;border-radius:8px;font-size:9px;font-weight:600;}
.pwrap{background:#e5e7eb;border-radius:6px;height:6px;width:70px;display:inline-block;vertical-align:middle;margin-left:4px;}
.pfill{border-radius:6px;height:6px;}
.no-data{text-align:center;padding:20px;color:var(--muted);font-size:12px;}

/* ═══════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════ */
.footer{
  height:var(--footer-h);
  background:#fff;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:10px;color:var(--muted);flex-shrink:0;
}
.footer a{color:var(--accent);text-decoration:none;font-weight:600;}
</style>
</head>
<body>

<!-- ══ TOPBAR ══ -->
<div class="topbar">
  <div class="topbar-title">ILJIN <span>LIVE PRODUCTION</span> DASHBOARD</div>
  <div class="topbar-nav">
    <span class="live-badge"><span class="pulse"></span>LIVE</span>
    <a href="index.php">📝 DPR Form</a>
    <a href="history.php">📊 History</a>
    <a href="admin.php" style="background:#ef4444;">⚙️ Admin</a>
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>

<!-- ══ FILTER BAR ══ -->
<form method="GET" class="filter-bar">
  <div class="fg"><label>Date</label><input type="date" name="date" value="<?=htmlspecialchars($filter_date)?>"></div>
  <div class="fg"><label>Line</label>
    <select name="line">
      <option value="">All Lines</option>
      <?php $lines_res->data_seek(0); while($l=$lines_res->fetch_assoc()):
        $s=($filter_line==$l['line_name'])?'selected':''; ?>
        <option value="<?=htmlspecialchars($l['line_name'])?>" <?=$s?>><?=htmlspecialchars($l['line_name'])?></option>
      <?php endwhile;?>
    </select>
  </div>
  <div class="fg"><label>Section</label>
    <select name="section">
      <option value="">All Sections</option>
      <?php $sections_res->data_seek(0); while($s2=$sections_res->fetch_assoc()):
        $s=($filter_section==$s2['section'])?'selected':''; ?>
        <option value="<?=htmlspecialchars($s2['section'])?>" <?=$s?>><?=strtoupper($s2['section'])?></option>
      <?php endwhile;?>
    </select>
  </div>
  <div class="fg"><label>Shift</label>
    <select name="shift">
      <option value="">All Shifts</option>
      <option value="Day"   <?=$filter_shift=='Day'  ?'selected':''?>>Day</option>
      <option value="Night" <?=$filter_shift=='Night'?'selected':''?>>Night</option>
    </select>
  </div>
  <button type="submit" class="btn-f">🔍 Apply</button>
  <a href="dashboard.php" class="btn-r">↺ Reset</a>
  <span id="rt">Refresh: <b id="rtv"><?=date('H:i:s')?></b></span>
</form>

<!-- ══ PAGE BODY ══ -->
<div class="page-body">

  <!-- ROW 1 — KPI -->
  <div class="kpi-row">
    <div class="kpi-card cl-green">
      <div class="kpi-icon">🏭</div>
      <div class="kpi-label">Total Production</div>
      <div class="kpi-value cl-green"><?=number_format($kpi['total_production']??0)?></div>
      <div class="kpi-sub">Units today</div>
    </div>
    <div class="kpi-card cl-red">
      <div class="kpi-icon">⚠️</div>
      <div class="kpi-label">Total Scrap</div>
      <div class="kpi-value cl-red"><?=number_format($kpi['total_scrap']??0)?></div>
      <div class="kpi-sub">Rate: <?=$scrap_pct?>%</div>
    </div>
    <div class="kpi-card cl-amber">
      <div class="kpi-icon">🔧</div>
      <div class="kpi-label">Repair Qty</div>
      <div class="kpi-value cl-amber"><?=number_format($kpi['total_repair']??0)?></div>
      <div class="kpi-sub">Under repair</div>
    </div>
    <div class="kpi-card cl-blue">
      <div class="kpi-icon">📋</div>
      <div class="kpi-label">Reports</div>
      <div class="kpi-value cl-blue"><?=$kpi['total_reports']??0?></div>
      <div class="kpi-sub"><?=$kpi['total_lines']??0?> lines active</div>
    </div>
    <div class="kpi-card cl-green">
      <div class="kpi-icon">✅</div>
      <div class="kpi-label">Approved</div>
      <div class="kpi-value cl-green"><?=$kpi['approved']??0?></div>
      <div class="kpi-sub">Verified DPRs</div>
    </div>
    <div class="kpi-card cl-purple">
      <div class="kpi-icon">⏳</div>
      <div class="kpi-label">Pending</div>
      <div class="kpi-value cl-purple"><?=$kpi['pending']??0?></div>
      <div class="kpi-sub">Awaiting verify</div>
    </div>
  </div>

  <!-- ROW 2 — Hourly + Charts -->
  <div class="mid-row">

    <!-- Hourly bars -->
    <div class="panel">
      <div class="panel-title g">📊 Hourly Flow — <?=date('d M Y',strtotime($filter_date))?></div>
      <?php
      $max_h = max(array_merge($hourly_values,[1]));
      $slots_s = ['09-10','10-11','11-12','12-13','13-14','14-15','15-16','16-17','17-18','18-19','19-21'];
      ?>
      <div class="hourly-visual">
        <?php for($i=0;$i<11;$i++):
          $v=$hourly_values[$i];
          $pct=($max_h>0)?round(($v/$max_h)*100):0;
          $bh=max(3, $pct * 0.72);
        ?>
        <div class="hour-col">
          <div class="hour-bar-wrap">
            <div class="hour-bar" style="height:<?=$bh?>px;" title="H<?=$i+1?>: <?=$v?> units"></div>
          </div>
          <div class="hour-val"><?=$v>0?$v:'-'?></div>
          <div class="hour-lbl">H<?=$i+1?></div>
        </div>
        <?php endfor;?>
      </div>
      <div class="h-grand">Total: <?=number_format($hourly['grand_total']??0)?> units</div>
    </div>

    <!-- Model vs Scrap chart -->
    <div class="panel">
      <div class="panel-title">Model vs Scrap</div>
      <?php if(count($chart_labels)>0):?>
      <canvas id="modelChart"></canvas>
      <?php else:?><div class="no-data">No data</div><?php endif;?>
    </div>

    <!-- Line doughnut -->
    <div class="panel">
      <div class="panel-title p">Line Output</div>
      <?php if(count($line_labels)>0):?>
      <canvas id="lineChart"></canvas>
      <?php else:?><div class="no-data">No data</div><?php endif;?>
    </div>

  </div><!-- /mid-row -->

  <!-- ROW 3 — Tables -->
  <div class="bot-row">

    <!-- Model detail table -->
    <div class="table-panel">
      <div class="tp-head">
        <h3>Model-wise Hourly Detail</h3>
        <span class="tp-sub"><?=date('d-M-Y',strtotime($filter_date))?> | <?=count($rows_model)?> models</span>
      </div>
      <?php if(count($rows_model)>0):
        $grand=['total'=>0,'scrap'=>0,'repair'=>0];
        $grand_h=array_fill(1,11,0);
        foreach($rows_model as $r){for($h=1;$h<=11;$h++)$grand_h[$h]+=(int)$r["h$h"];$grand['total']+=(int)$r['total'];$grand['scrap']+=(int)$r['scrap'];$grand['repair']+=(int)$r['repair'];}
      ?>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr>
            <th>#</th><th style="text-align:left">Model</th><th>Line</th><th>Leader</th><th>Shift</th><th>UPH</th>
            <?php for($h=1;$h<=11;$h++) echo "<th>H$h</th>";?>
            <th>TOTAL</th><th>SCRAP</th><th>REPAIR</th><th>STATUS</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows_model as $i=>$r):?>
          <tr>
            <td><?=$i+1?></td>
            <td class="mn"><?=htmlspecialchars($r['model_name'])?></td>
            <td><span class="lb"><?=htmlspecialchars($r['line_name'])?></span></td>
            <td><?=htmlspecialchars($r['drafter_name'] ?? '-')?></td>
            <td><?=htmlspecialchars($r['shift'])?></td>
            <td><?=$r['uph']?:'-'?></td>
            <?php for($h=1;$h<=11;$h++):$hv=(int)$r["h$h"];?>
              <td class="hc <?=$hv==0?'z':''?>"><?=$hv>0?$hv:''?></td>
            <?php endfor;?>
            <td class="tc"><?=number_format($r['total'])?></td>
            <td class="sc"><?=$r['scrap']>0?$r['scrap']:'-'?></td>
            <td style="color:var(--accent)"><?=$r['repair']>0?$r['repair']:'-'?></td>
            <td><?=$r['is_verified']?'<span class="badge-a">✓ OK</span>':'<span class="badge-p">⏳</span>'?></td>
          </tr>
          <?php endforeach;?>
          </tbody>
          <tfoot><tr>
            <td colspan="6" style="text-align:left;padding-left:8px;">GRAND TOTAL</td>
            <?php for($h=1;$h<=11;$h++) echo "<td>{$grand_h[$h]}</td>";?>
            <td><?=number_format($grand['total'])?></td>
            <td><?=$grand['scrap']?></td>
            <td><?=$grand['repair']?></td>
            <td></td>
          </tr></tfoot>
        </table>
      </div>
      <?php else:?><div class="no-data">📭 No data for <?=date('d-M-Y',strtotime($filter_date))?></div><?php endif;?>
    </div>

    <!-- Line summary table -->
    <div class="table-panel">
      <div class="tp-head">
        <h3 class="g">Line Summary</h3>
      </div>
      <?php if(count($rows_line)>0):?>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr>
            <th style="text-align:left">Line</th><th>Output</th><th>Scrap</th><th>Scrap%</th>
          </tr></thead>
          <tbody>
          <?php foreach($rows_line as $r):
            $sp=$r['total']>0?round(($r['scrap']/$r['total'])*100,1):0;
            $bw=min(100,$sp*6);
          ?>
          <tr>
            <td style="text-align:left;font-weight:700"><?=htmlspecialchars($r['line_name'])?><br><span class="lb"><?=strtoupper($r['section'])?></span></td>
            <td style="font-weight:800;color:var(--green)"><?=number_format($r['total'])?></td>
            <td class="sc"><?=$r['scrap']?></td>
            <td>
              <?=$sp?>%
              <span class="pwrap"><span class="pfill" style="width:<?=$bw?>%;background:<?=$sp>5?'#ef4444':'#10b981'?>;"></span></span>
            </td>
          </tr>
          <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <?php else:?><div class="no-data">No lines</div><?php endif;?>
    </div>

  </div><!-- /bot-row -->

</div><!-- /page-body -->

<!-- FOOTER -->
<div class="footer">
  <a href="https://www.iljin.co.in/">© 2026 ILJIN ELECTRONICS INDIA PVT LTD</a>
  &nbsp;|&nbsp; MADE BY RISHIK UPADHYAY
  &nbsp;|&nbsp; Auto-refresh: <b>60s</b>
</div>

<script>
<?php if(count($chart_labels)>0):?>
new Chart(document.getElementById('modelChart').getContext('2d'),{
  type:'bar',
  data:{
    labels:<?=json_encode($chart_labels)?>,
    datasets:[
      {label:'Production',data:<?=json_encode($chart_totals)?>,backgroundColor:'rgba(59,130,246,.75)',borderColor:'#3b82f6',borderWidth:1,borderRadius:3},
      {label:'Scrap',data:<?=json_encode($chart_scrap)?>,backgroundColor:'rgba(239,68,68,.65)',borderColor:'#ef4444',borderWidth:1,borderRadius:3}
    ]
  },
  options:{
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{position:'top',labels:{font:{size:9},boxWidth:10}},tooltip:{callbacks:{label:c=>` ${c.dataset.label}: ${c.raw}`}}},
    scales:{y:{beginAtZero:true,grid:{color:'#f1f5f9'},ticks:{font:{size:9}}},x:{ticks:{font:{size:8},maxRotation:30}}}
  }
});
<?php endif;?>

<?php if(count($line_labels)>0):?>
const lc=['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#84cc16','#f43f5e'];
new Chart(document.getElementById('lineChart').getContext('2d'),{
  type:'doughnut',
  data:{
    labels:<?=json_encode($line_labels)?>,
    datasets:[{data:<?=json_encode($line_totals)?>,backgroundColor:lc.slice(0,<?=count($line_labels)?>),borderWidth:2,hoverOffset:6}]
  },
  options:{
    responsive:true,maintainAspectRatio:false,cutout:'55%',
    plugins:{legend:{position:'right',labels:{font:{size:9},padding:8,boxWidth:10}},tooltip:{callbacks:{label:c=>` ${c.label}: ${c.raw}`}}}
  }
});
<?php endif;?>

let cd=60;
setInterval(()=>{
  cd--;
  if(cd<=0)location.reload();
  document.getElementById('rtv').textContent=new Date().toLocaleTimeString('en-IN')+' ('+cd+'s)';
},1000);
</script>
</body>
</html>