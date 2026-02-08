// js/zoom-pan.js
(function (global) {
    'use strict';

    // Fix UTF-8 encoding issues (e.g., "Â°" -> "°")
    function fixEncoding(str) {
        if (!str) return str;
        return str.replace(/Â°/g, '°').replace(/Â/g, '');
    }

    global.openZoomModal = function () {
        const modal = document.getElementById('zoomModal');
        const container = document.getElementById('zoomChartContainer');
        if (!modal || !container) return;

        // Make modal visible early so we can read container dimensions
        modal.style.display = 'flex';
        
        container.innerHTML = '';

        const payload = window.__originalChartData;
        if (!payload) return;

        // Delay chart rendering to ensure layout is complete
        requestAnimationFrame(() => {
            renderChart();
        });
        
        function renderChart() {

        const { 
            plotData: data, 
            units: unitsMap = {}, 
            longNames = {},
            ship = '',           // Call sign
            shipName = '',       // Full ship name
            date = '',           // Date (YYYYMMDD format)
            hs = '00:00',        // Start time
            he = '23:59'         // End time
        } = payload;
        const vars = Object.keys(data);
        
        // === CALCULATE LEGEND LINES FIRST (to determine top margin) ===
        const legendLineHeight = 22;
        const itemSpacing = 30;
        const availableWidth = 900; // Width available for legend items
        
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
            const unitPart = unitsMap[v] ? ` (${fixEncoding(unitsMap[v]).split(' (')[0]})` : '';
            const displayName = longName + unitPart;
            const textWidth = measureTextWidth(displayName);
            const itemWidth = 18 + textWidth; // box(12) + gap(6) + text
            legendItems.push({ v, displayName, itemWidth, textWidth });
        });
        
        // Arrange into lines
        const legendLines = [];
        let currentLine = [];
        let currentLineWidth = 0;
        
        legendItems.forEach(item => {
            const additionalWidth = currentLine.length === 0 ? item.itemWidth : (itemSpacing + item.itemWidth);
            const totalWidth = currentLineWidth + additionalWidth;
            
            if (totalWidth > availableWidth && currentLine.length > 0) {
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
        console.log('Number of legend lines:', numLegendLines);
        
        // === CALCULATE DYNAMIC TOP MARGIN ===
        const titlePadding = 20;
        const titleHeight = 25;
        const titleLegendGap = 15;
        const legendBuffer = 25;
        const dynamicTopMargin = titlePadding + titleHeight + titleLegendGap + (numLegendLines * legendLineHeight) + legendBuffer;

        // Prevent body scrolling when modal is open
        document.body.style.overflow = 'hidden';

        const parseTime = d3.timeParse('%Y-%m-%d %H:%M:%S');
        const formatTime = d3.timeFormat('%H:%M');

        // Process data
        const processedData = {};
        const allValidPoints = [];

        vars.forEach(v => {
            const points = (data[v]?.points || [])
                .map(p => ({
                    date: p.date,
                    value: p.value == null ? null : Number(p.value),
                    flag: p.flag || ' '
                }))
                .filter(p => p.value != null && !isNaN(p.value));

            processedData[v] = points;
            allValidPoints.push(...points);
        });

        if (allValidPoints.length === 0) return;

        const windDirectionVars = ['PL_WDIR', 'PL_WDIR2', 'PL_WDIR3'];
        const color = d3.scaleOrdinal(d3.schemeCategory10).domain(vars);

        // === GROUP BY UNITS & CREATE Y SCALES ===
        const unitGroups = {};
        vars.forEach(v => {
            const unit = unitsMap[v] || 'default';
            if (!unitGroups[unit]) unitGroups[unit] = [];
            unitGroups[unit].push(v);
        });

        const uniqueUnits = Object.keys(unitGroups);
        const numAxes = Math.min(uniqueUnits.length, 6); // Cap at 6 axes
        
        // === CALCULATE DYNAMIC LEFT/RIGHT MARGINS BASED ON NUMBER OF AXES ===
        const numLeftAxes = Math.ceil(numAxes / 2);
        const numRightAxes = Math.floor(numAxes / 2);
        const axisSpacing = 55;
        const baseMargin = 50;
        
        const dynamicLeftMargin = baseMargin + (numLeftAxes > 0 ? (numLeftAxes - 1) * axisSpacing + 50 : 0);
        const dynamicRightMargin = baseMargin + (numRightAxes > 0 ? (numRightAxes - 1) * axisSpacing + 50 : 0);
        
        const margin = { 
            top: Math.max(90, dynamicTopMargin), 
            right: Math.max(50, dynamicRightMargin), 
            bottom: 70, 
            left: Math.max(70, dynamicLeftMargin) 
        };
        
        // Calculate responsive plot dimensions based on container and modal size
        // Layout should be complete due to requestAnimationFrame delay
        const containerWidth = container.offsetWidth || 800;
        const containerHeight = container.offsetHeight || 600;
        const width = Math.max(containerWidth - margin.left - margin.right, 400);
        const height = Math.max(containerHeight - margin.top - margin.bottom, 300);

        // Base X scale
        const baseX = d3.scaleTime()
            .domain(d3.extent(allValidPoints, d => parseTime(d.date)))
            .range([0, width]);

        const yScales = {};  // yScales[v] for each variable

        uniqueUnits.forEach(unit => {
            const varsInGroup = unitGroups[unit];
            const points = varsInGroup.flatMap(v => processedData[v] || []);

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

            varsInGroup.forEach(v => yScales[v] = scale);
        });

        // SVG dimensions will be container size + margins
        const svgWidth = containerWidth;
        const svgHeight = containerHeight;

        // SVG setup with responsive dimensions
        const svg = d3.select(container)
            .append('svg')
            .attr('width', svgWidth)
            .attr('height', svgHeight)
            .style('width', '100%')
            .style('height', '100%')
            .attr('preserveAspectRatio', 'xMidYMid meet');

        const g = svg.append('g')
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

        // Draw title above the chart (with padding from top)
        // Title position: negative margin.top + titlePadding
        const titleY = -(margin.top - titlePadding - titleHeight/2);
        g.append('text')
            .attr('class', 'chart-title')
            .attr('x', (width) / 2)
            .attr('y', titleY)
            .attr('text-anchor', 'middle')
            .style('font-family', 'Arial, Helvetica, sans-serif')
            .style('font-size', '18px')
            .style('font-weight', 'bold')
            .style('fill', '#2c3e50')
            .text(titleText);

        g.append('defs').append('clipPath')
            .attr('id', 'zoom-clip')
            .append('rect')
            .attr('width', width)
            .attr('height', height);

        const plotArea = g.append('g')
            .attr('clip-path', 'url(#zoom-clip)');

        // === DRAW LINES USING PER-VARIABLE Y SCALE ===
        const lineElements = [];
        const hoverPaths = [];

        vars.forEach(v => {
            const yScale = yScales[v];
            const line = d3.line()
                .x(d => baseX(parseTime(d.date)))
                .y(d => yScale(d.value))
                .defined(d => d.value != null);

            // Visible line
            lineElements.push(
                plotArea.append('path')
                    .datum(processedData[v])
                    .attr('fill', 'none')
                    .attr('stroke', color(v))
                    .attr('stroke-width', 2.5)
                    .attr('d', line)
            );

            // Hover fat line
            hoverPaths.push(
                plotArea.append('path')
                    .datum(processedData[v])
                    .attr('fill', 'none')
                    .attr('stroke', 'transparent')
                    .attr('stroke-width', 20)
                    .attr('d', line)
                    .style('cursor', 'crosshair')
            );
        });

        // === DRAW MULTIPLE Y-AXES - evenly distributed left/right ===
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
                // Increase padding to avoid overlap with tick labels
                labelOffset = axisOffset - 80;
            } else {
                yAxis = d3.axisRight(scale).ticks(8);
                axisOffset = width + positionInSide * axisSpacing;
                // Increase padding to avoid overlap with tick labels
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

            const axisGroup = g.append('g')
                .attr('class', `y axis axis-${idx}`)
                .attr('transform', `translate(${axisOffset}, 0)`)
                .call(yAxis);

            // Color axis
            const axisColor = color(varsInGroup[0]);
            axisGroup.selectAll('path, line').style('stroke', axisColor);
            axisGroup.selectAll('text')
                .style('fill', axisColor)
                .style('font-size', '15px');

            // Y-axis label
            g.append('text')
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

        // X Axis
        const xAxisGroup = g.append('g')
            .attr('class', 'x axis')
            .attr('transform', `translate(0,${height})`);

        const updateXAxis = (scale) => {
            const minutesSpan = (scale.domain()[1] - scale.domain()[0]) / (1000 * 60);
            let tickInterval = d3.timeMinute.every(15);
            if (minutesSpan <= 30) tickInterval = d3.timeMinute.every(2);
            else if (minutesSpan <= 120) tickInterval = d3.timeMinute.every(5);

            xAxisGroup.call(d3.axisBottom(scale)
                .ticks(tickInterval)
                .tickFormat(d3.timeFormat('%H:%M'))
            );

            xAxisGroup.selectAll('text')
                .style('text-anchor', 'end')
                .attr('dx', '-0.8em')
                .attr('dy', '0.15em')
                .attr('transform', 'rotate(-45)');
        };

        updateXAxis(baseX);

        // Zoom state
        let currentX = baseX.copy();
        const currentYScales = {};
        vars.forEach(v => currentYScales[v] = yScales[v].copy());

        const updateChart = () => {
            vars.forEach(v => {
                const yScale = currentYScales[v];
                const line = d3.line()
                    .x(d => currentX(parseTime(d.date)))
                    .y(d => yScale(d.value))
                    .defined(d => d.value != null);

                lineElements[vars.indexOf(v)].attr('d', line);
                hoverPaths[vars.indexOf(v)].attr('d', line);
            });
        };

        const zoom = d3.zoom()
            .scaleExtent([1, 50])
            .on('zoom', (event) => {
                const t = event.transform;
                currentX = t.rescaleX(baseX);

                // Rescale each Y independently
                vars.forEach(v => {
                    currentYScales[v] = t.rescaleY(yScales[v]);
                });

                updateXAxis(currentX);
                updateChart();
            });

        svg.call(zoom);

        // Tooltip (exact match to combined-plot.js)
        const hoverTooltip = d3.select('body')
            .append('div')
            .attr('id', 'zoom-hover-tooltip')
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

        hoverPaths.forEach((path, i) => {
            const v = vars[i];
            path.on('mousemove', function (event) {
                const [mx] = d3.pointer(event, g.node());
                const x0 = currentX.invert(mx);
                const points = processedData[v];
                const bisect = d3.bisector(d => parseTime(d.date)).left;
                const i = bisect(points, x0, 1);
                const d0 = points[i - 1];
                const d1 = points[i];
                if (!d0 || !d1) return;

                const d = x0 - parseTime(d0.date) > parseTime(d1.date) - x0 ? d1 : d0;
                if (d.value == null) return;

                const timeStr = formatTime(parseTime(d.date));
                const isWindDir = windDirectionVars.includes(v);
                const valueStr = Number(d.value).toFixed(isWindDir ? 0 : 2);
                const unitLabel = unitsMap[v] ? fixEncoding(unitsMap[v]).split(' (')[0] : '';
                const displayValue = unitLabel ? `${valueStr} ${unitLabel}` : valueStr;

                // Tooltip flag bar variables
                const flag = d.flag && d.flag.trim() !== '' && d.flag.trim() !== ' ' ? d.flag.trim() : 'Z';
                const colors = {
                    'B': '#00FFFF', 'D': '#0000FF', 'E': '#8A2BE2', 'F': '#00FF00',
                    'G': '#FF8C00', 'I': '#FFFF00', 'J': '#FF00FF', 'K': '#FF0000',
                    'L': '#40E0D0', 'M': '#006400', 'S': '#FF69B4', 'Z': '#444444'
                };
                const bg = colors[flag] || '#444444';
                const textColor = flag === 'I' ? '#000' : '#FFF';

                hoverTooltip.html(`
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
            .on('mouseout', () => hoverTooltip.style('opacity', 0));
        });

        // Reset button
        const resetBtn = document.getElementById('resetZoomBtn');
        if (resetBtn) {
            resetBtn.onclick = () => {
                svg.transition().duration(600).call(zoom.transform, d3.zoomIdentity);
            };
        }

        // === LEGEND - Use pre-calculated lines from margin calculation ===
        // Legend starts below title: titleY + titleHeight + gap
        const legendStartY = titleY + titleHeight/2 + titleLegendGap;
        
        const legend = g.append('g')
            .attr('class', 'legend');

        const svgCenterX = width / 2;

        // Add color to legendItems (wasn't available during early calculation)
        legendItems.forEach(item => {
            item.col = color(item.v);
        });

        legendLines.forEach((line, lineNum) => {
            // Calculate actual line width
            let lineWidth = 0;
            line.forEach((item, idx) => {
                lineWidth += item.itemWidth;
                if (idx < line.length - 1) {
                    lineWidth += itemSpacing;
                }
            });
            
            // Center each line
            const lineStartX = svgCenterX - (lineWidth / 2);
            let xPos = lineStartX;
            const yPos = legendStartY + lineNum * legendLineHeight;

            line.forEach((item) => {
                const legendItem = legend.append('g')
                    .attr('transform', `translate(${xPos}, ${yPos})`);

                // Color box
                legendItem.append('rect')
                    .attr('x', 0)
                    .attr('y', -6)
                    .attr('width', 12)
                    .attr('height', 12)
                    .style('fill', item.col);

                // Text
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
        } // Close renderChart function
    };

    global.closeZoomModal = function () {
        const modal = document.getElementById('zoomModal');
        const tooltip = document.getElementById('zoom-hover-tooltip');
        if (tooltip) tooltip.remove();
        if (modal) modal.style.display = 'none';
        
        // Re-enable body scrolling when modal is closed
        document.body.style.overflow = 'auto';
    };

    global.downloadZoomCSV = function () {
        const payload = window.__originalChartData;
        if (!payload) return;

        const { plotData, units: unitsMap = {}, longNames = {} } = payload;
        const vars = Object.keys(plotData);
        
        if (vars.length === 0) return;

        // Collect all unique timestamps
        const allTimestamps = new Set();
        vars.forEach(v => {
            const points = plotData[v]?.points || [];
            points.forEach(p => allTimestamps.add(p.date));
        });

        // Sort timestamps
        const timestamps = Array.from(allTimestamps).sort();

        // Build CSV header
        let csv = 'Timestamp';
        vars.forEach(v => {
            const longName = longNames[v] || v;
            const unit = unitsMap[v] || '';
            csv += ',' + longName;
            if (unit) {
                csv += ' (' + unit.split(' (')[0] + ')';
            }
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

        // Create blob and download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        
        // Build filename with ship, date, time
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

    global.downloadZoomPlot = function () {
        const container = document.getElementById('zoomChartContainer');
        const svg = container.querySelector('svg');
        if (!svg) return;

        // Clone SVG to avoid modifying the original
        const svgClone = svg.cloneNode(true);

        // Get SVG dimensions
        const width = svgClone.getAttribute('width') || 1300;
        const height = svgClone.getAttribute('height') || 800;

        // Inject font styles to ensure consistent rendering on export
        const styleElement = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        styleElement.textContent = `
            text { font-family: Arial, Helvetica, sans-serif; }
            .chart-title { font-family: Arial, Helvetica, sans-serif; font-size: 18px; font-weight: bold; }
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

        // Serialize SVG to string
        const serializer = new XMLSerializer();
        let svgString = serializer.serializeToString(svgClone);

        // Create a blob from SVG string
        const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);

        // Create an image and canvas to convert SVG to PNG
        const img = new Image();
        img.onload = function () {
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            
            // Fill white background
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, width, height);
            
            // Draw the image
            ctx.drawImage(img, 0, 0);
            
            // Convert to PNG and download
            canvas.toBlob(function (blob) {
                // Build filename with ship, date, time
                const payload = window.__originalChartData || {};
                const shipName = (payload.shipName || payload.ship || 'zoom-plot').replace(/[^a-zA-Z0-9]/g, '_');
                const dateStr = payload.date ? payload.date.substring(0, 8) : new Date().toISOString().slice(0, 10).replace(/-/g, '');
                const hsTime = (payload.hs || '00:00').replace(':', '');
                const heTime = (payload.he || '23:59').replace(':', '');
                const filename = `${shipName}_${dateStr}_${hsTime}-${heTime}_zoom.png`;
                
                const downloadUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = filename;
                link.href = downloadUrl;
                link.click();
                
                // Clean up
                URL.revokeObjectURL(downloadUrl);
                URL.revokeObjectURL(url);
            });
        };
        img.src = url;
    };

})(window);