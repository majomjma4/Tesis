<?php

declare(strict_types=1);

/**
 * Persistencia temporal de favoritos respaldada por sesión.
 * Su interfaz puede reemplazarse posteriormente por consultas a MySQL.
 */
final class FavoriteModel
{
    private const SESSION_KEY = 'repository_favorites_by_user';

    public function getFavoriteIds(string $userId): array
    {
        $this->initializeUserFavorites($userId);

        return array_values($_SESSION[self::SESSION_KEY][$userId]);
    }

    public function isFavorite(string $userId, int $projectId): bool
    {
        return in_array($projectId, $this->getFavoriteIds($userId), true);
    }

    public function toggle(string $userId, int $projectId): bool
    {
        $favoriteIds = $this->getFavoriteIds($userId);
        $favoritePosition = array_search($projectId, $favoriteIds, true);

        if ($favoritePosition !== false) {
            unset($favoriteIds[$favoritePosition]);
            $_SESSION[self::SESSION_KEY][$userId] = array_values($favoriteIds);
            return false;
        }

        $favoriteIds[] = $projectId;
        $_SESSION[self::SESSION_KEY][$userId] = array_values(array_unique($favoriteIds));
        return true;
    }

    public function count(string $userId): int
    {
        return count($this->getFavoriteIds($userId));
    }

    private function initializeUserFavorites(string $userId): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if (!array_key_exists($userId, $_SESSION[self::SESSION_KEY])) {
            // Datos iniciales simulados para la etapa sin base de datos.
            $_SESSION[self::SESSION_KEY][$userId] = [1, 4];
        }
    }
}
