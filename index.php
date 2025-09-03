<?php
// index.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Visitors by Country</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Leaflet (free) -->
  <link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
  />
  <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
  ></script>

  <!-- TopoJSON + world countries (free data) -->
  <script src="https://unpkg.com/topojson-client@3/dist/topojson-client.min.js"></script>
  <script src="https://unpkg.com/world-atlas@2/countries-110m.json"></script>

  <!-- Turf.js for centroids -->
  <script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

  <style>
    html, body, #map { height: 100%; margin: 0; }
    .flag-icon {
      width: 32px; height: 24px;
      border: 1px solid rgba(0,0,0,0.25);
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
      background: #fff;
    }
    .leaflet-popup-content {
      margin: 8px 10px;
    }
    .legend {
      position: absolute; bottom: 12px; left: 12px;
      background: rgba(255,255,255,0.9); padding: 8px 10px; border-radius: 4px;
      font: 12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      box-shadow: 0 1px 4px rgba(0,0,0,0.2);
    }
  </style>
</head>
<body>
  <div id="map"></div>
  <div class="legend">
    <b>Visitors Map</b><br/>
    • Flag = country with ≥1 visitor<br/>
    • Click a flag for total visitors
  </div>

  <script>
    // Basic Leaflet map
    const map = L.map('map', { worldCopyJump: true }).setView([20, 0], 2);
    L.tileLayer(
      // Free tile source; switch to your own if you have one
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      { maxZoom: 5, attribution: '&copy; OpenStreetMap' }
    ).addTo(map);

    // load visitor counts
    async function loadCounts() {
      const res = await fetch('data.json', { cache: 'no-store' });
      if (!res.ok) return { countries: {} };
      return res.json();
    }

    function iso2fromTopoFeature(f) {
      // Different datasets label differently; world-atlas 110m includes "iso_a3"/"name"
      // We map from A3 to A2 via a minimal lookup when needed.
      // Prefer f.properties.iso_a2 if present, else try to map.
      const p = f.properties || {};
      if (p.iso_a2 && p.iso_a2 !== '-99') return p.iso_a2;
      // fallback: quick mapping for exceptions; for full coverage, extend this map
      const A3 = p.iso_a3;
      // minimal a3→a2 fallback (extend if you need edge islands)
      const a3to2 = {
        USA: 'US', CAN: 'CA', MEX: 'MX', BRA: 'BR', ARG: 'AR', CHN: 'CN', IND: 'IN',
        RUS: 'RU', AUS: 'AU', ZAF: 'ZA', GBR: 'GB', FRA: 'FR', DEU: 'DE', ESP: 'ES',
        ITA: 'IT', JPN: 'JP', KOR: 'KR', SAU: 'SA', TUR: 'TR', IRN: 'IR', EGY: 'EG',
        NGA: 'NG', ETH: 'ET', IDN: 'ID', PAK: 'PK', POL: 'PL', NLD: 'NL', SWE: 'SE',
        NOR: 'NO', FIN: 'FI', DNK: 'DK', BEL: 'BE', CHE: 'CH', AUT: 'AT', CZE: 'CZ',
        GRC: 'GR', PRT: 'PT', IRL: 'IE', NZL: 'NZ', ISR: 'IL', UKR: 'UA', HUN: 'HU',
        ROU: 'RO', BGR: 'BG', SRB: 'RS', HRV: 'HR', SVN: 'SI', SVK: 'SK', MLT: 'MT',
        ARE: 'AE', QAT: 'QA', KWT: 'KW', BHR: 'BH', OMN: 'OM', MAR: 'MA', DZA: 'DZ',
        TUN: 'TN', COL: 'CO', PER: 'PE', CHL: 'CL', VEN: 'VE', CUB: 'CU', DOM: 'DO',
        HND: 'HN', GTM: 'GT', CRI: 'CR', PAN: 'PA', URY: 'UY', PRY: 'PY', BOL: 'BO',
        ECU: 'EC', PRK: 'KP', VNM: 'VN', THA: 'TH', MYS: 'MY', PHL: 'PH', SGP: 'SG',
        HKG: 'HK', TWN: 'TW', LKA: 'LK', NPL: 'NP', BGD: 'BD', KEN: 'KE', TZA: 'TZ',
        UGA: 'UG', GHA: 'GH', CIV: 'CI', CMR: 'CM', SEN: 'SN', SDN: 'SD', IRQ: 'IQ',
        SYR: 'SY', LBN: 'LB', JOR: 'JO', YEM: 'YE', QZZ: 'XK' // Kosovo workaround
      };
      return a3to2[A3] || null;
    }

    async function main() {
      const counts = await loadCounts(); // {countries:{GB: 12, US: 9, ...}}
      const topo = window['countries-110m']; // from world-atlas
      const geo = topojson.feature(topo, topo.objects.countries);

      // Build a map: ISO2 → centroid + name
      const byISO2 = {};
      geo.features.forEach(f => {
        const code = iso2fromTopoFeature(f);
        if (!code) return;
        try {
          const center = turf.centroid(f);
          const [lng, lat] = center.geometry.coordinates;
          byISO2[code.toUpperCase()] = {
            lat, lng,
            name: (f.properties && (f.properties.name || f.properties.admin)) || code
          };
        } catch (e) { /* skip weird geometries */ }
      });

      // Drop a flag marker for each country with visitors
      for (const [code, total] of Object.entries(counts.countries || {})) {
        const c2 = code.toUpperCase();
        if (!byISO2[c2]) continue;
        const { lat, lng, name } = byISO2[c2];

        const icon = L.divIcon({
          className: 'flag-div',
          html: `<img class="flag-icon" alt="${c2}" src="https://flagcdn.com/32x24/${c2.toLowerCase()}.png" />`,
          iconSize: [32, 24],
          iconAnchor: [16, 12],
          popupAnchor: [0, -12]
        });

        const marker = L.marker([lat, lng], { icon }).addTo(map);
        marker.bindPopup(`<b>${name} (${c2})</b><br/>Visitors: ${total}`);
      }
    }
    main();
  </script>
</body>
</html>
