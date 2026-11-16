<?php
// Shared protection for records that must not be silently changed or erased.

function record_lock_schema(PDO $pdo): void {
    static $done = false;
    if ($done || $pdo->inTransaction()) return;
    $done = true;
    schema_once('record_locks', fn() => $pdo->exec("CREATE TABLE IF NOT EXISTS record_locks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT NOT NULL,
        reason VARCHAR(255) NOT NULL,
        locked_by INT NULL,
        locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_record_lock (entity_type, entity_id),
        INDEX idx_record_lock_type (entity_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"));
}

function record_lock(PDO $pdo, string $entityType, int $entityId, string $reason, ?int $userId = null): void {
    if ($entityId <= 0) return;
    record_lock_schema($pdo);
    $q = $pdo->prepare("INSERT INTO record_locks (entity_type, entity_id, reason, locked_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), locked_by=VALUES(locked_by), locked_at=CURRENT_TIMESTAMP");
    $q->execute([$entityType, $entityId, mb_substr($reason, 0, 255), $userId]);
}

function record_is_locked(PDO $pdo, string $entityType, int $entityId): bool {
    record_lock_schema($pdo);
    $q = $pdo->prepare('SELECT 1 FROM record_locks WHERE entity_type = ? AND entity_id = ? LIMIT 1');
    $q->execute([$entityType, $entityId]);
    return (bool)$q->fetchColumn();
}

function record_lock_reason(PDO $pdo, string $entityType, int $entityId): ?string {
    record_lock_schema($pdo);
    $q = $pdo->prepare('SELECT reason FROM record_locks WHERE entity_type = ? AND entity_id = ? LIMIT 1');
    $q->execute([$entityType, $entityId]);
    $v = $q->fetchColumn();
    return $v === false ? null : (string)$v;
}

function record_require_unlocked(PDO $pdo, string $entityType, int $entityId): void {
    $reason = record_lock_reason($pdo, $entityType, $entityId);
    if ($reason !== null) throw new RuntimeException('This record is locked: ' . $reason);
}
