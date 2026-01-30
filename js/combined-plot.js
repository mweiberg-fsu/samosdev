// js/combined-plot.js
(function (global) {
    'use strict';

    let tooltip = null;

    function initTooltip() {
        if (tooltip) return tooltip;
        tooltip = d3.select('body')
            .append('div')
            .attr('id', 'line-tooltip')
            .style('position', 'absolute')
            .style('background', 'white')
            .style('border', '2px solid #333')
            .style('border-radius', '8px')
            .style('padding', '0')
            .style('overflow', 'hidden')
            .style('box-shadow', '0 6px 16px rgba(0,0,0,0.3)')
            .style('pointer-events', 'none')
            .style('opacity', 0)
            .style('z-index', 10000)
            .style('font-family', 'Arial, sans-serif')
            .style('text-align', 'center')
            .style('min-width', '140px')
            .style('transition', 'opacity 0.15s');
        return tooltip;
    }

    global.renderCombinedPlot = function (payload, chartId) {

        console.log('Full payload:', payload);

        const {
            plotData: data,
            units: unitsMap = {},
            longNames = {},
            selectedVar = null,  // This is the key new field
            ship = '',           // Call sign
            shipName = '',       // Full ship name
            date = '',           // Date (YYYYMMDD format)
            hs = '00:00',        // Start time
            he = '23:59'         // End time
        } = payload;

        if (!data || Object.keys(data).length === 0) return;

        const vars = Object.keys(data);
        
        // === CALCULATE LEGEND LINES FIRST (to determine top margin) ===
        const legendLineHeight = 20;
        const itemSpacing = 25;
        const baseAvailableWidth = 500; // Base width for legend calculation
        
        // Helper function to measure text width
        const measureTextWidth = (text, fontSize = '12px', fontWeight = 'bold', fontFamily = 'sans-serif') => {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            context.font = `${fontWeight} ${fontSize} ${fontFamily}`;
            return context.measureText(text).width;
        };
        
        // Build legend items and calculate lines
        const legendItems = [];
        vars.forEach(v => {
            const longName = longNames[v] || v;
            const unitPart = unitsMap[v] ? ` (${unitsMap[v].split(' (')[0]})` : '';
            const displayName = longName + unitPart;
            const textWidth = measureTextWidth(displayName);
            const itemWidth = 18 + textWidth;
            legendItems.push({ v, displayName, itemWidth, textWidth });
        });
        
        // Arrange into lines
        const legendLines = [];
        let currentLine = [];
        let currentLineWidth = 0;
        
        legendItems.forEach(item => {
            const additionalWidth = currentLine.length === 0 ? item.itemWidth : (itemSpacing + item.itemWidth);
            const totalWidth = currentLineWidth + additionalWidth;
            
            if (totalWidth > baseAvailableWidth && currentLine.length > 0) {
                legendLines.push(currentLine);
                currentLine = [item];
                currentLineWidth = item.itemWidth;
            } else {
                currentLine.push(item);
                currentLineWidth = totalWidth;
            }
        });
        if (currentLine.length > 0) {
            legendLines.push(currentLine);
        }
        
        const numLegendLines = legendLines.length;
        
        // === CALCULATE DYNAMIC TOP MARGIN ===
        const titlePadding = 15;
        const titleHeight = 20;
        const titleLegendGap = 10;
        const legendBuffer = 20;
        const dynamicTopMargin = titlePadding + titleHeight + titleLegendGap + (numLegendLines * legendLineHeight) + legendBuffer;

        const parseTime = d3.timeParse('%Y-%m-%d %H:%M:%S');
        const formatTime = d3.timeFormat('%H:%M');

        // Process data + preserve flags
        const allValidPoints = [];
        const processedData = {};

        vars.forEach(v => {
            processedData[v] = (data[v]?.points || [])
                .map(p => ({
                    date: p.date,
                    value: p.value == null ? null : Number(p.value),
                    flag: p.flag || ' '
                }))
                .filter(p => p.value != null && !isNaN(p.value));
            allValidPoints.push(...processedData[v]);
        });

        if (allValidPoints.length === 0) return;

        const windDirectionVars = ['PL_WDIR', 'PL_WDIR2', 'PL_WDIR3'];
        const color = d3.scaleOrdinal(d3.schemeCategory10).domain(vars);

        // Group variables by their units
        const unitGroups = {};
        vars.forEach(v => {
            const unit = unitsMap[v] || 'default';
            if (!unitGroups[unit]) unitGroups[unit] = [];
            unitGroups[unit].push(v);
        });

        const uniqueUnits = Object.keys(unitGroups);
        const numAxes = Math.min(uniqueUnits.length, 6); // Cap at 6 axes
        
        // === CALCULATE DYNAMIC LEFT/RIGHT MARGINS BASED ON NUMBER OF AXES ===
        // Distribute axes: left gets ceil(n/2), right gets floor(n/2)
        const numLeftAxes = Math.ceil(numAxes / 2);
        const numRightAxes = Math.floor(numAxes / 2);
        const axisSpacing = 55;
        const baseMargin = 50; // Base margin for axis labels
        
        const dynamicLeftMargin = baseMargin + (numLeftAxes > 0 ? (numLeftAxes - 1) * axisSpacing + 50 : 0);
        const dynamicRightMargin = baseMargin + (numRightAxes > 0 ? (numRightAxes - 1) * axisSpacing + 50 : 0);
        
        const margin = { 
            top: Math.max(70, dynamicTopMargin), 
            right: Math.max(50, dynamicRightMargin), 
            bottom: 70, 
            left: Math.max(70, dynamicLeftMargin) 
        };
        const width = 790 - margin.left - margin.right;
        const height = 520 - margin.top - margin.bottom;

        const x = d3.scaleTime()
            .domain(d3.extent(allValidPoints, d => parseTime(d.date)))
            .range([0, width]);

        // Create separate y-scales for each variable
        const yScales = {};

        // Create a scale for each unique unit
        uniqueUnits.forEach((unit, idx) => {
            const varsInGroup = unitGroups[unit];
            const points = varsInGroup.flatMap(v => processedData[v] || []);

            if (points.length === 0) return;

            const isWindDir = varsInGroup.every(v => windDirectionVars.includes(v));

            let scale;
            if (isWindDir) {
                scale = d3.scaleLinear().domain([0, 360]).range([height, 0]);
            } else {
                const values = points.map(d => d.value);
                const min = d3.min(values);
                const max = d3.max(values);
                if (min === max) {
                    scale = d3.scaleLinear().domain([min * 0.9, min * 1.1]).range([height, 0]);
                } else {
                    const padding = (max - min) * 0.15;
                    scale = d3.scaleLinear().domain([min - padding, max + padding]).range([height, 0]);
                }
            }

            // Store scale for all variables in this group
            varsInGroup.forEach(v => {
                yScales[v] = scale;
            });
        });

        // Update SVG container with fixed margins for 3 left + 3 right axes
        d3.select('#' + chartId).html('');
        const svgContainer = d3.select('#' + chartId)
            .append('svg')
            .attr('width', width + margin.left + margin.right)
            .attr('height', height + margin.top + margin.bottom)
            .attr('viewBox', `0 0 ${width + margin.left + margin.right} ${height + margin.top + margin.bottom}`)
            .attr('preserveAspectRatio', 'xMidYMid meet')
            .style('max-width', '100%')
            .style('height', 'auto');

        const svg = svgContainer.append('g')
            .attr('transform', `translate(${margin.left},${margin.top})`);

        // ===== SHIP NAME DICTIONARY =====
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
            'WTEA': 'Thomas Jefferson',
        };

        // ===== ADD TITLE =====
        // Format the date from YYYYMMDD to readable format
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

        // Look up ship name from dictionary, fallback to payload shipName or call sign
        const resolvedShipName = shipNameDict[ship] || shipName || ship;

        // Build title string
        const titleParts = [];
        if (resolvedShipName) titleParts.push(resolvedShipName);
        if (ship && ship !== resolvedShipName) titleParts.push(`(${ship})`);
        
        let titleText = titleParts.join(' ');
        if (date) titleText += ` - ${formatDateStr(date)}`;

        // Always show time range (including default 00:00 - 23:59)
        const startTime = hs || '00:00';
        const endTime = he || '23:59';
        titleText += ` | ${startTime} - ${endTime} UTC`;

        // Draw title with padding from top
        const titleY = -(margin.top - titlePadding - titleHeight/2);
        svg.append('text')
            .attr('class', 'chart-title')
            .attr('x', width / 2)
            .attr('y', titleY)
            .attr('text-anchor', 'middle')
            .style('font-family', 'Arial, Helvetica, sans-serif')
            .style('font-size', '16px')
            .style('font-weight', 'bold')
            .style('fill', '#2c3e50')
            .text(titleText);

        // Draw y-axes - evenly distributed left/right
        // Left gets indices 0, 2, 4... Right gets indices 1, 3, 5...
        // This way: 1 axis = left, 2 axes = left+right, 3 axes = 2 left + 1 right, etc.
        
        let leftAxisCount = 0;
        let rightAxisCount = 0;
        
        uniqueUnits.slice(0, numAxes).forEach((unit, idx) => {
            const varsInGroup = unitGroups[unit];
            const scale = yScales[varsInGroup[0]];
            const isWindDir = varsInGroup.every(v => windDirectionVars.includes(v));

            // Alternate: even indices go left, odd indices go right
            const isLeft = (idx % 2 === 0);
            const positionInSide = isLeft ? leftAxisCount : rightAxisCount;
            
            if (isLeft) leftAxisCount++;
            else rightAxisCount++;
            
            let yAxis, axisOffset, labelOffset;
            
            if (isLeft) {
                yAxis = d3.axisLeft(scale).ticks(8);
                axisOffset = -positionInSide * axisSpacing;
                // Increased padding to provide extra buffer for left axes
                labelOffset = axisOffset - 80;
            } else {
                yAxis = d3.axisRight(scale).ticks(8);
                axisOffset = width + positionInSide * axisSpacing;
                // Increased padding to provide extra buffer for right axes
                labelOffset = axisOffset + 85;
            }
            
            if (isWindDir) {
                yAxis.tickValues([0, 45, 90, 135, 180, 225, 270, 315, 360])
                    .tickFormat(d => {
                        if (d === 0 || d === 360) return 'N';
                        if (d === 90) return 'E';
                        if (d === 180) return 'S';
                        if (d === 270) return 'W';
                        return d + '\u00B0';
                    });
            }

            const axisGroup = svg.append('g')
                .attr('class', `y axis axis-${idx}`)
                .attr('transform', `translate(${axisOffset}, 0)`)
                .call(yAxis);

            // Color-code the axis
            const axisColor = color(varsInGroup[0]);
            axisGroup.selectAll('path, line').style('stroke', axisColor);
            axisGroup.selectAll('text')
                .style('fill', axisColor)
                .style('font-size', '15px');

            // Y-axis label
            svg.append('text')
                .attr('transform', 'rotate(-90)')
                .attr('y', labelOffset)
                .attr('x', -height / 2)
                .attr('dy', isLeft ? '1em' : '-0.3em')
                .style('text-anchor', 'middle')
                .style('font-family', 'Arial, Helvetica, sans-serif')
                .style('font-size', '15px')
                .style('font-weight', 'bold')
                .style('fill', axisColor)
                .text(unit);
        });

        // X-Axis
        svg.append('g')
            .attr('class', 'x axis')
            .attr('transform', `translate(0,${height})`)
            .call(d3.axisBottom(x).ticks(12).tickFormat(d3.timeFormat('%H:%M')))
            .selectAll('text')
            .style('text-anchor', 'end')
            .style('font-size', '15px')
            .attr('dx', '-0.8em')
            .attr('dy', '0.15em')
            .attr('transform', 'rotate(-45)');

        const tip = initTooltip();

        // Draw lines + hover tooltip
        vars.forEach(v => {
            const points = processedData[v] || [];
            const yScale = yScales[v]; // Get the correct scale for this variable

            // Create line generator with correct y-scale
            const lineGen = d3.line()
                .x(d => x(parseTime(d.date)))
                .y(d => yScale(d.value))
                .defined(d => d.value != null);

            // Actual line
            svg.append('path')
                .datum(points)
                .attr('fill', 'none')
                .attr('stroke', color(v))
                .attr('stroke-width', 2)
                .attr('d', lineGen);

            // Invisible fat line for easier hovering
            svg.append('path')
                .datum(points)
                .attr('fill', 'none')
                .attr('stroke', 'transparent')
                .attr('stroke-width', 16)
                .attr('d', lineGen)
                .style('cursor', 'crosshair')
                .on('mousemove', function (event) {
                    const [mx] = d3.pointer(event, this);
                    const x0 = x.invert(mx);
                    const i = d3.bisector(d => parseTime(d.date)).left(points, x0, 1);
                    const d0 = points[i - 1];
                    const d1 = points[i];
                    if (!d0 || !d1) return;

                    const d = x0 - parseTime(d0.date) > parseTime(d1.date) - x0 ? d1 : d0;
                    if (d.value == null) return;

                    const timeStr = formatTime(parseTime(d.date));
                    const isWindDirectionVar = windDirectionVars.includes(v);
                    const valueStr = Number(d.value).toFixed(isWindDirectionVar ? 0 : 2);
                    const unitLabel = unitsMap[v] || '';
                    const displayValue = unitLabel ? `${valueStr} ${unitLabel.split(' (')[0]}` : valueStr; // strips long desc if needed

                    // Tooltip flag bar variables
                    const flag = d.flag && d.flag.trim() !== '' && d.flag.trim() !== ' ' ? d.flag.trim() : 'Z';
                    const colors = {
                        'B': '#00FFFF', 'D': '#0000FF', 'E': '#8A2BE2', 'F': '#00FF00',
                        'G': '#FF8C00', 'I': '#FFFF00', 'J': '#FF00FF', 'K': '#FF0000',
                        'L': '#40E0D0', 'M': '#006400', 'S': '#FF69B4', 'Z': '#444444'
                    };
                    const bg = colors[flag] || '#444444';
                    const textColor = flag === 'I' ? '#000' : '#FFF';

                    tip.html(`
                        <div style="display: flex; flex-direction: column; align-items: stretch; min-width: 180px; padding: 0; margin: 0;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 12px 12px 0 12px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 18px; height: 18px; background: ${color(v)}; border: 1px solid #000; border-radius: 4px;"></div>
                                    <strong style="font-size: 15px; color: #222;">${v}</strong>
                                </div>
                                <div style="font-size: 14px; color: #444;">${timeStr}</div>
                                <div style="font-weight: bold; font-size: 16px; color: #000; padding-bottom: 12px;">${displayValue}</div>
                            </div>
                            <div style="height: 35px; width: 100%; background: ${bg}; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 17px; color: ${textColor}; letter-spacing: 1px; border-top: 2px solid #222; flex-shrink: 0;">
                                FLAG: ${flag}
                            </div>
                        </div>
                    `)
                        .style('left', (event.pageX + 18) + 'px')
                        .style('top', (event.pageY - 90) + 'px')
                        .style('opacity', 1);
                })
                .on('mouseout', () => tip.style('opacity', 0));
        });

        // === LEGEND - Use pre-calculated lines from margin calculation ===
        const legendStartY = titleY + titleHeight/2 + titleLegendGap;
        
        const legend = svg.append('g')
            .attr('class', 'legend');

        // Add color to legendItems
        legendItems.forEach(item => {
            item.col = color(item.v);
        });

        legendLines.forEach((line, lineNum) => {
            // Calculate line width
            let lineWidth = 0;
            line.forEach((item, idx) => {
                lineWidth += item.itemWidth;
                if (idx < line.length - 1) {
                    lineWidth += itemSpacing;
                }
            });
            
            // Center each line
            const lineStartX = (width - lineWidth) / 2;
            let xPos = lineStartX;
            const yPos = legendStartY + lineNum * legendLineHeight;

            line.forEach(item => {
                const legendItem = legend.append('g')
                    .attr('transform', `translate(${xPos}, ${yPos})`);

                // Color box
                legendItem.append('rect')
                    .attr('x', 0)
                    .attr('y', -6)
                    .attr('width', 12)
                    .attr('height', 12)
                    .style('fill', item.col);

                // Label text
                legendItem.append('text')
                    .attr('x', 18)
                    .attr('y', 0)
                    .attr('dy', '0.35em')
                    .style('font-family', 'Arial, Helvetica, sans-serif')
                    .style('font-size', '12px')
                    .style('font-weight', 'bold')
                    .style('fill', item.col)
                    .text(item.displayName);

                xPos += item.itemWidth + itemSpacing;
            });
        });

        window.__originalChartData = payload;
    };

    // ----------------------------------------------------------------
    // DOWNLOAD & MODAL FUNCTIONS (unchanged)
    // ----------------------------------------------------------------
    global.downloadCombinedPlot = function (chartId) {
        const svg = document.querySelector('#' + chartId + ' svg');
        if (!svg) return;

        const svgClone = svg.cloneNode(true);
        svgClone.setAttribute('width', 790);
        svgClone.setAttribute('height', 520);
        if (!svgClone.hasAttribute('xmlns')) svgClone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        // Inject font styles to ensure consistent rendering on export
        const styleElement = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.textContent = `
            text { font-family: Arial, Helvetica, sans-serif; }
            .chart-title { font-family: Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold; }
            .legend text { font-family: Arial, Helvetica, sans-serif; font-size: 12px; font-weight: bold; }
            .axis text { font-family: Arial, Helvetica, sans-serif; }
        `;
        svgClone.insertBefore(styleElement, svgClone.firstChild);

        // Also explicitly set font-family on all text elements
        svgClone.querySelectorAll('text').forEach(textEl => {
            if (!textEl.style.fontFamily) {
                textEl.style.fontFamily = 'Arial, Helvetica, sans-serif';
            }
        });

        const serializer = new XMLSerializer();
        let source = serializer.serializeToString(svgClone);
        source = '<?xml version="1.0" standalone="no"?>\n' + source;

        const canvas = document.createElement('canvas');
        canvas.width = 790;
        canvas.height = 520;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        const img = new Image();
        const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        img.onload = function () {
            ctx.drawImage(img, 0, 0);
            URL.revokeObjectURL(url);
            canvas.toBlob(function (blob) {
                // Build filename with ship, date, time
                const payload = window.__originalChartData || {};
                const shipName = (payload.shipName || payload.ship || 'plot').replace(/[^a-zA-Z0-9]/g, '_');
                const dateStr = payload.date ? payload.date.substring(0, 8) : new Date().toISOString().slice(0, 10).replace(/-/g, '');
                const hsTime = (payload.hs || '00:00').replace(':', '');
                const heTime = (payload.he || '23:59').replace(':', '');
                const filename = `${shipName}_${dateStr}_${hsTime}-${heTime}.png`;
                
                const a = document.createElement('a');
                a.download = filename;
                a.href = URL.createObjectURL(blob);
                a.click();
                URL.revokeObjectURL(a.href);
            });
        };
        img.src = url;
    };

    global.downloadCombinedCSV = function (chartId) {
        // Try per-chart payloads (plot_all) first, then fallback to original chart data
        const payload = (window.__chartPayloads && window.__chartPayloads[chartId]) ? window.__chartPayloads[chartId] : (window.__originalChartData || null);
        if (!payload) return;

        const { plotData, units: unitsMap = {}, longNames = {} } = payload;
        const vars = Object.keys(plotData || {});
        if (vars.length === 0) return;

        // Collect all unique timestamps
        const allTimestamps = new Set();
        vars.forEach(v => {
            const points = plotData[v]?.points || [];
            points.forEach(p => allTimestamps.add(p.date));
        });

        const timestamps = Array.from(allTimestamps).sort();

        // Build CSV header
        let csv = 'Timestamp';
        vars.forEach(v => {
            const longName = longNames[v] || v;
            const unit = unitsMap[v] || '';
            csv += ',' + longName;
            if (unit) csv += ' (' + unit.split(' (')[0] + ')';
            csv += ',Flag';
        });
        csv += '\n';

        // Build CSV rows
        timestamps.forEach(ts => {
            csv += ts;
            vars.forEach(v => {
                const points = plotData[v]?.points || [];
                const point = points.find(p => p.date === ts);
                if (point) {
                    csv += ',' + (point.value !== null ? point.value : '');
                    csv += ',' + (point.flag && point.flag.trim() !== ' ' ? point.flag.trim() : '');
                } else {
                    csv += ',,';
                }
            });
            csv += '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const shipName = (payload.shipName || payload.ship || 'plot-data').replace(/[^a-zA-Z0-9]/g, '_');
        const dateStr = payload.date ? payload.date.substring(0, 8) : new Date().toISOString().slice(0, 10).replace(/-/g, '');
        const hsTime = (payload.hs || '00:00').replace(':', '');
        const heTime = (payload.he || '23:59').replace(':', '');
        const filename = `${shipName}_${dateStr}_${hsTime}-${heTime}.csv`;

        const link = document.createElement('a');
        link.download = filename;
        link.href = url;
        link.click();
        URL.revokeObjectURL(url);
    };

    global.openModal = function (chartId) {
        const modal = document.getElementById('chartModal');
        const container = document.getElementById('modalChartContainer');
        if (!modal || !container) return;

        const originalSvg = document.querySelector('#' + chartId + ' svg');
        if (!originalSvg) return;

        container.innerHTML = '';
        const svgClone = originalSvg.cloneNode(true);
        svgClone.setAttribute('width', 1200);
        svgClone.setAttribute('height', 800);
        svgClone.setAttribute('viewBox', '0 0 790 520');
        if (!svgClone.hasAttribute('xmlns')) svgClone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');

        container.appendChild(svgClone);
        modal.style.display = 'flex';

        const tip = document.getElementById('line-tooltip');
        if (tip) tip.style.display = 'none';
    };

    global.closeModal = function () {
        const modal = document.getElementById('chartModal');
        if (modal) modal.style.display = 'none';
        const tip = document.getElementById('line-tooltip');
        if (tip) tip.style.display = 'block';
    };
    // ---------------------------------------------------------------
    // TIME INPUT AUTO-FORMATTER: accepts 0000, 1230, 930, 9 → becomes 00:00, 12:30, etc.
    // ---------------------------------------------------------------
    function initTimeInputFormatter() {
        // Only run once
        if (window.__timeFormatterInitialized) return;
        window.__timeFormatterInitialized = true;

        const formatTimeInput = function (input) {
            let val = input.value.replace(/[^0-9]/g, ''); // strip non-digits

            if (val.length === 0) {
                input.value = '';
                return;
            }

            // Pad short inputs intelligently
            if (val.length === 1) {
                if (parseInt(val) > 2) val = '0' + val;
                val = val.padStart(4, '0');
            } else if (val.length === 2) {
                if (parseInt(val) > 23) val = '23';
                val = val.padStart(4, '0');
            } else if (val.length === 3) {
                val = val.padStart(4, '0');
            } else if (val.length >= 4) {
                val = val.substring(0, 4);
            }

            let hh = val.substring(0, 2);
            let mm = val.substring(2, 4);

            // Clamp hours and minutes
            if (parseInt(hh) > 23) hh = '23';
            if (parseInt(mm) > 59) mm = '59';

            input.value = hh + ':' + mm;
        };

        // Use event delegation + onblur for best UX (lets user type freely)
        document.addEventListener('blur', function (e) {
            if (e.target && (e.target.name === 'hs' || e.target.name === 'he')) {
                formatTimeInput(e.target);
            }
        }, true);

        // Optional: also format on Enter key (nice touch)
        document.addEventListener('keydown', function (e) {
            if ((e.target.name === 'hs' || e.target.name === 'he') && e.key === 'Enter') {
                formatTimeInput(e.target);
            }
        });
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTimeInputFormatter);
    } else {
        initTimeInputFormatter();
    }

})(window);