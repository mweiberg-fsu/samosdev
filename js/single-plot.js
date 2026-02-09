// js/single-plot.js
function renderSingleTimeSeriesPlot(config) {
  const { container, dataUrl, flagsUrl, shipInfo, plotIndex, totalPlots } = config;

  const margin = { top: 100, right: 50, bottom: 70, left: 70 };
  const width = 850 - margin.left - margin.right;
  const height = 300 - margin.top - margin.bottom;

  const parseDate = d3.timeParse("%Y-%m-%d %H:%M:%S");
  const formatTime = d3.timeFormat("%H:%M");

  const x = d3.scaleTime().range([0, width]);
  const y = d3.scaleLinear().range([height, 0]);

  const xAxis = d3.axisBottom(x).ticks(15).tickFormat(formatTime);
  const yAxis = d3.axisLeft(y).ticks(6);

  const line = d3.line()
    .x(d => x(d.date))
    .y(d => y(d.value));

  const colors = ["#00FFFF", "#0000FF", "#8A2BE2", "#00FF00", "#FF8C00", "#FFFF00",
                  "#FF00FF", "#FF0000", "#40E0D0", "#006400", "#FF69B4", "#000000", "#B22222"];
  const flag_names = ["B", "D", "E", "F", "G", "I", "J", "K", "L", "M", "S", "Z", "Other"];

  // Only show legend on first plot
  if (plotIndex === 0) {
    const legendSvg = d3.select("body").append("div")
      .attr("id", "global_legend")
      .style("text-align", "center")
      .style("margin", "20px 0")
      .append("svg")
      .attr("width", 900)
      .attr("height", 60);

    legendSvg.selectAll("rect")
      .data(flag_names)
      .enter().append("rect")
      .attr("x", (d, i) => 50 + i * 65)
      .attr("y", 20)
      .attr("width", 60)
      .attr("height", 20)
      .attr("fill", (d, i) => colors[i]);

    legendSvg.selectAll("text")
      .data(flag_names)
      .enter().append("text")
      .attr("x", (d, i) => 80 + i * 65)
      .attr("y", 35)
      .attr("text-anchor", "middle")
      .style("fill", "white")
      .style("font-weight", "bold")
      .text(d => d);

    legendSvg.append("text")
      .attr("x", 450)
      .attr("y", 10)
      .attr("text-anchor", "middle")
      .style("font-size", "16px")
      .style("font-weight", "bold")
      .text("QC Flag Legend");
  }

  // Load data and flags in parallel
  Promise.all([
    d3.json(dataUrl),
    d3.json(flagsUrl)
  ]).then(([data, flags]) => {
    const entries = Object.entries(data);
    const flagArray = flags || [];

    const formatted = entries.map(([time, value], i) => ({
      date: parseDate(time),
      value: +value,
      flag: (flagArray[i] || " ").trim()
    })).filter(d => d.date && d.value !== null && !isNaN(d.value));

    if (formatted.length === 0) {
      d3.select(`#${container}`).html("<p>No valid data to display.</p>");
      return;
    }

    x.domain(d3.extent(formatted, d => d.date));
    const values = formatted.map(d => d.value);
    const padding = (Math.max(...values) - Math.min(...values)) * 0.1 || 1;
    y.domain([
      Math.min(...values) - padding,
      Math.max(...values) + padding
    ]);

    const svg = d3.select(`#${container}`)
      .append("svg")
      .attr("width", width + margin.left + margin.right)
      .attr("height", height + margin.top + margin.bottom)
      .append("g")
      .attr("transform", `translate(${margin.left},${margin.top})`);

    // Axes
    svg.append("g")
      .attr("class", "x axis")
      .attr("transform", `translate(0,${height})`)
      .call(xAxis);

    svg.append("g")
      .attr("class", "y axis")
      .call(yAxis);

    // Line
    svg.append("path")
      .datum(formatted)
      .attr("fill", "none")
      .attr("stroke", "steelblue")
      .attr("stroke-width", 1.5)
      .attr("d", line);

    // Tooltip
    const tooltip = d3.select("body").append("div")
      .attr("class", "d3-tooltip")
      .style("opacity", 0)
      .style("position", "absolute")
      .style("background", "rgba(0,0,0,0.8)")
      .style("color", "white")
      .style("padding", "8px")
      .style("border-radius", "4px")
      .style("pointer-events", "none");

    // Points
    svg.selectAll("circle")
      .data(formatted)
      .enter().append("circle")
      .attr("cx", d => x(d.date))
      .attr("cy", d => y(d.value))
      .attr("r", d => d.flag !== " " ? 4 : 0)
      .attr("fill", d => {
        const idx = flag_names.indexOf(d.flag);
        return idx >= 0 ? colors[idx] : colors[12];
      })
      .on("mouseover", function(event, d) {
        tooltip.transition().duration(200).style("opacity", 1);
        tooltip.html(`Time: ${d3.timeFormat("%Y-%m-%d %H:%M")(d.date)}<br>Value: ${d.value}<br>Flag: ${d.flag || "None"}`)
          .style("left", (event.pageX + 10) + "px")
          .style("top", (event.pageY - 28) + "px");
      })
      .on("mouseout", () => tooltip.transition().duration(500).style("opacity", 0));

    // Labels
    const unitsMatch = shipInfo.match(/units:([^&]+)/);
    const units = unitsMatch ? unitsMatch[1].trim() : "";
    const varName = shipInfo.match(/var: ([^ ]+)/)[1];

    svg.append("text")
      .attr("transform", "rotate(-90)")
      .attr("y", 0 - margin.left + 15)
      .attr("x", 0 - (height / 2))
      .attr("dy", "1em")
      .style("text-anchor", "middle")
      .text(units);

    svg.append("text")
      .attr("x", width / 2)
      .attr("y", -20)
      .attr("text-anchor", "middle")
      .style("font-size", "18px")
      .style("font-weight", "bold")
      .text(varName);

    svg.append("text")
      .attr("x", width / 2)
      .attr("y", height + margin.bottom - 10)
      .attr("text-anchor", "middle")
      .style("font-size", "12px")
      .text(shipInfo.replace(/var: [^ ]+/, "").replace(/units:[^&]+/, "").trim());
  }).catch(err => {
    console.error("Error loading plot data:", err);
    d3.select(`#${container}`).html("<p style='color:red;'>Failed to load data.</p>");
  });
}