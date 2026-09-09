<?php
/**
 * Currency conversion helper for the Budget feature.
 *
 * Live rates are fetched from Frankfurter (https://frankfurter.dev), a free,
 * no-API-key exchange rate API sourced from central banks (ECB et al).
 * Rates are cached in the `fx_rate_cache` table for FX_CACHE_TTL_HOURS so
 * we aren't hitting the external API on every single add/edit — Frankfurter's
 * own rates only update once a day anyway.
 *
 * If the API call ever fails (network issue, rate limited, etc.), we fall
 * back to the last cached rate we have, and only fall back to the static
 * table below if we've genuinely never fetched that currency before.
 */

const FX_API_BASE = 'https://api.frankfurter.dev/v1';
const FX_CACHE_TTL_HOURS = 12;

// Currencies offered in the budget form's dropdown. Kept to currencies
// Frankfurter actually supports (it does not cover every world currency —
// e.g. FJD is not available, since it isn't published by Frankfurter's
// underlying central bank sources).
if (!isset($GLOBALS['SUPPORTED_CURRENCIES'])) {
    $GLOBALS['SUPPORTED_CURRENCIES'] = [
        'NZD', 'USD', 'AUD', 'GBP', 'EUR', 'JPY', 'SGD',
        'CAD', 'CNY', 'HKD', 'THB', 'INR', 'KRW', 'CHF', 'ZAR',
    ];
}

// Last-resort static rates, only used if we've never successfully cached a
// live rate for that currency AND the live API call fails. 1 unit of
// [currency] = X NZD. Keep these roughly current if you touch this file.
if (!isset($GLOBALS['CURRENCY_FALLBACK_RATES'])) {
    $GLOBALS['CURRENCY_FALLBACK_RATES'] = [
        'NZD' => 1.00,
        'USD' => 1.68,
        'AUD' => 1.10,
        'GBP' => 2.15,
        'EUR' => 1.85,
        'JPY' => 0.011,
        'SGD' => 1.25,
        'CAD' => 1.22,
        'CNY' => 0.24,
        'HKD' => 0.22,
        'THB' => 0.048,
        'INR' => 0.020,
        'KRW' => 0.0012,
        'CHF' => 2.00,
        'ZAR' => 0.095,
    ];
}

/**
 * Returns the list of currency codes offered in the budget form.
 */
function getSupportedCurrencies(): array
{
    return $GLOBALS['SUPPORTED_CURRENCIES'];
}

/**
 * Whether a given currency code is one we offer/accept.
 */
function isCurrencySupported(string $currency): bool
{
    return in_array(strtoupper(trim($currency)), $GLOBALS['SUPPORTED_CURRENCIES'], true);
}

//Fetches the exchange rate from live list of currencies Frankfurter API provides. Returns null if the currency is not supported or if the API call fails.
function getLiveSupportCurrencies(): ?array{
    $raw = @file_get_contents(FX_API_BASE . '/currencies');
    if ($raw === false) {
        return $GLOBALS['SUPPORTED_CURRENCIES'];
    }

    $all = json_decode($raw, true);
    if (!is_array($all)) {
        return $GLOBALS['SUPPORTED_CURRENCIES'];
    }

    $live = array_values(array_intersect($GLOBALS['SUPPORTED_CURRENCIES'], array_keys($all)));
    return !empty($live) ? $live : $GLOBALS['SUPPORTED_CURRENCIES'];
    
}

/**
 * Calls the Frankfurter API for a live 1-unit rate from $currency to NZD.
 * Returns null on any failure (network error, bad response, currency not
 * recognised by Frankfurter) so the caller can decide how to fall back.
 */
function fetchRateFromFrankfurter(string $currency): ?float
{
    if ($currency === 'NZD') {
        return 1.0;
    }

    $url = FX_API_BASE . '/latest?amount=1&from=' . urlencode($currency) . '&to=NZD';
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 422) {
            error_log("Frankfurter API request failed for $currency (HTTP $httpCode): $curlError");
            return null;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            error_log("Frankfurter API request failed for $currency (file_get_contents)");
            return null;
        }
    } else {
        error_log('Frankfurter API request skipped: neither curl nor allow_url_fopen is available.');
        return null;
    }

    $data = json_decode($raw, true);
    if (!isset($data['rates']['NZD'])) {
        error_log("Frankfurter API returned no NZD rate for $currency: " . $raw);
        return null;
    }

    return (float) $data['rates']['NZD'];
}

