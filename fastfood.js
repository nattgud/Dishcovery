// fastfood.js
// Publika API:n (globalt):
//   - window.fetchOpenFastFood({ lat, lon, distKm })
//   - window.fetchOpenFastFoodCb(opts, onSuccess, onError)
//
// Returnerar EXAKT samma struktur som din get.php:
// [ { name, start, end, state, dist, adress, contact }, ... ]

(function () {
	const OVERPASS_PRIMARY = "https://overpass-api.de/api/interpreter";
	const OVERPASS_FALLBACK = "https://overpass.kumi.systems/api/interpreter";
	const WEEK = ["monday","tuesday","wednesday","thursday","friday","saturday","sunday"];
	const DAYMAP = { Mo:"monday", Tu:"tuesday", We:"wednesday", Th:"thursday", Fr:"friday", Sa:"saturday", Su:"sunday" };
  
	// -------- Helpers --------
	function blankHours() {
	  return { monday:[], tuesday:[], wednesday:[], thursday:[], friday:[], saturday:[], sunday:[] };
	}
	function hoursAddRange(hours, days, open, close) {
	  for (const d of days) (hours[d] = hours[d] || []).push({ open, close });
	}
	function normalizeTime(t) {
	  if (/^\d{1,2}:\d{2}$/.test(t)) { const [h,m]=t.split(":"); return `${h.padStart(2,"0")}:${m}`; }
	  if (/^\d{1,2}$/.test(t))       { return `${String(t).padStart(2,"0")}:00`; }
	  return null;
	}
	// Parser för vanliga opening_hours: 24/7, "Mo-Fr 10:00-22:00", "Sa,Su 11-21", och multiintervall "Mo-Th 11-14,16-20"
	function parseOpeningHoursBasic(s) {
	  if (!s || typeof s !== "string") return blankHours();
	  s = s.trim();
	  const hours = blankHours();
  
	  if (/^24\/7$/i.test(s)) {
		WEEK.forEach(d => hours[d].push({ open:"00:00", close:"24:00" }));
		return hours;
	  }
  
	  const parts = s.split(/\s*;\s*/);
	  for (const part of parts) {
		if (!part) continue;
  
		// Case A: "DaysExpr times[,times]*"
		const m = part.match(/^([A-Za-z ,\-]+)\s+(.+)$/);
		if (m) {
		  const daysExpr = m[1].trim();
		  const timesExpr = m[2].trim();
		  let days = [];
  
		  for (let chunk of daysExpr.split(/\s*,\s*/)) {
			chunk = chunk.trim(); if (!chunk) continue;
			const dm = chunk.match(/^(Mo|Tu|We|Th|Fr|Sa|Su)-(Mo|Tu|We|Th|Fr|Sa|Su)$/);
			if (dm) {
			  const order = ["Mo","Tu","We","Th","Fr","Sa","Su"];
			  const i1 = order.indexOf(dm[1]), i2 = order.indexOf(dm[2]);
			  if (i1 !== -1 && i2 !== -1) {
				if (i1 <= i2) for (let i = i1; i <= i2; i++) days.push(DAYMAP[order[i]]);
				else { for (let i = i1; i < order.length; i++) days.push(DAYMAP[order[i]]);
					   for (let i = 0; i <= i2; i++) days.push(DAYMAP[order[i]]); }
			  }
			} else {
			  const ds = chunk.match(/^(Mo|Tu|We|Th|Fr|Sa|Su)$/);
			  if (ds) days.push(DAYMAP[ds[1]]);
			}
		  }
		  if (!days.length) days = WEEK;
  
		  // Hitta ALLA intervall i timesExpr
		  const ranges = [...timesExpr.matchAll(/([\d:]{1,5})\s*-\s*([\d:]{1,5})/g)];
		  for (const r of ranges) {
			const o = normalizeTime(r[1]), c = normalizeTime(r[2]);
			if (o && c) hoursAddRange(hours, days, o, c);
		  }
		  continue;
		}
  
		// Case B: "10:00-22:00" => alla dagar
		const mm = part.match(/^([\d:]{1,5})\s*-\s*([\d:]{1,5})$/);
		if (mm) {
		  const o = normalizeTime(mm[1]), c = normalizeTime(mm[2]);
		  if (o && c) hoursAddRange(hours, WEEK, o, c);
		}
	  }
	  WEEK.forEach(d => { if (!hours[d]) hours[d] = []; });
	  return hours;
	}
  
	function haversineKm(lat1, lon1, lat2, lon2) {
	  const R = 6371.0088;
	  const dLat = (lat2 - lat1) * Math.PI/180;
	  const dLon = (lon2 - lon1) * Math.PI/180;
	  const a = Math.sin(dLat/2)**2 +
				Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) *
				Math.sin(dLon/2)**2;
	  return R * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
	}
  
	async function httpPostOverpass(url, q) {
	  const resp = await fetch(url, {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
		body: new URLSearchParams({ data: q }).toString()
	  });
	  const text = await resp.text();
	  if (!resp.ok) throw new Error(`Overpass ${resp.status}: ${text.slice(0, 200)}...`);
	  try { return JSON.parse(text); }
	  catch { throw new Error("Overpass gav icke-JSON:\n" + text.slice(0, 300)); }
	}
  
	// -------- getdata.php + Overpass (utan cuisine-filter) --------
	async function fetchRawPlaces({ lat, lon, distKm }) {
	  lat = Number.isFinite(+lat) ? +lat : 56.029;      // default Kristianstad
	  lon = Number.isFinite(+lon) ? +lon : 14.156;
	  distKm = Number.isFinite(+distKm) && +distKm > 0 ? +distKm : 10;
  
	  const radiusMeters = Math.max(1, Math.round(distKm * 1000));
  
	  const q = `
  [out:json][timeout:25];
  (
	node(around:${radiusMeters},${lat},${lon})["amenity"="fast_food"];
	way(around:${radiusMeters},${lat},${lon})["amenity"="fast_food"];
	relation(around:${radiusMeters},${lat},${lon})["amenity"="fast_food"];
  
	node(around:${radiusMeters},${lat},${lon})["amenity"="restaurant"];
	way(around:${radiusMeters},${lat},${lon})["amenity"="restaurant"];
	relation(around:${radiusMeters},${lat},${lon})["amenity"="restaurant"];
  );
  out center tags;`.trim();
  
	  let resp;
	  try { resp = await httpPostOverpass(OVERPASS_PRIMARY, q); }
	  catch { resp = await httpPostOverpass(OVERPASS_FALLBACK, q); }
  
	  const out = [];
	  const seen = new Set();
  
	  for (const el of (resp.elements || [])) {
		const tags = el.tags || {};
		const name = tags.name;
		if (!name) continue;
  
		const amen = tags.amenity || "";
		if (amen !== "fast_food" && amen !== "restaurant") continue;
  
		const oh = tags.opening_hours || "";
		if (!oh) continue;
  
		let plat, plon;
		if (typeof el.lat === "number" && typeof el.lon === "number") {
		  plat = el.lat; plon = el.lon;
		} else if (el.center && typeof el.center.lat === "number" && typeof el.center.lon === "number") {
		  plat = el.center.lat; plon = el.center.lon;
		} else continue;
  
		const dist = haversineKm(lat, lon, plat, plon);
		if (dist > distKm) continue;
  
		const key = String(el.id || "") + "|" + name;
		if (seen.has(key)) continue;
		seen.add(key);
  
		const hoursRaw = parseOpeningHoursBasic(oh);
		const hours = {};
		for (const [day, ranges] of Object.entries(hoursRaw)) {
		  if (ranges && ranges.length) hours[day] = ranges;
		}
		if (!Object.keys(hours).length) continue;
  
		const address = `https://www.google.com/maps?q=${plat},${plon}`;
		const contact = (tags.website || tags.phone || "");
  
		out.push({ name, hours, dist, address, contact });
	  }
  
	  out.sort((a, b) => a.name.localeCompare(b.name, "sv"));
	  return out;
	}
  
	// -------- get.php (dagens öppna/snart) --------
	function filterOpenSoonToday(rawPlaces, now = new Date()) {
	  // PHP: sunday=0..saturday=6 (JS Date.getDay() matchar)
	  const dayIdx = now.getDay();
	  const dayName = ["sunday","monday","tuesday","wednesday","thursday","friday","saturday"][dayIdx];
  
	  const curH = now.getHours();
	  const curM = now.getMinutes();
	  // PHP-logik: minute >= 20 => hour = H+1, annars H
	  const hourThreshold = (curM >= 20) ? curH + 1 : curH;
  
	  const final = [];
	  for (const place of rawPlaces) {
		const ranges = place.hours?.[dayName] || [];
		for (const { open, close } of ranges) {
		  const startH = parseInt(open, 10);  // "08:30" -> 8
		  let endH = parseInt(close, 10);     // "24:00" -> 24
		  if (endH < startH) endH += 24;      // över midnatt
  
		  let state = "closed";
		  // EXAKT samma villkor som i din PHP:
		  if ((curH >= startH) && (hourThreshold < endH)) {
			state = "open";
		  } else if (((curH + 1) >= startH) && (hourThreshold < endH)) {
			state = "soon";
		  }
  
		  if (state !== "closed") {
			final.push({
			  name:   place.name,
			  start:  open,
			  end:    close,
			  state,
			  dist:   place.dist,
			  adress: place.address,  // behåll stavningen från PHP
			  contact:place.contact
			});
		  }
		}
	  }
	  return final;
	}
  
	// -------- Publikt API --------
	async function fetchOpenFastFood(opts = {}) {
	  const raw = await fetchRawPlaces({ lat: opts.lat, lon: opts.lon, distKm: opts.distKm });
	  return filterOpenSoonToday(raw, new Date());
	}
	function fetchOpenFastFoodCb(opts, onSuccess, onError) {
	  fetchOpenFastFood(opts).then(r => onSuccess && onSuccess(r)).catch(e => onError && onError(e));
	}
  
	window.fetchOpenFastFood = fetchOpenFastFood;
	window.fetchOpenFastFoodCb = fetchOpenFastFoodCb;
  })();
  