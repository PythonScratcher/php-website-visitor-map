<?php
// ---------------------------
// Load visitor counts
// ---------------------------
$data = [];
if (file_exists("data.json")) {
    $data = json_decode(file_get_contents("data.json"), true);
}
$visitorCounts = $data["countries"] ?? [];

// ---------------------------
// Load ISO2 -> Country Names from CSV
// ---------------------------
$csvUrl = "https://raw.githubusercontent.com/gavinr/world-countries-centroids/refs/heads/master/dist/countries.csv";
$csvContent = file_get_contents($csvUrl);
$lines = explode("\n", $csvContent);

// Build ISO2 -> [name, lat, lon] map
$centroids = [];
foreach ($lines as $i => $line) {
    if ($i === 0 || trim($line) === '') continue; // skip header
    $cols = str_getcsv($line);
    // CSV format: longitude,latitude,COUNTRY,ISO,COUNTRYAFF,AFF_ISO
    $lon = (float)$cols[0];
    $lat = (float)$cols[1];
    $country = $cols[2];
    $iso2 = $cols[3];
    if($iso2) $centroids[$iso2] = ['name'=>$country,'lat'=>$lat,'lon'=>$lon];
}

// Merge visitor counts with centroids
$countries = [];
foreach ($visitorCounts as $iso2 => $count) {
    if(isset($centroids[$iso2])) {
        $countries[$iso2] = [
            'name' => $centroids[$iso2]['name'],
            'lat' => $centroids[$iso2]['lat'],
            'lon' => $centroids[$iso2]['lon'],
            'count' => $count
        ];
    } else {
        // fallback if centroid missing
        $countries[$iso2] = [
            'name' => $iso2,
            'lat' => 0,
            'lon' => 0,
            'count' => $count
        ];
    }
}

// Sort by visitor count descending
uasort($countries, fn($a,$b) => $b['count'] <=> $a['count']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Visitor Map</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
html, body { margin:0; padding:0; height:100%; font-family:sans-serif; }
#container { display:flex; height:100%; }
#sidebar { width:250px; overflow-y:auto; border-right:1px solid #ccc; padding:10px; box-sizing:border-box; }
#sidebar input { width:100%; padding:5px; margin-bottom:10px; box-sizing:border-box; }
#sidebar ul { list-style:none; padding:0; margin:0; }
#sidebar li { margin-bottom:8px; display:flex; align-items:center; cursor:pointer; }
#sidebar img { width:24px; height:18px; margin-right:8px; }
#map { flex:1; }
</style>
</head>
<body>
<div id="container">
  <div id="sidebar">
    <input type="text" id="search" placeholder="Search country...">
    <ul id="countryList">
      <?php foreach($countries as $iso2=>$info): ?>
      <li data-iso="<?=htmlspecialchars($iso2)?>">
        <img src="https://flagcdn.com/w40/<?=strtolower($iso2)?>.png" alt="<?=htmlspecialchars($info['name'])?>">
        <span><?=htmlspecialchars($info['name'])?> (<?=$info['count']?>)</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div id="map"></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const countries = <?php echo json_encode($countries); ?>;

const map = L.map('map').setView([20,0], 2);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
  attribution:'&copy; OpenStreetMap contributors'
}).addTo(map);

const markers = {};
for(const [iso, info] of Object.entries(countries)) {
    const marker = L.marker([info.lat, info.lon],{
        icon: L.icon({
            iconUrl:`https://flagcdn.com/w80/${iso.toLowerCase()}.png`,
            iconSize:[40,20],
            iconAnchor:[20,15]
        })
    }).addTo(map)
    .bindPopup(`${info.name}: ${info.count} visitor(s)`);
    markers[iso] = marker;
}

// Sidebar search
const searchInput = document.getElementById('search');
const countryList = document.getElementById('countryList');
searchInput.addEventListener('input', ()=>{
    const q = searchInput.value.toLowerCase();
    for(const li of countryList.querySelectorAll('li')) {
        const name = li.textContent.toLowerCase();
        li.style.display = name.includes(q) ? 'flex' : 'none';
    }
});

// Clicking a country zooms to marker
countryList.addEventListener('click', e=>{
    const li = e.target.closest('li');
    if(!li) return;
    const iso = li.getAttribute('data-iso');
    if(markers[iso]) {
        map.setView(markers[iso].getLatLng(), 4);
        markers[iso].openPopup();
    }
});
</script>
</body>
</html>
