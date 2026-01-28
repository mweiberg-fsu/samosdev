<?php
/**
 * Modal Templates
 * Renders zoom & pan and ship track modals with their functions
 */

function RenderZoomModal()
{
  echo "
<div id=\"zoomModal\" style=\"
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    z-index:9999;
    justify-content:center;
    align-items:center;
    overflow:auto;
\">
    <div style=\"
        position:relative;
        background:#fff;
        padding:0;
        border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,0.4);
        max-width:95%;
        max-height:95%;
        overflow:auto;
    \">
        <div style=\"
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:15px 20px;
            background:#f8f9fa;
            border-bottom:2px solid #dee2e6;
            border-radius:12px 12px 0 0;
        \">
            <h2 style=\"margin:0; font-size:24px; font-weight:bold; color:#2c3e50;\">Zoom & Pan View</h2>
            <div style=\"display:flex; gap:10px;\">
                <button onclick=\"downloadZoomCSV()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:5px; font-weight:bold;\">Download CSV</button>
                <button onclick=\"downloadZoomPlot()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:5px; font-weight:bold;\">Download PNG</button>
                <button id=\"resetZoomBtn\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#3498db; color:white; border:none; border-radius:5px; font-weight:bold;\">Reset Zoom</button>
                <button onclick=\"closeZoomModal()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:5px; font-weight:bold;\">Close</button>
            </div>
        </div>
        <div style=\"padding:20px;\">
            <div id=\"zoomChartContainer\" style=\"width:1300px; height:800px; margin:0 auto; border:1px solid #ccc;\"></div>
        </div>
        <div style=\"text-align:center; color:#666; font-size:14px; padding-bottom:15px;\">
            Use mouse wheel to zoom | Click + drag to pan | Click \"Reset Zoom\" to return to full view
        </div>
    </div>
</div>";
}

function RenderShipTrackModal()
{
  echo <<<HTML
<div id="shipTrackModal" style="
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    z-index:9999;
    justify-content:center;
    align-items:center;
    overflow:auto;
">
    <div style="
        position:relative;
        background:#fff;
        padding:20px;
        border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,0.4);
        width:95%;
        height:95%;
        overflow:hidden;
    ">
        <button onclick="closeShipTrackModal()" style="
            position:absolute;
            top:10px; right:15px;
            background:#e74c3c;
            color:white;
            border:none;
            width:32px; height:32px;
            border-radius:50%;
            font-size:18px;
            cursor:pointer;
            font-weight:bold;
            z-index:10000;
        ">X</button>
        
        <h2 style="text-align:center; margin:0 0 15px; color:#2c3e50;">
            Ship Track with Interactive Map
        </h2>
        
        <div id="shipTrackContainer" style="width:100%; height:calc(100% - 60px); border:1px solid #ccc;"></div>
    </div>
</div>
HTML;
}

function RenderModalFunctions()
{
  global $ship, $date, $order, $file_history_id, $SERVER;
  
  echo '<script>
  function openShipTrackModal() {
      document.getElementById("shipTrackModal").style.display = "flex";
      if (typeof renderShipTrack === "function") {
          renderShipTrack({
              ship: "' . addslashes($ship) . '",
              date: "' . addslashes($date) . '",
              order: "' . addslashes($order) . '",
              history_id: "' . $file_history_id . '",
              server: "' . $SERVER . '"
          });
      }
  }

  function closeShipTrackModal() {
      document.getElementById("shipTrackModal").style.display = "none";
  }

  // Close modals when clicking outside
  window.addEventListener("click", function(e) {
      const modals = ["zoomModal", "shipTrackModal"];
      modals.forEach(id => {
          const modal = document.getElementById(id);
          if (modal && e.target === modal) {
              modal.style.display = "none";
          }
      });
  });
  </script>';
}
