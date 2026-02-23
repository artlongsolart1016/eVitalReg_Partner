<?php
/**
 * Birth Statistics Export - SUPER OPTIMIZED
 * Uses CSV temp file + Excel clipboard paste (mimics VB.NET speed)
 *
 * MODAL UPGRADE:
 *   ✅ Circular SVG ring with live % counter
 *   ✅ LONART matrix letter-by-letter animation while loading
 *   ✅ Auto-delete via download_export.php after download
 *   ✅ ob_start() on download_export.php prevents xlsx corruption
 */
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/export_error.log');

set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '2048M');

require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';
require_once 'classes/MySQL_DatabaseManager.php';

SecurityHelper::requireLogin();

header('Content-Type: application/json');

function loadMunRef(): array {
    $paths = ['C:/PhilCRIS/Resources/References/RMunicipality.ref', __DIR__ . '/Resources/References/RMunicipality.ref'];
    $dict = [];
    foreach ($paths as $p) {
        if (!file_exists($p)) continue;
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $code = trim($parts[3]);
                if ($code !== '' && !isset($dict[$code]))
                    $dict[$code] = ['municipality'=>trim($parts[0]),'province'=>trim($parts[1]),'country'=>trim($parts[2])];
            }
        }
        return $dict;
    }
    return $dict;
}

function weekFields(string $dateStr): array {
    try {
        $dt = new DateTime($dateStr);
        $y  = (int)$dt->format('Y'); $m = (int)$dt->format('n'); $d = (int)$dt->format('j');
        $wn = (int)(($d - 1) / 7) + 1;
        $ws = new DateTime("$y-$m-01"); $ws->modify('+' . (($wn-1)*7) . ' days');
        $we = clone $ws; $we->modify('+6 days');
        $last = new DateTime($dt->format('Y-m-t'));
        if ($we > $last) $we = clone $last;
        return ['wn'=>$wn,'ws'=>$ws->format('Y-m-d'),'we'=>$we->format('Y-m-d'),'year'=>$y,'month'=>$m,'day'=>$d];
    } catch (Exception $e) {
        return ['wn'=>null,'ws'=>null,'we'=>null,'year'=>null,'month'=>null,'day'=>null];
    }
}

function safeInt($v, $default = null) { return is_numeric($v) ? (int)$v : $default; }

function extractMunicipalityCode($s): string {
    if (empty($s)) return 'UNKNOWN';
    $parts = explode('|', $s);
    $code  = trim(end($parts));
    return $code !== '' ? $code : 'UNKNOWN';
}

