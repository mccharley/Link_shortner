<?php

declare(strict_types=1);

namespace LinkShortener\Repositories;

use LinkShortener\Config\Database;
use LinkShortener\Models\Link;
use PDO;
use DateTime;

class LinkRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function save(Link $link): bool
    {
        if ($link->getId() === null) {
            return $this->create($link);
        }
        
        return $this->update($link);
    }

    private function create(Link $link): bool
    {
        $sql = "INSERT INTO links (original_url, short_code, created_at, expires_at, created_by, click_count, is_active, metadata) 
                VALUES (:original_url, :short_code, :created_at, :expires_at, :created_by, :click_count, :is_active, :metadata)";
        
        $stmt = $this->db->prepare($sql);
        
        $data = $link->toArray();
        unset($data['id']);
        
        $result = $stmt->execute($data);
        
        if ($result) {
            $link->setId((int)$this->db->lastInsertId());
        }
        
        return $result;
    }

    private function update(Link $link): bool
    {
        $sql = "UPDATE links SET 
                original_url = :original_url, 
                short_code = :short_code, 
                expires_at = :expires_at, 
                created_by = :created_by, 
                click_count = :click_count, 
                is_active = :is_active, 
                metadata = :metadata 
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($link->toArray());
    }

    public function findByShortCode(string $shortCode): ?Link
    {
        $sql = "SELECT * FROM links WHERE short_code = :short_code AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['short_code' => $shortCode]);
        
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToLink($row);
    }

    public function findByOriginalUrl(string $originalUrl): ?Link
    {
        $sql = "SELECT * FROM links WHERE original_url = :original_url AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['original_url' => $originalUrl]);
        
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToLink($row);
    }

    public function findById(int $id): ?Link
    {
        $sql = "SELECT * FROM links WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToLink($row);
    }

    public function findByCreatedBy(string $createdBy, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM links WHERE created_by = :created_by ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':created_by', $createdBy);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $links = [];
        while ($row = $stmt->fetch()) {
            $links[] = $this->mapRowToLink($row);
        }
        
        return $links;
    }

    public function incrementClickCount(string $shortCode): bool
    {
        $sql = "UPDATE links SET click_count = click_count + 1 WHERE short_code = :short_code";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(['short_code' => $shortCode]);
    }

    public function deleteByShortCode(string $shortCode): bool
    {
        $sql = "UPDATE links SET is_active = 0 WHERE short_code = :short_code";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute(['short_code' => $shortCode]);
    }

    public function shortCodeExists(string $shortCode): bool
    {
        $sql = "SELECT 1 FROM links WHERE short_code = :short_code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['short_code' => $shortCode]);
        
        return $stmt->fetchColumn() !== false;
    }

    public function getStats(): array
    {
        $sql = "SELECT 
                COUNT(*) as total_links,
                SUM(click_count) as total_clicks,
                COUNT(DISTINCT created_by) as unique_creators,
                AVG(click_count) as avg_clicks_per_link
                FROM links 
                WHERE is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch() ?: [];
    }

    public function cleanupExpiredLinks(): int
    {
        $sql = "UPDATE links SET is_active = 0 WHERE expires_at < NOW() AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    private function mapRowToLink(array $row): Link
    {
        $createdAt = new DateTime($row['created_at']);
        $expiresAt = $row['expires_at'] ? new DateTime($row['expires_at']) : null;
        $metadata = json_decode($row['metadata'] ?? '{}', true);
        
        return new Link(
            $row['original_url'],
            $row['short_code'],
            $row['created_by'],
            (int)$row['id'],
            $createdAt,
            $expiresAt,
            (int)$row['click_count'],
            (bool)$row['is_active'],
            $metadata
        );
    }
}