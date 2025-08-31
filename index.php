<?php
session_start();

// Configuration
const MAX_ROOMS_PER_BOOKING = 5;

// Initialize hotel occupancy structure
function init_hotel() {
    $occupancy = [];
    for ($f = 1; $f <= 10; $f++) {
        $cols = ($f === 10) ? 7 : 10;
        $floorArr = [];
        for ($c = 1; $c <= $cols; $c++) {
            if ($f === 10) {
                $roomNumber = 1000 + $c;
            } else {
                $roomNumber = $f * 100 + $c;
            }
            $floorArr[$c] = ['room' => $roomNumber, 'booked' => false];
        }
        $occupancy[$f] = $floorArr;
    }
    return $occupancy;
}

function get_state() {
    if (!isset($_SESSION['hotel'])) {
        $_SESSION['hotel'] = init_hotel();
    }
    if (!isset($_SESSION['last_booking'])) {
        $_SESSION['last_booking'] = [];
    }
    return [$_SESSION['hotel'], $_SESSION['last_booking']];
}
function set_state($hotel, $lastBooking) {
    $_SESSION['hotel'] = $hotel;
    $_SESSION['last_booking'] = $lastBooking;
}

function available_columns_on_floor($hotel, $floor) {
    $cols = [];
    foreach ($hotel[$floor] as $c => $cell) {
        if (!$cell['booked']) $cols[] = $c;
    }
    sort($cols);
    return $cols;
}

function room_id($floor, $col) {
    return ($floor === 10) ? (1000 + $col) : ($floor * 100 + $col);
}

function travel_time_between($a, $b) {
    return 2 * abs($a['floor'] - $b['floor']) + abs($a['col'] - $b['col']);
}

function selection_travel_time($rooms) {
    if (count($rooms) <= 1) return 0;
    usort($rooms, function($x, $y){
        if ($x['floor'] === $y['floor']) return $x['col'] <=> $y['col'];
        return $x['floor'] <=> $y['floor'];
    });
    $first = $rooms[0];
    $last  = $rooms[count($rooms)-1];
    return travel_time_between($first, $last);
}

function windows_for_floor($cols, $t) {
    $wins = [];
    if (count($cols) < $t) return $wins;
    for ($i = 0; $i <= count($cols) - $t; $i++) {
        $subset = array_slice($cols, $i, $t);
        $span = $subset[$t-1] - $subset[0];
        $wins[] = ['cols' => $subset, 'span' => $span];
    }
    usort($wins, function($a,$b){
        if ($a['span'] === $b['span']) return $a['cols'][0] <=> $b['cols'][0];
        return $a['span'] <=> $b['span'];
    });
    return $wins;
}

function best_single_floor($hotel, $k) {
    $best = null;
    for ($f = 1; $f <= 10; $f++) {
        $cols = available_columns_on_floor($hotel, $f);
        if (count($cols) >= $k) {
            $wins = windows_for_floor($cols, $k);
            if (empty($wins)) continue;
            $w = $wins[0];
            $rooms = array_map(function($c) use ($f){ return ['floor'=>$f,'col'=>$c,'room'=>room_id($f,$c)];}, $w['cols']);
            $score = selection_travel_time($rooms);
            $cand = ['rooms'=>$rooms, 'score'=>$score, 'floor'=>$f, 'span'=>$w['span'], 'start_col'=>$w['cols'][0]];
            if ($best === null) {
                $best = $cand;
            } else {
                if ($cand['score'] < $best['score'] ||
                   ($cand['score'] === $best['score'] && ($cand['floor'] < $best['floor'] ||
                    ($cand['floor'] === $best['floor'] && $cand['start_col'] < $best['start_col'])))) {
                    $best = $cand;
                }
            }
        }
    }
    return $best ? $best['rooms'] : null;
}

