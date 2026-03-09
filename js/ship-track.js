// js/ship-track.js
function renderShipTrack(payload) {

    const { ship, date, order, history_id, server, figurePayload = null } = payload;

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

    const resolveFigureSeries = () => {
        if (!figurePayload || !figurePayload.plotData || typeof figurePayload.plotData !== 'object') {
            return { variable: '', pointsByTs: {}, min: null, max: null, units: '' };
        }

        const vars = Object.keys(figurePayload.plotData);
        if (!vars.length) {
            return { variable: '', pointsByTs: {}, min: null, max: null, units: '' };
        }

        const variable = vars[0];
        const series = figurePayload.plotData[variable] || {};
        const points = Array.isArray(series.points) ? series.points : [];
        const pointsByTs = {};
        let min = Number.POSITIVE_INFINITY;
        let max = Number.NEGATIVE_INFINITY;

        points.forEach(point => {
            if (!point || !point.date) return;
            const rawValue = point.value;
            const parsed = Number(rawValue);
            const isMissing = rawValue === null || rawValue === '' || rawValue === '-9999' || rawValue === '-8888' || rawValue === -9999 || rawValue === -8888;
            const numeric = (!isMissing && Number.isFinite(parsed)) ? parsed : null;
            const rawFlag = typeof point.flag === 'string' ? point.flag.trim() : '';
            const normalizedFlag = rawFlag !== '' ? rawFlag : ' ';

            pointsByTs[point.date] = {
                value: numeric,
                flag: normalizedFlag
            };

            if (numeric !== null) {
                if (numeric < min) min = numeric;
                if (numeric > max) max = numeric;
            }
        });

        if (min === Number.POSITIVE_INFINITY || max === Number.NEGATIVE_INFINITY) {
            min = null;
            max = null;
        }

        const units = figurePayload.units && figurePayload.units[variable] ? figurePayload.units[variable] : '';
        return { variable, pointsByTs, min, max, units };
    };

    const interpolateColor = (t) => {
        // Blue -> cyan -> yellow -> red ramp
        const clampT = Math.max(0, Math.min(1, t));
        const stops = [
            { t: 0, c: [49, 54, 149] },
            { t: 0.33, c: [38, 198, 218] },
            { t: 0.66, c: [253, 216, 53] },
            { t: 1, c: [229, 57, 53] }
        ];
        let s0 = stops[0];
        let s1 = stops[stops.length - 1];
        for (let i = 0; i < stops.length - 1; i++) {
            if (clampT >= stops[i].t && clampT <= stops[i + 1].t) {
                s0 = stops[i];
                s1 = stops[i + 1];
                break;
            }
        }
        const localT = (clampT - s0.t) / (s1.t - s0.t || 1);
        const r = Math.round(s0.c[0] + (s1.c[0] - s0.c[0]) * localT);
        const g = Math.round(s0.c[1] + (s1.c[1] - s0.c[1]) * localT);
        const b = Math.round(s0.c[2] + (s1.c[2] - s0.c[2]) * localT);
        return `rgb(${r},${g},${b})`;
    };

    const figureSeries = resolveFigureSeries();

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
            const lonByTime = lonData || {};

            for (let i = 0; i < latEntries.length; i++) {
                const [time, rawLat] = latEntries[i];
                const rawLon = Object.prototype.hasOwnProperty.call(lonByTime, time) ? lonByTime[time] : null;

                // remove UTF issues with parseFloat
                const latParsed = parseFloat(String(rawLat).replace(/[^\d.-]/g, ''));
                const lonParsed = parseFloat(String(rawLon).replace(/[^\d.-]/g, ''));

                if (isNaN(latParsed) || isNaN(lonParsed)) continue;
                if (latParsed < -90 || latParsed > 90 || lonParsed < -180 || lonParsed > 180) continue;

                // Cap coordinates to 2 decimal places for both display and mapping.
                const latNum = Math.round(latParsed * 100) / 100;
                const lonNum = Math.round(lonParsed * 100) / 100;

                const seriesPoint = figureSeries.pointsByTs[time] || { value: null, flag: ' ' };
                const hasValue = seriesPoint.value !== null;
                const valText = hasValue ? String(seriesPoint.value) : 'n/a';
                const flagText = (seriesPoint.flag || ' ').trim() || ' ';

                points.push({
                    lat: latNum,
                    lon: lonNum,
                    time,
                    value: seriesPoint.value,
                    flag: flagText
                });

                rows += `<tr>
        <td>${time}</td>
            <td style="text-align:right;">${latNum.toFixed(2)}&deg</td>
            <td style="text-align:right;">${lonNum.toFixed(2)}&deg</td>
            <td style="text-align:right;">${valText}</td>
            <td style="text-align:center;">${flagText}</td>
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
                        ${usedLatVar}/${usedLonVar}${figureSeries.variable ? ` | Colored by ${figureSeries.variable}` : ''} | ${hs}:00 - ${he}:59 UTC
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
                                    <th style="padding:8px; text-align:right;">${figureSeries.variable || 'Value'}</th>
                                    <th style="padding:8px; text-align:center;">Flag</th>
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
            const map = L.map('shipTrackMap').fitBounds(points.map(p => [p.lat, p.lon]));

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

            // Draw value-based gradient segments when data is available.
            let gradientApplied = false;
            if (figureSeries.min !== null && figureSeries.max !== null && points.length > 1) {
                const range = figureSeries.max - figureSeries.min;
                for (let i = 0; i < points.length - 1; i++) {
                    const p0 = points[i];
                    const p1 = points[i + 1];

                    if (p0.value === null && p1.value === null) {
                        L.polyline([[p0.lat, p0.lon], [p1.lat, p1.lon]], {
                            color: '#95a5a6',
                            weight: 3,
                            opacity: 0.45,
                            dashArray: '6 6'
                        }).addTo(map);
                        continue;
                    }

                    const segmentValue = p1.value !== null ? p1.value : p0.value;
                    const t = range > 0 ? (segmentValue - figureSeries.min) / range : 0.5;
                    const color = interpolateColor(t);

                    L.polyline([[p0.lat, p0.lon], [p1.lat, p1.lon]], {
                        color,
                        weight: 4,
                        opacity: 0.9
                    })
                        .addTo(map)
                        .bindTooltip(`${figureSeries.variable}: ${segmentValue}`);
                }
                gradientApplied = true;
            }

            if (!gradientApplied) {
                L.polyline(points.map(p => [p.lat, p.lon]), {
                    color: '#e74c3c',
                    weight: 4,
                    opacity: 0.8
                }).addTo(map);
            }

            // Start marker (green)
            L.circleMarker([points[0].lat, points[0].lon], {
                radius: 8,
                fillColor: '#2ecc71',
                color: '#000',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map)
                .bindPopup(`<b>Start</b><br>${points[0].time}<br>${figureSeries.variable || 'Value'}: ${points[0].value !== null ? points[0].value : 'n/a'}<br>Flag: ${(points[0].flag || ' ').trim() || ' '}`);

            // End marker (red)
            const last = points[points.length - 1];
            L.circleMarker([last.lat, last.lon], {
                radius: 8,
                fillColor: '#e74c3c',
                color: '#000',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map)
                .bindPopup(`<b>End</b><br>${last.time}<br>${figureSeries.variable || 'Value'}: ${last.value !== null ? last.value : 'n/a'}<br>Flag: ${(last.flag || ' ').trim() || ' '}`);

            points.forEach(p => {
                L.circleMarker([p.lat, p.lon], {
                    radius: 3,
                    color: '#2c3e50',
                    weight: 1,
                    opacity: 0.8,
                    fillColor: '#ecf0f1',
                    fillOpacity: 0.7
                }).addTo(map).bindTooltip(`${p.time}<br>${figureSeries.variable || 'Value'}: ${p.value !== null ? p.value : 'n/a'}<br>Flag: ${(p.flag || ' ').trim() || ' '}`);
            });

            // Add a small legend
            const legend = L.control({ position: 'bottomright' });
            legend.onAdd = () => {
                const div = L.DomUtil.create('div', 'info legend');
                const valueLegend = (gradientApplied && figureSeries.variable)
                    ? `
                    <div style="margin-top:6px; padding-top:6px; border-top:1px solid #d0d0d0;">
                        <div style="font-weight:600; margin-bottom:4px;">${figureSeries.variable}${figureSeries.units ? ` (${figureSeries.units})` : ''}</div>
                        <div style="width:140px; height:10px; background:linear-gradient(90deg, rgb(49,54,149), rgb(38,198,218), rgb(253,216,53), rgb(229,57,53)); border:1px solid #999;"></div>
                        <div style="display:flex; justify-content:space-between; font-size:11px; margin-top:2px;">
                            <span>${figureSeries.min}</span>
                            <span>${figureSeries.max}</span>
                        </div>
                    </div>`
                    : '';

                div.innerHTML = `
                <i style="background:#2ecc71; width:12px; height:12px; border-radius:50%; display:inline-block;"></i> Start<br>
                <i style="background:#e74c3c; width:12px; height:12px; border-radius:50%; display:inline-block;"></i> End
                ${valueLegend}`;
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