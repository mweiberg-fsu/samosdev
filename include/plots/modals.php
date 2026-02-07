<?php
/**
 * Modal Templates
 * Renders zoom & pan and ship track modals with their functions
 */

function RenderZoomModal()
{
  echo "
<style>
  #zoomModal {
    --modal-width: 90vw;
    --modal-height: 90vh;
    --modal-max-width: calc(100vw - 32px);
    --modal-max-height: calc(100vh - 32px);
  }
  
  /* iPad and tablets in portrait */
  @media (max-width: 768px) {
    #zoomModal {
      --modal-width: 95vw;
      --modal-height: 95vh;
      --modal-max-width: calc(100vw - 16px);
      --modal-max-height: calc(100vh - 16px);
    }
  }
  
  /* Large screens */
  @media (min-width: 1920px) {
    #zoomModal {
      --modal-width: 85vw;
      --modal-height: 85vh;
    }
  }
</style>

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
        width:var(--modal-width);
        height:var(--modal-height);
        max-width:var(--modal-max-width);
        max-height:var(--modal-max-height);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    \">
        <div style=\"
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:12px 15px;
            background:#f8f9fa;
            border-bottom:2px solid #dee2e6;
            border-radius:12px 12px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
            gap:10px;
            flex-wrap:wrap;
        \">
            <h2 style=\"margin:0; font-size:18px; font-weight:bold; color:#2c3e50; flex:1; min-width:150px;\">Zoom & Pan</h2>
            <div style=\"display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; align-items:center;\">
                <button onclick=\"downloadZoomCSV()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:4px; font-weight:bold; white-space:nowrap; flex-shrink:0;\">CSV</button>
                <button onclick=\"downloadZoomPlot()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#27ae60; color:white; border:none; border-radius:4px; font-weight:bold; white-space:nowrap; flex-shrink:0;\">PNG</button>
                <button id=\"resetZoomBtn\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#3498db; color:white; border:none; border-radius:4px; font-weight:bold; white-space:nowrap; flex-shrink:0;\">Reset</button>
                <button onclick=\"closeZoomModal()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:4px; font-weight:bold; white-space:nowrap; flex-shrink:0;\">Close</button>
            </div>
        </div>
        <div style=\"
            flex:1;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            box-sizing:border-box;
            min-height:0;
            padding:0;
        \">
            <div id=\"zoomChartContainer\" style=\"
                flex:1;
                width:100%;
                height:100%;
                border:1px solid #ccc;
                box-sizing:border-box;
                overflow:hidden;
            \"></div>
        </div>
        <div style=\"
            text-align:center;
            color:#666;
            font-size:12px;
            padding:10px 15px;
            flex-shrink:0;
            background:#f8f9fa;
            border-top:1px solid #dee2e6;
            box-sizing:border-box;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        \">
            Zoom: mouse wheel | Pan: click+drag | Reset: button
        </div>
    </div>
</div>";
}

function RenderShipTrackModal()
{
  echo <<<HTML
<style>
  #shipTrackModal {
    --modal-width: 95vw;
    --modal-height: 95vh;
    --modal-max-width: calc(100vw - 32px);
    --modal-max-height: calc(100vh - 32px);
  }
  
  /* iPad and tablets in portrait */
  @media (max-width: 768px) {
    #shipTrackModal {
      --modal-width: 98vw;
      --modal-height: 98vh;
      --modal-max-width: calc(100vw - 8px);
      --modal-max-height: calc(100vh - 8px);
    }
  }
  
  /* Large screens */
  @media (min-width: 1920px) {
    #shipTrackModal {
      --modal-width: 90vw;
      --modal-height: 90vh;
    }
  }
</style>

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
        width:var(--modal-width);
        height:var(--modal-height);
        max-width:var(--modal-max-width);
        max-height:var(--modal-max-height);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    ">
        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:12px 15px;
            background:#f8f9fa;
            border-bottom:2px solid #dee2e6;
            border-radius:12px 12px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
            gap:10px;
        ">
            <h2 style="
                margin:0;
                font-size:18px;
                font-weight:bold;
                color:#2c3e50;
                flex:1;
                min-width:150px;
                word-break:break-word;
            ">
                Ship Track
            </h2>
            <button onclick="closeShipTrackModal()" style="
                background:#e74c3c;
                color:white;
                border:none;
                padding:8px 12px;
                border-radius:4px;
                font-size:12px;
                cursor:pointer;
                font-weight:bold;
                flex-shrink:0;
                white-space:nowrap;
            ">Close</button>
        </div>
        
        <div id="shipTrackContainer" style="
            flex:1;
            width:100%;
            height:100%;
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