// ── AJAX EXPORT ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_birth') {
    try {
        $year       = intval($_POST['year']        ?? 0);
        $monthStart = intval($_POST['month_start'] ?? 1);
        $monthEnd   = intval($_POST['month_end']   ?? 12);
        $teenAge    = intval($_POST['teenage_age'] ?? 19);
        $srcPartner = !empty($_POST['source_partner']);
        $srcLGU     = !empty($_POST['source_lgu']);

        if ($year === 0)              throw new Exception('Year is required');
        if (!$srcPartner && !$srcLGU) throw new Exception('Please select at least one Source.');

        $templatePath = __DIR__ . DIRECTORY_SEPARATOR . 'ExcelTemplate' . DIRECTORY_SEPARATOR . 'BirthTemplate.xlsx';
        if (!file_exists($templatePath)) throw new Exception('Template not found: ' . $templatePath);

        $exportDir = __DIR__ . DIRECTORY_SEPARATOR . 'exports';
        if (!is_dir($exportDir)) mkdir($exportDir, 0777, true);

        foreach (glob($exportDir . DIRECTORY_SEPARATOR . '*.xlsx') as $old)
            if (filemtime($old) < time() - 3600) @unlink($old);

        $filename = $year . '_Birth_Statistics_Reports_' . date('Ymd_His') . '.xlsx';
        $destPath = $exportDir . DIRECTORY_SEPARATOR . $filename;
        if (!copy($templatePath, $destPath)) throw new Exception('Failed to copy template');
        sleep(1);

        $dbManager = new MySQL_DatabaseManager();
        $conn = $dbManager->getMainConnection();
        if (!$conn) throw new Exception('Database connection failed');

        $srcParts = [];
        if ($srcPartner) $srcParts[] = "(RegistryNum LIKE '!%' OR RegistryNum REGEXP '^[0-9]{4}-[0-9]+\$')";
        if ($srcLGU)     $srcParts[] = "LEFT(RegistryNum,1) != '!'";
        $srcWhere = '(' . implode(' OR ', $srcParts) . ')';

        $sql = "SELECT RegistryNum, DocumentStatus, CFirstName, CMiddleName, CLastName, CSexId, CBirthDate,
                CBirthAddress, CBirthMunicipality, CBirthMunicipalityId, CBirthProvince, CBirthProvinceId,
                CBirthCountry, CBirthCountryId, CBirthTypeId, MFirstName, MMiddleName, MLastName, MCitizenship,
                MCitizenshipId, MOccupation, MOccupationId, MAge, MAddress, MMunicipality, MMunicipalityId,
                MProvince, MProvinceId, MCountry, MCountryId, FFirstName, FMiddleName, FLastName, FCitizenship,
                FCitizenshipId, FOccupation, FOccupationId, FAge, FAddress, FMunicipality, FMunicipalityId,
                FProvince, FProvinceId, FCountry, FCountryId, AttendantId, AttendantName, AttendantTitle,
                PreparerName, PreparerTitle, PreparerDate, DateReceived, DateRegistered
                FROM phcris.birthdocument
                WHERE $srcWhere AND YEAR(CBirthDate) = ? AND MONTH(CBirthDate) BETWEEN ? AND ?
                ORDER BY LEFT(RegistryNum,4), CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(RegistryNum,'-',-1),'-',1) AS UNSIGNED) ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('SQL prepare failed');
        $stmt->bind_param('iii', $year, $monthStart, $monthEnd);
        if (!$stmt->execute()) throw new Exception('SQL execute failed');
        $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        if (empty($records)) throw new Exception('No records found');

        foreach ($records as &$r) {
            $bd = $r['CBirthDate'] ?? '';
            if ($bd !== '' && strtotime($bd) !== false) {
                $w = weekFields($bd);
                $r['Week Number']     = $w['wn']; $r['Week Start Date'] = $w['ws'];
                $r['Week End Date']   = $w['we']; $r['Year']  = $w['year'];
                $r['Month'] = $w['month'];        $r['Day']   = $w['day'];
            } else {
                $r['Week Number'] = $r['Week Start Date'] = $r['Week End Date']
                    = $r['Year'] = $r['Month'] = $r['Day'] = null;
            }
            $r['MAgeNumeric'] = safeInt($r['MAge'] ?? null, null);
            $r['FAgeNumeric'] = safeInt($r['FAge'] ?? null, null);
        }
        unset($r);

        $munRef = loadMunRef();
        $munGroups = [];
        foreach ($records as $r) {
            $sex  = strtoupper(trim((string)($r['CSexId'] ?? '')));
            $code = extractMunicipalityCode($r['MMunicipality'] ?? '');
            if (!isset($munGroups[$code])) $munGroups[$code] = [0,0,0];
            if ($sex === 'MALE')        $munGroups[$code][0]++;
            elseif ($sex === 'FEMALE') $munGroups[$code][1]++;
            $munGroups[$code][2]++;
        }
        uksort($munGroups, fn($a,$b) => ($a==='UNKNOWN'?'ZZZZZ':$a)<=>($b==='UNKNOWN'?'ZZZZZ':$b));

        $munStats = []; $sNo = 1;
        foreach ($munGroups as $code => list($male,$female,$total)) {
            $ref = isset($munRef[$code]) ? $munRef[$code]
                 : ['municipality'=>($code==='UNKNOWN'?'Not Stated':"Unknown ($code)"),'province'=>'Not Stated','country'=>'Philippines'];
            $munStats[] = ['no'=>$sNo++,'mun'=>$ref['municipality'],'prov'=>$ref['province'],'ctry'=>$ref['country'],
                           'male'=>$male,'female'=>$female,'total'=>$total];
        }

        $csvPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'birth_' . uniqid() . '.csv';
        $csv  = fopen($csvPath, 'w');
        $cols = ['RegistryNum','DocumentStatus','CFirstName','CMiddleName','CLastName','CSexId','CBirthDate',
                 'CBirthAddress','CBirthMunicipality','CBirthMunicipalityId','CBirthProvince','CBirthProvinceId',
                 'CBirthCountry','CBirthCountryId','CBirthTypeId','MFirstName','MMiddleName','MLastName',
                 'MCitizenship','MCitizenshipId','MOccupation','MOccupationId','MAge','MAddress','MMunicipality',
                 'MMunicipalityId','MProvince','MProvinceId','MCountry','MCountryId','FFirstName','FMiddleName',
                 'FLastName','FCitizenship','FCitizenshipId','FOccupation','FOccupationId','FAge','FAddress',
                 'FMunicipality','FMunicipalityId','FProvince','FProvinceId','FCountry','FCountryId','AttendantId',
                 'AttendantName','AttendantTitle','PreparerName','PreparerTitle','PreparerDate','DateReceived',
                 'DateRegistered','Week Number','Week Start Date','Week End Date','Year','Month','Day','MAgeNumeric','FAgeNumeric'];
        fputcsv($csv, $cols);
        foreach ($records as $rec) {
            $row = [];
            foreach ($cols as $col) $row[] = $rec[$col] ?? '';
            fputcsv($csv, $row);
        }
        fclose($csv);

        $excel = new COM("Excel.Application");
        try {
            $excel->Visible = false; $excel->DisplayAlerts = false; $excel->ScreenUpdating = false;
            $workbook = $excel->Workbooks->Open(realpath($destPath));
            $dsSheet  = $workbook->Sheets("Data Source");
            $dsSheet->Cells->Clear();
            $csvWb = $excel->Workbooks->Open(realpath($csvPath), null, null, 4);
            $csvWb->Sheets(1)->UsedRange->Copy();
            $dsSheet->Paste($dsSheet->Cells(1,1));
            $csvWb->Close(false);

            $munSheet = $workbook->Sheets("ByMunicipality");
            if (!empty($munStats)) {
                $r = 14;
                foreach ($munStats as $m) {
                    $munSheet->Cells($r,2)->Value=$m['no']; $munSheet->Cells($r,3)->Value=$m['mun'];
                    $munSheet->Cells($r,4)->Value=$m['prov']; $munSheet->Cells($r,5)->Value=$m['ctry'];
                    $munSheet->Cells($r,6)->Value=$m['male']; $munSheet->Cells($r,7)->Value=$m['female'];
                    $munSheet->Cells($r,8)->Formula="=F{$r}+G{$r}"; $r++;
                }
                $totR=$r; $lastDR=$r-1;
                $munSheet->Range($munSheet->Cells($totR,2),$munSheet->Cells($totR,5))->Merge();
                $munSheet->Cells($totR,2)->Value='TOTAL';
                $munSheet->Cells($totR,6)->Formula="=SUM(F14:F{$lastDR})";
                $munSheet->Cells($totR,7)->Formula="=SUM(G14:G{$lastDR})";
                $munSheet->Cells($totR,8)->Formula="=SUM(H14:H{$lastDR})";
            }

            $teenGroups = [];
            foreach ($records as $r) {
                $mage = safeInt($r['MAge'] ?? null, null);
                if ($mage===null || $mage>$teenAge) continue;
                $code = extractMunicipalityCode($r['MMunicipality'] ?? '');
                if (!isset($teenGroups[$code])) $teenGroups[$code] = [];
                $mn = trim(($r['MFirstName']??'').' '.($r['MMiddleName']??'').' '.($r['MLastName']??''));
                $mn = preg_replace('/\s+/',' ',$mn);
                if (empty($mn)) $mn = 'Unknown';
                $teenGroups[$code][] = ['motherName'=>$mn,'address'=>$r['MAddress']??'','age'=>$mage];
            }
            uksort($teenGroups, fn($a,$b)=>($a==='UNKNOWN'?'ZZZZZ':$a)<=>($b==='UNKNOWN'?'ZZZZZ':$b));

            $teenSheet = $workbook->Sheets("TeenAge");
            if (!empty($teenGroups)) {
                $r=15; $groupingInfo=[]; $munHeaderRows=[];
                foreach ($teenGroups as $code=>$mothers) {
                    if ($code==='UNKNOWN')        { $mun='Unknown';         $prov='Unknown'; $ctry='Philippines'; }
                    elseif(isset($munRef[$code])) { $mun=$munRef[$code]['municipality']; $prov=$munRef[$code]['province']; $ctry=$munRef[$code]['country']; }
                    else                          { $mun="Unknown ($code)"; $prov='Unknown'; $ctry='Philippines'; }
                    $hRow=$r;
                    $teenSheet->Cells($r,2)->Value=count($groupingInfo)+1;
                    $teenSheet->Cells($r,3)->Value=$mun; $teenSheet->Cells($r,5)->Value=$prov;
                    $teenSheet->Cells($r,6)->Value=$ctry; $teenSheet->Cells($r,7)->Value=count($mothers);
                    $teenSheet->Range($teenSheet->Cells($r,2),$teenSheet->Cells($r,7))->Font->Bold=true;
                    $munHeaderRows[]=$r; $r++;
                    $dStart=$r; $dNo=1;
                    foreach ($mothers as $mo) {
                        $teenSheet->Cells($r,3)->Value=$dNo; $teenSheet->Cells($r,4)->Value=$mo['motherName'];
                        $teenSheet->Cells($r,6)->Value=$mo['address']; $teenSheet->Cells($r,7)->Value=$mo['age'];
                        $r++; $dNo++;
                    }
                    $groupingInfo[]=['header'=>$hRow,'start'=>$dStart,'end'=>$r-1];
                }
                $totRow=$r;
                $teenSheet->Range($teenSheet->Cells($totRow,3),$teenSheet->Cells($totRow,6))->Merge();
                $teenSheet->Cells($totRow,3)->Value='TOTAL'; $teenSheet->Cells($totRow,3)->Font->Bold=true;
                $formula='=';
                foreach ($munHeaderRows as $i=>$hr) { if($i>0) $formula.='+'; $formula.="G{$hr}"; }
                $teenSheet->Cells($totRow,7)->Formula=$formula; $teenSheet->Cells($totRow,7)->Font->Bold=true;
                foreach ($groupingInfo as $g)
                    if ($g['start']<=$g['end'])
                        try{$teenSheet->Rows($g['start'].':'.$g['end'])->OutlineLevel=2;}catch(Exception $e){}
                foreach ($groupingInfo as $g)
                    try{$teenSheet->Rows($g['header'])->OutlineLevel=1;}catch(Exception $e){}
                try{$teenSheet->Outline->ShowLevels(1);}catch(Exception $e){}
            }

            $excel->Calculate();
            $workbook->Save(); $workbook->Close(); $excel->Quit();
            @unlink($csvPath);

            echo json_encode(['success'=>true,'download'=>'download_export.php?file='.urlencode($filename),'records'=>count($records)]);
            exit;
        } catch (Exception $ex) {
            if (isset($excel)) @$excel->Quit();
            throw new Exception('COM Error: '.$ex->getMessage());
        }
    } catch (Exception $ex) {
        echo json_encode(['success'=>false,'error'=>$ex->getMessage()]);
        exit;
    }
}

