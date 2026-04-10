Artemis Space Weather Dashboard
A lightweight PHP + JavaScript dashboard that visualizes real‑time solar activity using NASA’s DONKI API.
Built for clarity, stability, and fast data exploration.

🚀 Features
Live NASA DONKI data (Flares, CMEs, Radiation Storms)

7 / 14 / 30‑day range selector

Interactive Chart.js visualizations

Expandable detail tables

Responsive, mobile‑friendly layout

Robust backend with chunked API fetching

🧩 Tech Stack
Frontend:

HTML5, CSS3, Vanilla JS

Chart.js for visualizations

ResizeObserver + debounced resizing for stable charts

Backend:

PHP 8

cURL for NASA API requests

Chunked fetch strategy to avoid timeouts

Defensive JSON parsing & error handling

⚙️ How It Works
User selects a date range

Frontend requests /api.php?range=XX

Backend splits the range into 7‑day chunks

Each chunk is fetched from NASA DONKI

Results are merged and sanitized

Charts + tables update dynamically

📁 Project Structure
Code
/public
  index.php        → UI
  app.js           → Frontend logic
  styles.css       → Styling

/api.php           → Aggregated API endpoint
/includes
  fetch.php        → Chunked NASA fetcher
  format.php       → Description helpers
🛠 Key Engineering Decisions
Chunked API fetching to prevent DONKI timeouts

Stable chart containers to eliminate layout jumps

Defensive coding for missing/partial NASA fields

Internal scrollable tables to avoid pushing charts around

📈 Future Enhancements

Add proper domain

Add loading indicators

Add Geomagnetic Storm (Kp Index) dataset

Add caching layer for faster loads

CSV export for tables

Dark/light mode

Auto‑refresh mode

Linked event chains (flare → CME → SEP)

📜 License
NASA DONKI data is public domain.
Project code is for educational and portfolio use.