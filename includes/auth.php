<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Redirects the user to the login page if they are not logged in.
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Redirects the user to a dashboard if they don't have the required role.
 *
 * @param string $role The required role (e.g., 'FARMER', 'BUYER', 'DA').
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        // Redirect based on current role
        if ($_SESSION['role'] === 'FARMER') {
            header("Location: farmer/index.php");
        } elseif ($_SESSION['role'] === 'BUYER') {
            header("Location: buyer/index.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}

/**
 * Returns the currently logged-in user's ID.
 *
 * @return int|null
 */
function getLoggedInUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Returns the currently logged-in user's role.
 *
 * @return string|null
 */
function getLoggedInRole(): ?string {
    return $_SESSION['role'] ?? null;
}
?>