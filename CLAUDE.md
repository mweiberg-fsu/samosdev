# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

SAMOS (Shipboard Automated Meteorological and Oceanographic System) Visual QC web application. Displays time series plots, polar plots, combined variable plots, and geographic maps for quality-controlled ship meteorological data. PHP backend + vanilla JS/D3.js frontend, deployed on FSU COAPS servers.

## Architecture

### Entry Point & Routing
`index.v30.php` — main entry point. Routes to plot modules via `$mode` URL parameter:
- `0` — QC pie chart (passed/failed)
- `1` — A-Y flag distribution
- `3` — flag distribution
- `4` — Z flags
- `5` — Leaflet map (`InsertMap`)
- `7` — combined plot (`InsertCombinedPlot`)
- `8` — multifunction plot (`InsertMultifunctionPlot`)
- `9/11` — new-style timeseries (`InsertPlotNew`)
- `10` — plot all groups (`InsertPlotAllGroups`)
- `12` — polar plot (`InsertPolarPlot`)

### Key URL Parameters
`ship`, `id` (ship_id), `date` (YYYYMMDD), `order`, `history_id`, `mode`, `v` (variable_id), `vars[]`, `hs`/`he` (hour start/end)

### PHP Modules (`include/plots/`)
Each file exports one primary function:
- `timeseries_plot.php` → `InsertPlot()`
- `combined_plot.php` → `InsertCombinedPlot()`
- `multifunction_plot.php` → `InsertMultifunctionPlot()`
- `polar_plot.php` → `InsertPolarPlot()`
- `plot_new.php` → `InsertPlotNew()`
- `plot_all_groups.php` → `InsertPlotAllGroups()`
- `map.php` → `InsertMap()`
- `helpers.php` — `make_cell_color_decision()`, `flags_array()`, `GetVariableTitle()`

### Data Flow
1. PHP fetches variable metadata from MySQL (`qc_summary`/`merged_qc_summary` + `known_variable`)
2. PHP calls `nc_to_csv.pl` via `exec()` to extract data from NetCDF files
3. Data returned as JSON to JavaScript
4. D3.js renders interactive SVG charts

### JavaScript (`js/`)
- `combined-plot.js` — multi-variable D3 line chart with tooltip, CSV/PNG export, legend toggle
- `zoom-pan.js` — zoom modal (`openZoomModal`/`closeZoomModal`), shift+drag bounding-box zoom, `downloadZoomCSV`/`downloadZoomPlot`
- `single-plot.js` — single-variable timeseries
- `polar-plot.js` — wind rose / polar plots
- `ship-track.js` — Leaflet ship track map rendering

Shared state between modules via `window.__combinedSelectionState`, `window.__zoomSelectionState`, `window.__chartPayloads`.

### Global Config (`global.inc.v36.php`)
Environment detection via `$_SERVER['SERVER_NAME']`:
- Production: `samos.coaps.fsu.edu` / `samos-web.coaps.fsu.edu` → DB `SAMOS_working`
- Dev (default): everything else → DB `SAMOS_development` on `samosdev-proc.coaps.fsu.edu`

DB credentials defined as constants (`SAMOS_ACD_USER`, `SAMOS_ACD_PASS`, etc.). Uses PEAR DB with `mysqli` on PHP ≥ 7.

### Data Backend
- `plot_chart.v05.php` — JSON data endpoint, invokes `nc_to_csv.pl` via `exec()`
- `plot_series_flags.v06.php` — flag data JSON endpoint
- `nc_to_csv.pl` — Perl script reading NetCDF files, requires `NetCDF` Perl module and `SAMOS_CODES_DIR`

### QC Flag System
Flags A–Y are quality issues; Z = passed. Flag `$` = special value (-8888), `#` = missing (-9999). Flag colors defined in both PHP (`global.inc`) and JS (`flagColors` objects in `combined-plot.js`/`zoom-pan.js`).

## Database Tables
- `qc_summary` / `merged_qc_summary` — per-variable flag counts for daily/merged files
- `known_variable` — variable metadata (name, units, order, bounds)
- `daily_file_history` / `merged_file_history` — file version tracking
- `version_no` — processing version (`process_version_no` ≥ 100 = finalized)
- `ship` — vessel info (call sign, name, separation date)

## Development vs Production
`SAMOS_BGCOLOR` visually distinguishes environments: `#FFFFCC` (yellow) = production, `#CCFFFF` (cyan) = dev. No build step — PHP/JS served directly.
