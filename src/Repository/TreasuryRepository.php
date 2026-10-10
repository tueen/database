<?php

declare(strict_types=1);

namespace Tueen\Database\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\Treasury;
use Tueen\Database\Entity\TreasuryTransaction;
use Tueen\Database\Exception\InsufficientBalanceException;

/**
 * @extends EntityRepository<Treasury>
 */
class TreasuryRepository extends EntityRepository
{
    public string $defaultCurrency = 'XTR';

    public function getBalance(?string $currency = null): string
    {
        $currency = strtoupper(trim($currency ?? $this->defaultCurrency));
        $treasury = $this->find($currency);

        return $treasury?->balance ?? '0.000000000000000000';
    }

    public function recordIncome(
        string $currency,
        string $amount,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): TreasuryTransaction {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency));

        return $this->executeTransaction(function () use ($em, $currency, $amount, $source, $sourceId, $createdBy) {
            $treasury = $em->find(Treasury::class, $currency, LockMode::PESSIMISTIC_WRITE);
            if ($treasury === null) {
                $treasury = new Treasury($currency);
                $em->persist($treasury);
            }

            $balanceBefore = $treasury->balance;
            $balanceAfter = bcadd($balanceBefore, $amount, 18);
            $treasury->balance = $balanceAfter;

            $totalCommBefore = $treasury->totalCommission;
            $totalCommAfter = bcadd($totalCommBefore, $amount, 18);
            $treasury->totalCommission = $totalCommAfter;

            $tx = new TreasuryTransaction(
                currency: $currency,
                amount: $amount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                totalCommissionBefore: $totalCommBefore,
                totalCommissionAfter: $totalCommAfter,
                totalConsumedBefore: $treasury->totalConsumed,
                totalConsumedAfter: $treasury->totalConsumed,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($tx);
            $em->flush();

            return $tx;
        });
    }

    public function recordExpense(
        string $currency,
        string $amount,
        ?string $source = null,
        ?int $sourceId = null,
        ?Chat $createdBy = null,
    ): TreasuryTransaction {
        $em = $this->getEntityManager();
        $currency = strtoupper(trim($currency));

        return $this->executeTransaction(function () use ($em, $currency, $amount, $source, $sourceId, $createdBy) {
            $treasury = $em->find(Treasury::class, $currency, LockMode::PESSIMISTIC_WRITE);
            $currentBalance = $treasury?->balance ?? '0.000000000000000000';

            if (bccomp($currentBalance, $amount, 18) < 0) {
                throw InsufficientBalanceException::forTreasury($currency, $currentBalance, $amount);
            }

            if ($treasury === null) {
                $treasury = new Treasury($currency);
                $em->persist($treasury);
            }

            $balanceBefore = $treasury->balance;
            $balanceAfter = bcsub($balanceBefore, $amount, 18);
            $treasury->balance = $balanceAfter;

            $totalConsBefore = $treasury->totalConsumed;
            $totalConsAfter = bcadd($totalConsBefore, $amount, 18);
            $treasury->totalConsumed = $totalConsAfter;

            $negativeAmount = '-' . ltrim($amount, '-');

            $tx = new TreasuryTransaction(
                currency: $currency,
                amount: $negativeAmount,
                balanceBefore: $balanceBefore,
                balanceAfter: $balanceAfter,
                totalCommissionBefore: $treasury->totalCommission,
                totalCommissionAfter: $treasury->totalCommission,
                totalConsumedBefore: $totalConsBefore,
                totalConsumedAfter: $totalConsAfter,
                source: $source,
                sourceId: $sourceId,
                createdBy: $createdBy,
            );

            $em->persist($tx);
            $em->flush();

            return $tx;
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
}
