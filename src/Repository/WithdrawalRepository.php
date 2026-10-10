<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\WalletBalance;
use Tueen\Database\Entity\Withdrawal;
use Tueen\Database\Entity\WithdrawalMeta;
use Tueen\Database\Enum\WithdrawalStatus;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<Withdrawal>
 */
class WithdrawalRepository extends EntityRepository
{
    public string $defaultCurrency = 'XTR';

    public function request(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
        string $feeAmount = '0.000000000000000000',
        string $commissionRate = '0.00',
        ?string $payoutMethod = null,
        ?string $destination = null,
        bool $freezeBalance = true,
    ): Withdrawal {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency, $feeAmount, $commissionRate, $payoutMethod, $destination, $freezeBalance) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            if ($freezeBalance) {
                /** @var WalletRepository $walletRepo */
                $walletRepo = $em->getRepository(WalletBalance::class);
                $walletRepo->defaultCurrency = $this->defaultCurrency;
                $walletRepo->freeze($chatEntity, $amount, $currency);
            }

            $withdrawal = new Withdrawal(
                chat: $chatEntity,
                amount: $amount,
                currency: $currency,
                feeAmount: $feeAmount,
                commissionRate: $commissionRate,
                payoutMethod: $payoutMethod,
                destination: $destination,
            );

            $em->persist($withdrawal);
            $em->flush();

            return $withdrawal;
        });
    }

    public function approve(
        Withdrawal|int $withdrawal,
        ?Chat $processedBy = null,
        ?string $transactionId = null,
        bool $debitFrozen = true,
    ): Withdrawal {
        $em = $this->getEntityManager();

        return $this->executeTransaction(function () use ($em, $withdrawal, $processedBy, $transactionId, $debitFrozen) {
            $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);

            if ($entity === null) {
                throw RecordNotFoundException::forEntity(Withdrawal::class, is_int($withdrawal) ? $withdrawal : $withdrawal->id);
            }

            if ($entity->status->isFinal()) {
                throw new \LogicException("Cannot approve a withdrawal that is already {$entity->status->value}.");
            }

            if ($debitFrozen && $entity->chat !== null && $entity->currency !== null) {
                /** @var WalletRepository $walletRepo */
                $walletRepo = $em->getRepository(WalletBalance::class);
                $walletRepo->defaultCurrency = $this->defaultCurrency;
                $tx = $walletRepo->debitFrozen(
                    chat: $entity->chat,
                    amount: $entity->amount,
                    currency: $entity->currency,
                    source: 'withdrawal_approved',
                    sourceId: $entity->id,
                    createdBy: $processedBy,
                );
                $entity->walletTransaction = $tx;
            }

            $entity->status = WithdrawalStatus::Approved;
            $entity->processedBy = $processedBy;
            $entity->processedAt = new DateTimeImmutable();
            if ($transactionId !== null) {
                $entity->transactionId = $transactionId;
            }

            $em->flush();

            return $entity;
        });
    }

    public function reject(
        Withdrawal|int $withdrawal,
        ?Chat $processedBy = null,
        ?string $reason = null,
        bool $unfreezeBalance = true,
    ): Withdrawal {
        $em = $this->getEntityManager();

        return $this->executeTransaction(function () use ($em, $withdrawal, $processedBy, $reason, $unfreezeBalance) {
            $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);

            if ($entity === null) {
                throw RecordNotFoundException::forEntity(Withdrawal::class, is_int($withdrawal) ? $withdrawal : $withdrawal->id);
            }

            if ($entity->status->isFinal()) {
                throw new \LogicException("Cannot reject a withdrawal that is already {$entity->status->value}.");
            }

            if ($unfreezeBalance && $entity->chat !== null && $entity->currency !== null) {
                /** @var WalletRepository $walletRepo */
                $walletRepo = $em->getRepository(WalletBalance::class);
                $walletRepo->defaultCurrency = $this->defaultCurrency;
                $walletRepo->unfreeze(
                    chat: $entity->chat,
                    amount: $entity->amount,
                    currency: $entity->currency,
                );
            }

            $entity->status = WithdrawalStatus::Rejected;
            $entity->processedBy = $processedBy;
            $entity->processedAt = new DateTimeImmutable();
            if ($reason !== null) {
                $entity->adminNote = $reason;
            }

            $em->flush();

            return $entity;
        });
    }

    public function getMeta(Withdrawal|int $withdrawal, string $key, mixed $default = null): mixed
    {
        $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);
        return $entity?->getMeta($key, $default) ?? $default;
    }

    public function setMeta(Withdrawal|int $withdrawal, string $key, mixed $value, ?string $group = null): WithdrawalMeta
    {
        $em = $this->getEntityManager();
        $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);
        if ($entity === null) {
            throw RecordNotFoundException::forEntity(Withdrawal::class, is_int($withdrawal) ? $withdrawal : $withdrawal->id);
        }

        $meta = $entity->setMeta($key, $value, $group);
        $em->persist($meta);
        $em->flush();

        return $meta;
    }

    public function deleteMeta(Withdrawal|int $withdrawal, string $key): bool
    {
        $em = $this->getEntityManager();
        $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);
        if ($entity === null) {
            return false;
        }

        $deleted = $entity->deleteMeta($key);
        if ($deleted) {
            $em->flush();
        }

        return $deleted;
    }

    public function hasMeta(Withdrawal|int $withdrawal, string $key, ?string $group = null): bool
    {
        $entity = $withdrawal instanceof Withdrawal ? $withdrawal : $this->find($withdrawal);
        return $entity?->hasMeta($key, $group) ?? false;
    }

    /**
     * @template T
     * @param callable(\Doctrine\ORM\EntityManagerInterface): T $func
     * @return T
     */
    private function executeTransaction(callable $func): mixed
    {
        $em = $this->getEntityManager();
        $conn = $em->getConnection();

        $alreadyInTransaction = $conn->isTransactionActive();
        if (!$alreadyInTransaction) {
            $conn->beginTransaction();
        }

        try {
            $result = $func($em);
            $em->flush();
            if (!$alreadyInTransaction) {
                $conn->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if (!$alreadyInTransaction && $conn->isTransactionActive()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }
}
