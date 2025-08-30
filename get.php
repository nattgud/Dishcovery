<?php
if(!isset($_GET["lat"]) || !isset($_GET["long"]) || !isset($_GET["dist"])) {
	echo "false";
	exit();
}
$days = [
	"sunday" =>		0,
	"monday" =>		1,
	"tuesday" =>	2,
	"wednesday" =>	3,
	"thursday" =>	4,
	"friday" =>		5,
	"saturday" =>	6
];
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host   = $_SERVER['HTTP_HOST'];
$tdata = file_get_contents($scheme."://".$host."/mat/getdata.php?lat=".((isset($_GET["lat"]))?urlencode($_GET["lat"]):"")."&long=".((isset($_GET["long"]))?urlencode($_GET["long"]):"")."&dist=".((isset($_GET["dist"]))?intval($_GET["dist"]):15));
$data = json_decode($tdata, true);
$finalData = [];
foreach($data as $place) {
	$name = $place["name"];
	$times = [];
	foreach($place["hours"] as $day => $ddata) {
		foreach($ddata as $time) {
			array_push($finalData, [
				"store" =>	$name,
				"dist" =>	$place["dist"],
				"day" =>	$days[$day],
				"start" =>	$time["open"],
				"end" =>	$time["close"],
				"address" =>$place["address"],
				"contact" =>$place["contact"]
			]);
		}
	}
}
$data = $finalData;
$final = [];
$day = intval(date("w"));
if(intval(date("i")) >= 20) {
	$hour = intval(date("H"))+1;
} else {
	$hour = intval(date("H"));
}
foreach($data as $row) {
	$state = "closed";
	if($row["day"] == $day) {
		$end = intval($row["end"]);
		if($end < intval($row["start"])) {
			$end += 24;
		}
		if((intval(date("H")) >= intval($row["start"])) && ($hour < $end)) {
			$state = "open";
		} elseif((intval(date("H"))+1 >= intval($row["start"])) && ($hour < $end)) {
			$state = "soon";
		}
		if($state != "closed") {
			array_push($final, [
				"name" =>	$row["store"],
				"start" =>	$row["start"],
				"end" =>	$row["end"],
				"state" =>	$state,
				"dist" =>	$row["dist"],
				"adress" =>	$row["address"],
				"contact" =>$row["contact"]
			]);
		}
	}
}
echo json_encode($final);
?>