// ── HTML FORM ───────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
$currentYear = (int)date('Y');
$fYear    = isset($_POST['year'])        ? intval($_POST['year'])        : $currentYear;
$fM1      = isset($_POST['month_start']) ? intval($_POST['month_start']) : 1;
$fM2      = isset($_POST['month_end'])   ? intval($_POST['month_end'])   : 12;
$fTeen    = isset($_POST['teenage_age']) ? intval($_POST['teenage_age']) : 19;
$fPartner = !empty($_POST['source_partner']);
$fLGU     = !empty($_POST['source_lgu']);
$months   = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Birth Statistics Export</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0a0e27;color:#fff;font-family:'Segoe UI',sans-serif;
     min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px 0}

/* ── Card ── */
.card{background:#1e293b;border:2px solid #667eea;border-radius:12px;width:540px;
      max-width:96vw;box-shadow:0 20px 60px rgba(0,0,0,.6);overflow:hidden}
.card-head{background:linear-gradient(135deg,#ff6b35,#f7931e);padding:16px 22px;
           display:flex;align-items:center;gap:12px}
.card-head h3{margin:0;font-size:1.1rem;font-weight:700;color:#fff}
.card-head i{font-size:1.4rem;color:#ffd60a}
.card-body{padding:22px}

/* ── Form rows ── */
.frow{display:grid;grid-template-columns:185px 14px 1fr;align-items:start;gap:8px;margin-bottom:13px}
.frow label{color:#94a3b8;font-weight:600;font-size:.875rem;padding-top:8px}
.frow span{color:#94a3b8;font-weight:700;padding-top:8px}
.form-control,.form-select{background:#0f172a;border:1px solid #334155;color:#fff;
    padding:7px 10px;border-radius:6px;font-size:.875rem;width:100%}
.form-control:focus,.form-select:focus{background:#0f172a;border-color:#667eea;
    box-shadow:0 0 0 3px rgba(102,126,234,.2);outline:none;color:#fff}
.sbox{background:rgba(102,126,234,.08);border:1px solid #334155;border-radius:8px;
      padding:13px 15px;margin-bottom:13px}
.sbox-title{font-size:.73rem;font-weight:700;text-transform:uppercase;color:#00d9ff;
            margin-bottom:9px;letter-spacing:.05em}
.chk{display:flex;align-items:center;gap:10px;padding:5px 0}
.chk input[type=checkbox]{width:17px;height:17px;accent-color:#667eea;cursor:pointer;flex-shrink:0}
.chk label{color:#e2e8f0;font-size:.88rem;cursor:pointer;margin:0}
.divider{border:none;border-top:1px solid #334155;margin:14px 0}
.btn-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:18px}
.btn-ok{background:linear-gradient(135deg,#ff6b35,#f7931e);color:#fff;border:none;
        padding:11px;border-radius:7px;font-weight:700;font-size:.9rem;cursor:pointer;transition:opacity .2s}
.btn-ok:hover{opacity:.88}
.btn-ok:disabled{opacity:.45;cursor:not-allowed}
.btn-cancel{background:#1e293b;color:#94a3b8;border:1px solid #334155;padding:11px;
            border-radius:7px;font-weight:700;font-size:.9rem;cursor:pointer}
.btn-cancel:hover{background:#334155;color:#fff}

/* ══════════════════════════════════════════
   PROGRESS MODAL — BIRTH (orange theme)
══════════════════════════════════════════ */
.modal-overlay{
    display:none;position:fixed;inset:0;
    background:rgba(5,8,30,.93);
    z-index:9999;
    align-items:center;justify-content:center;
    flex-direction:column;
    backdrop-filter:blur(5px);
}
.modal-overlay.show{display:flex}

.modal-box{
    background:#1e293b;
    border:2px solid #ff6b35;
    border-radius:18px;
    padding:38px 44px 32px;
    text-align:center;
    min-width:300px;max-width:360px;width:90%;
    box-shadow:0 0 80px rgba(255,107,53,.25), 0 30px 70px rgba(0,0,0,.55);
    animation:mIn .35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes mIn{from{opacity:0;transform:scale(.82)}to{opacity:1;transform:scale(1)}}

/* Ring */
.ring-wrap{position:relative;width:140px;height:140px;margin:0 auto 10px}
.ring-wrap svg{transform:rotate(-90deg);overflow:visible;
    filter:drop-shadow(0 0 9px rgba(255,107,53,.55))}
.ring-track{fill:none;stroke:#1c1a2e;stroke-width:12}
.ring-fill{fill:none;stroke:url(#bRingGrad);stroke-width:12;stroke-linecap:round;
    stroke-dasharray:376.99;stroke-dashoffset:376.99;
    transition:stroke-dashoffset .5s cubic-bezier(.4,0,.2,1)}
.ring-center{position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;pointer-events:none}
.pct-num{font-family:'Segoe UI',monospace;font-size:2.2rem;font-weight:900;
    line-height:1;color:#ff6b35;text-shadow:0 0 20px rgba(255,107,53,.9)}
.pct-sym{font-size:.7rem;font-weight:700;color:#64748b;margin-top:2px}



/* Modal text */
.modal-title{font-size:1rem;font-weight:700;color:#fff;margin-bottom:5px}
.modal-sub{font-size:.78rem;color:#64748b;line-height:1.6;margin-bottom:12px}

/* Thin bar */
.thin-bar{height:3px;background:rgba(255,255,255,.07);border-radius:4px;margin-bottom:5px;overflow:hidden}
.thin-fill{height:100%;border-radius:4px;width:0%;
    background:linear-gradient(90deg,#ff6b35,#ffd60a);
    transition:width .5s cubic-bezier(.4,0,.2,1)}

.ticks{display:flex;justify-content:space-between;padding:0 3px;margin-bottom:10px}
.tick{font-size:.58rem;color:#475569}

.status-txt{font-size:.73rem;font-weight:600;font-style:italic;min-height:18px;color:#f97316}
</style>
</head>
<body>

<!-- ══ LONART PROGRESS MODAL ══ -->
<div class="modal-overlay" id="progressModal">
  <div class="modal-box">

    <div class="ring-wrap">
      <svg width="140" height="140" viewBox="0 0 140 140">
        <defs>
          <linearGradient id="bRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%"   stop-color="#ff6b35"/>
            <stop offset="100%" stop-color="#ffd60a"/>
          </linearGradient>
        </defs>
        <circle class="ring-track" cx="70" cy="70" r="60"/>
        <circle class="ring-fill" id="bRingFill" cx="70" cy="70" r="60"/>
      </svg>
      <div class="ring-center">
        <span class="pct-num" id="bPctNum">0</span>
        <span class="pct-sym">%</span>
      </div>
    </div>



    <p class="modal-title">Generating Birth Statistics…</p>
    <p class="modal-sub">For 5,000+ records this takes ~1–2 minutes.<br><strong>Do not close this window.</strong></p>

    <div class="thin-bar"><div class="thin-fill" id="bThinFill"></div></div>
    <div class="ticks"><span class="tick">0</span><span class="tick">25</span><span class="tick">50</span><span class="tick">75</span><span class="tick">100</span></div>
    <p class="status-txt" id="bStatusTxt">Connecting to database…</p>

  </div>
</div>

<!-- ══ MAIN CARD ══ -->
<div class="card">
  <div class="card-head">
    <i class="fas fa-file-excel"></i>
    <h3>Birth Statistics Export (Optimized)</h3>
  </div>
  <div class="card-body">
    <form id="frm" method="POST">
    <input type="hidden" name="action" value="export_birth">

    <div class="frow">
      <label>Year</label><span>:</span>
      <select name="year" class="form-select" required>
        <?php for($y=$currentYear;$y>=1900;$y--): ?>
        <option value="<?=$y?>"<?=$y===$fYear?' selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="frow">
      <label>Month Start</label><span>:</span>
      <select name="month_start" class="form-select" required id="m1">
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>"<?=$m===$fM1?' selected':''?>><?=$m?> — <?=$months[$m-1]?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="frow">
      <label>Month End</label><span>:</span>
      <select name="month_end" class="form-select" required id="m2">
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>"<?=$m===$fM2?' selected':''?>><?=$m?> — <?=$months[$m-1]?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="frow">
      <label>Teenage Age</label><span>:</span>
      <input type="number" name="teenage_age" class="form-control"
             placeholder="19" min="1" max="100" value="<?=$fTeen?>" required>
    </div>

    <hr class="divider">

    <div class="sbox">
      <div class="sbox-title"><i class="fas fa-database"></i>&nbsp;Source</div>
      <div class="chk">
        <input type="checkbox" name="source_partner" id="sp" value="1"<?=$fPartner?' checked':''?>>
        <label for="sp">Partner</label>
      </div>
      <div class="chk">
        <input type="checkbox" name="source_lgu" id="sl" value="1"<?=$fLGU?' checked':''?>>
        <label for="sl">LGU Register</label>
      </div>
    </div>

    <div class="btn-row">
      <button type="submit" class="btn-ok" id="btnGo">
        <i class="fas fa-file-export"></i>&nbsp;Export
      </button>
      <button type="button" class="btn-cancel" onclick="history.back()">
        <i class="fas fa-times"></i>&nbsp;Cancel
      </button>
    </div>
    </form>
  </div>
</div>

<script>
/* ─────────────────────────────────────────────
   SIMPLE PROGRESS ENGINE (No LONART)
───────────────────────────────────────────── */
const CIRC = 376.99;   // 2π × 60

const bState = { timers: [] };

function bSetPct(pct){
    const p = Math.min(100, Math.max(0, Math.round(pct)));
    document.getElementById('bRingFill').style.strokeDashoffset = CIRC - (p/100)*CIRC;
    document.getElementById('bPctNum').textContent = p;
    document.getElementById('bThinFill').style.width = p + '%';
}

function bClearAll(){
    bState.timers.forEach(clearTimeout);
    bState.timers = [];
}

/* ── Staged simulation ── */
const B_STAGES = [
    { pct:  6, delay:   500, msg:'Connecting to database…'          },
    { pct: 16, delay:  1300, msg:'Fetching birth records…'          },
    { pct: 28, delay:  2400, msg:'Computing week fields…'           },
    { pct: 40, delay:  3600, msg:'Building municipality groups…'    },
    { pct: 52, delay:  5200, msg:'Writing CSV temp file…'           },
    { pct: 64, delay:  7000, msg:'Opening Excel template via COM…'  },
    { pct: 73, delay:  9500, msg:'Pasting data into Data Source…'   },
    { pct: 81, delay: 12500, msg:'Filling ByMunicipality sheet…'    },
    { pct: 88, delay: 16000, msg:'Filling TeenAge sheet…'           },
    { pct: 94, delay: 20000, msg:'Calculating & saving workbook…'   },
];

function bAnimateTo100(onDone){
    let cur = parseInt(document.getElementById('bPctNum').textContent)||0;
    const step=()=>{
        cur=Math.min(100,cur+2);
        bSetPct(cur);
        document.getElementById('bStatusTxt').textContent =
            cur<100 ? 'Finalising workbook…' : '✅ Done! Starting download…';

        if(cur<100){
            requestAnimationFrame(step);
        } else {
            setTimeout(onDone,400);
        }
    };
    requestAnimationFrame(step);
}

/* ── Form submit ── */
document.getElementById('frm').addEventListener('submit', function(e){
    e.preventDefault();

    const m1=parseInt(document.getElementById('m1').value);
    const m2=parseInt(document.getElementById('m2').value);
    if(m2<m1){
        alert('Month End cannot be before Month Start.');
        return;
    }

    document.getElementById('progressModal').classList.add('show');
    document.getElementById('btnGo').disabled = true;

    bSetPct(0);
    document.getElementById('bStatusTxt').textContent = 'Connecting to database…';

    B_STAGES.forEach(s=>{
        const t=setTimeout(()=>{
            bSetPct(s.pct);
            document.getElementById('bStatusTxt').textContent=s.msg;
        },s.delay);
        bState.timers.push(t);
    });

    fetch(window.location.pathname, {
        method:'POST',
        body:new FormData(this)
    })
    .then(r=>r.json())
    .then(data=>{
        bClearAll();

        if(data.success){
            bAnimateTo100(()=>{
                document.getElementById('progressModal').classList.remove('show');
                document.getElementById('btnGo').disabled=false;

                alert(
                    '✅ Export completed!\n' +
                    data.records.toLocaleString() +
                    ' records processed.\nDownload will start automatically.'
                );

                const a=document.createElement('a');
                a.href=data.download;
                a.download='';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                setTimeout(()=>{
                    window.location.href='birth_transmission.php';
                },2500);
            });
        } else {
            document.getElementById('progressModal').classList.remove('show');
            document.getElementById('btnGo').disabled=false;
            bSetPct(0);
            alert('❌ Export failed:\n'+(data.error||'Unknown error'));
        }
    })
    .catch(err=>{
        bClearAll();
        document.getElementById('progressModal').classList.remove('show');
        document.getElementById('btnGo').disabled=false;
        bSetPct(0);
        alert('❌ Network error: '+err.message);
    });
});
</script>
</body>
</html>