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
// Load ISO2 -> Country Names
// ---------------------------
$csvUrl = "https://raw.githubusercontent.com/lukes/ISO-3166-Countries-with-Regional-Codes/refs/heads/master/all/all.csv";
$csvContent = file_get_contents($csvUrl);
$lines = explode("\n", $csvContent);
$isoMap = [];
foreach ($lines as $i => $line) {
    if ($i === 0 || trim($line) === '') continue;
    $cols = str_getcsv($line);
    $iso2 = $cols[1];
    $name = $cols[0];
    $isoMap[$iso2] = $name;
}

// Merge visitor counts with country names
$countries = [];
foreach ($visitorCounts as $iso2 => $count) {
    $countries[$iso2] = [
        "name" => $isoMap[$iso2] ?? $iso2,
        "count" => $count
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Visitor Map Embed</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    html, body { margin:0; padding:0; height:100%; }
    #map { width:100%; height:100%; }
  </style>
</head>
<body>
<div id="map"></div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(async () => {
  const visitorData = <?php echo json_encode($countries); ?>;

  const centroids = await fetch("centroids.json").then(r => r.json());

  const map = L.map('map', {zoomControl:false, dragging:false, scrollWheelZoom:false}).setView([20,0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  for (const [iso2, info] of Object.entries(visitorData)) {
    if (!centroids[iso2]) continue;
    const [lat, lng] = centroids[iso2];
    const icon = L.icon({
      iconUrl: `https://flagcdn.com/w80/${iso2.toLowerCase()}.png`,
      iconSize: [40,30],
      iconAnchor: [20,15]
    });
    L.marker([lat, lng], {icon}).addTo(map)
      .bindPopup(`${info.name}: ${info.count} visitor(s)`);
  }
})();
</script>
</body>
</html>
