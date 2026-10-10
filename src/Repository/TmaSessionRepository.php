<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\TmaSession;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<TmaSession>
 */
class TmaSessionRepository extends EntityRepository
{
    /**
     * Creates a new cryptographically secure session for a Telegram Mini App user.
     *
     * @param array<string, mixed>|null $payload
     */
    public function createSession(
        Chat|int $chat,
        int $ttl = 86400,
        ?string $queryId = null,
        ?string $startParam = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $payload = null,
        ?DateTimeImmutable $authDate = null,
    ): TmaSession {
        $em = $this->getEntityManager();

        $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
        if ($chatEntity === null) {
            throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
        }

        // Generate 64-character high-entropy cryptographic token
        $token = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable("+{$ttl} seconds");

        $session = new TmaSession(
            chat: $chatEntity,
            token: $token,
            expiresAt: $expiresAt,
            queryId: $queryId,
            startParam: $startParam,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            payload: $payload,
            authDate: $authDate,
        );

        $em->persist($session);
        $em->flush();

        return $session;
    }

    /**
     * Authenticates a bearer token, touches the lastActiveAt timestamp, and returns the session if valid.
     */
    public function authenticate(string $token, bool $touch = true): ?TmaSession
    {
        $session = $this->findOneBy(['token' => trim($token)]);

        if ($session === null || !$session->isValid) {
            return null;
        }

        if ($touch) {
            $session->touch();
            $this->getEntityManager()->flush();
        }

        return $session;
    }

    /**
     * Revokes a specific session token immediately.
     */
    public function revoke(string $token): bool
    {
        $session = $this->findOneBy(['token' => trim($token)]);
        if ($session === null) {
            return false;
        }

        $session->revoke();
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Revokes all active sessions for a given chat.
     */
    public function revokeAllForChat(Chat|int $chat): int
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;

        $qb = $this->getEntityManager()->createQueryBuilder();
        $affected = $qb->update(TmaSession::class, 's')
            ->set('s.isRevoked', ':revoked')
            ->set('s.lastActiveAt', ':now')
            ->where('s.chat = :chatId')
            ->andWhere('s.isRevoked = false')
            ->setParameter('revoked', true)
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('chatId', $chatId)
            ->getQuery()
            ->execute();

        $this->getEntityManager()->clear();

        return is_numeric($affected) ? (int) $affected : 0;
    }

    /**
     * Returns all active non-expired sessions for a chat.
     *
     * @return list<TmaSession>
     */
    public function getActiveSessionsForChat(Chat|int $chat): array
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;

        $qb = $this->createQueryBuilder('s');

        /** @var list<TmaSession> */
        return $qb->where('s.chat = :chatId')
            ->andWhere('s.isRevoked = false')
            ->andWhere('s.expiresAt > :now')
            ->setParameter('chatId', $chatId)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('s.lastActiveAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cleans up expired sessions from the database.
     */
    public function cleanupExpired(): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $affected = $qb->delete(TmaSession::class, 's')
            ->where('s.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();

        $this->getEntityManager()->clear();

        return is_numeric($affected) ? (int) $affected : 0;
    }
}
