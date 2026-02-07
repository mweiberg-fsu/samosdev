<?php
/**
 * Modal Templates
 * Renders zoom & pan and ship track modals with their functions
 */

function RenderZoomModal()
{
  echo "
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap');

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
  background:radial-gradient(circle at 15% 20%, rgba(41,128,185,0.35), rgba(44,62,80,0.9));
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:0;
    margin:0;
    overflow:hidden;
\">
    <div style=\"
        position:relative;
    background:#ffffff;
        padding:0;
    border-radius:16px;
    box-shadow:0 20px 50px rgba(0,0,0,0.4);
        width:var(--modal-width);
        height:var(--modal-height);
        max-width:var(--modal-max-width);
        max-height:var(--modal-max-height);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    border:1px solid rgba(255,255,255,0.4);
    \">
        <div style=\"
            display:flex;
            justify-content:space-between;
            align-items:center;
      padding:14px 18px;
      background:linear-gradient(135deg, #0f4c75, #1b6ca8, #3282b8);
      color:white;
      border-bottom:2px solid rgba(255,255,255,0.2);
      border-radius:16px 16px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
            gap:10px;
            flex-wrap:wrap;
      font-family:'Space Grotesk', 'Segoe UI', sans-serif;
        \">
      <div style=\"display:flex; flex-direction:column; gap:4px;\">
        <h2 style=\"margin:0; font-size:20px; font-weight:700; letter-spacing:0.2px;\">Zoom & Pan</h2>
        <span style=\"font-size:12px; opacity:0.8;\">Explore fine detail with pan + zoom</span>
      </div>
            <div style=\"display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; align-items:center;\">
        <button onclick=\"downloadZoomCSV()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#2ecc71; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;\">CSV</button>
        <button onclick=\"downloadZoomPlot()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#2ecc71; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;\">PNG</button>
        <button id=\"resetZoomBtn\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#3498db; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;\">Reset</button>
        <button onclick=\"closeZoomModal()\" style=\"padding:8px 12px; font-size:12px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;\">Close</button>
            </div>
        </div>
        <div style=\"
            flex:1;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            box-sizing:border-box;
            min-height:0;
            min-width:0;
      padding:10px;
      background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(243,248,252,0.95));
        \">
            <div id=\"zoomChartContainer\" style=\"
                flex:1;
                width:100%;
                height:100%;
                box-sizing:border-box;
                overflow:hidden;
        background:#ffffff;
        border-radius:12px;
        border:1px solid #d9e3ef;
            \"></div>
        </div>
        <div style=\"
            text-align:center;
      color:#5a6b7b;
            font-size:12px;
            padding:10px 15px;
            flex-shrink:0;
      background:#f4f7fb;
      border-top:1px solid #d9e3ef;
            box-sizing:border-box;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
      font-family:'Space Grotesk', 'Segoe UI', sans-serif;
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
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap');

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
  background:radial-gradient(circle at 15% 20%, rgba(41,128,185,0.35), rgba(44,62,80,0.9));
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:0;
    margin:0;
    overflow:hidden;
">
    <div style="
        position:relative;
    background:#ffffff;
        padding:0;
    border-radius:16px;
    box-shadow:0 20px 50px rgba(0,0,0,0.4);
        width:var(--modal-width);
        height:var(--modal-height);
        max-width:var(--modal-max-width);
        max-height:var(--modal-max-height);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
    border:1px solid rgba(255,255,255,0.4);
    ">
        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
      padding:14px 18px;
      background:linear-gradient(135deg, #0f4c75, #1b6ca8, #3282b8);
      color:white;
      border-bottom:2px solid rgba(255,255,255,0.2);
      border-radius:16px 16px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
            gap:10px;
      flex-wrap:wrap;
      font-family:'Space Grotesk', 'Segoe UI', sans-serif;
        ">
      <div style="display:flex; flex-direction:column; gap:4px;">
        <h2 style="margin:0; font-size:20px; font-weight:700; letter-spacing:0.2px;">Ship Track</h2>
        <span style="font-size:12px; opacity:0.8;">Satellite view of the selected range</span>
      </div>
            <button onclick="closeShipTrackModal()" style="
                background:#e74c3c;
                color:white;
                border:none;
                padding:8px 12px;
        border-radius:6px;
                font-size:12px;
                cursor:pointer;
        font-weight:700;
                flex-shrink:0;
                white-space:nowrap;
            ">Close</button>
        </div>
        
        <div id="shipTrackContainer" style="
            flex:1;
            width:100%;
            height:100%;
            min-height:0;
      border:1px solid #d9e3ef;
            box-sizing:border-box;
            overflow:hidden;
      background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(243,248,252,0.95));
      padding:10px;
        "></div>
    </div>
</div>
HTML;
}

