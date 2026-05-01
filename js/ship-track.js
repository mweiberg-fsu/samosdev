// js/ship-track.js
function renderShipTrack(payload) {
    const { ship, date, order, history_id, server, chartId = null } = payload;
    const container = document.getElementById('shipTrackContainer');
    if (!container) {
        return;
    }

    const normalizeHour = (value, fallback) => {
        const parsed = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);
        if (isNaN(parsed)) {
            return fallback;
        }
        return Math.max(0, Math.min(23, parsed)).toString().padStart(2, '0');
    };

    const parseNumeric = (value) => {
        if (value === null || typeof value === 'undefined' || value === '') {
            return null;
        }
        const parsed = parseFloat(String(value).replace(/[^\d.-]/g, ''));
        return isNaN(parsed) ? null : parsed;
    };

    const hsInput = document.querySelector('input[name="hs"]');
    const heInput = document.querySelector('input[name="he"]');
    const hs = normalizeHour(hsInput ? hsInput.value : null, '00');
    const he = normalizeHour(heInput ? heInput.value : null, '23');

    const safeShip = String(ship || '').replace(/[^\x00-\x7F]/g, '');
    const safeOrder = String(order || '').replace(/[^\x00-\x7F]/g, '');
    const safeDate = String(date || '').replace(/[^\x00-\x7F]/g, '');

    const currentPayload = (chartId && window.__chartPayloads && window.__chartPayloads[chartId])
        ? window.__chartPayloads[chartId]
        : (window.__originalChartData || null);

    const allPlottedVars = currentPayload && currentPayload.plotData
        ? Object.keys(currentPayload.plotData)
        : [];

    const selectionState = window.__combinedSelectionState || {};
    const selectedSet = selectionState[chartId] || selectionState.__default__ || null;
    const selectedVars = (selectedSet && selectedSet.size)
        ? allPlottedVars.filter(v => selectedSet.has(v))
        : allPlottedVars.slice();

    const valueVars = selectedVars.filter(v => {
        const upper = String(v).toUpperCase();
        return upper !== 'LAT' && upper !== 'LON';
    });

    const legendNameMap = currentPayload && currentPayload.longNames ? currentPayload.longNames : {};

    container.innerHTML = '<p style="margin:10px 0; text-align:center;">Loading ship track and map...</p>';

    const possibleLatVars = ['LAT', 'lat', 'Lat'];
    const possibleLonVars = ['LON', 'lon', 'Lon'];

    const uniqueVarsToFetch = [];
    const addFetchVar = (name) => {
        if (name && !uniqueVarsToFetch.includes(name)) {
            uniqueVarsToFetch.push(name);
        }
    };

    possibleLatVars.forEach(addFetchVar);
    possibleLonVars.forEach(addFetchVar);
    valueVars.forEach(addFetchVar);

    const fetchVar = (varName) => {
        const url = `${server}/charts/plot_chart.php?ship=${encodeURIComponent(ship)}&date=${encodeURIComponent(date)}&order=${encodeURIComponent(order)}&var=${encodeURIComponent(varName)}&version_no=100&hs=${encodeURIComponent(hs)}&he=${encodeURIComponent(he)}&history_id=${encodeURIComponent(history_id)}`;
        return fetch(url)
            .then(r => r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`)))
            .then(data => ({ varName, data }))
            .catch(() => ({ varName, data: null }));
    };

    Promise.all(uniqueVarsToFetch.map(fetchVar)).then((responses) => {
        let latData = null;
        let lonData = null;
        let usedLatVar = '';
        let usedLonVar = '';

        const varDataByName = {};
        responses.forEach(item => {
            if (!item || !item.varName || !item.data) {
                return;
            }
            varDataByName[item.varName] = item.data;
        });

        for (let i = 0; i < possibleLatVars.length; i++) {
            const candidate = possibleLatVars[i];
            if (varDataByName[candidate] && Object.keys(varDataByName[candidate]).length > 0) {
                latData = varDataByName[candidate];
                usedLatVar = candidate;
                break;
            }
        }

        for (let i = 0; i < possibleLonVars.length; i++) {
            const candidate = possibleLonVars[i];
            if (varDataByName[candidate] && Object.keys(varDataByName[candidate]).length > 0) {
                lonData = varDataByName[candidate];
                usedLonVar = candidate;
                break;
            }
        }

        if (!latData || !lonData) {
            container.innerHTML = `<p style="color:#e74c3c; text-align:center; margin:16px 0;">No position data found for ${hs}:00 - ${he}:59 UTC.</p>`;
            return;
        }

        const lonMap = new Map(Object.entries(lonData));
        const points = [];
        Object.entries(latData).forEach(([time, rawLat]) => {
            const rawLon = lonMap.has(time) ? lonMap.get(time) : null;
            const latNum = parseNumeric(rawLat);
            const lonNum = parseNumeric(rawLon);
            if (latNum === null || lonNum === null) {
                return;
            }

            const point = {
                time,
                lat: Math.round(latNum * 100) / 100,
                lon: Math.round(lonNum * 100) / 100,
                vars: {}
            };

            valueVars.forEach(v => {
                const source = varDataByName[v];
                point.vars[v] = source && Object.prototype.hasOwnProperty.call(source, time)
                    ? parseNumeric(source[time])
                    : null;
            });

            points.push(point);
        });

        if (!points.length) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center; margin:16px 0;">No valid points in the selected time range.</p>';
            return;
        }

        const valueHeaderHtml = valueVars.map(v => {
            const headerLabel = legendNameMap[v] ? `${v} (${legendNameMap[v]})` : v;
            return `<th style="padding:7px 8px; text-align:right;">${headerLabel}</th>`;
        }).join('');

        container.innerHTML = `
            <div style="display:flex; flex-direction:column; height:100%; min-height:0; gap:10px;">
                <div style="flex:0 0 auto;">
                    <h3 style="margin:0 0 4px; text-align:center; color:#19324d;">
                        Ship Track - ${safeShip} (Order ${safeOrder} - ${safeDate})
                    </h3>
                    <div style="text-align:center; color:#5b6b79; font-size:12px;">
                        ${usedLatVar}/${usedLonVar} | ${hs}:00 - ${he}:59 UTC | ${points.length} points
                    </div>
                </div>
                <div id="shipTrackMap" style="flex:1 1 62%; min-height:320px; border:2px solid #2f7db5; border-radius:10px;"></div>
                <div style="flex:1 1 38%; min-height:180px; overflow:auto; border:1px solid #d3dde8; border-radius:8px; background:#fff;">
                    <table style="width:100%; border-collapse:collapse; font-size:12px;">
                        <thead style="background:#214764; color:#fff; position:sticky; top:0; z-index:2;">
                            <tr>
                                <th style="padding:7px 8px; text-align:left;">Time (UTC)</th>
                                <th style="padding:7px 8px; text-align:right;">Lat</th>
                                <th style="padding:7px 8px; text-align:right;">Lon</th>
                                ${valueHeaderHtml}
                            </tr>
                        </thead>
                        <tbody id="shipTrackTableBody"></tbody>
                    </table>
                </div>
            </div>`;

        const map = L.map('shipTrackMap', { preferCanvas: true }).fitBounds(points.map(p => [p.lat, p.lon]));
        setTimeout(() => map.invalidateSize(), 0);

        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '© Esri'
        }).addTo(map);

        L.tileLayer('https://wayback.maptiles.arcgis.com/arcgis/rest/services/World_Labels/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '© Esri Labels'
        }).addTo(map);

        const trackLayer = L.layerGroup().addTo(map);

        L.circleMarker([points[0].lat, points[0].lon], {
            radius: 7,
            fillColor: '#2ecc71',
            color: '#000',
            weight: 1.5,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map).bindPopup(`<b>Start</b><br>${points[0].time}`);

        const lastPoint = points[points.length - 1];
        L.circleMarker([lastPoint.lat, lastPoint.lon], {
            radius: 7,
            fillColor: '#e74c3c',
            color: '#000',
            weight: 1.5,
            opacity: 1,
            fillOpacity: 0.9
        }).addTo(map).bindPopup(`<b>End</b><br>${lastPoint.time}`);

        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = () => {
            const div = L.DomUtil.create('div', 'info legend');
            div.style.background = 'rgba(255,255,255,0.9)';
            div.style.padding = '8px 10px';
            div.style.fontSize = '12px';
            div.style.borderRadius = '6px';
            div.style.lineHeight = '1.4';
            div.innerHTML = `
                <div><span style="display:inline-block;width:10px;height:10px;background:#2ecc71;border-radius:50%;margin-right:6px;"></span>Start</div>
                <div><span style="display:inline-block;width:10px;height:10px;background:#e74c3c;border-radius:50%;margin-right:6px;"></span>End</div>
                <div style="margin-top:4px;color:#334b63;">Track: fixed line color</div>`;
            return div;
        };
        legend.addTo(map);

        const tableBody = container.querySelector('#shipTrackTableBody');

        const renderTable = () => {
            if (!tableBody) {
                return;
            }

            let rows = '';
            points.forEach(p => {
                const valueCells = valueVars.map(v => {
                    const value = p.vars[v];
                    return `<td style="padding:6px 8px; text-align:right;">${value === null ? '-' : value.toFixed(3)}</td>`;
                }).join('');

                rows += `<tr style="border-bottom:1px solid #eef2f7;">
                    <td style="padding:6px 8px;">${p.time}</td>
                    <td style="padding:6px 8px; text-align:right;">${p.lat.toFixed(2)}&deg;</td>
                    <td style="padding:6px 8px; text-align:right;">${p.lon.toFixed(2)}&deg;</td>
                    ${valueCells}
                </tr>`;
            });
            tableBody.innerHTML = rows;
        };

        const drawTrack = () => {
            trackLayer.clearLayers();

            if (points.length < 2) {
                return;
            }

            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1];
                const curr = points[i];

                L.polyline([[prev.lat, prev.lon], [curr.lat, curr.lon]], {
                    color: '#f39c12',
                    weight: 4,
                    opacity: 0.9
                }).addTo(trackLayer);
            }
        };

        renderTable();
        drawTrack();
    }).catch((err) => {
        container.innerHTML = `<p style="color:#e74c3c; text-align:center; margin:16px 0;">Error: ${err && err.message ? err.message : 'Unable to render ship track'}</p>`;
    });
}
