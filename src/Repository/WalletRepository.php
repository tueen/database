<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\WalletBalance;
use Tueen\Database\Entity\WalletTransaction;
use Tueen\Database\Exception\InsufficientBalanceException;
use Tueen\Database\Exception\RecordNotFoundException;

/**
 * @extends EntityRepository<WalletBalance>
 */
class WalletRepository extends EntityRepository
{
    public string $defaultCurrency = 'XTR';

    public function getBalance(Chat|int $chat, ?string $currency = null): string
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        $wallet = $this->findOneBy(['chat' => $chatId, 'currency' => $currency]);
        return $wallet?->balance ?? '0.000000000000000000';
    }

    public function getAvailableBalance(Chat|int $chat, ?string $currency = null): string
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        $wallet = $this->findOneBy(['chat' => $chatId, 'currency' => $currency]);
        return $wallet !== null ? $wallet->availableBalance : '0.000000000000000000';
    }

    public function getFrozenBalance(Chat|int $chat, ?string $currency = null): string
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        $wallet = $this->findOneBy(['chat' => $chatId, 'currency' => $currency]);
        return $wallet !== null ? $wallet->frozenBalance : '0.000000000000000000';
    }

    public function getWallet(Chat|int $chat, ?string $currency = null): ?WalletBalance
    {
        $chatId = $chat instanceof Chat ? $chat->id : $chat;
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->findOneBy(['chat' => $chatId, 'currency' => $currency]);
    }

    public function credit(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): WalletTransaction {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $wallet = $this->findWalletWithLock($chatEntity, $currency);
            if ($wallet === null) {
                $wallet = new WalletBalance($chatEntity, $currency, '0.000000000000000000');
                $em->persist($wallet);
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = bcadd($balanceBefore, $amount, 18);
            $wallet->balance = $balanceAfter;

            $transaction = new WalletTransaction(
                chat: $chatEntity,
                currency: $currency,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                totalConsumedBefore: $wallet->totalConsumed,
                totalConsumedAfter: $wallet->totalConsumed,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($transaction);
            $em->flush();

            return $transaction;
        });
    }

    public function debit(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): WalletTransaction {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $wallet = $this->findWalletWithLock($chatEntity, $currency);
            $availableBalance = $wallet !== null ? $wallet->availableBalance : '0.000000000000000000';

            if (bccomp($availableBalance, $amount, 18) < 0) {
                throw InsufficientBalanceException::forChat($chatEntity->id, $currency, $availableBalance, $amount);
            }

            if ($wallet === null) {
                $wallet = new WalletBalance($chatEntity, $currency, '0.000000000000000000');
                $em->persist($wallet);
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = bcsub($balanceBefore, $amount, 18);
            $wallet->balance = $balanceAfter;

            $totalConsumedBefore = $wallet->totalConsumed;
            $totalConsumedAfter = bcadd($totalConsumedBefore, $amount, 18);
            $wallet->totalConsumed = $totalConsumedAfter;

            $negativeAmount = '-' . ltrim($amount, '-');

            $transaction = new WalletTransaction(
                chat: $chatEntity,
                currency: $currency,
                amount: $negativeAmount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                totalConsumedBefore: $totalConsumedBefore,
                totalConsumedAfter: $totalConsumedAfter,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($transaction);
            $em->flush();

            return $transaction;
        });
    }

    public function freeze(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
    ): WalletBalance {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $wallet = $this->findWalletWithLock($chatEntity, $currency);
            $available = $wallet !== null ? $wallet->availableBalance : '0.000000000000000000';

            if (bccomp($available, $amount, 18) < 0) {
                throw InsufficientBalanceException::forChat($chatEntity->id, $currency, $available, $amount);
            }

            if ($wallet === null) {
                $wallet = new WalletBalance($chatEntity, $currency, '0.000000000000000000');
                $em->persist($wallet);
            }

            $wallet->frozenBalance = bcadd($wallet->frozenBalance, $amount, 18);
            $em->flush();

            return $wallet;
        });
    }

    public function unfreeze(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
    ): WalletBalance {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $wallet = $this->findWalletWithLock($chatEntity, $currency);
            if ($wallet === null) {
                return new WalletBalance($chatEntity, $currency, '0.000000000000000000');
            }

            $newFrozen = bcsub($wallet->frozenBalance, $amount, 18);
            $wallet->frozenBalance = bccomp($newFrozen, '0', 18) > 0 ? $newFrozen : '0.000000000000000000';
            $em->flush();

            return $wallet;
        });
    }

    public function debitFrozen(
        Chat|int $chat,
        string $amount,
        ?string $currency = null,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): WalletTransaction {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($em, $chat, $amount, $currency, $source, $sourceId, $createdBy) {
            $chatEntity = $chat instanceof Chat ? $chat : $em->find(Chat::class, $chat);
            if ($chatEntity === null) {
                throw RecordNotFoundException::forEntity(Chat::class, is_int($chat) ? $chat : $chat->id);
            }

            $wallet = $this->findWalletWithLock($chatEntity, $currency);
            if ($wallet === null) {
                throw InsufficientBalanceException::forChat($chatEntity->id, $currency, '0.000000000000000000', $amount);
            }

            // Deduct from frozen balance
            $newFrozen = bcsub($wallet->frozenBalance, $amount, 18);
            $wallet->frozenBalance = bccomp($newFrozen, '0', 18) > 0 ? $newFrozen : '0.000000000000000000';

            $balanceBefore = $wallet->balance;
            $balanceAfter = bcsub($balanceBefore, $amount, 18);
            $wallet->balance = $balanceAfter;

            $totalConsumedBefore = $wallet->totalConsumed;
            $totalConsumedAfter = bcadd($totalConsumedBefore, $amount, 18);
            $wallet->totalConsumed = $totalConsumedAfter;

            $negativeAmount = '-' . ltrim($amount, '-');

            $transaction = new WalletTransaction(
                chat: $chatEntity,
                currency: $currency,
                amount: $negativeAmount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                totalConsumedBefore: $totalConsumedBefore,
                totalConsumedAfter: $totalConsumedAfter,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($transaction);
            $em->flush();

            return $transaction;
        });
    }

    /**
     * @return array{debit: WalletTransaction, credit: WalletTransaction}
     */
    public function transfer(
        Chat|int $fromChat,
        Chat|int $toChat,
        string $amount,
        ?string $currency = null,
        ?string $source = 'transfer',
    ): array {
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));

        return $this->executeTransaction(function () use ($fromChat, $toChat, $amount, $currency, $source) {
            $debitTx = $this->debit($fromChat, $amount, $currency, $source);
            $creditTx = $this->credit($toChat, $amount, $currency, $source);

            return [
                'debit' => $debitTx,
                'credit' => $creditTx,
            ];
        });
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

    private function findWalletWithLock(Chat $chat, string $currency): ?WalletBalance
    {
        return $this->getEntityManager()
            ->createQuery('SELECT w FROM ' . WalletBalance::class . ' w WHERE w.chat = :chat AND w.currency = :currency')
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setParameter('chat', $chat)
            ->setParameter('currency', $currency)
            ->getOneOrNullResult();
    }
}
