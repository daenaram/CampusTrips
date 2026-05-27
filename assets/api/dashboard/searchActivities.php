<?php

function searchActivities(PDO $pdo, array $data): array {
    $keyword = trim($data['keyword'] ?? '');
    $city = trim($data['city'] ?? '');
    $category = trim($data['category'] ?? '');
    $activityDate = trim($data['activity_date'] ?? '');

    $query = 'SELECT * FROM activities';
    $conditions = [];
    $params = [];

    if ($keyword !== '') {
        $conditions[] = '(activity_name LIKE :keyword OR description LIKE :keyword)';
        $params[':keyword'] = "%$keyword%";
    }

    if ($city !== '') {
        $conditions[] = '(city LIKE :city OR country LIKE :city)';
        $params[':city'] = "%$city%";
    }

    if ($category !== '') {
        $conditions[] = 'category LIKE :category';
        $params[':category'] = "%$category%";
    }

    if ($activityDate !== '') {
        $conditions[] = 'DATE(activity_date) = :activity_date';
        $params[':activity_date'] = $activityDate;
    }

    if (count($conditions) > 0) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $query .= ' ORDER BY activity_date ASC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}