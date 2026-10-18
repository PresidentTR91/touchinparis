<?php
declare(strict_types=1);
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

function parseWorkbook(string $filename): array {
    $zip=new ZipArchive();
    if($zip->open($filename)!==true) throw new RuntimeException('Export Google Sheets invalide.');
    try {
        $xml=function(string $name) use($zip): SimpleXMLElement {
            $raw=$zip->getFromName($name);
            if($raw===false || strlen($raw)>12000000 || stripos($raw,'<!DOCTYPE')!==false) throw new RuntimeException('Structure du classeur invalide.');
            $doc=simplexml_load_string($raw,SimpleXMLElement::class,LIBXML_NONET|LIBXML_NOCDATA);
            if($doc===false) throw new RuntimeException('XML invalide.');
            return $doc;
        };
        $strings=[];
        if($zip->locateName('xl/sharedStrings.xml')!==false) {
            foreach($xml('xl/sharedStrings.xml')->si as $s) { $value=''; foreach($s->xpath('.//*[local-name()="t"]') as $part) $value.=(string)$part; $strings[]=$value; }
        }
        $relations=[];
        foreach($xml('xl/_rels/workbook.xml.rels')->Relationship as $r) {
            $target=(string)$r['Target'];
            if(str_contains($target,'..')||str_contains($target,':')) continue;
            $relations[(string)$r['Id']]=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
        }
        $sheets=[]; $errors=[];
        foreach($xml('xl/workbook.xml')->sheets->sheet as $sheet) {
            $name=(string)$sheet['name']; $rid=(string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if(!isset($relations[$rid])) throw new RuntimeException('Onglet inaccessible.');
            $rows=[];
            foreach($xml($relations[$rid])->sheetData->row as $row) {
                $rowNumber=(int)$row['r']; if($rowNumber>2000) throw new RuntimeException('Classeur trop volumineux.');
                $cells=[]; $filled=false;
                foreach($row->c as $c) {
                    preg_match('/^([A-Z]+)/',(string)$c['r'],$m); $column=0;
                    foreach(str_split($m[1]??'A') as $letter) $column=$column*26+ord($letter)-64;
                    if($column>100) throw new RuntimeException('Trop de colonnes.');
                    $type=(string)$c['t']; $raw=(string)$c->v; $value=null;
                    if($type==='s') $value=$strings[(int)$raw]??'';
                    elseif($type==='inlineStr') { $value=''; foreach($c->xpath('.//*[local-name()="t"]') as $part) $value.=(string)$part; }
                    elseif($type==='e') { $value=$raw; $errors[]=['sheet'=>$name,'cell'=>(string)$c['r'],'error'=>$raw]; }
                    elseif($raw!=='') $value=($type==='str')?$raw:(is_numeric($raw)?0+$raw:$raw);
                    if($value!==null && $value!=='') $filled=true;
                    $cells[$column-1]=$value;
                }
                if($filled) { $count=$cells?max(array_keys($cells))+1:0; $values=array_fill(0,$count,null); foreach($cells as $i=>$v) $values[$i]=$v; $rows[]=['row'=>$rowNumber,'values'=>$values]; }
            }
            $sheets[$name]=$rows;
        }
        foreach(['Accueil','Equipes','Matchs poules','Classement poules','Ranking 1-24','Cup','Plate','Bowl','Classement final','Reglement'] as $required) if(!isset($sheets[$required])) throw new RuntimeException('Onglet attendu absent : '.$required);
        if(count(array_filter($sheets['Equipes'],fn($r)=>$r['row']>=6))<1) throw new RuntimeException('Aucune équipe trouvée.');
        return ['sheets'=>$sheets,'errors'=>$errors,'updatedAt'=>gmdate('c')];
    } finally { $zip->close(); }
}

function downloadWorkbook(string $sheetId): array {
    if(!preg_match('/^[a-zA-Z0-9_-]+$/',$sheetId)) throw new RuntimeException('Identifiant de fichier invalide.');
    $file=tempnam(sys_get_temp_dir(),'tip-sheet-');
    $buffer=fopen($file,'wb'); $bytes=0;
    $ch=curl_init('https://docs.google.com/spreadsheets/d/'.$sheetId.'/export?format=xlsx');
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'TouchInParisCompetition/1.0',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use($buffer,&$bytes){$bytes+=strlen($chunk);if($bytes>8000000)return 0;return fwrite($buffer,$chunk);}]);
    try {
        $ok=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_HTTP_CODE); fclose($buffer); $buffer=null;
        if($ok===false||$status!==200) throw new RuntimeException('Lecture Google Sheets indisponible.');
        return parseWorkbook($file);
    } finally { if(is_resource($buffer))fclose($buffer); curl_close($ch); @unlink($file); }
}

function competitionData(array $config): array {
    $cacheFile=__DIR__.'/cache.php';
    $cached=is_file($cacheFile)?require $cacheFile:null;
    $data=is_array($cached)?$cached:require __DIR__.'/snapshot.php';
    $data['mode']=is_array($cached)?'cache':'snapshot';
    $age=time()-strtotime($data['updatedAt']);
    $attemptFile=__DIR__.'/sync-attempt.php';
    $lastAttempt=is_file($attemptFile)?(int)(require $attemptFile):0;
    if($age>=60 && time()-$lastAttempt>=60) {
        $lock=fopen(__DIR__.'/sync-lock.php','c+');
        if($lock && flock($lock,LOCK_EX|LOCK_NB)) {
            try {
                file_put_contents($attemptFile,'<?php return '.time().';',LOCK_EX);
                $fresh=downloadWorkbook($config['sheet_id']);
                if($fresh['errors']) throw new RuntimeException('Le Sheet contient des erreurs de calcul.');
                $code='<?php return '.var_export($fresh,true).';';
                $temporary=__DIR__.'/cache-new.php';
                if(file_put_contents($temporary,$code,LOCK_EX)===false || !rename($temporary,$cacheFile)) throw new RuntimeException('Cache non accessible en écriture.');
                if(function_exists('opcache_invalidate'))opcache_invalidate($cacheFile,true);
                $data=$fresh; $data['mode']='live';
            } catch(Throwable $e) { $data['warning']='Synchronisation indisponible : affichage de la dernière copie disponible.'; }
            finally { flock($lock,LOCK_UN); fclose($lock); }
        } elseif($lock) fclose($lock);
    }
    if(time()-strtotime($data['updatedAt'])>180) $data['warning']='Données non actualisées : dernière copie affichée. Vérifiez le partage Google Sheets et la connexion.';
    if($data['mode']==='snapshot') $data['warning']='Copie initiale du classeur. La synchronisation Google Sheets n’a pas encore abouti sur ce serveur.';
    $data['referees']=require __DIR__.'/referees.php';
    return $data;
}
