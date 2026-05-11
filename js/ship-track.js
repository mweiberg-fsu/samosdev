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

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const formatDateDisplay = (rawDate) => {
        const clean = String(rawDate || '').replace(/[^\d]/g, '');
        if (clean.length >= 8) {
            return `${clean.slice(0, 4)}-${clean.slice(4, 6)}-${clean.slice(6, 8)}`;
        }
        return String(rawDate || '');
    };

    const formatTimeDisplay = (rawTime) => {
        const value = String(rawTime || '').trim();
        if (!value) {
            return '';
        }

        if (value.includes('T')) {
            return value.split('T').pop().replace(/Z$/, '').split('.')[0];
        }

        const parts = value.split(/\s+/);
        if (parts.length > 1) {
            return parts[parts.length - 1];
        }

        return value;
    };

    const parseUtcTimestamp = (rawTs) => {
        const str = String(rawTs || '').trim();
        if (!str) return null;

        const cleanDate = String(date || '').replace(/[^\d]/g, '').slice(0, 8);
        if (cleanDate.length === 8) {
            if (/^\d{6}$/.test(str)) {
                const hh = str.slice(0, 2);
                const mi = str.slice(2, 4);
                const ss = str.slice(4, 6);
                const dt = new Date(`${cleanDate.slice(0, 4)}-${cleanDate.slice(4, 6)}-${cleanDate.slice(6, 8)}T${hh}:${mi}:${ss}Z`);
                return Number.isNaN(dt.getTime()) ? null : dt;
            }

            if (/^\d{4}$/.test(str)) {
                const hh = str.slice(0, 2);
                const mi = str.slice(2, 4);
                const dt = new Date(`${cleanDate.slice(0, 4)}-${cleanDate.slice(4, 6)}-${cleanDate.slice(6, 8)}T${hh}:${mi}:00Z`);
                return Number.isNaN(dt.getTime()) ? null : dt;
            }

            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(str)) {
                const parts = str.split(':');
                const hh = parts[0].padStart(2, '0');
                const mi = parts[1];
                const ss = (parts[2] || '00').padStart(2, '0');
                const dt = new Date(`${cleanDate.slice(0, 4)}-${cleanDate.slice(4, 6)}-${cleanDate.slice(6, 8)}T${hh}:${mi}:${ss}Z`);
                return Number.isNaN(dt.getTime()) ? null : dt;
            }
        }

        const normalized = str.includes('T') ? str : str.replace(' ', 'T');
        const withZone = /Z$|[+-]\d\d:?\d\d$/.test(normalized) ? normalized : `${normalized}Z`;
        const dt = new Date(withZone);
        return Number.isNaN(dt.getTime()) ? null : dt;
    };

    const formatUtcTimestamp = (dateObj) => {
        const yyyy = dateObj.getUTCFullYear();
        const mm = String(dateObj.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(dateObj.getUTCDate()).padStart(2, '0');
        const hh = String(dateObj.getUTCHours()).padStart(2, '0');
        const mi = String(dateObj.getUTCMinutes()).padStart(2, '0');
        const ss = String(dateObj.getUTCSeconds()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`;
    };

    const resamplePointsToOneMinute = (sourcePoints, varsList) => {
        if (!Array.isArray(sourcePoints) || sourcePoints.length <= 1) {
            return sourcePoints || [];
        }

        const sorted = sourcePoints.slice().sort((a, b) => a.tsMs - b.tsMs);
        const output = [];

        for (let i = 0; i < sorted.length - 1; i++) {
            const start = sorted[i];
            const end = sorted[i + 1];

            if (!output.length || output[output.length - 1].tsMs !== start.tsMs) {
                output.push({ ...start, vars: { ...start.vars } });
            }

            const gapMs = end.tsMs - start.tsMs;
            if (gapMs <= 60000) {
                continue;
            }

            for (let t = start.tsMs + 60000; t < end.tsMs; t += 60000) {
                const ratio = (t - start.tsMs) / gapMs;
                const interpVars = {};

                varsList.forEach(v => {
                    const sv = start.vars[v];
                    const ev = end.vars[v];
                    interpVars[v] = (sv === null || ev === null)
                        ? null
                        : sv + (ev - sv) * ratio;
                });

                const interpDate = new Date(t);
                output.push({
                    time: formatUtcTimestamp(interpDate),
                    tsMs: t,
                    lat: start.lat + (end.lat - start.lat) * ratio,
                    lon: start.lon + (end.lon - start.lon) * ratio,
                    vars: interpVars,
                    isInterpolated: true
                });
            }
        }

        const last = sorted[sorted.length - 1];
        if (!output.length || output[output.length - 1].tsMs !== last.tsMs) {
            output.push({ ...last, vars: { ...last.vars } });
        }

        return output;
    };

    const filterPointsByInterval = (sourcePoints, intervalMinutes) => {
        if (!Array.isArray(sourcePoints) || !sourcePoints.length) {
            return [];
        }

        const minutes = Number(intervalMinutes);
        if (!Number.isFinite(minutes) || minutes <= 1) {
            return sourcePoints.slice();
        }

        const stepMs = minutes * 60000;
        const startTs = sourcePoints[0].tsMs;
        const filtered = sourcePoints.filter((point, idx) => {
            if (idx === 0) return true;
            return ((point.tsMs - startTs) % stepMs) === 0;
        });

        const lastPoint = sourcePoints[sourcePoints.length - 1];
        if (!filtered.length || filtered[filtered.length - 1].tsMs !== lastPoint.tsMs) {
            filtered.push(lastPoint);
        }

        return filtered;
    };

    if (!document.getElementById('shipTrackTooltipStyle')) {
        const style = document.createElement('style');
        style.id = 'shipTrackTooltipStyle';
        style.textContent = '.ship-track-tooltip{max-width:640px !important; min-width:420px !important; white-space:normal !important;}';
        document.head.appendChild(style);
    }

    const hsInput = document.querySelector('input[name="hs"]');
    const heInput = document.querySelector('input[name="he"]');
    const hs = normalizeHour(hsInput ? hsInput.value : null, '00');
    const he = normalizeHour(heInput ? heInput.value : null, '23');

    const safeShip = String(ship || '').replace(/[^\x00-\x7F]/g, '');
    const safeOrder = String(order || '').replace(/[^\x00-\x7F]/g, '');
    const safeDate = String(date || '').replace(/[^\x00-\x7F]/g, '');
    const displayDate = formatDateDisplay(date);

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

    const gradientStops = {
        viridis: [
            [68, 1, 84],
            [59, 82, 139],
            [33, 145, 140],
            [94, 201, 98],
            [253, 231, 37]
        ],
        plasma: [
            [13, 8, 135],
            [126, 3, 168],
            [203, 71, 119],
            [248, 149, 64],
            [240, 249, 33]
        ],
        inferno: [
            [0, 0, 4],
            [87, 15, 109],
            [187, 55, 84],
            [249, 142, 8],
            [252, 255, 164]
        ],
        cividis: [
            [0, 34, 78],
            [57, 82, 139],
            [95, 120, 130],
            [155, 161, 103],
            [253, 234, 69]
        ],
        turbo: [
            [48, 18, 59],
            [50, 104, 225],
            [62, 193, 129],
            [249, 251, 14],
            [180, 4, 38]
        ]
    };

    const colorForRatio = (ratio, gradientKey) => {
        const stops = gradientStops[gradientKey] || gradientStops.viridis;
        const r = Math.max(0, Math.min(1, ratio));
        const scaled = r * (stops.length - 1);
        const i = Math.min(stops.length - 2, Math.floor(scaled));
        const t = scaled - i;

        const c0 = stops[i];
        const c1 = stops[i + 1];
        const red = Math.round(c0[0] + (c1[0] - c0[0]) * t);
        const green = Math.round(c0[1] + (c1[1] - c0[1]) * t);
        const blue = Math.round(c0[2] + (c1[2] - c0[2]) * t);
        return `rgb(${red}, ${green}, ${blue})`;
    };

    const colorForAnomaly = (value, maxAbs) => {
        if (value === null || maxAbs <= 0) {
            return 'rgb(247,247,247)';
        }
        const t = Math.max(-1, Math.min(1, value / maxAbs));
        if (t < 0) {
            const x = t + 1;
            const r = Math.round(49 + (247 - 49) * x);
            const g = Math.round(130 + (247 - 130) * x);
            const b = Math.round(189 + (247 - 189) * x);
            return `rgb(${r}, ${g}, ${b})`;
        }
        const r = Math.round(247 + (214 - 247) * t);
        const g = Math.round(247 + (39 - 247) * t);
        const b = Math.round(247 + (40 - 247) * t);
        return `rgb(${r}, ${g}, ${b})`;
    };

    const gradientCss = (gradientKey) => {
        const stops = gradientStops[gradientKey] || gradientStops.viridis;
        const step = 100 / (stops.length - 1);
        const parts = stops.map((c, i) => `rgb(${c[0]}, ${c[1]}, ${c[2]}) ${Math.round(i * step)}%`);
        return `linear-gradient(90deg, ${parts.join(', ')})`;
    };

    const anomalyGradientCss = () => 'linear-gradient(90deg, rgb(49,130,189) 0%, rgb(247,247,247) 50%, rgb(214,39,40) 100%)';

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
        const rawPoints = [];
        Object.entries(latData).forEach(([time, rawLat]) => {
            const rawLon = lonMap.has(time) ? lonMap.get(time) : null;
            const latNum = parseNumeric(rawLat);
            const lonNum = parseNumeric(rawLon);
            const parsedTime = parseUtcTimestamp(time);
            if (latNum === null || lonNum === null) {
                return;
            }
            if (!parsedTime) {
                return;
            }

            const point = {
                time: formatUtcTimestamp(parsedTime),
                tsMs: parsedTime.getTime(),
                lat: latNum,
                lon: lonNum,
                vars: {}
            };

            valueVars.forEach(v => {
                const source = varDataByName[v];
                point.vars[v] = source && Object.prototype.hasOwnProperty.call(source, time)
                    ? parseNumeric(source[time])
                    : null;
            });

            rawPoints.push(point);
        });

        const allPoints = resamplePointsToOneMinute(rawPoints, valueVars);

        if (!allPoints.length) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center; margin:16px 0;">No valid points in the selected time range.</p>';
            return;
        }

        let activeTimeFilterMinutes = 1;
        let showTimesteps = true;
        let points = allPoints.slice();

        let activeVar = valueVars.length ? valueVars[0] : null;
        let anomalyMode = false;
        const anomalyPairs = [];

        for (let i = 0; i < valueVars.length; i++) {
            for (let j = i + 1; j < valueVars.length; j++) {
                anomalyPairs.push({
                    key: `${valueVars[i]}__${valueVars[j]}`,
                    left: valueVars[i],
                    right: valueVars[j]
                });
            }
        }

        let activeAnomalyPair = anomalyPairs.length ? anomalyPairs[0] : null;

        const toggleHtml = valueVars.length > 1
            ? `<div id="shipTrackVarToggles" style="display:flex; flex-wrap:wrap; gap:8px; margin:8px 0 10px;"></div>`
            : '';

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
                        ${usedLatVar}/${usedLonVar} | ${hs}:00 - ${he}:59 UTC | <span id="shipTrackPointCount">${points.length}</span> points
                    </div>
                    ${toggleHtml}
                </div>
                <div id="shipTrackMapPanel" style="flex:1 1 auto; min-height:320px; border:2px solid #2f7db5; border-radius:10px; display:flex; overflow:hidden; transition:flex-basis 280ms ease, min-height 280ms ease;">
                    <div id="shipTrackMap" style="flex:1; min-height:320px;"></div>
                </div>
                <div id="shipTrackTablePanel" style="flex:0 0 44px; min-height:44px; max-height:44px; overflow:hidden; border:1px solid #d3dde8; border-radius:8px; background:#fff; transition:flex-basis 280ms ease, min-height 280ms ease, max-height 280ms ease;">
                    <button id="shipTrackTableToggle" type="button" style="
                        width:100%;
                        border:none;
                        background:#214764;
                        color:#fff;
                        padding:10px 12px;
                        text-align:left;
                        font-size:12px;
                        font-weight:700;
                        cursor:pointer;
                    ">Data Points (click to expand)</button>
                    <div id="shipTrackTableWrap" style="max-height:0; opacity:0; overflow:auto; pointer-events:none; transition:max-height 280ms ease, opacity 220ms ease;">
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
                </div>
            </div>`;

        const map = L.map('shipTrackMap', { preferCanvas: true }).fitBounds(allPoints.map(p => [p.lat, p.lon]));
        setTimeout(() => map.invalidateSize(), 0);

        const basemapSelect = document.getElementById('shipTrackBasemapSelect');
        const timeFilterSelect = document.getElementById('shipTrackTimeFilterSelect');
        const gradientSelect = document.getElementById('shipTrackGradientSelect');
        let activeBaseLayer = null;
        let activeGradient = (gradientSelect && gradientSelect.value) ? gradientSelect.value : 'plasma';

        const createBaseLayer = (key) => {
            switch (key) {
                case 'osm-standard':
                    return L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    });
                case 'carto-voyager':
                    return L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                        maxZoom: 20,
                        subdomains: 'abcd',
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                    });
                case 'opentopo':
                    return L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                        maxZoom: 17,
                        attribution: '&copy; OpenStreetMap contributors, SRTM | &copy; OpenTopoMap'
                    });
                case 'carto-dark':
                    return L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                        maxZoom: 20,
                        subdomains: 'abcd',
                        attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
                    });
                case 'esri-imagery':
                default:
                    return L.layerGroup([
                        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            maxZoom: 19,
                            attribution: '&copy; Esri'
                        }),
                        L.tileLayer('https://wayback.maptiles.arcgis.com/arcgis/rest/services/World_Labels/MapServer/tile/{z}/{y}/{x}', {
                            maxZoom: 19,
                            attribution: '&copy; Esri Labels'
                        })
                    ]);
            }
        };

        const applyBasemap = (key) => {
            if (activeBaseLayer) {
                map.removeLayer(activeBaseLayer);
            }
            activeBaseLayer = createBaseLayer(key);
            activeBaseLayer.addTo(map);
        };

        if (basemapSelect) {
            if (!basemapSelect.value) {
                basemapSelect.value = 'osm-standard';
            }
            applyBasemap(basemapSelect.value);
            basemapSelect.onchange = () => {
                applyBasemap(basemapSelect.value || 'osm-standard');
            };
        } else {
            applyBasemap('osm-standard');
        }

        const trackLayer = L.layerGroup().addTo(map);
        const pointInteractionLayer = L.layerGroup().addTo(map);
        const endpointLayer = L.layerGroup().addTo(map);
        const pointCountEl = document.getElementById('shipTrackPointCount');
        let activePopupMarker = null;
        const hoverTooltip = L.tooltip({
            sticky: true,
            direction: 'top',
            opacity: 0.95,
            className: 'ship-track-tooltip'
        });

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
                <div id="shipTrackColorbar" style="margin-top:6px;"></div>`;
            return div;
        };
        legend.addTo(map);

        const tableBody = container.querySelector('#shipTrackTableBody');
        const legendColorbar = document.getElementById('shipTrackColorbar');
        const mapPanel = container.querySelector('#shipTrackMapPanel');
        const tablePanel = container.querySelector('#shipTrackTablePanel');
        const tableWrap = container.querySelector('#shipTrackTableWrap');
        const tableToggle = container.querySelector('#shipTrackTableToggle');
        let isTableExpanded = false;

        const updatePointCount = () => {
            if (pointCountEl) {
                pointCountEl.textContent = String(points.length);
            }
        };

        const applyTimeFilter = () => {
            if (activeTimeFilterMinutes === 0) {
                points = allPoints.slice();
                showTimesteps = false;
            } else {
                points = filterPointsByInterval(allPoints, activeTimeFilterMinutes);
                showTimesteps = true;
            }
            updatePointCount();
        };

        const setTableExpanded = (expanded) => {
            isTableExpanded = expanded;
            if (!mapPanel || !tablePanel || !tableWrap || !tableToggle) {
                return;
            }

            if (expanded) {
                mapPanel.style.flex = '1 1 62%';
                tablePanel.style.flex = '1 1 38%';
                tablePanel.style.minHeight = '180px';
                tablePanel.style.maxHeight = 'none';
                tableWrap.style.maxHeight = '100%';
                tableWrap.style.opacity = '1';
                tableWrap.style.pointerEvents = 'auto';
                tableToggle.textContent = 'Data Points (click to collapse)';
            } else {
                mapPanel.style.flex = '1 1 auto';
                tablePanel.style.flex = '0 0 44px';
                tablePanel.style.minHeight = '44px';
                tablePanel.style.maxHeight = '44px';
                tableWrap.style.maxHeight = '0';
                tableWrap.style.opacity = '0';
                tableWrap.style.pointerEvents = 'none';
                tableToggle.textContent = 'Data Points (click to expand)';
            }

            setTimeout(() => map.invalidateSize(), 180);
        };

        if (tableToggle) {
            tableToggle.addEventListener('click', () => {
                setTableExpanded(!isTableExpanded);
            });
        }

        const buildPointDetailsHtml = (point) => {
            const valueRows = valueVars.map(v => {
                const label = legendNameMap[v] ? `${v} (${legendNameMap[v]})` : v;
                const units = (currentPayload && currentPayload.units && currentPayload.units[v]) ? ` ${currentPayload.units[v]}` : '';
                const value = point.vars[v];
                const formatted = (value === null) ? '-' : `${value.toFixed(3)}${units}`;
                return `<div style="margin-top:2px;"><strong>${escapeHtml(label)}</strong>: ${escapeHtml(formatted)}</div>`;
            }).join('');

            const valuesBlock = valueRows
                ? `<div style="margin-top:6px; font-size:12px;">${valueRows}</div>`
                : '<div style="margin-top:6px; color:#55697c;">No selected data variables</div>';

            return `
                <div style="min-width:420px; max-width:620px; font-size:12px; line-height:1.35;">
                    <div style="font-weight:700; color:#1f3f5b; margin-bottom:4px;">Track Point</div>
                    <div><strong>Date:</strong> ${escapeHtml(displayDate)}</div>
                    <div><strong>Time (UTC):</strong> ${escapeHtml(formatTimeDisplay(point.time))}</div>
                    <div><strong>Lat/Lon:</strong> ${point.lat.toFixed(2)}&deg;, ${point.lon.toFixed(2)}&deg;</div>
                    ${valuesBlock}
                </div>`;
        };

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
            pointInteractionLayer.clearLayers();
            endpointLayer.clearLayers();

            if (!points.length) {
                return;
            }

            L.circleMarker([points[0].lat, points[0].lon], {
                radius: 7,
                fillColor: '#2ecc71',
                color: '#000',
                weight: 1.5,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(endpointLayer).bindPopup(`<b>Start</b><br>${points[0].time}`);

            const lastPoint = points[points.length - 1];
            L.circleMarker([lastPoint.lat, lastPoint.lon], {
                radius: 7,
                fillColor: '#e74c3c',
                color: '#000',
                weight: 1.5,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(endpointLayer).bindPopup(`<b>End</b><br>${lastPoint.time}`);

            if (points.length < 2) {
                return;
            }

            let min = null;
            let max = null;
            let maxAbs = null;
            const anomalyPair = anomalyMode && activeAnomalyPair
                ? [activeAnomalyPair.left, activeAnomalyPair.right]
                : null;

            const metricForPoint = (p) => {
                if (anomalyMode && anomalyPair) {
                    const a = p.vars[anomalyPair[0]];
                    const b = p.vars[anomalyPair[1]];
                    if (a === null || b === null) {
                        return null;
                    }
                    return a - b;
                }
                if (!activeVar) {
                    return null;
                }
                return p.vars[activeVar];
            };

            points.forEach(p => {
                const value = metricForPoint(p);
                if (value === null) {
                    return;
                }
                min = (min === null) ? value : Math.min(min, value);
                max = (max === null) ? value : Math.max(max, value);
            });

            if (anomalyMode && min !== null && max !== null) {
                maxAbs = Math.max(Math.abs(min), Math.abs(max));
            }

            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1];
                const curr = points[i];
                let segmentColor = '#f39c12';

                const segmentValue = metricForPoint(curr);
                if (segmentValue !== null && min !== null && max !== null) {
                    if (anomalyMode && maxAbs !== null) {
                        segmentColor = colorForAnomaly(segmentValue, maxAbs);
                    } else if (max > min) {
                        const ratio = (segmentValue - min) / (max - min);
                        segmentColor = colorForRatio(ratio, activeGradient);
                    } else {
                        segmentColor = colorForRatio(0.5, activeGradient);
                    }
                } else {
                    segmentColor = '#9aa7b7';
                }

                L.polyline([[prev.lat, prev.lon], [curr.lat, curr.lon]], {
                    color: segmentColor,
                    weight: 4,
                    opacity: 0.9,
                    smoothFactor: 0
                }).addTo(trackLayer);
            }

            if (showTimesteps) {
                points.forEach(point => {
                const tooltipHtml = buildPointDetailsHtml(point);
                let pointColor = '#f39c12';
                const pointValue = metricForPoint(point);
                if (pointValue !== null && min !== null && max !== null) {
                    if (anomalyMode && maxAbs !== null) {
                        pointColor = colorForAnomaly(pointValue, maxAbs);
                    } else if (max > min) {
                        const ratio = (pointValue - min) / (max - min);
                        pointColor = colorForRatio(ratio, activeGradient);
                    } else {
                        pointColor = colorForRatio(0.5, activeGradient);
                    }
                } else {
                    pointColor = '#9aa7b7';
                }

                const marker = L.circleMarker([point.lat, point.lon], {
                    radius: 3,
                    color: pointColor,
                    weight: 1,
                    opacity: 0.95,
                    fillColor: pointColor,
                    fillOpacity: 0.95,
                    interactive: true
                })
                    .addTo(pointInteractionLayer)
                    .bindPopup(tooltipHtml, {
                        maxWidth: 700,
                        autoPan: true
                    });

                marker.on('mouseover', () => {
                    if (activePopupMarker) {
                        return;
                    }
                    hoverTooltip
                        .setLatLng([point.lat, point.lon])
                        .setContent(tooltipHtml);
                    map.openTooltip(hoverTooltip);
                });

                marker.on('mouseout', () => {
                    if (activePopupMarker) {
                        return;
                    }
                    map.closeTooltip(hoverTooltip);
                });

                marker.on('popupopen', () => {
                    activePopupMarker = marker;
                    map.closeTooltip(hoverTooltip);
                });

                marker.on('popupclose', () => {
                    if (activePopupMarker === marker) {
                        activePopupMarker = null;
                    }
                });
                });
            }

            if (legendColorbar) {
                if ((activeVar || anomalyPair) && min !== null && max !== null) {
                    if (anomalyMode && anomalyPair && maxAbs !== null) {
                        legendColorbar.innerHTML = `
                            <div style="font-size:11px; color:#2f4356; margin-bottom:3px;">Difference (${anomalyPair[0]} - ${anomalyPair[1]})</div>
                            <div style="height:10px; border-radius:999px; border:1px solid rgba(0,0,0,0.25); background:${anomalyGradientCss()};"></div>
                            <div style="display:flex; justify-content:space-between; margin-top:3px; font-size:11px; color:#2f4356;">
                                <span>${(-maxAbs).toFixed(3)}</span>
                                <span>0.000</span>
                                <span>${maxAbs.toFixed(3)}</span>
                            </div>`;
                    } else {
                        legendColorbar.innerHTML = `
                            <div style="font-size:11px; color:#2f4356; margin-bottom:3px;">Colorbar (${activeGradient})</div>
                            <div style="height:10px; border-radius:999px; border:1px solid rgba(0,0,0,0.25); background:${gradientCss(activeGradient)};"></div>
                            <div style="display:flex; justify-content:space-between; margin-top:3px; font-size:11px; color:#2f4356;">
                                <span>${min.toFixed(3)}</span>
                                <span>${max.toFixed(3)}</span>
                            </div>`;
                    }
                } else {
                    legendColorbar.innerHTML = '<div style="font-size:11px; color:#62778c; margin-top:2px;">Colorbar unavailable</div>';
                }
            }
        };

        if (gradientSelect) {
            if (!gradientSelect.value) {
                gradientSelect.value = 'plasma';
            }
            activeGradient = gradientSelect.value;
            gradientSelect.onchange = () => {
                activeGradient = gradientSelect.value || 'plasma';
                drawTrack();
            };
        }

        if (timeFilterSelect) {
            const parsedDefault = parseInt(timeFilterSelect.value, 10);
            activeTimeFilterMinutes = Number.isFinite(parsedDefault) ? parsedDefault : 1;
            timeFilterSelect.onchange = () => {
                const parsed = parseInt(timeFilterSelect.value, 10);
                activeTimeFilterMinutes = Number.isFinite(parsed) ? parsed : 1;
                applyTimeFilter();
                renderTable();
                drawTrack();
            };
        }

        if (valueVars.length > 1) {
            const toggleHost = container.querySelector('#shipTrackVarToggles');
            if (toggleHost) {
                const varButtons = [];

                valueVars.forEach(v => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = legendNameMap[v] ? `${v} - ${legendNameMap[v]}` : v;
                    button.style.border = '1px solid #7c91a4';
                    button.style.padding = '5px 9px';
                    button.style.borderRadius = '999px';
                    button.style.cursor = 'pointer';
                    button.style.fontSize = '12px';
                    button.style.background = (v === activeVar) ? '#214764' : '#f5f8fb';
                    button.style.color = (v === activeVar) ? '#fff' : '#214764';
                    button.addEventListener('click', () => {
                        anomalyMode = false;
                        activeVar = v;
                        if (anomalySelect) {
                            anomalySelect.value = '';
                        }
                        Array.from(toggleHost.children).forEach(child => {
                            if (child.tagName === 'BUTTON') {
                                child.style.background = '#f5f8fb';
                                child.style.color = '#214764';
                            }
                        });
                        button.style.background = '#214764';
                        button.style.color = '#fff';
                        drawTrack();
                    });
                    toggleHost.appendChild(button);
                    varButtons.push(button);
                });

                let anomalySelect = null;

                if (anomalyPairs.length) {
                    anomalySelect = document.createElement('select');
                    anomalySelect.dataset.mode = 'anomaly-select';
                    anomalySelect.style.border = '1px solid #b9770e';
                    anomalySelect.style.padding = '5px 9px';
                    anomalySelect.style.borderRadius = '999px';
                    anomalySelect.style.cursor = 'pointer';
                    anomalySelect.style.fontSize = '12px';
                    anomalySelect.style.background = '#fff4d6';
                    anomalySelect.style.color = '#7a4900';

                    const placeholderOption = document.createElement('option');
                    placeholderOption.value = '';
                    placeholderOption.textContent = 'Difference...';
                    anomalySelect.appendChild(placeholderOption);

                    anomalyPairs.forEach(pair => {
                        const option = document.createElement('option');
                        option.value = pair.key;
                        option.textContent = `${pair.left} vs ${pair.right}`;
                        anomalySelect.appendChild(option);
                    });

                    anomalySelect.addEventListener('change', () => {
                        const nextPair = anomalyPairs.find(pair => pair.key === anomalySelect.value) || null;
                        activeAnomalyPair = nextPair || activeAnomalyPair;
                        anomalyMode = !!nextPair;

                        varButtons.forEach(button => {
                            button.style.background = '#f5f8fb';
                            button.style.color = '#214764';
                        });

                        if (!anomalyMode && activeVar) {
                            const activeLabel = legendNameMap[activeVar] ? `${activeVar} - ${legendNameMap[activeVar]}` : activeVar;
                            const activeButton = varButtons.find(button => button.textContent === activeLabel);
                            if (activeButton) {
                                activeButton.style.background = '#214764';
                                activeButton.style.color = '#fff';
                            }
                        }

                        drawTrack();
                    });

                    toggleHost.appendChild(anomalySelect);
                }
            }
        }

        applyTimeFilter();
        renderTable();
        setTableExpanded(false);
        drawTrack();
    }).catch((err) => {
        container.innerHTML = `<p style="color:#e74c3c; text-align:center; margin:16px 0;">Error: ${err && err.message ? err.message : 'Unable to render ship track'}</p>`;
    });
}
