<!DOCTYPE html>
<html lang="sv">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dishcovery</title>
	<link rel="stylesheet" href="css.css?r=<?php echo rand(0, 9999999); ?>">
	<script src="fastfood.js?r=<?php echo rand(0, 9999999); ?>"></script>
	<script src="js.js?r=<?php echo rand(0, 9999999); ?>"></script>
	<script>
	let prog = 0;
	let dist = <?php
$default_dist = 1.5;
$default_dist = 7;
echo $default_dist;	
?>;
	function progress(stage = "done") {
		document.querySelector("#progress").innerText = Math.round((prog/3)*100)+"% "+stage;
		prog++;
	}
	window.addEventListener("load", function() {
		reloadData();
	});
	function reloadData() {
		document.querySelector("#distinput").disabled = true;
		document.querySelector("#log").style.display = "block";
		prog = 0;
		progress("Getting position");
		getLocation(function(pos) {
			getData(pos.coords.latitude, pos.coords.longitude, Math.round(pos.coords.accuracy/1000));
		});
	}
	function getLocation(f) {
		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(function(d) {
				success(d, f);
			}, error);
		} else {
			alert("Can't get location. Did you allow it?");
		}
	}
	function success(position, f) {
		f(position);
	}
	function error() {
		alert("Sorry, no position available.");
	}
	let maxDist = dist;
	function getData(lat, long, acc) {
		acc = Math.min(acc, 2);
		progress("Getting places to eat");
		document.querySelector("#dist").innerText = dist+"+"+acc;
		maxDist = Number(dist)+Number(acc);
		getMyData(draw, {
			long: long,
			lat: lat,
			dist: maxDist
		});
	}
	function mEl(tag = "DIV") {
		return document.createElement(tag);
	}
	function draw(data) {
		progress("Populating list");
		let out = document.querySelector("#out");
		out.innerHTML = "";
<?php
if(!isset($_GET["tables"])) {
?>
		//		Cards
		data.sort(function(a, b) {
			return Number(a.dist)-Number(b.dist);
		});
		let lim = 0;
		console.log(data);
		for(let store of data) {
			let card = mEl("DIV");
			card.classList.add("card");
			card.classList.add(store.state);

			let iconContainer = mEl("SPAN");
			let dist = mEl("DIV");
			dist.classList.add("circle");
			let distPercent = store.dist/maxDist;
			dist.style.setProperty("--size", (((Math.floor(distPercent * 10)/10)) * 90) + "%");
			iconContainer.appendChild(dist);
			let distText = mEl("P");
			distText.innerHTML = "<span>"+(Math.round(store.dist*10)/10)+"</span><span>km</span>";
			dist.appendChild(distText);
			card.appendChild(iconContainer);

			let nameContainer = mEl("SPAN");
			nameContainer.innerText = store.name;
			card.appendChild(nameContainer);

			let contactContainer = mEl("SPAN");
			let link = mEl("a");
			if(store.contact.substring(0, 4) == "http") {
				link.href = store.contact;
				link.target = "_blank";
				linkImage = mEl("IMG");
				linkImage.src = "img/url.png";
				link.appendChild(linkImage);
			} else if(store.contact.trim().length > 0) {
				link.href = "tel:"+store.contact;
				link.target = "_blank";
				linkImage = mEl("IMG");
				linkImage.src = "img/phone.png";
				link.appendChild(linkImage);
			} else {
				link = mEl("P");
				link.innerText = store.contact
			}
			contactContainer.appendChild(link);
			card.appendChild(contactContainer);
			
			let mapContainer = mEl("SPAN");
			let adress = store.adress;
			if(adress.substring(0, 4) == "http") {
				adress = "<a href='"+adress+"' target='_blank'><img src='img/map.png'></a>";
			}
			mapContainer.innerHTML = adress;
			card.appendChild(mapContainer);
						
			out.appendChild(card);
		}
		document.querySelectorAll(".card").forEach(el => observer.observe(el));

		if(out.children.length === 0) {
			out.innerText = "No places inside the radius.";
		}
<?php
} else {
?>
		//		Table
		if(data.length === 0) {
			out.innerHTML = "<tr><td colspan=7>No place within the radius</td></tr>";
		} else {
			data.sort(function(a, b) {
				return Number(a.dist)-Number(b.dist);
			});
			for(let store of data) {
				let row = document.createElement("TR");
				let td = [];
				for(let c = 0; c < 7; c++) {
					td[c] = document.createElement("TD");
				}
				let icon = document.createElement("IMG");
				icon.style.width = "20px";
				icon.src = "img/"+store.state+".png";
				td[0].appendChild(icon);

				td[1].innerText = (Math.round(store.dist*10)/10)+"km";
				td[2].innerText = store.name;
				let link = store.contact;
				if(link.substring(0, 4) == "http") {
					link = "<a href='"+link+"' target='_blank'><img src='img/url.png' style='width: 20px;'></a>";
				} else if(link.trim().length > 0) {
					link = "<a href='tel:"+link+"' target='_blank'><img src='img/phone.png' style='width: 20px;'></a>";
				}
				td[3].innerHTML = link;
				let adress = store.adress;
				if(adress.substring(0, 4) == "http") {
					adress = "<a href='"+adress+"("+store.name+")' target='_blank'><img src='img/map.png' style='width: 20px;'></a>";
					td[4].innerHTML = adress;
				} else {
					td[4].innerText = adress;
				}
				td[5].innerText = store.start;
				td[6].innerText = store.end;
				for(let c of td) {
					row.appendChild(c);
				}
				out.appendChild(row);
			}
		}
<?php
}
?>
		document.querySelector("#log").style.display = "none";
		reloadTimer = setTimeout(reloadData, 60000*5);
		document.querySelector("#distinput").disabled = false;
	}
	let reloadTimer = null;
	function updDist(val) {
		clearTimeout(reloadTimer);
		dist = val;
		document.querySelector("#dist").innerText = dist;
		reloadTimer = setTimeout(reloadData, 1000);
	}
</script>
</head>
<body>
	<header>
		<div><img src="img/logo.png?new"></div>
		<div><h1>Dishcovery</h1>
		<p id="log">Loading <span id="progress"></span></p>
		<p>Search radius: <span id="dist"><?php echo $default_dist;	?></span>km</p></div>
		<input type="range" min="0.5" max="20" step="0.5" value="<?php echo $default_dist;	?>" onchange="updDist(this.value);" id="distinput">
	</header>
	<main>
		<section>
<?php
if(!isset($_GET["tables"])) {
?>
			<div id="out"></div>
<?php
} else {
?>
			<table><thead><th></th><th>Distance</th><th colspan=3>Name</th><th>Open</th><th>Close</th></tr></thead><tbody id="out"></tbody></table>
<?php
}
?>
		</section>
	</main>
	<footer>
<?php
if(!isset($_GET["tables"])) {
?>
		<a href="./?tables">Byt till tabellvy</a>
<?php
} else {
?>
		<a href=".">Byt till standardvy</a>
<?php
}
?>
		<p>&copy; Copyright David Andersson 2025</p>
	</footer>
</body>
</html>