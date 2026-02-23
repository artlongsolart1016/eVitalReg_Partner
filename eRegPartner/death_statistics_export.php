<?php
/**
 * Death Statistics Export - OPTIMIZED
 * Uses CSV temp file + Excel clipboard paste
 *
 * MODAL UPGRADE:
 *   ✅ Circular SVG ring with live % counter
 *   ✅ LONART matrix letter-by-letter animation while loading
 *   ✅ Auto-delete via download_export.php after download
 *   ✅ ob_start() on download_export.php prevents xlsx corruption
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/export_error.log');

set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

require_once 'config/config.php';
require_once 'classes/SecurityHelper.php';
require_once 'classes/MySQL_DatabaseManager.php';

SecurityHelper::requireLogin();

function loadMunRef(): array {
    $paths = [
        'C:/PhilCRIS/Resources/References/RMunicipality.ref',
        __DIR__ . '/Resources/References/RMunicipality.ref',
        __DIR__ . '/references/RMunicipality.ref',
    ];
    foreach ($paths as $p) {
        if (!file_exists($p)) continue;
        $dict = [];
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
    return [];
}

function weekFields(string $dateStr): array {
    try {
        $dt   = new DateTime($dateStr);
        $y=(int)$dt->format('Y'); $m=(int)$dt->format('n'); $d=(int)$dt->format('j');
        $wn=(int)(($d-1)/7)+1;
        $ws=new DateTime("$y-$m-01"); $ws->modify('+'.(($wn-1)*7).' days');
        $we=clone $ws; $we->modify('+6 days');
        $last=new DateTime($dt->format('Y-m-t'));
        if($we>$last) $we=clone $last;
        return ['wn'=>$wn,'ws'=>$ws->format('Y-m-d'),'we'=>$we->format('Y-m-d'),'year'=>$y,'month'=>$m,'day'=>$d];
    } catch(Exception $e){
        return ['wn'=>null,'ws'=>null,'we'=>null,'year'=>null,'month'=>null,'day'=>null];
    }
}

function safeInt($v, $default=null){ return is_numeric($v)?(int)$v:$default; }

// ── AJAX EXPORT ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='export_death'){

    header('Content-Type: application/json');
    $csvPath = null;

    try {
        $year       = intval($_POST['year']        ?? 0);
        $monthStart = intval($_POST['month_start'] ?? 1);
        $monthEnd   = intval($_POST['month_end']   ?? 12);
        $inclCause  = !empty($_POST['include_cause']);
        $cause1     = trim($_POST['cause1'] ?? '');
        $cause2     = trim($_POST['cause2'] ?? '');
        $cause3     = trim($_POST['cause3'] ?? '');
        $srcPartner = !empty($_POST['source_partner']);
        $srcLGU     = !empty($_POST['source_lgu']);

        if($year===0)               throw new Exception('Year is required');
        if(!$srcPartner&&!$srcLGU)  throw new Exception('Please select at least one Source.');
        if($inclCause&&$cause1===''&&$cause2===''&&$cause3==='')
            throw new Exception('At least one Cause of Death must be entered.');

        $templatePath = __DIR__.DIRECTORY_SEPARATOR.'ExcelTemplate'.DIRECTORY_SEPARATOR.'deathtemplate.xlsx';
        if(!file_exists($templatePath)) throw new Exception('Template not found: '.$templatePath);

        $exportDir = __DIR__.DIRECTORY_SEPARATOR.'exports';
        if(!is_dir($exportDir)) mkdir($exportDir,0777,true);

        foreach(glob($exportDir.DIRECTORY_SEPARATOR.'*.xlsx') as $old)
            if(filemtime($old)<time()-3600) @unlink($old);

        $filename = $year.'_Death_Statistics_Reports_'.date('Ymd_His').'.xlsx';
        $destPath = $exportDir.DIRECTORY_SEPARATOR.$filename;
        if(!copy($templatePath,$destPath)) throw new Exception('Failed to copy template');
        sleep(1);

        $dbManager = new MySQL_DatabaseManager();
        $conn = $dbManager->getMainConnection();
        if(!$conn) throw new Exception('Database connection failed');

        $srcParts=[];
        if($srcPartner) $srcParts[]="(RegistryNum LIKE '!%' OR RegistryNum REGEXP '^[0-9]{4}-[0-9]+\$')";
        if($srcLGU)     $srcParts[]="LEFT(RegistryNum,1) != '!'";
        $srcWhere='('.implode(' OR ',$srcParts).')';

        $causeWhere='';
        if($inclCause){
            $causeTerms=[];
            foreach([$cause1,$cause2,$cause3] as $c){
                $c=trim($c);
                if($c!==''){
                    $c=$conn->real_escape_string($c);
                    $causeTerms[]="(CCauseImmediate LIKE '%$c%' OR CCauseAntecedent LIKE '%$c%'
                                    OR CCauseUnderlying LIKE '%$c%' OR CCauseOther LIKE '%$c%')";
                }
            }
            if(!empty($causeTerms)) $causeWhere=' AND ('.implode(' OR ',$causeTerms).')';
        }

        $sql = "SELECT RegistryNum, DocumentStatus, CFirstName, CMiddleName, CLastName, CSexId,
                    CDeathDate, CBirthDate, CAgeYears, CAgeMonths, CAgeDays, CAgeHours, CAgeMinutes,
                    CDeathAddress, CDeathMunicipality, CDeathMunicipalityId,
                    CDeathProvince, CDeathProvinceId, CDeathCountry, CDeathCountryId,
                    CCivilStatusId, CReligion, CCitizenship,
                    CResidenceAddress, CResidenceMunicipality, CResidenceMunicipalityId,
                    CResidenceProvince, CResidenceProvinceId, CResidenceCountry, CResidenceCountryId,
                    COccupation, FFirstName, FMiddleName, FLastName,
                    MFirstName, MMiddleName, MLastName,
                    CCauseImmediate, CCauseImmediateId, CCauseImmediateInterval,
                    CCauseAntecedent, CCauseAntecedentId, CCauseAntecedentInterval,
                    CCauseUnderlying, CCauseUnderlyingId, CCauseUnderlyingInterval,
                    CCauseOther, CCauseOtherId,
                    AttendantId, AttendantName, AttendantTitle, AttendantAttendedFrom,
                    PreparerName, PreparerTitle, PreparerDate, DateReceived, DateRegistered
                FROM phcris.deathdocument
                WHERE $srcWhere
                AND YEAR(CDeathDate)=? AND MONTH(CDeathDate) BETWEEN ? AND ?
                $causeWhere
                ORDER BY RegistryNum ASC";

        $stmt=$conn->prepare($sql);
        if(!$stmt) throw new Exception('SQL prepare failed: '.$conn->error);
        $stmt->bind_param('iii',$year,$monthStart,$monthEnd);
        if(!$stmt->execute()) throw new Exception('SQL execute failed: '.$stmt->error);
        $records=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        if(empty($records)) throw new Exception('No records found for the selected filters.');

        foreach($records as &$r){
            $dd=$r['CDeathDate']??'';
            if($dd!==''&&strtotime($dd)!==false){
                $w=weekFields($dd);
                $r['Week Number']=$w['wn']; $r['Week Start Date']=$w['ws'];
                $r['Week End Date']=$w['we']; $r['Year']=$w['year'];
                $r['Month']=$w['month']; $r['Day']=$w['day'];
            } else {
                $r['Week Number']=$r['Week Start Date']=$r['Week End Date']
                    =$r['Year']=$r['Month']=$r['Day']=null;
            }
            $r['AgeYearsNumeric']=safeInt($r['CAgeYears']??null,null);
        }
        unset($r);

        $munRef=loadMunRef();

        $cols=[
            'RegistryNum','DocumentStatus','CFirstName','CMiddleName','CLastName','CSexId',
            'CDeathDate','CBirthDate','CAgeYears','CAgeMonths','CAgeDays','CAgeHours','CAgeMinutes',
            'CDeathAddress','CDeathMunicipality','CDeathMunicipalityId',
            'CDeathProvince','CDeathProvinceId','CDeathCountry','CDeathCountryId',
            'CCivilStatusId','CReligion','CCitizenship',
            'CResidenceAddress','CResidenceMunicipality','CResidenceMunicipalityId',
            'CResidenceProvince','CResidenceProvinceId','CResidenceCountry','CResidenceCountryId',
            'COccupation','FFirstName','FMiddleName','FLastName',
            'MFirstName','MMiddleName','MLastName',
            'CCauseImmediate','CCauseImmediateId','CCauseImmediateInterval',
            'CCauseAntecedent','CCauseAntecedentId','CCauseAntecedentInterval',
            'CCauseUnderlying','CCauseUnderlyingId','CCauseUnderlyingInterval',
            'CCauseOther','CCauseOtherId',
            'AttendantId','AttendantName','AttendantTitle','AttendantAttendedFrom',
            'PreparerName','PreparerTitle','PreparerDate','DateReceived','DateRegistered',
            'Week Number','Week Start Date','Week End Date','Year','Month','Day','AgeYearsNumeric',
        ];

        $csvPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'death_'.uniqid().'.csv';
        $csv=fopen($csvPath,'w');
        fputcsv($csv,$cols);
        foreach($records as $rec){
            $row=[];
            foreach($cols as $col) $row[]=$rec[$col]??'';
            fputcsv($csv,$row);
        }
        fclose($csv);

        $munGroups=[];
        foreach($records as $r){
            $munVal=(string)($r['CResidenceMunicipality']??'');
            $code='UNKNOWN';
            if(strpos($munVal,'|')!==false){
                $code=trim(substr($munVal,strrpos($munVal,'|')+1));
                if($code==='') $code='UNKNOWN';
            }
            $sex=strtoupper(trim((string)($r['CSexId']??'')));
            if(!isset($munGroups[$code])) $munGroups[$code]=[0,0,0];
            if($sex==='MALE')       $munGroups[$code][0]++;
            elseif($sex==='FEMALE') $munGroups[$code][1]++;
            $munGroups[$code][2]++;
        }
        uksort($munGroups,fn($a,$b)=>($a==='UNKNOWN'?'ZZZZZ':$a)<=>($b==='UNKNOWN'?'ZZZZZ':$b));
        $munStats=[];$sNo=1;
        foreach($munGroups as $code=>list($male,$female,$total)){
            if($code==='UNKNOWN')        {$mun='Not Stated';     $prov='Not Stated';$ctry='Philippines';}
            elseif(isset($munRef[$code])){$mun=$munRef[$code]['municipality'];$prov=$munRef[$code]['province'];$ctry=$munRef[$code]['country'];}
            else                         {$mun="Unknown ($code)";$prov='Unknown';   $ctry='Philippines';}
            $munStats[]=['no'=>$sNo++,'mun'=>$mun,'prov'=>$prov,'ctry'=>$ctry,'male'=>$male,'female'=>$female,'total'=>$total];
        }

        $causeRows=[];
        if($inclCause){
            $cNo=1;
            foreach($records as $r){
                $has=trim((string)($r['CCauseImmediate']??''))!==''
                   ||trim((string)($r['CCauseAntecedent']??''))!==''
                   ||trim((string)($r['CCauseUnderlying']??''))!==''
                   ||trim((string)($r['CCauseOther']??''))!=='';
                if(!$has) continue;
                $dd=$r['CDeathDate']??'';
                $ddf=($dd!==''&&strtotime($dd)!==false)?date('Y-m-d',strtotime($dd)):'';
                $causeRows[]=['no'=>$cNo++,'reg'=>$r['RegistryNum']??'','last'=>$r['CLastName']??'',
                    'first'=>$r['CFirstName']??'','mid'=>$r['CMiddleName']??'','dod'=>$ddf,
                    'imm'=>$r['CCauseImmediate']??'','ant'=>$r['CCauseAntecedent']??'',
                    'und'=>$r['CCauseUnderlying']??'','undInt'=>$r['CCauseUnderlyingInterval']??'',
                    'other'=>$r['CCauseOther']??''];
            }
        }

        $doaRows=[];$dNo=1;
        foreach($records as $r){
            $af=strtoupper(trim((string)($r['AttendantAttendedFrom']??'')));
            $isDOA=strpos($af,'DEAD')===0||strpos($af,'DOA')!==false||strpos($af,'ER DEATH')!==false;
            if(!$isDOA) continue;
            $dd=$r['CDeathDate']??'';
            $dod=($dd!==''&&strtotime($dd)!==false)?date('Y-m-d',strtotime($dd)):'';
            $doaRows[]=['no'=>$dNo++,'reg'=>$r['RegistryNum']??'','last'=>$r['CLastName']??'',
                        'first'=>$r['CFirstName']??'','mid'=>$r['CMiddleName']??'','dod'=>$dod];
        }

        $excel=new COM("Excel.Application");
        try {
            $excel->Visible=false;$excel->DisplayAlerts=false;$excel->ScreenUpdating=false;
            $workbook=$excel->Workbooks->Open(realpath($destPath));
            $dsSheet=$workbook->Sheets("Data Source");
            $dsSheet->Cells->Clear();
            $csvWb=$excel->Workbooks->Open(realpath($csvPath),null,null,4);
            $csvWb->Sheets(1)->UsedRange->Copy();
            $dsSheet->Paste($dsSheet->Cells(1,1));
            $csvWb->Close(false);
            @unlink($csvPath);$csvPath=null;

            $munSheet=$workbook->Sheets("ByMunicipality");
            if(!empty($munStats)){
                $r=14;
                foreach($munStats as $m){
                    $munSheet->Cells($r,2)->Value=$m['no'];$munSheet->Cells($r,3)->Value=$m['mun'];
                    $munSheet->Cells($r,4)->Value=$m['prov'];$munSheet->Cells($r,5)->Value=$m['ctry'];
                    $munSheet->Cells($r,6)->Value=$m['male'];$munSheet->Cells($r,7)->Value=$m['female'];
                    $munSheet->Cells($r,8)->Formula="=F{$r}+G{$r}";$r++;
                }
                $totR=$r;$lastDR=$r-1;
                $munSheet->Range($munSheet->Cells($totR,2),$munSheet->Cells($totR,5))->Merge();
                $munSheet->Cells($totR,2)->Value='TOTAL';
                $munSheet->Cells($totR,6)->Formula="=SUM(F14:F{$lastDR})";
                $munSheet->Cells($totR,7)->Formula="=SUM(G14:G{$lastDR})";
                $munSheet->Cells($totR,8)->Formula="=SUM(H14:H{$lastDR})";
            }

            if($inclCause&&!empty($causeRows)){
                $causeSheet=$workbook->Sheets("CauseOfDeath");
                $cr=7;
                foreach($causeRows as $c){
                    $causeSheet->Cells($cr,1)->Value=$c['no'];$causeSheet->Cells($cr,2)->Value=$c['reg'];
                    $causeSheet->Cells($cr,3)->Value=$c['last'];$causeSheet->Cells($cr,4)->Value=$c['first'];
                    $causeSheet->Cells($cr,5)->Value=$c['mid'];$causeSheet->Cells($cr,6)->Value=$c['dod'];
                    $causeSheet->Cells($cr,7)->Value=$c['imm'];$causeSheet->Cells($cr,8)->Value=$c['ant'];
                    $causeSheet->Cells($cr,9)->Value=$c['und'];$causeSheet->Cells($cr,10)->Value=$c['undInt'];
                    $causeSheet->Cells($cr,11)->Value=$c['other'];$cr++;
                }
            }

            if(!empty($doaRows)){
                $doaSheet=$workbook->Sheets("DeadonArrival");
                $dr=9;
                foreach($doaRows as $d){
                    $doaSheet->Cells($dr,2)->Value=$d['no'];$doaSheet->Cells($dr,3)->Value=$d['reg'];
                    $doaSheet->Cells($dr,4)->Value=$d['last'];$doaSheet->Cells($dr,5)->Value=$d['first'];
                    $doaSheet->Cells($dr,6)->Value=$d['mid'];$doaSheet->Cells($dr,7)->Value=$d['dod'];$dr++;
                }
            }

            $excel->Calculate();$workbook->Save();$workbook->Close();$excel->Quit();

            echo json_encode(['success'=>true,'download'=>'download_export.php?file='.urlencode($filename),'records'=>count($records)]);

        } catch(Exception $ex){
            if(isset($excel)) @$excel->Quit();
            throw new Exception('COM Error: '.$ex->getMessage());
        }

    } catch(Exception $ex){
        if($csvPath&&file_exists($csvPath)) @unlink($csvPath);
        echo json_encode(['success'=>false,'error'=>$ex->getMessage()]);
    }
    exit;
}

// ── HTML FORM ───────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
$currentYear=(int)date('Y');
$fYear   =intval($_POST['year']        ??$currentYear);
$fM1     =intval($_POST['month_start'] ??1);
$fM2     =intval($_POST['month_end']   ??12);
$fInclC  =!empty($_POST['include_cause']);
$fC1     =$_POST['cause1']??'';
$fC2     =$_POST['cause2']??'';
$fC3     =$_POST['cause3']??'';
$fPartner=!empty($_POST['source_partner']);
$fLGU   =!empty($_POST['source_lgu']);
$months  =['January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Death Statistics Export</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0a0e27;color:#fff;font-family:'Segoe UI',sans-serif;
     min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}

/* ── Card ── */
.card{background:#1e293b;border:2px solid #667eea;border-radius:12px;width:540px;max-width:100%;
      box-shadow:0 20px 60px rgba(0,0,0,.6)}
