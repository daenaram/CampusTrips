<?php

function searchFlights(PDO $pdo, array $data): array {
    $departureCity = trim($data['departure_city'] ?? '');
    $arrivalCity   = trim($data['arrival_city'] ?? '');
    $airline       = trim($data['airline'] ?? '');
    $departureDate = trim($data['departure_date'] ?? '');
    $returnDate    = trim($data['return_date'] ?? '');

    $query = 'SELECT * FROM flights';
    $conditions = [];
    $params = [];

    if ($departureCity !== '') {
        $conditions[] = 'departure_city LIKE :departure_city OR departure_airport LIKE :departure_city';
        $params[':departure_city'] = "%$departureCity%";
    }

    if ($arrivalCity !== '') {
        $conditions[] = 'arrival_city LIKE :arrival_city OR arrival_airport LIKE :arrival_city';
        $params[':arrival_city'] = "%$arrivalCity%";
    }

    if ($airline !== '') {
        $conditions[] = 'airline LIKE :airline';
        $params[':airline'] = "%$airline%";
    }

    if ($departureDate !== '' && $returnDate !== '') {
        $conditions[] = 'DATE(departure_datetime) BETWEEN :departure_date AND :return_date';
        $params[':departure_date'] = $departureDate;
        $params[':return_date'] = $returnDate;
    } elseif ($departureDate !== '') {
        $conditions[] = 'DATE(departure_datetime) = :departure_date';
        $params[':departure_date'] = $departureDate;
    } elseif ($returnDate !== '') {
        $conditions[] = 'DATE(departure_datetime) = :return_date';
        $params[':return_date'] = $returnDate;
    }

    if (count($conditions) > 0) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $query .= ' ORDER BY departure_datetime ASC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
