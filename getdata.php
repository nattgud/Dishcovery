<?php
$USER_LAT = null;
$USER_LON = null; 
$MAX_KM   = 10; 

header('Content-Type: application/json; charset=utf-8');

$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : $USER_LAT;
$lon = isset($_GET['long']) ? floatval($_GET['long']) : $USER_LON;
$maxKm = isset($_GET['dist']) ? floatval($_GET['dist']) : $MAX_KM;

if ($lat === null || $lon === null) { $lat = 56.029; $lon = 14.156; }

function http_get_json($url, $payload=null) {
    $opts = [
        'http' => [
            'method' => $payload ? 'POST' : 'GET',
            'header' => "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n",
            'content' => $payload ? http_build_query(['data' => $payload]) : null,
            'timeout' => 25,
        ]
    ];
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) { http_response_code(502); echo json_encode(['error'=>'Failed to fetch Overpass'], JSON_UNESCAPED_UNICODE); exit; }
    $json = json_decode($raw, true);
    if (!$json) { http_response_code(502); echo json_encode(['error'=>'Invalid JSON from Overpass'], JSON_UNESCAPED_UNICODE); exit; }
    return $json;
}

$WEEK = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$DAYMAP = ['Mo'=>'monday','Tu'=>'tuesday','We'=>'wednesday','Th'=>'thursday','Fr'=>'friday','Sa'=>'saturday','Su'=>'sunday'];

function blank_hours() {
    return ['monday'=>[], 'tuesday'=>[], 'wednesday'=>[], 'thursday'=>[], 'friday'=>[], 'saturday'=>[], 'sunday'=>[]];
}
function hours_add_range(&$hours, $days, $open, $close) {
    foreach ($days as $d) { $hours[$d][] = ['open'=>$open,'close'=>$close]; }
}
function normalize_time($t) {
    if (preg_match('/^\d{1,2}:\d{2}$/', $t)) { [$h,$m]=explode(':',$t); return str_pad($h,2,'0',STR_PAD_LEFT).":$m"; }
    if (preg_match('/^\d{1,2}$/', $t))      { $h=str_pad($t,2,'0',STR_PAD_LEFT); return "$h:00"; }
    return null;
}

function parse_opening_hours_basic($s) {
    global $WEEK,$DAYMAP;
    $hours = blank_hours();
    if (!$s || !is_string($s)) return $hours;
    $s = trim($s);
    if (preg_match('/^24\/7$/i',$s)) { foreach ($WEEK as $d){ $hours[$d][]= ['open'=>'00:00','close'=>'24:00']; } return $hours; }
    $parts = preg_split('/\s*;\s*/',$s);
    foreach ($parts as $part) {
        if ($part==='') continue;
        if (!preg_match('/^([A-Za-z, -]+)\s+([\d:]{1,5})-([\d:]{1,5})/',$part,$m)) {
            if (preg_match('/^([\d:]{1,5})-([\d:]{1,5})$/',$part,$mm)) {
                $o=normalize_time($mm[1]); $c=normalize_time($mm[2]); if($o&&$c) hours_add_range($hours,$WEEK,$o,$c);
            }
            continue;
        }
        $daysExpr=trim($m[1]); $open=normalize_time($m[2]); $close=normalize_time($m[3]); if(!$open||!$close) continue;
        $days=[]; foreach (preg_split('/\s*,\s*/',$daysExpr) as $chunk) {
            $chunk=trim($chunk); if($chunk==='') continue;
            if (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)-(Mo|Tu|We|Th|Fr|Sa|Su)$/',$chunk,$dm)) {
                $order=['Mo','Tu','We','Th','Fr','Sa','Su']; $i1=array_search($dm[1],$order,true); $i2=array_search($dm[2],$order,true);
                if($i1!==false && $i2!==false){
                    if($i1<=$i2){ for($i=$i1;$i<=$i2;$i++) $days[]=$DAYMAP[$order[$i]]; }
                    else{ for($i=$i1;$i<count($order);$i++) $days[]=$DAYMAP[$order[$i]]; for($i=0;$i<=$i2;$i++) $days[]=$DAYMAP[$order[$i]]; }
                }
            } elseif (preg_match('/^(Mo|Tu|We|Th|Fr|Sa|Su)$/',$chunk,$ds)) { $days[]=$DAYMAP[$ds[1]]; }
        }
        if (empty($days)) $days=$WEEK;
        hours_add_range($hours,$days,$open,$close);
    }
    foreach ($WEEK as $d){ if(!isset($hours[$d])) $hours[$d]=[]; }
    return $hours;
}
function haversine_km($lat1,$lon1,$lat2,$lon2){
    $R=6371.0088;
    $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
    $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
    return $R*2*asin(min(1,sqrt($a)));
}


$radiusMeters = intval($maxKm*1000);
$q = <<<QL
[out:json][timeout:25];
(
  // Klassisk snabbmat
  node(around:$radiusMeters,$lat,$lon)["amenity"="fast_food"];
  way(around:$radiusMeters,$lat,$lon)["amenity"="fast_food"];
  relation(around:$radiusMeters,$lat,$lon)["amenity"="fast_food"];

  // Restauranger med snabbmats-cuisine
  node(around:$radiusMeters,$lat,$lon)["amenity"="restaurant"]["cuisine"~"$cuisineRegex"];
  way(around:$radiusMeters,$lat,$lon)["amenity"="restaurant"]["cuisine"~"$cuisineRegex"];
  relation(around:$radiusMeters,$lat,$lon)["amenity"="restaurant"]["cuisine"~"$cuisineRegex"];
);
out center tags;
QL;

$resp = http_get_json('https://overpass-api.de/api/interpreter', $q);

$out = [];
$seen = [];

if (!empty($resp['elements'])) {
    foreach ($resp['elements'] as $el) {
        $tags = $el['tags'] ?? [];
        $name = $tags['name'] ?? null;
        if (!$name) continue;

        $isFast = (
			(($tags['amenity'] ?? '') === 'fast_food') ||
			(isset($tags['cuisine']) && preg_match("/$cuisineRegex/u", $tags['cuisine']))
		);
		if (!$isFast) continue;

        $oh = $tags['opening_hours'] ?? '';
        if ($oh === '') continue;

        if (isset($el['lat']) && isset($el['lon'])) {
            $plat = floatval($el['lat']); $plon = floatval($el['lon']);
        } elseif (isset($el['center']['lat']) && isset($el['center']['lon'])) {
            $plat = floatval($el['center']['lat']); $plon = floatval($el['center']['lon']);
        } else {
            continue;
        }

        $dist = haversine_km($lat, $lon, $plat, $plon);
        if ($dist > $maxKm) continue;

        $key = ($el['id'] ?? uniqid()) . '|' . $name;
        if (isset($seen[$key])) continue;
        $seen[$key] = true;

        $hoursRaw = parse_opening_hours_basic($oh);
        $hours = [];
        foreach ($hoursRaw as $day=>$ranges) {
            if (!empty($ranges)) $hours[$day] = $ranges;
        }
        if (empty($hours)) continue;

		$address = "https://www.google.com/maps?q=".$el['lat'].",".$el['lon'];

		$contact = (isset($tags["website"])?$tags["website"]:(isset($tags["phone"])?$tags["phone"]:""));

        $out[] = [
			'name'    =>	$name,
			'hours'   =>	$hours,
			'dist'    =>	$dist,
			'address' =>	$address,
			'contact' =>	$contact
		];
    }
}

usort($out, fn($a,$b)=>strcoll($a['name'],$b['name']));

echo json_encode($out, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
