<?php
// Seed dummy data quietly, using the existing $pdo from database.php.

// Seed flights only if table is empty
$flightCount = (int) $pdo->query("SELECT COUNT(*) FROM flights")->fetchColumn();

if ($flightCount === 0) {
    $airlines = ['Air New Zealand', 'Qantas', 'Jetstar', 'Emirates', 'Singapore Airlines'];
    $routes = [
        ['Auckland', 'AKL', 'Sydney',      'SYD'],
        ['Auckland', 'AKL', 'Melbourne',   'MEL'],
        ['Auckland', 'AKL', 'Singapore',   'SIN'],
        ['Auckland', 'AKL', 'Los Angeles', 'LAX'],
        ['Auckland', 'AKL', 'London',      'LHR'],
        ['Auckland', 'AKL', 'Tokyo',       'NRT'],
        ['Auckland', 'AKL', 'Fiji',        'NAN'],
        ['Auckland', 'AKL', 'Bali',        'DPS'],
    ];
    $cabins = ['Economy', 'Business', 'First'];

    $stmt = $pdo->prepare(
        "INSERT INTO flights
            (airline, flight_number, departure_city, arrival_city,
             departure_airport, arrival_airport, departure_datetime,
             arrival_datetime, duration_minutes, stops, cabin_class, price_nzd)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    for ($i = 0; $i < 30; $i++) {
        $airline   = $airlines[array_rand($airlines)];
        $route     = $routes[array_rand($routes)];
        $cabin     = $cabins[array_rand($cabins)];
        $flightNum = strtoupper(substr(str_replace(' ', '', $airline), 0, 2)) . rand(100, 999);

        $daysAhead     = rand(1, 60);
        $hour          = rand(5, 22);
        $minute        = [0, 15, 30, 45][rand(0, 3)];
        $departureTime = date('Y-m-d H:i:s', strtotime("+{$daysAhead} days {$hour}:{$minute}"));
        $durationMins  = rand(180, 1440);
        $arrivalTime   = date('Y-m-d H:i:s', strtotime($departureTime) + ($durationMins * 60));

        $basePrice = rand(200, 1500);
        if ($cabin === 'Business') $basePrice *= 2.5;
        if ($cabin === 'First')    $basePrice *= 4;

        $stmt->execute([
            $airline,
            $flightNum,
            $route[0],
            $route[2],
            $route[1],
            $route[3],
            $departureTime,
            $arrivalTime,
            $durationMins,
            rand(0, 2),
            $cabin,
            round($basePrice, 2),
        ]);
    }
}

// Seed accommodations only if table is empty
$accCount = (int) $pdo->query("SELECT COUNT(*) FROM accommodations")->fetchColumn();

if ($accCount === 0) {
    $accTypes  = ['Hotel', 'Hostel', 'Airbnb', 'Motel'];
    $accCities = [
        ['Sydney',      'Australia'],
        ['Melbourne',   'Australia'],
        ['Singapore',   'Singapore'],
        ['Tokyo',       'Japan'],
        ['London',      'United Kingdom'],
        ['Los Angeles', 'United States'],
        ['Bali',        'Indonesia'],
        ['Fiji',        'Fiji'],
    ];
    $accNames      = ['Grand', 'Central', 'City', 'Harbor', 'Pacific', 'Royal', 'Park', 'Bay'];
    $amenitiesList = ['WiFi', 'Pool', 'Parking', 'Breakfast', 'Gym', 'Bar', 'Spa', 'Airport Shuttle'];

    $stmt = $pdo->prepare(
        "INSERT INTO accommodations
            (name, type, city, country, address,
             check_in_time, check_out_time,
             price_per_night_nzd, rating, amenities)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    for ($i = 0; $i < 30; $i++) {
        $type   = $accTypes[array_rand($accTypes)];
        $city   = $accCities[array_rand($accCities)];
        $prefix = $accNames[array_rand($accNames)];
        $name   = "{$prefix} {$type} {$city[0]}";

        $shuffled  = $amenitiesList;
        shuffle($shuffled);
        $amenities = implode(', ', array_slice($shuffled, 0, rand(3, 6)));

        $price = rand(50, 300);
        if ($type === 'Hotel')  $price = rand(150, 600);
        if ($type === 'Hostel') $price = rand(30, 80);

        $rating = round(rand(30, 50) / 10, 1);

        $stmt->execute([
            $name,
            $type,
            $city[0],
            $city[1],
            rand(1, 999) . ' ' . $prefix . ' Street',
            '14:00:00',
            '11:00:00',
            round($price, 2),
            $rating,
            $amenities,
        ]);
    }
}
?>