.card-head{background:linear-gradient(135deg,#0d47a1,#1565c0);padding:16px 22px;
           display:flex;align-items:center;gap:12px;border-radius:10px 10px 0 0}
.card-head h3{margin:0;font-size:1.1rem;font-weight:700}
.card-body{padding:22px;max-height:70vh;overflow-y:auto}

/* ── Form rows ── */
.frow{display:grid;grid-template-columns:140px 14px 1fr;align-items:start;gap:8px;margin-bottom:12px}
.frow label{color:#94a3b8;font-weight:600;font-size:.85rem;padding-top:8px}
.frow span{color:#94a3b8;font-weight:700;padding-top:8px}
.form-control,.form-select{background:#0f172a;border:1px solid #334155;color:#fff;
    padding:8px;border-radius:6px;font-size:.85rem;width:100%}
.form-control:focus,.form-select:focus{border-color:#667eea;outline:none}
.sbox{background:rgba(102,126,234,.08);border:1px solid #334155;border-radius:8px;
      padding:12px;margin-bottom:12px}
.sbox-title{font-size:.7rem;font-weight:700;text-transform:uppercase;color:#00d9ff;margin-bottom:8px}
.chk{display:flex;align-items:center;gap:8px;padding:4px 0}
.chk input{width:16px;height:16px;accent-color:#667eea}
.chk label{color:#e2e8f0;font-size:.85rem;margin:0}
.chk small{color:#64748b;font-size:.73rem}
.cause-fields{display:none;margin-top:8px}
.cause-fields.show{display:block}
.cause-fields .form-control{margin-bottom:6px}
.divider{border:none;border-top:1px solid #334155;margin:10px 0}
.btn-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px}
.btn-ok{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;
        padding:10px;border-radius:6px;font-weight:700;font-size:.9rem;cursor:pointer}
.btn-ok:hover{opacity:.88}
.btn-ok:disabled{opacity:.45;cursor:not-allowed}
.btn-cancel{background:#1e293b;color:#94a3b8;border:1px solid #334155;padding:10px;
            border-radius:6px;font-weight:700;cursor:pointer}
.btn-cancel:hover{background:#334155}

/* ══════════════════════════════════════════
   PROGRESS MODAL — DEATH (blue theme)
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
    border:2px solid #1565c0;
    border-radius:18px;
    padding:38px 44px 32px;
    text-align:center;
    min-width:300px;max-width:360px;width:90%;
    box-shadow:0 0 80px rgba(21,101,192,.28), 0 30px 70px rgba(0,0,0,.55);
    animation:mIn .35s cubic-bezier(.34,1.56,.64,1);
}
@keyframes mIn{from{opacity:0;transform:scale(.82)}to{opacity:1;transform:scale(1)}}

/* Ring */
.ring-wrap{position:relative;width:140px;height:140px;margin:0 auto 10px}
.ring-wrap svg{transform:rotate(-90deg);overflow:visible;
    filter:drop-shadow(0 0 9px rgba(21,101,192,.65))}
.ring-track{fill:none;stroke:#0c1826;stroke-width:12}
.ring-fill{fill:none;stroke:url(#dRingGrad);stroke-width:12;stroke-linecap:round;
    stroke-dasharray:376.99;stroke-dashoffset:376.99;
    transition:stroke-dashoffset .5s cubic-bezier(.4,0,.2,1)}
.ring-center{position:absolute;inset:0;display:flex;flex-direction:column;
    align-items:center;justify-content:center;pointer-events:none}
.pct-num{font-family:'Segoe UI',monospace;font-size:2.2rem;font-weight:900;
    line-height:1;color:#00d9ff;text-shadow:0 0 20px rgba(0,217,255,.9)}
.pct-sym{font-size:.7rem;font-weight:700;color:#64748b;margin-top:2px}

/* ── LONART letters ── */
.lonart-wrap{
    display:flex;align-items:center;justify-content:center;gap:3px;
    height:30px;margin:8px auto 14px;
}
.lon-l{
    font-family:'Share Tech Mono',monospace;
    font-size:1.4rem;font-weight:700;
    width:22px;text-align:center;line-height:1;
    opacity:0;
    color:rgba(0,217,255,.2);
}
.lon-l.scrambling{opacity:1;color:rgba(96,165,250,.55);animation:lFlicker .09s steps(1) infinite}
.lon-l.locked{
    opacity:1 !important;
    color:#00d9ff;
    text-shadow:0 0 14px rgba(0,217,255,.95);
    animation:none !important;
}
.lon-l.locked::after{
    content:'';display:block;width:14px;height:2px;
    background:#00d9ff;box-shadow:0 0 6px rgba(0,217,255,.9);
    border-radius:2px;margin:3px auto 0;
    animation:uBlink 1.1s ease-in-out infinite;
}
@keyframes lFlicker{0%{opacity:.9}25%{opacity:.5}50%{opacity:1}75%{opacity:.6}100%{opacity:.9}}
@keyframes uBlink{0%,100%{opacity:1}50%{opacity:0}}

/* Modal text */
.modal-title{font-size:1rem;font-weight:700;color:#fff;margin-bottom:5px}
.modal-sub{font-size:.78rem;color:#64748b;line-height:1.6;margin-bottom:12px}

/* Thin bar */
.thin-bar{height:3px;background:rgba(255,255,255,.07);border-radius:4px;margin-bottom:5px;overflow:hidden}
.thin-fill{height:100%;border-radius:4px;width:0%;
    background:linear-gradient(90deg,#1565c0,#00d9ff);
    transition:width .5s cubic-bezier(.4,0,.2,1)}

.ticks{display:flex;justify-content:space-between;padding:0 3px;margin-bottom:10px}
.tick{font-size:.58rem;color:#475569}

.status-txt{font-size:.73rem;font-weight:600;font-style:italic;min-height:18px;color:#60a5fa}
</style>
</head>
<body>

<!-- ══ LONART PROGRESS MODAL ══ -->
<div class="modal-overlay" id="progressModal">
  <div class="modal-box">

    <div class="ring-wrap">
      <svg width="140" height="140" viewBox="0 0 140 140">
        <defs>
          <linearGradient id="dRingGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%"   stop-color="#1565c0"/>
            <stop offset="50%"  stop-color="#2196f3"/>
            <stop offset="100%" stop-color="#00d9ff"/>
          </linearGradient>
        </defs>
        <circle class="ring-track" cx="70" cy="70" r="60"/>
        <circle class="ring-fill" id="dRingFill" cx="70" cy="70" r="60"/>
      </svg>
      <div class="ring-center">
        <span class="pct-num" id="dPctNum">0</span>
        <span class="pct-sym">%</span>
      </div>
    </div>

    <!-- LONART -->
    <div class="lonart-wrap">
      <span class="lon-l" id="dL0">L</span>
      <span class="lon-l" id="dL1">O</span>
      <span class="lon-l" id="dL2">N</span>
      <span class="lon-l" id="dL3">A</span>
      <span class="lon-l" id="dL4">R</span>
      <span class="lon-l" id="dL5">T</span>
    </div>

    <p class="modal-title">Generating Death Statistics…</p>
    <p class="modal-sub">For 5,000+ records this takes ~1–2 minutes.<br><strong>Do not close this window.</strong></p>

    <div class="thin-bar"><div class="thin-fill" id="dThinFill"></div></div>
    <div class="ticks"><span class="tick">0</span><span class="tick">25</span><span class="tick">50</span><span class="tick">75</span><span class="tick">100</span></div>
    <p class="status-txt" id="dStatusTxt">Connecting to database…</p>

  </div>
</div>

<!-- ══ MAIN CARD ══ -->
<div class="card">
  <div class="card-head">
    <i class="fas fa-file-excel"></i>
    <h3>Death Statistics Export (Optimized)</h3>
  </div>
  <div class="card-body">
    <form id="frm" method="POST">
    <input type="hidden" name="action" value="export_death">

    <div class="frow">
      <label>Year</label><span>:</span>
      <select name="year" class="form-select" required>
        <?php for($y=$currentYear;$y>=1900;$y--): ?>
        <option value="<?=$y?>"<?=$y===$fYear?' selected':''?>><?=$y?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="frow">
      <label>Month From</label><span>:</span>
      <select name="month_start" class="form-select" id="m1" required>
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>"<?=$m===$fM1?' selected':''?>><?=$m?> — <?=$months[$m-1]?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="frow">
      <label>Month To</label><span>:</span>
      <select name="month_end" class="form-select" id="m2" required>
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?=$m?>"<?=$m===$fM2?' selected':''?>><?=$m?> — <?=$months[$m-1]?></option>
        <?php endfor; ?>
      </select>
    </div>

    <hr class="divider">

    <div class="sbox">
      <div class="sbox-title">Cause Filter</div>
      <div class="chk">
        <input type="checkbox" name="include_cause" id="ic" value="1"<?=$fInclC?' checked':''?>>
        <label for="ic">Include cause of death filter</label>
      </div>
      <div class="cause-fields<?=$fInclC?' show':''?>" id="cf">
        <input type="text" name="cause1" class="form-control" placeholder="Cause 1" value="<?=htmlspecialchars($fC1)?>">
        <input type="text" name="cause2" class="form-control" placeholder="Cause 2" value="<?=htmlspecialchars($fC2)?>">
        <input type="text" name="cause3" class="form-control" placeholder="Cause 3" value="<?=htmlspecialchars($fC3)?>">
      </div>
    </div>

    <div class="sbox">
      <div class="sbox-title">Source</div>
      <div class="chk">
        <input type="checkbox" name="source_partner" value="1"<?=$fPartner?' checked':''?>>
        <label>Partner <small>(Registry starts with "!" or yyyy-nnnnn)</small></label>
      </div>
      <div class="chk">
        <input type="checkbox" name="source_lgu" value="1"<?=$fLGU?' checked':''?>>
        <label>LGU Register <small>(Registry does NOT start with "!")</small></label>
      </div>
    </div>

    <div class="btn-row">
      <button type="submit" class="btn-ok" id="btnGo">
        <i class="fas fa-download"></i>&nbsp;Export
      </button>
      <button type="button" class="btn-cancel" onclick="history.back()">
        <i class="fas fa-times"></i>&nbsp;Cancel
      </button>
    </div>
    </form>
  </div>
</div>

<script>
/* ═══════════════════════════════════════════════
   DEATH PROGRESS ENGINE (No LONART)
═══════════════════════════════════════════════ */

const CIRC = 376.99;

const dState = { timers: [] };

function dSetPct(pct){
    const p = Math.min(100, Math.max(0, Math.round(pct)));
    document.getElementById('dRingFill').style.strokeDashoffset = CIRC - (p/100)*CIRC;
    document.getElementById('dPctNum').textContent = p;
    document.getElementById('dThinFill').style.width = p + '%';
}

function dClearAll(){
    dState.timers.forEach(clearTimeout);
    dState.timers = [];
}

const D_STAGES = [
    { pct:  6, delay:   500, msg:'Connecting to database…'             },
    { pct: 15, delay:  1200, msg:'Fetching death records…'             },
    { pct: 26, delay:  2300, msg:'Computing week fields & age…'        },
    { pct: 38, delay:  3500, msg:'Building municipality groups…'       },
    { pct: 49, delay:  5000, msg:'Building cause-of-death list…'       },
    { pct: 59, delay:  6800, msg:'Writing CSV temp file…'              },
    { pct: 68, delay:  9000, msg:'Opening Excel template via COM…'     },
    { pct: 76, delay: 11500, msg:'Pasting data into Data Source…'      },
    { pct: 84, delay: 14500, msg:'Filling ByMunicipality sheet…'       },
    { pct: 90, delay: 17500, msg:'Filling CauseOfDeath / DOA sheets…'  },
    { pct: 95, delay: 21000, msg:'Calculating & saving workbook…'      },
];

function dAnimateTo100(onDone){
    let cur = parseInt(document.getElementById('dPctNum').textContent) || 0;

    const step = () => {
        cur = Math.min(100, cur + 2);
        dSetPct(cur);

        document.getElementById('dStatusTxt').textContent =
            cur < 100
                ? 'Finalising workbook…'
                : '✅ Done! Starting download…';

        if (cur < 100) {
            requestAnimationFrame(step);
        } else {
            setTimeout(onDone, 400);
        }
    };

    requestAnimationFrame(step);
}

/* ── Cause toggle ── */
document.getElementById('ic').addEventListener('change', function(){
    document.getElementById('cf').classList.toggle('show', this.checked);
});

/* ── Form submit ── */
document.getElementById('frm').addEventListener('submit', function(e){

    e.preventDefault();

    const m1 = parseInt(document.getElementById('m1').value);
    const m2 = parseInt(document.getElementById('m2').value);

    if(m2 < m1){
        alert('Month To cannot be before Month From.');
        return;
    }

    document.getElementById('progressModal').classList.add('show');
    document.getElementById('btnGo').disabled = true;

    dSetPct(0);
    document.getElementById('dStatusTxt').textContent = 'Connecting to database…';

    D_STAGES.forEach(s => {
        const t = setTimeout(() => {
            dSetPct(s.pct);
            document.getElementById('dStatusTxt').textContent = s.msg;
        }, s.delay);

        dState.timers.push(t);
    });

    fetch('death_statistics_export.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(r => r.json())
    .then(data => {

        dClearAll();

        if(data.success){

            dAnimateTo100(() => {

                document.getElementById('progressModal').classList.remove('show');
                document.getElementById('btnGo').disabled = false;

                alert(
                    '✅ Export completed!\n' +
                    data.records.toLocaleString() +
                    ' records processed.\nDownload will start automatically.'
                );

                const a = document.createElement('a');
                a.href = data.download;
                a.download = '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);

                setTimeout(() => {
                    window.location.href = 'death_transmission.php';
                }, 2500);

            });

        } else {

            document.getElementById('progressModal').classList.remove('show');
            document.getElementById('btnGo').disabled = false;
            dSetPct(0);

            alert('❌ Export failed:\n' + (data.error || 'Unknown error'));
        }

    })
    .catch(err => {

        dClearAll();

        document.getElementById('progressModal').classList.remove('show');
        document.getElementById('btnGo').disabled = false;
        dSetPct(0);

        alert('❌ Network error: ' + err.message);
    });

});
</script>
</body>
</html>