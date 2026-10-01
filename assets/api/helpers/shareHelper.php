<?php
/**
 * Shareable trip link helpers.
 * Place this file at: assets/api/helpers/shareHelper.php
 *
 * Used by userDashboard/Dashboard.php (owner-facing sharing controls) and
 * Pages/shared/sharedTrip.php (the public, read-only page a link opens).
 *
 * One rule everything else here follows: a token is never reused or
 * reactivated. "Disable" flips is_active on the current row; "Generate New
 * Link" revokes the current row for good and inserts a brand new one. Old
 * rows are kept (not deleted) so the public page can always resolve an old
 * token and explain why it no longer works.
 */

function generateShareToken(): string
{
    return bin2hex(random_bytes(20));
}

/**
 * The single row currently representing a trip's share link (its most
 * recently generated one), or null if the trip has never been shared.
 */
function getCurrentShareForTrip(PDO $pdo, int $tripId, int $userId): ?array
{
    $stmt = $pdo->prepare("
        SELECT id, trip_id, token, is_active, access_level, view_count, last_viewed_at, created_at
        FROM trip_shares
        WHERE trip_id = ? AND user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$tripId, $userId]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    return $share ?: null;
}

/**
 * Revokes whatever share link currently exists for the trip (if any) and
 * creates a brand new, unique one. Used both the first time a trip is
 * shared and every time "Generate New Link" is clicked — the two are the
 * same operation. The new link inherits the previous link's view/edit
 * permission (defaulting to view-only for a trip's very first link) so
 * regenerating a link doesn't silently reset it to read-only.
 */
function createShareLink(PDO $pdo, int $tripId, int $userId): array
{
    $existing = getCurrentShareForTrip($pdo, $tripId, $userId);
    $accessLevel = $existing['access_level'] ?? 'view';

    $revokeStmt = $pdo->prepare("
        UPDATE trip_shares
        SET is_active = 0, revoked_at = COALESCE(revoked_at, NOW())
        WHERE trip_id = ? AND user_id = ? AND is_active = 1
    ");
    $revokeStmt->execute([$tripId, $userId]);

    $insertStmt = $pdo->prepare("
        INSERT INTO trip_shares (trip_id, user_id, token, is_active, access_level)
        VALUES (?, ?, ?, 1, ?)
    ");

    // The UNIQUE constraint on token makes a collision self-correcting; a
    // couple of retries is just a safety net against an extremely unlucky
    // random draw, not something expected to ever actually trigger.
    $attempts = 0;
    do {
        $token = generateShareToken();
        try {
            $insertStmt->execute([$tripId, $userId, $token, $accessLevel]);
            break;
        } catch (PDOException $e) {
            $attempts++;
            if ($attempts >= 3) {
                throw $e;
            }
        }
    } while (true);

    return getCurrentShareForTrip($pdo, $tripId, $userId);
}

/**
 * Sets whether the trip's current link is view-only or allows editing.
 * Only ever touches the latest row, same as setShareLinkActive().
 */
function setShareAccessLevel(PDO $pdo, int $tripId, int $userId, string $accessLevel): bool
{
    if (!in_array($accessLevel, ['view', 'edit'], true)) {
        return false;
    }

    $current = getCurrentShareForTrip($pdo, $tripId, $userId);
    if (!$current) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE trip_shares
        SET access_level = ?
        WHERE id = ? AND trip_id = ? AND user_id = ?
    ");
    $stmt->execute([$accessLevel, $current['id'], $tripId, $userId]);

    return true;
}

/**
 * Enables or disables the trip's current share link. Only ever touches the
 * latest row for the trip, so a link retired by createShareLink() can never
 * be reactivated through this.
 */
function setShareLinkActive(PDO $pdo, int $tripId, int $userId, bool $active): bool
{
    $current = getCurrentShareForTrip($pdo, $tripId, $userId);
    if (!$current) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE trip_shares
        SET is_active = ?, revoked_at = ?
        WHERE id = ? AND trip_id = ? AND user_id = ?
    ");
    $stmt->execute([
        $active ? 1 : 0,
        $active ? null : ($current['revoked_at'] ?? date('Y-m-d H:i:s')),
        $current['id'],
        $tripId,
        $userId,
    ]);

    return true;
}

/**
 * Looks up a token for the public share page. Returns null only when the
 * token itself doesn't exist; every other outcome (trip deleted, link
 * disabled, trip made private) comes back as a row so the caller can show
 * a specific message rather than a generic 404.
 *
 * 'trip_exists' is null when the trip behind this token has been deleted.
 */
function getShareByToken(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            ts.id, ts.trip_id, ts.token, ts.is_active, ts.access_level, ts.view_count,
            t.id AS trip_exists, t.user_id AS owner_id, t.title, t.destination,
            t.start_date, t.end_date, t.notes, t.is_private
        FROM trip_shares ts
        LEFT JOIN trips t ON t.id = ts.trip_id
        WHERE ts.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);
    return $share ?: null;
}

function recordShareView(PDO $pdo, int $shareId): void
{
    $stmt = $pdo->prepare("
        UPDATE trip_shares
        SET view_count = view_count + 1, last_viewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$shareId]);
}

