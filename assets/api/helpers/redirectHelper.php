<?php
/**
 * Where to send a user after they sign in. Only ever allows a small
 * whitelist of "come back here" targets — never an arbitrary posted URL,
 * to rule out this becoming an open redirect. Anything else falls back to
 * the dashboard. Shared by the password and Google sign-in flows.
 */
function safePostLoginRedirect(?string $requested, string $dashboardUrl): string
{
    if ($requested === null) {
        return $dashboardUrl;
    }

    $allowedPatterns = [
        // A shared trip link's "Sign in to view or edit this trip" prompt
        '#^/AUT-Web-Based-Travel-Planner/Pages/shared/sharedTrip\.php\?token=[a-f0-9]{40}$#',
        '#^/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard\.php\?shared_token=[a-f0-9]{40}$#',
        // A clicked reminder notification, opening that trip's details
        '#^/AUT-Web-Based-Travel-Planner/Pages/userDashboard/Dashboard\.php\?open_trip=[0-9]+$#',
    ];

    foreach ($allowedPatterns as $pattern) {
        if (preg_match($pattern, $requested)) {
            return $requested;
        }
    }

    return $dashboardUrl;
}