function best_multi_floor($hotel, $k) {
    $perFloor = [];
    for ($f = 1; $f <= 10; $f++) {
        $cols = available_columns_on_floor($hotel, $f);
        $floorWins = [];
        $maxT = min($k, count($cols));
        for ($t = 1; $t <= $maxT; $t++) {
            $wins = windows_for_floor($cols, $t);
            if (!empty($wins)) {
                foreach ($wins as $w) {
                    $rooms = array_map(function($c) use ($f){ return ['floor'=>$f,'col'=>$c,'room'=>room_id($f,$c)]; }, $w['cols']);
                    $floorWins[] = ['t'=>$t, 'rooms'=>$rooms, 'span'=>$w['span']];
                }
            }
        }
        usort($floorWins, function($a,$b){
            if ($a['t'] === $b['t']) return $a['span'] <=> $b['span'];
            return $b['t'] <=> $a['t'];
        });
        $perFloor[$f] = $floorWins;
    }

    $best = ['score'=>PHP_INT_MAX, 'rooms'=>[]];
    $floors = range(1,10);

    $choose = function($idx, $remaining, $currentRooms) use (&$choose, $floors, $perFloor, &$best) {
        if ($remaining === 0) {
            $score = selection_travel_time($currentRooms);
            if ($score < $best['score']) {
                $best = ['score'=>$score, 'rooms'=>$currentRooms];
            } else if ($score === $best['score']) {
                $floorsUsed = array_unique(array_map(fn($r)=>$r['floor'], $currentRooms));
                $bestFloorsUsed = array_unique(array_map(fn($r)=>$r['floor'], $best['rooms']));
                if (count($floorsUsed) < count($bestFloorsUsed)) {
                    $best = ['score'=>$score, 'rooms'=>$currentRooms];
                } elseif (count($floorsUsed) === count($bestFloorsUsed)) {
                    if (min($floorsUsed) < (empty($bestFloorsUsed) ? 99 : min($bestFloorsUsed))) {
                        $best = ['score'=>$score, 'rooms'=>$currentRooms];
                    }
                }
            }
            return;
        }
        if ($idx >= count($floors)) return;

        $capacity = 0;
        for ($i = $idx; $i < count($floors); $i++) {
            $f = $floors[$i];
            $capHere = 0;
            foreach ($perFloor[$f] as $w) $capHere = max($capHere, $w['t']);
            $capacity += $capHere;
        }
        if ($capacity < $remaining) return;

        $f = $floors[$idx];

        // skip
        $choose($idx+1, $remaining, $currentRooms);

        $seenT = [];
        foreach ($perFloor[$f] as $w) {
            $t = $w['t'];
            if ($t > $remaining) continue;
            if (!isset($seenT[$t])) $seenT[$t] = 0;
            if ($seenT[$t] >= 5) continue;
            $seenT[$t]++;
            $merged = array_merge($currentRooms, $w['rooms']);
            $choose($idx+1, $remaining - $t, $merged);
        }
    };

    $choose(0, $k, []);
    return $best['score'] === PHP_INT_MAX ? null : $best['rooms'];
}

function book_rooms(&$hotel, $k) {
    if ($k < 1 || $k > MAX_ROOMS_PER_BOOKING) {
        return ['error' => 'Please request between 1 and '.MAX_ROOMS_PER_BOOKING.' rooms.'];
    }
    $same = best_single_floor($hotel, $k);
    $selection = null;
    if ($same !== null) {
        $selection = $same;
    } else {
        $multi = best_multi_floor($hotel, $k);
        if ($multi !== null) $selection = $multi;
    }
    if ($selection === null) {
        return ['error' => 'Not enough rooms available to fulfill this booking.'];
    }
    foreach ($selection as $r) {
        $hotel[$r['floor']][$r['col']]['booked'] = true;
    }
    return ['rooms' => $selection, 'time' => selection_travel_time($selection)];
}

function reset_all() {
    $_SESSION['hotel'] = init_hotel();
    $_SESSION['last_booking'] = [];
}

function randomize(&$hotel) {
    $allCells = [];
    for ($f=1; $f<=10; $f++) {
        foreach ($hotel[$f] as $c=>$cell) {
            $allCells[] = ['floor'=>$f, 'col'=>$c];
        }
    }
    shuffle($allCells);
    $n = count($allCells);
    $pct = rand(30, 60) / 100.0;
    $toBook = (int)floor($pct * $n);
    foreach ($hotel as $f=>$row) {
        foreach ($row as $c=>$cell) $hotel[$f][$c]['booked'] = false;
    }
    for ($i=0; $i<$toBook; $i++) {
        $pos = $allCells[$i];
        $hotel[$pos['floor']][$pos['col']]['booked'] = true;
    }
}

// Handle form actions
list($hotel, $lastBooking) = get_state();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'reset') {
        reset_all();
        list($hotel, $lastBooking) = get_state();
        $message = "All bookings cleared.";
    } elseif ($action === 'random') {
        randomize($hotel);
        $lastBooking = [];
        set_state($hotel, $lastBooking);
        $message = "Random occupancy generated.";
    } elseif ($action === 'book') {
        $k = intval($_POST['rooms'] ?? 0);
        $result = book_rooms($hotel, $k);
        if (isset($result['error'])) {
            $message = $result['error'];
        } else {
            $lastBooking = $result['rooms'];
            set_state($hotel, $lastBooking);
            $message = "Booked ".count($lastBooking)." rooms. Total travel time between first & last: ".$result['time']." minutes.";
        }
    }
    set_state($hotel, $lastBooking);
}

