# Hotel Room Reservation System (PHP)

This is a single-file PHP application that implements the Hotel Room Reservation System required for the Unstop SDE-3 assessment.

## Features
- Hotel layout:
  - Floors 1–9: rooms 101–110
  - Floor 10: rooms 1001–1007
- Travel times:
  - Horizontal: 1 minute per adjacent room
  - Vertical: 2 minutes per floor
- Booking rules implemented:
  - Max 5 rooms per booking
  - Priority: same floor allocations first (minimize horizontal span)
  - If not possible, system searches multi-floor combinations (k ≤ 5) to minimize travel time between the first and last room in the booking
- UI:
  - Form to input number of rooms (1–5)
  - Buttons: **Book**, **Random** (generate random occupancy), **Reset**
  - Visualization of each floor and room status
- Session-backed state (no DB). Bookings persist in PHP session.

## How to run (local)
1. Install XAMPP / Laragon / a PHP + Apache setup.
2. Copy `index.php` into your `htdocs` (or server root).
3. Start Apache and open `http://localhost/unstop-assessment/`.


## Files
- `index.php` — application
- `README.md` — this file

## Notes / Assumptions
- Vertical travel measured only as floors difference times 2 (stairs/lift location is ignored as a separate cost).
- Selection scoring uses the travel time between the first and last room after sorting selected rooms by floor then column.
