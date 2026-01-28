// js/ship-track.js
function renderShipTrack(payload) {

    // Force all fetch responses to be treated as UTF-8
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        return originalFetch(...args).then(response => {
            // Clone the response and override its charset
            const clone = response.clone();
            const newHeaders = new Headers(clone.headers);
            newHeaders.set('Content-Type', 'application/json; charset=utf-8');
            return new Response(clone.body, {
                status: clone.status,
                statusText: clone.statusText,
                headers: newHeaders
            });
        });
    };

    const { ship, date, order, history_id, server } = payload;

    // Read current time range from the form inputs (this is the key!)
    const hsInput = document.querySelector('input[name="hs"]');
    const heInput = document.querySelector('input[name="he"]');
    let hs = '00', he = '23';
    if (hsInput && heInput) {
        hs = hsInput.value.padStart(2, '0');
        he = heInput.value.padStart(2, '0');
    }

    const container = document.getElementById('shipTrackContainer');
    container.innerHTML = '<p>Loading ship track and map...</p>';

    const possibleLatVars = ['LAT', 'lat', 'Lat'];
    const possibleLonVars = ['LON', 'lon', 'Lon'];

    let latData = null, lonData = null;
    let usedLatVar = '', usedLonVar = '';

    const fetchVar = (varName) => {
        const url = `${server}/charts/plot_chart.php?ship=${ship}&date=${date}&order=${order}&var=${varName}&version_no=100&hs=${hs}&he=${he}&history_id=${history_id}`;
        return fetch(url)
            .then(r => r.ok ? r.json() : Promise.reject(`HTTP ${r.status}`))
            .then(data => {
                const values = Object.values(data).filter(v => v !== null && v !== '');
                return values.length > 0 ? { data, varName } : null;
            })
            .catch(() => null);
    };

    Promise.all([
        ...possibleLatVars.map(fetchVar),
        ...possibleLonVars.map(fetchVar)
    ])
        .then(results => {
            for (const res of results) {
                if (res && possibleLatVars.includes(res.varName) && !latData) { latData = res.data; usedLatVar = res.varName; }
                if (res && possibleLonVars.includes(res.varName) && !lonData) { lonData = res.data; usedLonVar = res.varName; }
            }

            if (!latData || !lonData) {
                container.innerHTML = `<p style="color:#e74c3c; text-align:center;">No position data found for ${hs}:00 – ${he}:59 UTC</p>`;
                return;
            }

            const latEntries = Object.entries(latData);
            const points = [];
            let rows = '';
            let validCount = 0;

            for (let i = 0; i < latEntries.length; i++) {
                const [time, rawLat] = latEntries[i];
                const lonEntry = Object.entries(lonData).find(([t]) => t === time);
                const rawLon = lonEntry ? lonEntry[1] : null;

                // remove UTF issues with parseFloat
                const latNum = parseFloat(String(rawLat).replace(/[^\d.-]/g, ''));
                const lonNum = parseFloat(String(rawLon).replace(/[^\d.-]/g, ''));

                if (isNaN(latNum) || isNaN(lonNum)) continue;

                points.push([latNum, lonNum, time]);

                rows += `<tr>
        <td>${time}</td>
        <td style="text-align:right;">${latNum.toFixed(3)}&deg</td>
        <td style="text-align:right;">${lonNum.toFixed(3)}&deg</td>
    </tr>`;
                validCount++;
            }

            if (validCount === 0) {
                container.innerHTML = '<p style="color:#e74c3c;">No valid points in selected time range.</p>';
                return;
            }

            // =============== RENDER TABLE + MAP ===============
            container.innerHTML = `
                <h3 style="margin:0 0 10px; text-align:center;">
                    Ship Track - ${ship.replace(/[^\x00-\x7F]/g, '')} (Order ${order.replace(/[^\x00-\x7F]/g, '')} - ${date.replace(/[^\x00-\x7F]/g, '')})
                    <small style="display:block; color:#666;">
                        ${usedLatVar}/${usedLonVar} | ${hs}:00 - ${he}:59 UTC
                    </small>
                </h3>

                <!-- Flex container: table small, map dominates -->
                <div style="display:flex; flex-direction:column; height:80vh; max-height:900px; gap:12px;">
                    
                    <!-- Table: fixed height ~10 rows visible -->
                    <div style="flex: 0 0 auto; max-height:180px; overflow-y:auto; border:1px solid #ddd; background:white; border-radius:6px;">
                        <table style="width:100%; border-collapse:collapse; font-size:13px;">
                            <thead style="background:#2c3e50; color:white; position:sticky; top:0; z-index:10;">
                                <tr>
                                    <th style="padding:8px;">Time (UTC)</th>
                                    <th style="padding:8px; text-align:right;">Lat</th>
                                    <th style="padding:8px; text-align:right;">Lon</th>
                                </tr>
                            </thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>

                    <!-- Map takes all remaining space -->
                    <div id="shipTrackMap" style="flex: 1; min-height:400px; border:2px solid #3498db; border-radius:8px;"></div>
                </div>

                <p style="text-align:center; margin:10px 0 0; color:#27ae60; font-weight:bold;">
                    ${validCount} points plotted on map
                </p>`;

            // =============== LEAFLET MAP ===============
            const map = L.map('shipTrackMap').fitBounds(points.map(p => [p[0], p[1]]));

            // =============== PREVENT BACKGROUND SCROLL WHEN HOVERING OVER CONTAINER ===============
            const containerElement = container;

            // Stop scroll propagation when mouse is over the ship track panel
            containerElement.addEventListener('wheel', (e) => {
                if (e.deltaY === 0) return;

                const atTop = containerElement.scrollTop === 0 && e.deltaY < 0;
                const atBottom = containerElement.scrollTop + containerElement.clientHeight >= containerElement.scrollHeight - 10 && e.deltaY > 0;

                if (atTop || atBottom) {
                    e.preventDefault(); // Only prevent if trying to scroll past bounds
                }
            }, { passive: false });

            // Also block mousewheel on map specifically (Leaflet sometimes eats events weirdly)
            containerElement.addEventListener('mouseenter', () => {
                document.body.style.overflow = 'hidden';
            });
            containerElement.addEventListener('mouseleave', () => {
                document.body.style.overflow = '';
            });

            // 1. Satellite background
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: '© Esri — Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP'
            }).addTo(map);

            // 2. Labels on top (cities, countries, islands, oceans)
            L.tileLayer('https://wayback.maptiles.arcgis.com/arcgis/rest/services/World_Labels/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: '© Esri — Labels'
            }).addTo(map);

            // Polyline (the actual track)
            const polyline = L.polyline(points.map(p => [p[0], p[1]]), {
                color: '#e74c3c',
                weight: 4,
                opacity: 0.8
            }).addTo(map);

            // Start marker (green)
            L.circleMarker([points[0][0], points[0][1]], {
                radius: 8,
                fillColor: '#2ecc71',
                color: '#000',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map)
                .bindPopup(`<b>Start</b><br>${points[0][2]}`);

            // End marker (red)
            const last = points[points.length - 1];
            L.circleMarker([last[0], last[1]], {
                radius: 8,
                fillColor: '#e74c3c',
                color: '#000',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map)
                .bindPopup(`<b>End</b><br>${last[2]}`);

            // Add a small legend
            const legend = L.control({ position: 'bottomright' });
            legend.onAdd = () => {
                const div = L.DomUtil.create('div', 'info legend');
                div.innerHTML = `
                <i style="background:#2ecc71; width:12px; height:12px; border-radius:50%; display:inline-block;"></i> Start<br>
                <i style="background:#e74c3c; width:12px; height:12px; border-radius:50%; display:inline-block;"></i> End`;
                div.style.background = 'rgba(255,255,255,0.8)';
                div.style.padding = '6px 8px';
                div.style.fontSize = '12px';
                div.style.borderRadius = '5px';
                return div;
            };
            legend.addTo(map);
        })
        .catch(err => {
            container.innerHTML = `<p style="color:red;">Error: ${err.message}</p>`;
        });
}