$lastIds = [];
foreach ($lastBooking as $r) {
    $lastIds[$r['floor'].'-'.$r['col']] = true;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Hotel Room Reservation (PHP)</title>
<style>
    :root { --bg:#0f172a; --card:#111827; --grid:#1f2937; --ok:#10b981; --no:#ef4444; --sel:#3b82f6; --text:#e5e7eb; --muted:#9ca3af; }
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--text);font-family:ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial}
    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    .panel{background:var(--card);border-radius:16px;padding:16px 18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
    h1{font-size:22px;margin:0 0 12px}
    form{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin:8px 0 16px}
    input[type=number]{padding:10px 12px;border-radius:12px;border:1px solid #374151;background:#0b1220;color:var(--text);width:140px}
    button{padding:10px 14px;border-radius:12px;border:0;background:#334155;color:#fff;cursor:pointer}
    button.primary{background:#2563eb}
    button.danger{background:#b91c1c}
    button:disabled{opacity:.6;cursor:not-allowed}
    .msg{margin:8px 0 16px;color:var(--muted)}
    .grid{display:grid;grid-template-columns: 120px 1fr; gap:10px; align-items:center; margin:10px 0}
    .floor-label{color:#94a3b8;font-size:14px}
    .rooms{display:flex;gap:8px;flex-wrap:wrap}
    .room{min-width:56px;padding:8px;border-radius:12px;background:var(--grid);text-align:center;font-size:12px;border:1px solid #374151;position:relative}
    .room.available{outline:1px solid rgba(16,185,129,.4)}
    .room.booked{background:linear-gradient(180deg, #1f2937 0%, #111827 100%);border-color:#374151; opacity:.7}
    .room.badge{position:absolute;top:-8px;right:-8px;background:#000;color:#fff}
    .legend{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;font-size:12px;color:#94a3b8}
    .dot{width:10px;height:10px;border-radius:50%;display:inline-block;margin-right:6px;vertical-align:middle}
    .dot.ok{background:var(--ok)}
    .dot.no{background:var(--no)}
    .dot.sel{background:var(--sel)}
    .room.selected{outline:2px solid var(--sel); box-shadow:0 0 0 2px rgba(59,130,246,.25) inset}
    .room.available:not(.selected){background:linear-gradient(180deg, #0f172a 0%, #111827 100%)}
    .footer{margin-top:16px;font-size:12px;color:#94a3b8}
</style>
</head>
<body>
<div class="container">
  <div class="panel">
    <h1>Hotel Room Reservation System</h1>
    <form method="post">
      <label for="rooms">No. of rooms (1–5)</label>
      <input id="rooms" name="rooms" type="number" min="1" max="<?php echo MAX_ROOMS_PER_BOOKING; ?>" value="4" />
      <button class="primary" type="submit" name="action" value="book">Book</button>
      <button class="danger" type="submit" name="action" value="reset">Reset</button>
      <button type="submit" name="action" value="random">Random</button>
    </form>
    <?php if ($message): ?>
      <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="legend">
        <span><span class="dot ok"></span>Available</span>
        <span><span class="dot no"></span>Booked</span>
        <span><span class="dot sel"></span>Just booked</span>
    </div>

    <?php
    for ($f = 10; $f >= 1; $f--) {
        $cols = ($f === 10) ? 7 : 10;
        echo '<div class="grid">';
        echo '<div class="floor-label">Floor '.$f.($f===10?' (Top)':'').'</div>';
        echo '<div class="rooms">';
        for ($c=1; $c<=$cols; $c++) {
            $cell = $hotel[$f][$c];
            $roomNum = $cell['room'];
            $booked = $cell['booked'];
            $key = $f.'-'.$c;
            $selected = isset($lastIds[$key]);
            $cls = 'room ';
            $cls .= $booked ? 'booked' : 'available';
            if ($selected) $cls .= ' selected';
            $style = '';
            if ($booked && !$selected) $style = 'style="border-color:#ef4444; outline:1px solid rgba(239,68,68,.35)"';
            if (!$booked) $style = 'style="outline:1px solid rgba(16,185,129,.35)"';
            echo '<div class="'.$cls.'" '.$style.' title="Floor '.$f.' | Column '.$c.'">';
            echo '<div>'.$roomNum.'</div>';
            echo '</div>';
        }
        echo '</div></div>';
    }
    ?>
  </div>
</div>
</body>
</html>
