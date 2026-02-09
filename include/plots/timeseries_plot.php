<?php
/**
 * Time Series Plot Module
 * Handles single-variable time series plots with optional zoom and flags (mode=6)
 */

function InsertPlot()
{
  global $file_history_id, $variables, $order, $mode, $ship, $date, $height, $ship_id, $SERVER;

  if ($order > 100)
    $query = "SELECT max, min, variable_name, process_version_no, units FROM merged_qc_summary INNER JOIN known_variable kv ON known_variable_id=variable_id AND merged_file_history_id=$file_history_id INNER JOIN merged_file_history mfh USING(merged_file_history_id) INNER JOIN version_no vn USING (version_id) ORDER BY kv.order_value ASC";
  else
    $query = "SELECT max, min, variable_name, process_version_no, units FROM qc_summary INNER JOIN known_variable kv ON known_variable_id=variable_id AND daily_file_history_id=$file_history_id INNER JOIN daily_file_history dfh USING(daily_file_history_id) INNER JOIN version_no vn USING (version_id) ORDER BY kv.order_value ASC";

  db_query($query);
  if (db_error()) {
    echo "ERROR: $query.<br />\n";
    return;
  }

  $vars_options = "";
  $variables_list = array();

  while ($row = db_get_row()) {
    if ($row->variable_name != "time") {
      $variables_list[] = $row;  // Collect all rows in order
      $vars_options .= "\n        <option value=\"" . $row->variable_name . '" selected>' . $row->variable_name . '</option>';
    }
  }

  // Display the form FIRST, before the plots
  if ($vars_options != "") {
    print <<<FORM
      <form action="index.php?ship=$ship&id=$ship_id&date=$date&order=$order&history_id=$file_history_id&mode=$mode&fbound={$_REQUEST['fbound']}" method="POST">
        <table class="search">
          <tr>
            <th>Choose a variable<br /><span class="small">or multiple variables (ctrl-click or <br />apple key-click)</span></th>
            <td>
              <select name="vars[]" multiple="1">
                {$vars_options}
              </select> 
  	    </td>
	  </tr>
	  <tr>
            <th>Choose a time range<br /><span class="small">for zooming in</span></th>
            <td>
              <select name="hs">
		  <option value="00" SELECTED>0000 UTC</option>
		  <option value="01">0100 UTC</option>
		  <option value="02">0200 UTC</option>
		  <option value="03">0300 UTC</option>
		  <option value="04">0400 UTC</option>
		  <option value="05">0500 UTC</option>
		  <option value="06">0600 UTC</option>
		  <option value="07">0700 UTC</option>
		  <option value="08">0800 UTC</option>
		  <option value="09">0900 UTC</option>
		  <option value="10">1000 UTC</option>
		  <option value="11">1100 UTC</option>
		  <option value="12">1200 UTC</option>
		  <option value="13">1300 UTC</option>
		  <option value="14">1400 UTC</option>
		  <option value="15">1500 UTC</option>
		  <option value="16">1600 UTC</option>
		  <option value="17">1700 UTC</option>
		  <option value="18">1800 UTC</option>
		  <option value="19">1900 UTC</option>
		  <option value="20">2000 UTC</option>
		  <option value="21">2100 UTC</option>
		  <option value="22">2200 UTC</option>
		  <option value="23">2300 UTC</option>
              </select>
	      &nbsp;to&nbsp;
              <select name="he">
		  <option value="00">0059 UTC</option>
		  <option value="01">0159 UTC</option>
		  <option value="02">0259 UTC</option>
		  <option value="03">0359 UTC</option>
		  <option value="04">0459 UTC</option>
		  <option value="05">0559 UTC</option>
		  <option value="06">0659 UTC</option>
		  <option value="07">0759 UTC</option>
		  <option value="08">0859 UTC</option>
		  <option value="09">0959 UTC</option>
		  <option value="10">1059 UTC</option>
		  <option value="11">1159 UTC</option>
		  <option value="12">1259 UTC</option>
		  <option value="13">1359 UTC</option>
		  <option value="14">1459 UTC</option>
		  <option value="15">1559 UTC</option>
		  <option value="16">1659 UTC</option>
		  <option value="17">1759 UTC</option>
		  <option value="18">1859 UTC</option>
		  <option value="19">1959 UTC</option>
		  <option value="20">2059 UTC</option>
		  <option value="21">2159 UTC</option>
		  <option value="22">2259 UTC</option>
		  <option value="23" SELECTED>2359 UTC</option>
              </select>
  	    </td>
	  </tr>
	  <tr>
	    <td></td><td><input type="submit" name="submit" value="[Plot]" /></td>
          </tr>
        </table>
      </form>
<script>
// Sync time range selections with URL parameters on page load for [Plot] view
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const hsParam = urlParams.get('hs');
  const heParam = urlParams.get('he');
  
  // Sync start time dropdown
  if (hsParam) {
    const hsSelect = document.querySelector('select[name="hs"]');
    if (hsSelect) {
      for (let option of hsSelect.options) {
        if (option.value == hsParam) {
          option.selected = true;
          break;
        }
      }
    }
  }
  
  // Sync end time dropdown
  if (heParam) {
    const heSelect = document.querySelector('select[name="he"]');
    if (heSelect) {
      for (let option of heSelect.options) {
        if (option.value == heParam) {
          option.selected = true;
          break;
        }
      }
    }
  }
  
  console.log('Plot view: Time range selections synced with URL parameters');
});
</script>
FORM;
  }

  $num_of_plots = 0;

  // Now iterate through the ordered list and plot only selected variables
  foreach ($variables_list as $row) {
    $version_no = $row->process_version_no;
    if ($version_no < 100)
      $version_no = 100;

    if (is_array($_REQUEST['vars']) && in_array($row->variable_name, $_REQUEST['vars'])) {
      $flags_url = $SERVER . "/charts/plot_series_flags.php?ship=$ship&date=$date&order=$order&var={$row->variable_name}&version_no={$version_no}&units=" . urlencode($row->units) . "&fbound={$_REQUEST['fbound']}&hs={$_REQUEST['hs']}&he={$_REQUEST['he']}";
      $flags = flags_array($flags_url);
      $ship_str = "ship: $ship   date: $date   order: $order   var: $row->variable_name   version_number: $version_no   units: $row->units";

      // Create unique div ID for each plot
      $plot_div_id = "plot_" . $num_of_plots;
      InsertTimeSeriesPlot("plot_chart.php?ship=$ship&date=$date&order=$order&var={$row->variable_name}&version_no={$version_no}&units=" . urlencode($row->units) . "&fbound={$_REQUEST['fbound']}&hs={$_REQUEST['hs']}&he={$_REQUEST['he']}", $ship_str, $flags, $num_of_plots, $plot_div_id);
      echo "\n";
      $num_of_plots++;
    }
  }
}

