<?php

/**
 * This script is to handle the trip category by using a bookmark UI 
 */

//The list of categories
function getTripCategories(): array
{
    return [
        'Conference' => ['label' => 'Conference', 'color' => '#c51e34'],
        'Field Trip' => ['label' => 'Field Trip', 'color' => '#008b8b'],
        'Group Trip' => ['label' => 'Group Trip', 'color' => '#c46210'],
        'Personal Trip' => ['label' => 'Personal Trip', 'color' => '#ffbf00']
    ];
}

/*return category color and label
 *if category is missing or null, return default 'Personal Trip'
 */

function getCategoryDetails(?string $category): array
{
    $categories = getTripCategories();
    $key = ($category !== null && isset($categories[$category])) ? $category : 'Personal Trip';
    return array_merge(['key' => $key], $categories[$key]);
}

function tripCategory(?string $category): string
{
    $allowed = array_keys(getTripCategories());
    return in_array($category, $allowed, true) ? $category : 'Personal Trip';
}

?>