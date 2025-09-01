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
function getMyData(f, pos) {
	console.log(pos);
	fetchOpenFastFood({ lat: pos.lat, lon: pos.long, distKm: pos.dist })
	.then(data => { f(data); })
	.catch(err => console.error("Fel:", err));
}