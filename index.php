<!DOCTYPE html>
<html lang="sv">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dishcovery</title>
	<style>
		p,td,th {
			font-family: Verdana, Sans-Serif;
		}
		p {
			font-weight: bold;
		}
		th {
			text-align: left;
		}
	</style>
	<script>
	let prog = 0;
	let dist = 8;
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
	function getData(lat, long, acc) {
		progress("Getting places to eat");
		document.querySelector("#dist").innerText = dist+"+"+acc;
		ajax(draw, "get.php?lat="+lat+"&long="+long+"&dist="+(Number(dist)+Number(acc)));
	}
	function ajax(f, url) {
		var xhr = new XMLHttpRequest();
		xhr.open("GET", url, true);
		xhr.onload = function() {
			if (xhr.status === 200) {
				var data = JSON.parse(xhr.responseText);
				console.log(data);
				if(data !== false) {
					f(data);
				} else {
					alert("Something weird happened. Try again.");
				}
			} else {
				alert("Something went wrong. Try again.");
			}
		};
		xhr.send();
	}
	function draw(data) {
		progress("Populating list");
		let out = document.querySelector("#out");
		out.innerHTML = "";
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

				//td[1].innerText = {"closed": "Stängd", "open": "Öppet", "soon": "Öppnar snart"}[store.state];
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
<p>Search radius: <span id="dist">5</span>km<input type="range" min="1" max="100" step="1" value="5" style="width: 100%;" onchange="updDist(this.value);" id="distinput"></p>
<table><thead><th></th><th>Distance</th><th colspan=3>Name</th><th>Open</th><th>Close</th></tr></thead><tbody id="out"></tbody></table>
<p id="log">Loading <span id="progress"></span></p>
</body>
</html>