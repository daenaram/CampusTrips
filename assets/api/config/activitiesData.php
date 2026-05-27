<?php
// Seed activities only if table is empty

$activityCount = (int) $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();

if ($activityCount === 0) {

    $activities = [

        ['Sky Tower Visit', 'Auckland', 'New Zealand', 'Sightseeing'],
        ['Hobbiton Movie Set Tour', 'Matamata', 'New Zealand', 'Tour'],
        ['Milford Sound Cruise', 'Queenstown', 'New Zealand', 'Nature'],
        ['Te Papa Museum', 'Wellington', 'New Zealand', 'Museum'],
        ['Waitomo Glowworm Caves', 'Waitomo', 'New Zealand', 'Adventure'],

        ['Sydney Opera House Tour', 'Sydney', 'Australia', 'Sightseeing'],
        ['Bondi Beach Day', 'Sydney', 'Australia', 'Beach'],
        ['Great Barrier Reef Diving', 'Cairns', 'Australia', 'Adventure'],
        ['Melbourne Laneways Tour', 'Melbourne', 'Australia', 'Culture'],
        ['Uluru Sunset Experience', 'Alice Springs', 'Australia', 'Nature'],

        ['Snorkeling at Coral Coast', 'Sigatoka', 'Fiji', 'Beach'],
        ['Island Hopping Tour', 'Nadi', 'Fiji', 'Tour'],
        ['Fiji Cultural Village', 'Nadi', 'Fiji', 'Culture'],
        ['Cloud 9 Floating Bar', 'Mamanuca Islands', 'Fiji', 'Relaxation'],
        ['Shiraito Waterfall Visit', 'Suva', 'Fiji', 'Nature'],

        ['Tokyo Disneyland', 'Tokyo', 'Japan', 'Theme Park'],
        ['Shibuya Crossing Experience', 'Tokyo', 'Japan', 'Sightseeing'],
        ['Fushimi Inari Shrine', 'Kyoto', 'Japan', 'Culture'],
        ['Osaka Street Food Tour', 'Osaka', 'Japan', 'Food'],
        ['Mount Fuji Day Trip', 'Hakone', 'Japan', 'Nature'],

        ['Statue of Liberty Tour', 'New York', 'United States', 'Sightseeing'],
        ['Times Square Visit', 'New York', 'United States', 'Entertainment'],
        ['Grand Canyon Helicopter Tour', 'Arizona', 'United States', 'Adventure'],
        ['Hollywood Walk of Fame', 'Los Angeles', 'United States', 'Sightseeing'],
        ['Disneyland California', 'Anaheim', 'United States', 'Theme Park'],

        ['Eiffel Tower Visit', 'Paris', 'France', 'Sightseeing'],
        ['Colosseum Tour', 'Rome', 'Italy', 'History'],
        ['Santorini Sunset Cruise', 'Santorini', 'Greece', 'Relaxation'],
        ['Marina Bay Sands SkyPark', 'Singapore', 'Singapore', 'Sightseeing'],
        ['Burj Khalifa Observation Deck', 'Dubai', 'United Arab Emirates', 'Sightseeing']

    ];

    $stmt = $pdo->prepare(
        "INSERT INTO activities
            (activity_name, city, country, category,
             activity_date,
             cost_nzd, rating, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($activities as $activity) {

        $activityName = $activity[0];
        $city         = $activity[1];
        $country      = $activity[2];
        $category     = $activity[3];

        $daysAhead = rand(1, 90);
        $hour      = rand(8, 20);
        $minute    = [0, 15, 30, 45][rand(0, 3)];

        $activityDate = date('Y-m-d', strtotime("+{$daysAhead} days"));
        // $activityTime = sprintf('%02d:%02d:00', $hour, $minute);

        $cost   = rand(20, 250);
        $rating = round(rand(35, 50) / 10, 1);

        $description = "{$activityName} in {$city}, {$country}";

        $stmt->execute([
            $activityName,
            $city,
            $country,
            $category,
            $activityDate,
            // $activityTime,
            $cost,
            $rating,
            $description
        ]);
    }
}
?>