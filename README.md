# PHP Visitor Map Tracker

A fully self-contained PHP visitor tracker and map, supporting both IPv4 and IPv6, displaying country statistics with flags. Ideal for embedding visitor stats or building a live visitor map.

---

## Features

* Tracks visits per country using the **IP2Location DB1.LITE.IPV6.CSV** database.
* Supports **IPv4 and IPv6**.
* Ignores local/private/reserved IP addresses (10.0.0.0/8, 100.64.0.0/10, 172.16/12, 192.168/16, 127.0.0.0/8, fc00::/7, fe80::/10, etc.).
* **Optional test IP override** secured with a key
* **Map page (`map.php`)** displays visitor countries with flags on a Leaflet map.
* Sidebar showing all countries visited, visitor counts, search filter, and missing countries.
* **Top 10 countries SVG (`stats.php`)** with embedded flags for embedding as an `<img>` on any website.
* Lightweight, self-contained, easy to deploy.

---


## Setup

1. **Download IP2Location DB1 Lite (IPv6 CSV)**
   Place it as `IP2LOCATION-LITE-DB1.IPV6.CSV` in your project folder.

2. **Prepare `countries.csv`**
   Contains country centroids for map placement:

   ```
   longitude,latitude,COUNTRY,ISO,COUNTRYAFF,AFF_ISO
   139.6917,35.6895,Japan,JP,Asia,AS
   ...
   ```

   This CSV is used by `map.php` to position country markers and show flags.
   The file may need to be updated in the future (see below).

3. **Set permissions**
   Ensure PHP can read the CSV files and write to `data.json`.

4. **Optional: Configure proxy trust** in `tracker.php`:

   ```php
   $TRUST_PROXY = true; // if behind Cloudflare or Nginx reverse proxy
   $TRUSTED_PROXIES = ['127.0.0.1']; // add your proxy IPs
   ```

5. **Set a test key** (for test IP override) in `tracker.php`:

   ```php
   $TEST_KEY = 'YOUR_SECRET_KEY';
   ```

---

## Usage

### 1. Logging Visits

Include the tracker script on your pages, e.g.:

```html
<img src="tracker.php" style="display:none;" alt="">
```

Or fetch it via JavaScript:

```js
fetch('tracker.php')
  .then(res => res.json())
  .then(data => console.log(data));
```

**Test IP override:**

```
tracker.php?testip=1.2.3.4&key=YOUR_SECRET_KEY
```

---

### 2. Map Page

Visit:

```
map.php
```

**Features:**

* **Leaflet map** with country flags.
* **Sidebar**:

  * Search countries by name
  * Shows visitor counts
  * Missing countries list
  * “Countries Visited X/Y” counter
* Click a country to zoom in and show the popup.

---

### 3. Embeddable Stats Image

Use `stats.php` to show top 10 countries as an image anywhere:

```html
<a href="map.php">
  <img src="stats.php" alt="Top 10 Visitors">
</a>
```

* Flags are embedded as **base64** in the SVG for full `<img>` support.
* Shows ISO code + visitor count + total visitors.

---

### 4. Sidebar Features

* Filter countries in real-time with the search box.
* Toggle missing countries list.
* Shows **visited vs total countries** at the top of the sidebar.

---

## CSV Details

### `countries.csv` format

```
longitude,latitude,COUNTRY,ISO,COUNTRYAFF,AFF_ISO
```

* `longitude` / `latitude`: marker placement on the map
* `COUNTRY`: full country name
* `ISO`: ISO2 country code (used for flags)
* `COUNTRYAFF` / `AFF_ISO`: optional continent grouping

### `IP2LOCATION-LITE-DB1.IPV6.CSV`

* First column: IP range start (decimal or IP)
* Second column: IP range end
* Third column: ISO2 country code

---

### Updating `countries.csv`

The `countries.csv` file provides the country names, ISO codes, and coordinates for the map markers. It may need to be updated in the future to:

* Add new countries or territories
* Fix coordinates for accurate marker placement
* Update continent grouping (optional)

**Steps to update safely:**

1. Keep the CSV in the same directory as `map.php`.
2. Backup your current `countries.csv` before replacing it.
3. Ensure all ISO codes match [ISO 3166-1 alpha-2](https://www.iso.org/iso-3166-country-codes.html).
4. Keep the header row intact (`longitude,latitude,COUNTRY,ISO,COUNTRYAFF,AFF_ISO`).
5. After updating, refresh `map.php` in the browser; the script automatically reads the CSV.

> ⚠️ Note: If coordinates are missing or incorrect, the map marker may appear in the wrong location. Always validate lat/lon values before deploying.

---

## Security

* Private IPs are **never counted**.
* Test IP override requires `$TEST_KEY`.
* If using behind a proxy, configure `$TRUST_PROXY` and `$TRUSTED_PROXIES`.

---

## Notes

* `map.php` replaced the old `index.php`.
* SVG `stats.php` is **self-contained**, flags are embedded to work with `<img>` tags.
* CSV files can be updated as needed; ensure proper lat/lon for accurate map placement.
* `data.json` auto-creates when the first visitor is logged.
* Optional: You can cache flag images locally for faster SVG generation in `stats.php`.

---

## License

MIT License. Free to use, modify, and embed.