/**
 * Resolves a token down to "is this link currently usable at all" (found,
 * trip still exists, link active, trip public) and returns the share row on
 * success — the one non-obvious state ('trip_gone' vs 'invalid' vs
 * 'private') a caller needs collapsed into a single yes/no.
 *
 * Used by the public share page both to decide what to render and, on every
 * edit-mode mutation, to re-derive which trip/owner a request is allowed to
 * touch — a posted trip_id is never trusted for that.
 */
function resolveUsableShare(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $share = getShareByToken($pdo, $token);
    if (!$share || $share['trip_exists'] === null || !((int) $share['is_active']) || (int) $share['is_private']) {
        return null;
    }

    return $share;
}

/**
 * Records that a signed-in user has taken up a share link, so the trip
 * keeps showing up in their own dashboard (labelled as shared) on future
 * visits without them needing the link again. One row per (trip, visitor);
 * following a newer link for the same trip just updates it. Ongoing
 * visibility is still re-checked live against trip_shares/trips every time
 * (see getSharedTripsForUser()/getEditableTripOwnerId()) — this row is
 * only "how we met", not a standing grant that survives the link being
 * disabled, regenerated, or the trip going private.
 */
function grantShareRecipient(PDO $pdo, int $shareId, int $tripId, int $userId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO trip_share_recipients (share_id, trip_id, user_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE share_id = VALUES(share_id), last_accessed_at = NOW()
    ");
    $stmt->execute([$shareId, $tripId, $userId]);
}

/**
 * Trips shared with this user that are still currently accessible (the
 * link is active and the trip is public) — everything a stale or revoked
 * grant would otherwise leave behind is filtered out here, live, every
 * time. Shaped to drop straight into the same $trips array as the user's
 * own trips, with is_owner/owner_name/link_access_level added on.
 */
function getSharedTripsForUser(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT
            t.id, t.title, t.destination, t.start_date, t.end_date, t.notes,
            t.travel_style, t.is_private, t.created_at,
            t.user_id AS owner_id, u.name AS owner_name,
            ts.access_level AS link_access_level
        FROM trip_share_recipients r
        JOIN trip_shares ts ON ts.id = r.share_id
        JOIN trips t ON t.id = r.trip_id
        JOIN users u ON u.id = t.user_id
        WHERE r.user_id = ? AND ts.is_active = 1 AND t.is_private = 0
        ORDER BY r.last_accessed_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The user_id whose saved_flights/saved_accommodations/saved_activities/
 * trips rows a mutation should target for this trip and this signed-in
 * visitor: their own id if they own the trip, the trip owner's id if they
 * hold a currently-active edit-permission share, or null if neither is
 * true right now. Every edit-capable handler in Dashboard.php calls this
 * fresh rather than trusting anything about "who can edit" from earlier in
 * the request — a disabled/regenerated/privated share stops authorizing
 * writes immediately, not just page views.
 */
function getEditableTripOwnerId(PDO $pdo, int $tripId, int $sessionUserId): ?int
{
    $stmt = $pdo->prepare("SELECT user_id FROM trips WHERE id = ?");
    $stmt->execute([$tripId]);
    $ownerId = $stmt->fetchColumn();

    if ($ownerId === false) {
        return null;
    }

    if ((int) $ownerId === $sessionUserId) {
        return $sessionUserId;
    }

    $stmt = $pdo->prepare("
        SELECT t.user_id AS owner_id
        FROM trip_share_recipients r
        JOIN trip_shares ts ON ts.id = r.share_id
        JOIN trips t ON t.id = r.trip_id
        WHERE r.trip_id = ? AND r.user_id = ?
          AND ts.is_active = 1 AND ts.access_level = 'edit' AND t.is_private = 0
        LIMIT 1
    ");
    $stmt->execute([$tripId, $sessionUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (int) $row['owner_id'] : null;
}

/**
 * Absolute URL for a share token, built from the current request so it
 * works the same on localhost, a WAMP vhost, or a real domain.
 */
function buildShareUrl(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/AUT-Web-Based-Travel-Planner/Pages/shared/sharedTrip.php?token=' . urlencode($token);
}
?>
