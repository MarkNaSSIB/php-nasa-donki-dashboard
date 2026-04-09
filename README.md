# php-nasa-donki-dashboard
Artemis Space Weather Dashboard
A lightweight, full‑stack PHP application that visualizes real‑time solar activity using NASA’s DONKI API.
The dashboard provides a clean, responsive interface for monitoring solar flares, coronal mass ejections (CMEs), and radiation storm activity over selectable time ranges.

Overview
The Artemis Space Weather Dashboard is designed as a practical demonstration of backend API handling, defensive coding, and frontend data visualization.
It fetches live space‑weather data from NASA’s DONKI service, processes it on the server, and presents it through interactive charts and expandable detail tables.

The project emphasizes stability, clarity, and resilience — ensuring the dashboard remains responsive even when NASA’s API returns large payloads or inconsistent data.

Features
✔ Live NASA DONKI Data
Solar Flares

Coronal Mass Ejections (CMEs)

Radiation Storms (SEP‑based events)

✔ Dynamic Date Range Selection
Choose 7, 14, or 30‑day windows for all datasets.

✔ Interactive Visualizations
Chart.js bar charts

Responsive, stable rendering

Debounced resizing and layout‑safe chart containers

✔ Expandable Detail Tables
Clean, scrollable tables for each event type

Smooth expand/collapse transitions

No layout shifting or chart distortion

✔ Robust Backend Fetching
Chunked API requests to avoid DONKI timeouts

Defensive JSON parsing

Error masking and safe fallbacks

Unified data aggregation

Architecture
Frontend
HTML5 / CSS3

Vanilla JavaScript

Chart.js for visualizations

Responsive layout with stable chart wrappers

Debounced resize logic + ResizeObserver

Backend
PHP 8

cURL for HTTP requests

Defensive parsing and sanitization

Structured error handling

Data Source
NASA DONKI API

/FLR – Solar Flares

/CME – Coronal Mass Ejections

/SEP – Radiation Storms (used for “rads” dataset)

Key Engineering Techniques
Chunked API Fetching
NASA’s API can return large datasets or slow responses.
To ensure reliability, the backend:

Splits the requested range into smaller chunks

Fetches each chunk individually

Merges results safely

Logs failures without breaking the UI

Defensive Coding
Null‑safe accessors

Sanitized JSON parsing

Hard limits on array sizes

Graceful fallback values

Masked API errors in UI

Stable Chart Rendering
Chart.js can miscalculate canvas height during layout changes.
This project solves that with:

A dedicated .chart-wrapper container

maintainAspectRatio: false

Debounced chart.resize()

ResizeObserver for layout stability

Responsive UI/UX
Mobile‑friendly layout

Scrollable table sections

Smooth transitions

Clear visual hierarchy

Project Structure
Code
/public
  index.php        → Main UI
  app.js           → Frontend logic & charts
  styles.css       → UI styling
  favicon.png

/api.php           → Backend API aggregator
/includes
  format.php       → Description helpers
  fetch.php        → Chunked NASA API fetcher

How It Works
User selects a date range

Frontend requests /api.php?range=XX

Backend splits the range into 7‑day chunks

Each chunk is fetched from NASA DONKI

Results are merged, sanitized, and returned as JSON

Frontend renders summary cards, charts, and tables

Current Limitations

Only three event types implemented (Flares, CMEs, Radiation Storms)

No persistent caching layer

No historical deep‑range mode

No geomagnetic storm data (planned for future expansion)

Future Enhancements

Update web address

Add Loading Indicators

Add Geomagnetic Storm (Kp Index) dataset

Add SEP/RBE detail views

Add server‑side caching (Redis or file cache)

Add dark/light mode

Add CSV export for tables

Add auto‑refresh mode

Add linked‑event visualization (flare → CME → SEP chain)

Purpose
This project serves as:

A portfolio‑ready demonstration of backend + frontend integration

A practical example of defensive API consumption

A clean, stable UI for real‑time scientific data

A foundation for future expansion into a full space‑weather monitoring tool

License
This project is for educational and portfolio purposes.
NASA DONKI data is public domain.