/**Refreshes the supported currencies rates.
 * This function fetches the latest exchange rates for all supported currencies from the Frankfurter API and updates the local cache in the database. It is intended to be run periodically (e.g., via a cron job) to ensure that the cached rates remain up-to-date.
 */
function refreshAllFxRates(PDO $pdo): bool 
{
    $currencies = array_values(array_diff($GLOBALS['SUPPORTED_CURRENCIES'], ['NZD'])); // Exclude NZD since its rate is always 1
    if (empty($currencies)) {
        return true; 
    }

    $symbols = implode(',', $currencies);
    $url = FX_API_BASE . '/latest?base=NZD&symbols=' . urlencode($symbols);
    $raw = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) {
            error_log("Bulk FX refresh failed (HTTP $httpCode)");
            return false;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
        if ($raw === false) {
            error_log('Bulk FX refresh failed (file_get_contents)');
            return false;
        }
    } else {
        return false;
    }

    $data = json_decode($raw, true);
    if (!isset($data['rates']) || !is_array($data['rates'])) {
        error_log('Bulk FX refresh: unexpected response shape: ' . $raw);
        return false;
    }

    try {
        $upsert = $pdo->prepare("
            INSERT INTO fx_rate_cache (currency, rate_to_nzd, fetched_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE rate_to_nzd = VALUES(rate_to_nzd), fetched_at = VALUES(fetched_at)
        ");

        foreach ($data['rates'] as $currency => $nzdToCurrency) {
            $nzdToCurrency = (float) $nzdToCurrency;
            if ($nzdToCurrency > 0) {
                $rateToNzd = 1 / $nzdToCurrency; // invert: NZD->X becomes X->NZD
                $upsert->execute([$currency, round($rateToNzd, 6)]);
            }
        }
    } catch (PDOException $e) {
        error_log('Bulk FX refresh DB write error: ' . $e->getMessage());
        return false;
    }

    return true;
}




/**
 * Gets the current rate for 1 unit of $currency in NZD, using the DB cache
 * where possible and only calling out to Frankfurter when the cached value
 * is missing or older than FX_CACHE_TTL_HOURS.
 */
function getFxRateToNZD(PDO $pdo, string $currency): float
{
    $currency = strtoupper(trim($currency));
    if ($currency === 'NZD') {
        return 1.0;
    }

    $cached = null;
    try {
        $stmt = $pdo->prepare("SELECT rate_to_nzd, fetched_at FROM fx_rate_cache WHERE currency = ?");
        $stmt->execute([$currency]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('fx_rate_cache read error: ' . $e->getMessage());
    }

    if ($cached) {
        $fetchedAt = new DateTime($cached['fetched_at']);
        $ageHours = (time() - $fetchedAt->getTimestamp()) / 3600;
        if ($ageHours < FX_CACHE_TTL_HOURS) {
            return (float) $cached['rate_to_nzd'];
        }
    }

    $liveRate = fetchRateFromFrankfurter($currency);

    if ($liveRate !== null) {
        try {
            $upsert = $pdo->prepare("
                INSERT INTO fx_rate_cache (currency, rate_to_nzd, fetched_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE rate_to_nzd = VALUES(rate_to_nzd), fetched_at = VALUES(fetched_at)
            ");
            $upsert->execute([$currency, $liveRate]);
        } catch (PDOException $e) {
            error_log('fx_rate_cache write error: ' . $e->getMessage());
        }
        return $liveRate;
    }

    // Live call failed — prefer a stale cached rate over a hardcoded guess.
    if ($cached) {
        return (float) $cached['rate_to_nzd'];
    }

    return $GLOBALS['CURRENCY_FALLBACK_RATES'][$currency] ?? 1.00;
}

/**
 * Converts an amount in the given currency to NZD, rounded to 2 decimals.
 */
function convertToNZD(PDO $pdo, float $amount, string $currency): float
{
    $rate = getFxRateToNZD($pdo, $currency);
    return round($amount * $rate, 2);
}