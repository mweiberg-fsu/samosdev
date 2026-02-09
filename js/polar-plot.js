// js/polar-plot.js
(function (global) {
    'use strict';

    const allowedLongNames = new Set([
        'Platform Course',
        'Platform Heading',
        'Earth Relative Wind Direction',
        'Platform Relative Wind Direction',
        'Platform Speed Over Ground'
    ]);

    const allowedVarNames = new Set([
        'PL_CRS', 'PL_CRS2', 'PL_CRS3',
        'PL_HD', 'PL_HD2', 'PL_HD3',
        'DIR', 'DIR2', 'DIR3',
        'ER_WDIR', 'ER_WDIR2', 'ER_WDIR3',
        'PL_WDIR', 'PL_WDIR2', 'PL_WDIR3',
        'SPD', 'SPD1', 'SPD2', 'SPD3',
        'PL_SPD', 'PL_SPD2', 'PL_SPD3'
    ]);

    const degreeUnitPattern = /(deg|degree|degrees|\u00B0)/i;

    function normalizeAngle(value) {
        const wrapped = value % 360;
        return wrapped < 0 ? wrapped + 360 : wrapped;
    }

    function unwrapAngles(points) {
        if (!points || points.length === 0) return points;
        
        // For polar plots, we need to carefully handle the 0/360 boundary
        // Strategy: Detect smooth wraps vs. actual discontinuities
        const segments = [];
        let currentSegment = [{ ...points[0], segmentId: 0 }];
        let segmentId = 0;
        
        for (let i = 1; i < points.length; i++) {
            const curr = points[i].value;
            const prev = points[i - 1].value;
            
            // Check for large time gap (more than 5 minutes) - always split here
            const timeDiff = Math.abs(points[i].time - points[i - 1].time);
            const fiveMinutes = 5 * 60 * 1000; // milliseconds
            
            if (timeDiff > fiveMinutes) {
                // Split due to time gap
                segments.push(currentSegment);
                segmentId++;
                currentSegment = [{ ...points[i], segmentId }];
                continue;
            }
            
            // Calculate both raw difference and shortest angular difference
            const rawDiff = Math.abs(curr - prev);
            
            // Calculate shortest angular difference (accounting for wrap)
            let angularDiff = curr - prev;
            if (angularDiff > 180) {
                angularDiff -= 360;
            } else if (angularDiff < -180) {
                angularDiff += 360;
            }
            
            // Detect if this is a boundary crossing (0/360 wrap)
            // This happens when raw difference is large (>180) but angular difference is small
            const isBoundaryCross = rawDiff > 180 && Math.abs(angularDiff) < 90;
            
            // Detect if this is a real discontinuity (not a smooth wrap)
            // We consider it discontinuous if the angle changes by more than 120° 
            // (even on the shortest path) - this indicates actual data jump, not smooth rotation
            const isDiscontinuity = Math.abs(angularDiff) > 120;
            
            if (isBoundaryCross) {
                // This is a smooth 0/360 crossing - split segment to avoid visual artifacts
                // but it represents continuous rotation
                segments.push(currentSegment);
                segmentId++;
                currentSegment = [{ ...points[i], segmentId }];
            } else if (isDiscontinuity) {
                // Real discontinuity in the data - split here
                segments.push(currentSegment);
                segmentId++;
                currentSegment = [{ ...points[i], segmentId }];
            } else {
                // Continue current segment - this is smooth continuous data
                currentSegment.push({
                    ...points[i],
                    segmentId
                });
            }
        }
        
        segments.push(currentSegment);
        
        // Flatten all segments into one array
        return segments.flat();
    }

    function formatDateStr(d) {
        if (!d || d.length !== 8) return d || '';
        const year = d.substring(0, 4);
        const month = d.substring(4, 6);
        const day = d.substring(6, 8);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const monthName = months[parseInt(month, 10) - 1] || month;
        return `${monthName} ${parseInt(day, 10)}, ${year}`;
    }

    function isPolarVar(varName, unitsMap, longNames) {
        const units = unitsMap[varName] || '';
        const longName = (longNames[varName] || '').trim();
        return allowedLongNames.has(longName) || allowedVarNames.has(varName) || degreeUnitPattern.test(units);
    }

    function buildPolarDataset(payload) {
        const { plotData, units: unitsMap = {}, longNames = {} } = payload;
        const allVars = Object.keys(plotData || {});
        const vars = allVars.filter((v) => isPolarVar(v, unitsMap, longNames));

        const processedData = {};
        const allPoints = [];

        vars.forEach((v) => {
            const points = (plotData[v]?.points || [])
                .map((p) => ({
                    date: p.date,
                    value: p.value == null ? null : Number(p.value),
                    flag: p.flag || ' '
                }))
                .filter((p) => p.value != null && !Number.isNaN(p.value));

            processedData[v] = points;
            allPoints.push(...points);
        });

        return { vars, processedData, allPoints, unitsMap, longNames };
    }

    function renderPolarChart(payload, container, selectedVars = null, initialTransform = null) {
        container.innerHTML = '';

        if (!global.d3) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center;">D3 is not available.</p>';
            return;
        }

        const { vars: allVars, processedData, allPoints, unitsMap, longNames } = buildPolarDataset(payload);

        if (!allVars.length || !allPoints.length) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center;">No degree-based variables found for polar plotting.</p>';
            return;
        }

        // Filter to only selected variables
        const vars = selectedVars ? allVars.filter(v => selectedVars.includes(v)) : allVars;
        
        if (!vars.length) {
            container.innerHTML = '<p style="color:#999; text-align:center;">Select at least one variable to display.</p>';
            return;
        }

        // Recalculate points for selected variables only
        const visiblePoints = [];
        vars.forEach(v => {
            if (processedData[v]) {
                visiblePoints.push(...processedData[v]);
            }
        });

        if (!visiblePoints.length) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center;">No data available for selected variables.</p>';
            return;
        }

        const parseTime = d3.timeParse('%Y-%m-%d %H:%M:%S');
        const timeExtent = d3.extent(visiblePoints, (d) => parseTime(d.date));

        const containerWidth = container.offsetWidth || 900;
        const containerHeight = container.offsetHeight || 700;

        const margin = { top: 40, right: 70, bottom: 70, left: 70 };
        const width = Math.max(containerWidth - margin.left - margin.right, 380);
        const height = Math.max(containerHeight - margin.top - margin.bottom, 380);

        const outerRadius = Math.min(width, height) / 2 - 10;
        const innerRadius = Math.max(26, outerRadius * 0.18);

        const rScale = d3.scaleTime()
            .domain(timeExtent)
            .range([innerRadius, outerRadius]);

        const angleScale = d3.scaleLinear()
            .domain([0, 360])
            .range([0, Math.PI * 2]);

        const svg = d3.select(container)
            .append('svg')
            .attr('width', containerWidth)
            .attr('height', containerHeight)
            .attr('viewBox', `0 0 ${containerWidth} ${containerHeight}`)
            .attr('preserveAspectRatio', 'xMidYMid meet')
            .style('width', '100%')
            .style('height', '100%');

        const tooltip = (() => {
            const existing = d3.select('#polar-tooltip');
            if (!existing.empty()) return existing;
            return d3.select('body')
                .append('div')
                .attr('id', 'polar-tooltip')
                .style('position', 'absolute')
                .style('background', 'rgba(22, 32, 45, 0.92)')
                .style('border', '1px solid rgba(255,255,255,0.2)')
                .style('border-radius', '8px')
                .style('padding', '8px 10px')
                .style('color', '#f7f9fb')
                .style('font-family', '"Space Grotesk", "Segoe UI", sans-serif')
                .style('font-size', '12px')
                .style('pointer-events', 'none')
                .style('opacity', 0)
                .style('z-index', 10001);
        })();

        const defs = svg.append('defs');
        const glow = defs.append('filter')
            .attr('id', 'polar-glow')
            .attr('x', '-50%')
            .attr('y', '-50%')
            .attr('width', '200%')
            .attr('height', '200%');

        glow.append('feGaussianBlur')
            .attr('stdDeviation', '3')
            .attr('result', 'coloredBlur');
        const feMerge = glow.append('feMerge');
        feMerge.append('feMergeNode').attr('in', 'coloredBlur');
        feMerge.append('feMergeNode').attr('in', 'SourceGraphic');

        const radialGradient = defs.append('radialGradient')
            .attr('id', 'polar-bg')
            .attr('cx', '50%')
            .attr('cy', '45%')
            .attr('r', '65%');

        radialGradient.append('stop')
            .attr('offset', '0%')
            .attr('stop-color', '#f7fbff');
        radialGradient.append('stop')
            .attr('offset', '70%')
            .attr('stop-color', '#eef6ff');
        radialGradient.append('stop')
            .attr('offset', '100%')
            .attr('stop-color', '#dfe9f5');

        svg.append('rect')
            .attr('x', 0)
            .attr('y', 0)
            .attr('width', containerWidth)
            .attr('height', containerHeight)
            .attr('fill', 'url(#polar-bg)');

        const centerX = margin.left + width / 2;
        const centerY = margin.top + height / 2;

        const zoomRoot = svg.append('g')
            .attr('transform', `translate(${centerX}, ${centerY})`);

        const plot = zoomRoot.append('g');

        const color = d3.scaleOrdinal(d3.schemeCategory10).domain(allVars);
        const timeTicks = rScale.ticks(4).filter((d) => d);

        plot.selectAll('.polar-grid')
            .data(timeTicks)
            .enter()
            .append('circle')
            .attr('class', 'polar-grid')
            .attr('r', (d) => rScale(d))
            .attr('fill', 'none')
            .attr('stroke', '#b3c4d7')
            .attr('stroke-width', 1)
            .attr('stroke-dasharray', '4 6')
            .attr('opacity', 0.6);

        const angleTicks = [0, 90, 180, 270];
        plot.selectAll('.polar-angle-line')
            .data(angleTicks)
            .enter()
            .append('line')
            .attr('class', 'polar-angle-line')
            .attr('x1', 0)
            .attr('y1', 0)
            .attr('x2', (d) => Math.cos(angleScale(d) - Math.PI / 2) * outerRadius)
            .attr('y2', (d) => Math.sin(angleScale(d) - Math.PI / 2) * outerRadius)
            .attr('stroke', '#94a8bf')
            .attr('stroke-width', 1)
            .attr('opacity', 0.7);

        const cardinalMap = {
            0: 'N',
            90: 'E',
            180: 'S',
            270: 'W'
        };

        plot.selectAll('.polar-angle-label')
            .data(angleTicks)
            .enter()
            .append('text')
            .attr('class', 'polar-angle-label')
            .attr('x', (d) => Math.cos(angleScale(d) - Math.PI / 2) * (outerRadius + 18))
            .attr('y', (d) => Math.sin(angleScale(d) - Math.PI / 2) * (outerRadius + 18))
            .attr('text-anchor', 'middle')
            .attr('dominant-baseline', 'central')
            .style('font-family', '"Space Grotesk", "Segoe UI", sans-serif')
            .style('font-size', '13px')
            .style('font-weight', '700')
            .style('fill', '#2c3e50')
            .text((d) => cardinalMap[d] || d);

        const timeFormat = d3.timeFormat('%H:%M');
        plot.selectAll('.polar-time-label')
            .data(timeTicks)
            .enter()
            .append('text')
            .attr('class', 'polar-time-label')
            .attr('x', (d) => rScale(d) + 8)
            .attr('y', -6)
            .style('font-family', '"Space Grotesk", "Segoe UI", sans-serif')
            .style('font-size', '11px')
            .style('fill', '#3d5066')
            .text((d) => timeFormat(d));

        const lineRadial = d3.lineRadial()
            .angle((d) => (d.value / 360) * Math.PI * 2 - Math.PI / 2)
            .radius((d) => rScale(parseTime(d.date)))
            .curve(d3.curveLinear)
            .defined((d) => d.value != null);

        const hoverFormat = d3.timeFormat('%Y-%m-%d %H:%M');
        const timeBisect = d3.bisector((d) => d.time).left;

        const showTooltip = (event, d, label, unit) => {
            const angleValue = normalizeAngle(d.originalValue).toFixed(1);
            const valueText = unit ? `${angleValue} ${unit}` : `${angleValue}°`;

            tooltip
                .style('opacity', 1)
                .html(`
                    <div style="font-weight:700; margin-bottom:4px; color:#9fd2ff;">${label}</div>
                    <div>Time: ${hoverFormat(d.time)}</div>
                    <div>Angle: ${valueText}</div>
                `)
                .style('left', `${event.pageX + 12}px`)
                .style('top', `${event.pageY - 18}px`);
        };

        vars.forEach((v) => {
            const points = (processedData[v] || [])
                .map((p) => ({
                    ...p,
                    time: parseTime(p.date)
                }))
                .filter((p) => p.time)
                .sort((a, b) => a.time - b.time);
            if (!points.length) return;

            // Unwrap angles to prevent crossing 0/360 boundary issues
            const unwrappedPoints = unwrapAngles(points.map(p => ({
                ...p,
                originalValue: p.value
            })));

            // Group points by segment
            const segments = [];
            let currentSeg = [];
            let lastSegmentId = unwrappedPoints[0]?.segmentId;
            
            unwrappedPoints.forEach(p => {
                if (p.segmentId !== lastSegmentId) {
                    if (currentSeg.length > 0) segments.push(currentSeg);
                    currentSeg = [p];
                    lastSegmentId = p.segmentId;
                } else {
                    currentSeg.push(p);
                }
            });
            if (currentSeg.length > 0) segments.push(currentSeg);

            // Draw each segment separately
            segments.forEach(segmentPoints => {
                if (segmentPoints.length < 2) return;
                
                plot.append('path')
                    .datum(segmentPoints)
                    .attr('fill', 'none')
                    .attr('stroke', color(v))
                    .attr('stroke-width', 1.5)
                    .attr('filter', 'url(#polar-glow)')
                    .attr('d', lineRadial);
            });

            // Draw end marker on the last point
            const lastPoint = unwrappedPoints[unwrappedPoints.length - 1];
            const lastAngleRad = (lastPoint.value / 360) * Math.PI * 2 - Math.PI / 2;
            plot.append('circle')
                .attr('cx', Math.cos(lastAngleRad) * rScale(lastPoint.time))
                .attr('cy', Math.sin(lastAngleRad) * rScale(lastPoint.time))
                .attr('r', 4.5)
                .attr('fill', color(v))
                .attr('stroke', '#ffffff')
                .attr('stroke-width', 1.5);

            // Invisible hover path for all segments
            segments.forEach(segmentPoints => {
                if (segmentPoints.length < 2) return;
                
                plot.append('path')
                    .datum(segmentPoints)
                    .attr('fill', 'none')
                    .attr('stroke', 'transparent')
                    .attr('stroke-width', 18)
                    .attr('d', lineRadial)
                    .style('cursor', 'crosshair')
                    .style('pointer-events', 'stroke')
                    .on('mousemove', (event) => {
                        const [mx, my] = d3.pointer(event, plot.node());
                        const radius = Math.sqrt((mx * mx) + (my * my));
                        if (radius < innerRadius || radius > outerRadius) {
                            tooltip.style('opacity', 0);
                            return;
                        }

                        const timeValue = rScale.invert(radius);
                        const index = timeBisect(segmentPoints, timeValue);
                        const prev = segmentPoints[index - 1];
                        const next = segmentPoints[index];
                        const nearest = !prev ? next : !next ? prev : (timeValue - prev.time > next.time - timeValue ? next : prev);

                        if (!nearest) return;

                        const label = longNames[v] || v;
                        const unit = unitsMap[v] ? unitsMap[v].split(' (')[0] : '';

                        showTooltip(event, nearest, label, unit);
                    })
                    .on('mouseleave', () => {
                        tooltip.style('opacity', 0);
                    });
            });
        });

        const zoom = d3.zoom()
            .scaleExtent([0.7, 6])
            .on('start', () => {
                tooltip.style('opacity', 0);
            })
            .on('zoom', (event) => {
                plot.attr('transform', event.transform);
            });

        svg.call(zoom);
        
        // Apply initial transform if provided (to preserve zoom state)
        if (initialTransform) {
            svg.call(zoom.transform, initialTransform);
        }

        const resetBtn = document.getElementById('resetPolarBtn');
        if (resetBtn) {
            resetBtn.onclick = () => {
                svg.transition().duration(600).call(zoom.transform, d3.zoomIdentity);
            };
        }
    }

    global.openPolarModal = function () {
        const modal = document.getElementById('polarModal');
        const container = document.getElementById('polarChartContainer');
        const headerArea = document.getElementById('polarHeaderArea');
        const titleText = document.getElementById('polarTitleText');
        const controlsDiv = document.getElementById('polarVariableControls');
        
        if (!modal || !container) return;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        container.innerHTML = '';
        if (controlsDiv) controlsDiv.innerHTML = '';

        const payload = window.__originalPolarData || window.__originalChartData;
        if (!payload) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center;">No plot payload found.</p>';
            return;
        }

        // Build dataset to get available variables
        const { vars: allVars, unitsMap, longNames } = buildPolarDataset(payload);
        
        if (!allVars.length) {
            container.innerHTML = '<p style="color:#e74c3c; text-align:center;">No degree-based variables found.</p>';
            return;
        }

        // Set title
        if (titleText) {
            const { ship = '', shipName = '', date = '', hs = '00:00', he = '23:59' } = payload;
            
            const shipNameDict = {
                'WDC9': 'Atlantic Explorer',
                'KAQP': 'Atlantis',
                'WTED': 'Bell M. Shimada',
                'WTEB': 'Fairweather',
                'ZGOJ7': 'Falkor (too)',
                'WTEK': 'Ferdinand Hassler',
                'WTEO': 'Gordon Gunter',
                'NEPP': 'Healy',
                'WTDF': 'Henry B. Bigelow',
                'VLMJ': 'Investigator',
                'WDA7827': 'Kilo Moana',
                'WTER': 'Nancy Foster',
                'WARL': 'Neil Armstrong',
                'VMIC': 'Nuyina',
                'WTDH': 'Okeanos Explorer',
                'WTDO': 'Oregon II',
                'WTEP': 'Oscar Dyson',
                'WTEE': 'Oscar Elton Sette',
                'WTDL': 'Pisces',
                'WTEF': 'Rainier',
                'WTEG': 'Reuben Lasker',
                'WSQ2674': 'Robert Gordon Sproul',
                'KAOU': 'Roger Revelle',
                'WTEC': 'Ron Brown',
                'WSAF': 'Sally Ride',
                'WDN7246': 'Sikuliaq',
                'WDN7246C': 'Sikuliaq',
                'KTDQ': 'T.G. Thompson',
                'ZMFR': 'Tangaroa',
                'WTEA': 'Thomas Jefferson'
            };

            const formatDateStr = (d) => {
                if (!d || d.length !== 8) return d;
                const year = d.substring(0, 4);
                const month = d.substring(4, 6);
                const day = d.substring(6, 8);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                               'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthName = months[parseInt(month, 10) - 1] || month;
                return `${monthName} ${parseInt(day, 10)}, ${year}`;
            };

            const resolvedShipName = shipNameDict[ship] || shipName || ship;
            const titleParts = [];
            if (resolvedShipName) titleParts.push(resolvedShipName);
            if (ship && ship !== resolvedShipName) titleParts.push(`(${ship})`);
            
            let title = titleParts.join(' ');
            if (date) title += ` - ${formatDateStr(date)}`;
            title += ` | ${hs} - ${he} UTC`;
            
            titleText.textContent = title;
        }

        // Initialize all variables as selected
        const selectedVars = new Set(allVars);
        
        // Create color scale
        const color = d3.scaleOrdinal(d3.schemeCategory10).domain(allVars);

        // Create checkboxes for each variable
        if (controlsDiv) {
            allVars.forEach(v => {
                const label = document.createElement('label');
                label.style.cssText = 'display:flex; align-items:center; gap:6px; cursor:pointer; user-select:none;';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = true;
                checkbox.style.cssText = 'cursor:pointer;';
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        selectedVars.add(v);
                    } else {
                        selectedVars.delete(v);
                    }
                    // Preserve current zoom transform
                    const svg = container.querySelector('svg');
                    const currentTransform = svg ? d3.zoomTransform(svg) : null;
                    requestAnimationFrame(() => {
                        renderPolarChart(payload, container, Array.from(selectedVars), currentTransform);
                    });
                });
                
                const colorBox = document.createElement('span');
                colorBox.style.cssText = `width:14px; height:14px; background:${color(v)}; border:1px solid #333; border-radius:3px; display:inline-block;`;
                
                const varName = document.createElement('span');
                varName.textContent = v;
                varName.style.cssText = 'font-weight:600; color:#2c3e50;';
                
                label.appendChild(checkbox);
                label.appendChild(colorBox);
                label.appendChild(varName);
                controlsDiv.appendChild(label);
            });
        }

        requestAnimationFrame(() => {
            renderPolarChart(payload, container, Array.from(selectedVars));
        });
    };

    global.closePolarModal = function () {
        const modal = document.getElementById('polarModal');
        if (modal) modal.style.display = 'none';
        const tooltip = document.getElementById('polar-tooltip');
        if (tooltip) tooltip.style.opacity = 0;
        document.body.style.overflow = '';
    };

    global.downloadPolarPlot = function () {
        const container = document.getElementById('polarChartContainer');
        if (!container) return;
        const svg = container.querySelector('svg');
        if (!svg) return;

        const svgClone = svg.cloneNode(true);
        const width = svgClone.getAttribute('width') || 1200;
        const height = svgClone.getAttribute('height') || 900;

        const styleElement = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.textContent = 'text { font-family: "Space Grotesk", "Segoe UI", sans-serif; }';
        svgClone.insertBefore(styleElement, svgClone.firstChild);

        const serializer = new XMLSerializer();
        const svgString = serializer.serializeToString(svgClone);
        const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);

        const img = new Image();
        img.onload = function () {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.drawImage(img, 0, 0);

            canvas.toBlob(function (pngBlob) {
                const payload = window.__originalPolarData || window.__originalChartData || {};
                const shipName = (payload.shipName || payload.ship || 'polar-plot').replace(/[^a-zA-Z0-9]/g, '_');
                const dateStr = payload.date ? payload.date.substring(0, 8) : new Date().toISOString().slice(0, 10).replace(/-/g, '');
                const hsTime = (payload.hs || '00:00').replace(':', '');
                const heTime = (payload.he || '23:59').replace(':', '');
                const filename = `${shipName}_${dateStr}_${hsTime}-${heTime}_polar.png`;

                const downloadUrl = URL.createObjectURL(pngBlob);
                const link = document.createElement('a');
                link.download = filename;
                link.href = downloadUrl;
                link.click();

                URL.revokeObjectURL(downloadUrl);
                URL.revokeObjectURL(url);
            });
        };
        img.src = url;
    };
})(window);
