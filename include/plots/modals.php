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
    top:0; left:0; right:0; bottom:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:0;
    margin:0;
    overflow:hidden;
\">
    <div style=\"
        position:relative;
        background:#fff;
        padding:0;
        border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,0.4);
        width:90vw;
        height:90vh;
        max-width:calc(100vw - 32px);
        max-height:calc(100vh - 32px);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    \">
        <div style=\"
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:15px 20px;
            background:#f8f9fa;
            border-bottom:2px solid #dee2e6;
            border-radius:12px 12px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
        \">
            <h2 style=\"margin:0; font-size:24px; font-weight:bold; color:#2c3e50;\">Zoom & Pan View</h2>
            <div style=\"display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;\">
                <button onclick=\"downloadZoomCSV()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:5px; font-weight:bold; white-space:nowrap;\">Download CSV</button>
                <button onclick=\"downloadZoomPlot()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:5px; font-weight:bold; white-space:nowrap;\">Download PNG</button>
                <button id=\"resetZoomBtn\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#3498db; color:white; border:none; border-radius:5px; font-weight:bold; white-space:nowrap;\">Reset Zoom</button>
                <button onclick=\"closeZoomModal()\" style=\"padding:10px 20px; font-size:14px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:5px; font-weight:bold; white-space:nowrap;\">Close</button>
            </div>
        </div>
        <div style=\"flex:1; overflow:hidden; display:flex; flex-direction:column; box-sizing:border-box; min-height:0;\">
            <div id=\"zoomChartContainer\" style=\"flex:1; margin:0 auto; border:1px solid #ccc; width:100%; min-height:0; box-sizing:border-box;\"></div>
        </div>
        <div style=\"text-align:center; color:#666; font-size:14px; padding:15px 20px; flex-shrink:0; background:#f8f9fa; border-top:1px solid #dee2e6; box-sizing:border-box; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;\">
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
    top:0; left:0; right:0; bottom:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.9);
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:0;
    margin:0;
    overflow:hidden;
">
    <div style="
        position:relative;
        background:#fff;
        padding:0;
        border-radius:12px;
        box-shadow:0 8px 32px rgba(0,0,0,0.4);
        width:95vw;
        height:95vh;
        max-width:calc(100vw - 32px);
        max-height:calc(100vh - 32px);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    ">
        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:15px 20px;
            background:#f8f9fa;
            border-bottom:2px solid #dee2e6;
            border-radius:12px 12px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
        ">
            <h2 style="margin:0; font-size:24px; font-weight:bold; color:#2c3e50;">
                Ship Track with Interactive Map
            </h2>
            <button onclick="closeShipTrackModal()" style="
                background:#e74c3c;
                color:white;
                border:none;
                width:40px; height:40px;
                border-radius:5px;
                font-size:18px;
                cursor:pointer;
                font-weight:bold;
                flex-shrink:0;
            ">×</button>
        </div>
        
        <div id="shipTrackContainer" style="
            flex:1;
            width:100%;
            min-height:0;
            border:1px solid #ccc;
            box-sizing:border-box;
            overflow:hidden;
        "></div>
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