/**
 * Updated function with unique div ID parameter for rendering time series plots
 * with D3.js visualization and flag indicators
 */
function InsertTimeSeriesPlot($link, $str_ship, $flags_arr, $num_of_plots, $plot_div_id = "my_dataviz")
{
  global $SERVER;
  ?>

  <meta charset="utf-8">

  <!-- Load d3.js -->
  <script src="d3_charts/libs/d3.min.js"></script>

  <!-- Create a unique div for this specific plot -->
  <div id="<?php echo $plot_div_id; ?>" style="margin-bottom: 10px;"></div>

  <script>
    (function () {
      // Wrap in IIFE to avoid variable conflicts between plots

      // Get URL
      var url = "<?php print ($link); ?>";
      console.log('InsertTimeSeriesPlot url:');
      console.log(url);

      var index_plot = url.indexOf("?");
      var flags_url = "<?php echo $SERVER; ?>/charts/plot_series_flags.php" + url.substring(index_plot, url.length);
      console.log(flags_url);

      // Get ship information that goes on the bottom of each plot
      var ship_info = "<?php print ($str_ship); ?>";

      // Get flags that are passed into the function from InsertPlot() and flags_array() functions
      var flags_array = <?php echo json_encode($flags_arr); ?>;
      console.log(flags_array);

      // For legend to show only once
      var num_plots = <?php print ($num_of_plots); ?>;
      console.log(num_plots);

      // Unique div ID for this plot
      var plotDivId = "<?php echo $plot_div_id; ?>";

      // y axis label info
      var index = ship_info.indexOf("units");
      var units = ship_info.substring(index, ship_info.length);

      // title info (variable name)
      var index_var = ship_info.indexOf("var");
      var index_version = ship_info.indexOf("version_number");
      var var_name = ship_info.substring(index_var + 5, index_version);

      // ship info without var name and units
      var short_ship_info = ship_info.substring(0, index_var) + ship_info.substring(index_version, index);

      var key_array = [];
      var value_array = [];

      // set the dimensions and margins of the graph
      var margin = { top: 80, right: 20, bottom: 50, left: 50 },
        width = 800 - margin.left - margin.right,
        height = 270 - margin.top - margin.bottom;

      // Parse the date / time
      var parseDate = d3.timeParse("%Y-%m-%d %H:%M:%S");

      // Set the ranges
      var x = d3.scaleTime().range([0, width]);
      var y = d3.scaleLinear().range([height, 0]);

      // Define the axes
      var xAxis = d3.axisBottom(x)
        .ticks(20)
        .tickFormat(d3.timeFormat("%H:%M"));

      var yAxis = d3.axisLeft(y)
        .ticks(5);

      // Define the line
      var valueline = d3.line()
        .x(function (d) { return x(d.date); })
        .y(function (d) { return y(d.value); });

      var colors = ["#00FFFF", "#0000FF", "#8A2BE2", "#00FF00", "#FF8C00", "#FFFF00",
        "#FF00FF", "#FF0000", "#40E0D0", "#006400", "#FF69B4", "#000000", "#B22222"];
      var flag_names = ["B", "D", "E", "F", "G", "I",
        "J", "K", "L", "M", "S", "Z", "Other"];

      // Function that creates legend at the top of all the plots
      function buildLegend(colors, flag_names, targetDiv) {
        var data = [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1];

        var color_leg = d3.select("#" + targetDiv)
          .append("svg")
          .attr("width", "100%")
          .attr("height", "15%")

        // Make the bars of the bar graph
        color_leg.selectAll("rect")
          .data(data)
          .enter().append("rect")
          .attr("class", "bar")
          .attr("height", function (d, i) { return (d * 30) })
          .attr("width", "130")
          .attr("x", function (d, i) { return (i * 60) + 25 })
          .attr("y", function (d, i) { return 10 - (d * 10) })
          .attr("fill", function (d, i) { return colors[i]; });

        // Flag names(letters) in the bars of the bar graph
        color_leg.selectAll("text")
          .data(data)
          .enter().append("text")
          .text(function (d, i) { return flag_names[i] })
          .attr("x", function (d, i) { return (i * 60) + 36 })
          .attr("y", function (d, i) { return 25 - (d * 10) })
          .attr("fill", "white");

        // Title of the graph
        color_leg.append("text")
          .attr("x", 400)
          .attr("y", 0 + 50)
          .attr("text-anchor", "middle")
          .style("font-size", "16px")
          .attr("font-weight", 700)
          .text("Flag Colors");
      }

      // Make sure legend only shows once (on first plot)
      if (num_plots == 0)
        buildLegend(colors, flag_names, plotDivId);

      // Function that builds the time series plots
      function buildPlot(url_link, ship, y_label, title, flags_arr, colors, flag_names, targetDiv) {
        console.log("flags_arr:");
        console.log(flags_arr);

        //Read the data
        d3.json(url_link, function (error, data) {
          node = d3.entries(data);
          var i = 0;

          node.forEach(function (d) {
            d.date = parseDate(d.key);
            d.flag = flags_arr[i];
            i++;
          });

          // need for ranges
          value_array = d3.values(data);

          console.log(d3.extent(node, function (d) {
            return d.date;
          }));

          // Scale the range of the data
          x.domain(d3.extent(node, function (d) {
            return d.date;
          }));

          // Set range for y axis to be dynamic in relation to the data
          if (Math.min(...value_array) == Math.max(...value_array)) {
            y.domain([Math.max(...value_array) - 1.5, Math.max(...value_array) + 1.5]);
          } else {
            y.domain([Math.min(...value_array), Math.max(...value_array)]);
          }

          // append the svg object to the specific div for this plot
          var svg = d3.select("#" + targetDiv)
            .append("svg")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
            .append("g")
            .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

          // Add the X Axis
          svg.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + height + ")")
            .call(xAxis);

          // Add the Y Axis
          svg.append("g")
            .attr("class", "y axis")
            .call(yAxis);

          // Add a tooltip div that stays at the bottom of the plot
          // First, ensure the parent div has relative positioning and bottom padding
          d3.select("#" + targetDiv)
            .style("position", "relative")
            .style("padding-bottom", "40px");
          
          var tooltip = d3.select("#" + targetDiv)
            .append("div")
            .style("opacity", 0)
            .attr("class", "tooltip")
            .style("background-color", "white")
            .style("border", "solid")
            .style("border-width", "1px")
            .style("border-radius", "5px")
            .style("padding", "10px")
            .style("position", "absolute")
            .style("bottom", "0px")
            .style("left", "0px")
            .style("width", "100%")
            .style("box-sizing", "border-box");

          var mouseover = function (d) {
            tooltip.style("opacity", 1)
          }

          var mousemove = function (d) {
            tooltip
              .html("Time: " + d.date + " &nbsp;&nbsp; Value: " + d.value + " &nbsp;&nbsp; Flag: " + d.flag)
          }

          var mouseleave = function (d) {
            tooltip
              .transition()
              .duration(100)
              .style("opacity", 0)
          }

          // Add the line connecting the dots
          svg.append("path")
            .datum(node)
            .attr("fill", "none")
            .attr("stroke", "black")
            .attr("stroke-width", 1.5)
            .attr("d", d3.line()
              .x(function (d) { return x(d.date) })
              .y(function (d) { return y(d.value) })
            )

          // Add the scatterplot
          var path = svg.selectAll("dot")
            .data(node)
            .enter().append("circle")
            .attr("cx", function (d) {
              if (d.flag != " ")
                return x(d.date);
              else
                return x(0);
            })
            .attr("cy", function (d) {
              if (d.flag != " ")
                return y(d.value);
              else
                return y(0);
            })
            .attr("r", 3)
            .style("fill", function (d) {
              if (d.flag == "B")
                color = colors[0];
              else if (d.flag == "D")
                color = colors[1];
              else if (d.flag == "E")
                color = colors[2];
              else if (d.flag == "F")
                color = colors[3];
              else if (d.flag == "G")
                color = colors[4];
              else if (d.flag == "I")
                color = colors[5];
              else if (d.flag == "J")
                color = colors[6];
              else if (d.flag == "K")
                color = colors[7];
              else if (d.flag == "L")
                color = colors[8];
              else if (d.flag == "M")
                color = colors[9];
              else if (d.flag == "S")
                color = colors[10];
              else if (d.flag == "Z")
                color = colors[11];
              else {
                color = colors[12];
              }
              return color;
            })
            .on("mouseover", mouseover)
            .on("mousemove", mousemove)
            .on("mouseleave", mouseleave);

          // Add ship information below plot
          svg.append("text")
            .attr("x", (width / 2))
            .attr("y", height + margin.bottom - 10)
            .attr("text-anchor", "middle")
            .style("font-size", "13px")
            .text(ship);

          // text label for the y axis (units)
          svg.append("text")
            .attr("transform", "rotate(-90)")
            .attr("y", 0 - margin.left)
            .attr("x", 0 - (height / 2))
            .attr("dy", "1em")
            .style("font-size", "12px")
            .style("text-anchor", "middle")
            .text(y_label);

          // add Title (var name)
          svg.append("text")
            .attr("x", (width / 2))
            .attr("y", 0 - (margin.top / 2))
            .attr("text-anchor", "middle")
            .attr("font-weight", 700)
            .style("font-size", "20px")
            .text(title);
        });
      }

      buildPlot(url, short_ship_info, units, var_name, flags_array, colors, flag_names, plotDivId);
    })();
  </script>

  <?php
}
