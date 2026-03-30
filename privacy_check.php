<?php
/**
 * privacy_check.php
 * Include this on any page that shows another user's content.
 *
 * Usage:
 *   require_once 'privacy_check.php';
 *   $canView = canViewProfile($con, $viewer_id, $profile_owner_id);
 *   if (!$canView) { include 'locked_profile.php'; exit(); }
 *
 * Rules:
 *   - You can ALWAYS view your own profile
 *   - If profile is PUBLIC  → everyone can view
 *   - If profile is PRIVATE → only mutual followers can view
 *     (mutual = viewer follows owner AND owner follows viewer)
 */

/**
 * canViewProfile()
 * Returns true if $viewer_id is allowed to see $owner_id's profile.
 */
function canViewProfile(mysqli $con, int $viewer_id, int $owner_id): bool
{
    // Always can view own profile
    if ($viewer_id === $owner_id) return true;

    // Check if owner's account is private
    $stmt = $con->prepare("SELECT is_private FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) return false;                  // user doesn't exist
    if ((int)$row['is_private'] === 0) return true;  // public account

    // Private account — check mutual follow
    // viewer must follow owner AND owner must follow viewer
    $chk = $con->prepare("
        SELECT COUNT(*) AS c FROM follows
        WHERE (follower_id = ? AND following_id = ?)   -- viewer follows owner
           OR (follower_id = ? AND following_id = ?)   -- owner follows viewer
    ");
    $chk->bind_param('iiii', $viewer_id, $owner_id, $owner_id, $viewer_id);
    $chk->execute();
    $mutual = (int)$chk->get_result()->fetch_assoc()['c'];
    $chk->close();

    // Both rows must exist (count = 2) for true mutual follow
    return $mutual >= 2;
}

/**
 * isPrivateAccount()
 * Quick check — returns true if the user has a private account.
 */
function isPrivateAccount(mysqli $con, int $user_id): bool
{
    $stmt = $con->prepare("SELECT is_private FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && (int)$row['is_private'] === 1;
}