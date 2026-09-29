<?php

namespace App;

use App\Models\User;

class Auth
{
    private static ?array $currentUser = null;

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['user_id'])) {
            self::$currentUser = User::find((int) $_SESSION['user_id']);
        }
    }

    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$currentUser   = $user;
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        self::$currentUser = null;
    }

    public static function setUser(array $user): void
    {
        self::$currentUser = $user;
    }

    public static function check(): bool
    {
        return self::$currentUser !== null;
    }

    public static function user(): ?array
    {
        return self::$currentUser;
    }

    public static function id(): ?int
    {
        return self::$currentUser ? (int) self::$currentUser['id'] : null;
    }

    public static function isAdmin(): bool
    {
        return (self::$currentUser['role'] ?? '') === 'admin';
    }

    public static function isCommercial(): bool
    {
        return (self::$currentUser['role'] ?? '') === 'commercial';
    }

    public static function isManager(): bool
    {
        return (self::$currentUser['role'] ?? '') === 'manager';
    }

    /**
     * Store the logged-in manager's ACCESS is scoped to
     * (users.managed_store_id). Null for admins and commercials.
     *
     * Not the store they work at: that is commercials.store_id, and for a
     * bookable manager both hold the same value.
     */
    public static function storeId(): ?int
    {
        $storeId = self::$currentUser['managed_store_id'] ?? null;
        return $storeId !== null ? (int) $storeId : null;
    }
}