function RenderPolarModal()
{
  echo <<<HTML
<style>
  @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap');

  #polarModal {
    --modal-width: 92vw;
    --modal-height: 92vh;
    --modal-max-width: calc(100vw - 32px);
    --modal-max-height: calc(100vh - 32px);
  }

  @media (max-width: 768px) {
    #polarModal {
      --modal-width: 96vw;
      --modal-height: 96vh;
      --modal-max-width: calc(100vw - 16px);
      --modal-max-height: calc(100vh - 16px);
    }
  }

  @media (min-width: 1920px) {
    #polarModal {
      --modal-width: 86vw;
      --modal-height: 86vh;
    }
  }
</style>

<div id="polarModal" style="
    display:none;
    position:fixed;
    top:0; left:0; right:0; bottom:0;
    width:100%; height:100%;
    background:radial-gradient(circle at 15% 20%, rgba(41,128,185,0.35), rgba(44,62,80,0.9));
    z-index:9999;
    justify-content:center;
    align-items:center;
    padding:0;
    margin:0;
    overflow:hidden;
">
    <div style="
        position:relative;
        background:#ffffff;
        padding:0;
        border-radius:16px;
        box-shadow:0 20px 50px rgba(0,0,0,0.4);
        width:var(--modal-width);
        height:var(--modal-height);
        max-width:var(--modal-max-width);
        max-height:var(--modal-max-height);
        overflow:hidden;
        display:flex;
        flex-direction:column;
        box-sizing:border-box;
        border:1px solid rgba(255,255,255,0.4);
    ">
        <div style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:14px 18px;
            background:linear-gradient(135deg, #0f4c75, #1b6ca8, #3282b8);
            color:white;
            border-bottom:2px solid rgba(255,255,255,0.2);
            border-radius:16px 16px 0 0;
            flex-shrink:0;
            box-sizing:border-box;
            gap:10px;
            flex-wrap:wrap;
            font-family:'Space Grotesk', 'Segoe UI', sans-serif;
        ">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <h2 style="margin:0; font-size:20px; font-weight:700; letter-spacing:0.2px;">Polar Plot</h2>
                <span style="font-size:12px; opacity:0.8;">Angle = degrees, radius = time progression</span>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; align-items:center;">
              <button onclick="downloadPolarPlot()" style="padding:8px 12px; font-size:12px; cursor:pointer; background:#2ecc71; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;">PNG</button>
              <button id="resetPolarBtn" style="padding:8px 12px; font-size:12px; cursor:pointer; background:#3498db; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;">Reset</button>
              <button onclick="closePolarModal()" style="padding:8px 12px; font-size:12px; cursor:pointer; background:#e74c3c; color:white; border:none; border-radius:6px; font-weight:700; white-space:nowrap; flex-shrink:0;">Close</button>
            </div>
        </div>
        <div style="
            flex:1;
            overflow:hidden;
            display:flex;
            flex-direction:column;
            box-sizing:border-box;
            min-height:0;
            min-width:0;
            padding:10px;
            background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(243,248,252,0.95));
        ">
            <div id="polarChartContainer" style="
                flex:1;
                width:100%;
                height:100%;
                box-sizing:border-box;
                overflow:hidden;
                background:#ffffff;
                border-radius:12px;
                border:1px solid #d9e3ef;
            "></div>
        </div>
        <div style="
            text-align:center;
            color:#5a6b7b;
            font-size:12px;
            padding:10px 15px;
            flex-shrink:0;
            background:#f4f7fb;
            border-top:1px solid #d9e3ef;
            box-sizing:border-box;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            font-family:'Space Grotesk', 'Segoe UI', sans-serif;
        ">
            Zoom: mouse wheel | Pan: click+drag | Reset: button
        </div>
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
        const modals = ["zoomModal", "shipTrackModal", "polarModal"];
        modals.forEach(id => {
          const modal = document.getElementById(id);
          if (modal && e.target === modal) {
            modal.style.display = "none";
            if (id === "polarModal") {
              document.body.style.overflow = "";
            }
          }
        });
  });
  </script>';
}
