# EasyReserve

EasyReserve is a small PHP/MySQL reservation system with a Persian (Jalali/Shamsi) date experience.

It provides:
- **Reservation form** (client-side validation + AJAX) — `form.html`
- **Reservation API** — `reserve.php`
- **Reservations listing & filtering** with **DataTables** — `read.php`
- **Persian date utilities** — `helpers.php`
- **Database schema** — `db.sql`

---

## Features

- Shows the **next Monday** date in **Jalali** format on the form.
- Validates:
  - National ID (10 digits + checksum)
  - Mobile number (`09xxxxxxxxx`)
- Prevents duplicate reservations by **(national_id + visit_day)**.
- Limits total reservations per day (default **50**).
- Sends an SMS after successful reservation (placeholder SMS provider in `reserve.php`).
- Admin/manager view:
  - Filter by Jalali date
  - Queue number ordering
  - Export to **Excel / CSV / Print**

---

## Tech Stack

- PHP
- MySQL / MariaDB
- Bootstrap (RTL)
- jQuery + DataTables

---

## Project Files

- `form.html` — Public reservation form + JS validations + AJAX POST
- `reserve.php` — Accepts POST data, writes to DB, returns JSON
- `read.php` — Displays reservations table with DataTables (Jalali date filter)
- `helpers.php` — Jalali/Gregorian conversion + formatting helpers
- `db.sql` — Creates database + `reservations` table
- `assets/` — JS/CSS dependencies

---

## Database Setup

1) Import schema:

```sql
-- db.sql
CREATE DATABASE IF NOT EXISTS visits ...;
USE visits;
CREATE TABLE reservations (...);
```

2) Create DB user and credentials for:
- host
- user
- password
- dbname (`visits`)

---

## Configuration (Required)

You must edit the placeholder values in both PHP entry points:

### 1) `reserve.php`
Replace:
- `YOUR_DB_HOST`
- `YOUR_DB_USER`
- `YOUR_DB_PASSWORD`
- `YOUR_DB_NAME`

Also configure SMS provider in `sendSMS()`:
- `$api_url = "https://sms-provider.com/api/send";`
- adjust `$data` fields to match your real provider

### 2) `read.php`
Replace the same DB placeholders:
- `YOUR_DB_HOST`
- `YOUR_DB_USER`
- `YOUR_DB_PASSWORD`
- `YOUR_DB_NAME`

---

## How to Run

1) Host the project with PHP enabled (e.g. Apache + PHP or PHP built-in server).
2) Ensure `assets/` is reachable by the browser.
3) Open `form.html` to create reservations.
4) Open `read.php` to view reservations.

### AJAX endpoint
`form.html` posts to:
- `http://nobat.khiec.ir/reserve.php`

If you run locally, update that URL to your own domain, e.g.:
- `http://localhost/EasyReserve/reserve.php`

---

## Usage

### Reservation (public)
- The form automatically shows **the next Monday** date (Jalali) and sends:
  - `full_name`
  - `national_id`
  - `phone`
  - `subject`
  - `visit_day` (Gregorian `YYYY-MM-DD`)

On success, the server returns JSON which is shown in the form result box.

### Listing (admin)
- `read.php` renders a table of reservations for a Jalali date.
- Filter input accepts Jalali dates like:
  - `1403/02/15`

---

## Notes / Limitations

- SMS sending is a **placeholder**. Replace the provider implementation in `sendSMS()`.
- DB credentials are currently hardcoded as placeholders; for production, consider using environment variables or a separate config file.

---

## License

MIT (see `LICENSE`).

