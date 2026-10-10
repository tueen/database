<?php

declare(strict_types=1);

namespace Tueen\Database;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tueen\Database\Config\DatabaseConfig;
use Tueen\Database\Connection\ConnectionFactory;
use Tueen\Database\Entity\BroadcastMessage;
use Tueen\Database\Entity\CacheItem;
use Tueen\Database\Entity\Chat;
use Tueen\Database\Entity\ChatXp;
use Tueen\Database\Entity\Contact;
use Tueen\Database\Entity\Coupon;
use Tueen\Database\Entity\DataItem;
use Tueen\Database\Entity\EntityItem;
use Tueen\Database\Entity\History;
use Tueen\Database\Entity\MessageRelay;
use Tueen\Database\Entity\Option;
use Tueen\Database\Entity\Order;
use Tueen\Database\Entity\Referral;
use Tueen\Database\Entity\Subscription;
use Tueen\Database\Entity\TmaSession;
use Tueen\Database\Entity\Treasury;
use Tueen\Database\Entity\WalletBalance;
use Tueen\Database\Entity\Withdrawal;
use Tueen\Database\Migration\AutoMigrator;
use Tueen\Database\Migration\MigrationRegistry;
use Tueen\Database\Repository\BroadcastRepository;
use Tueen\Database\Repository\CacheRepository;
use Tueen\Database\Repository\ChatRepository;
use Tueen\Database\Repository\ChatXpRepository;
use Tueen\Database\Repository\ContactRepository;
use Tueen\Database\Repository\CouponRepository;
use Tueen\Database\Repository\DataRepository;
use Tueen\Database\Repository\EntityItemRepository;
use Tueen\Database\Repository\HistoryRepository;
use Tueen\Database\Repository\MessageRelayRepository;
use Tueen\Database\Repository\OptionRepository;
use Tueen\Database\Repository\OrderRepository;
use Tueen\Database\Repository\ReferralRepository;
use Tueen\Database\Repository\SubscriptionRepository;
use Tueen\Database\Repository\TmaSessionRepository;
use Tueen\Database\Repository\TreasuryRepository;
use Tueen\Database\Repository\WalletRepository;
use Tueen\Database\Repository\WithdrawalRepository;

final class DatabaseManager
{
    private EntityManagerInterface $entityManager;
    private AutoMigrator $autoMigrator;
    private MigrationRegistry $migrationRegistry;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;

    public function __construct(
        private DatabaseConfig $config,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
        $this->logger = $logger ?? new NullLogger();
        $this->migrationRegistry = new MigrationRegistry($config->migrationPaths);

        $this->bootstrap();
    }

    /**
     * @param DatabaseConfig|array<string, mixed> $config
     */
    public static function create(DatabaseConfig|array $config = []): self
    {
        $dbConfig = $config instanceof DatabaseConfig ? $config : DatabaseConfig::fromArray($config);
        return new self($dbConfig);
    }

    private function bootstrap(): void
    {
        // 1. Configure Caching
        $cache = match ($this->config->cacheDriver) {
            'redis' => $this->config->cacheDsn !== null
                ? new RedisAdapter(RedisAdapter::createConnection($this->config->cacheDsn))
                : new ArrayAdapter(),
            'filesystem' => new FilesystemAdapter(directory: sys_get_temp_dir() . '/tueen_cache'),
            default => new ArrayAdapter(),
        };

        // 2. Setup Doctrine ORM Metadata Configuration
        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            paths: $this->config->entityPaths,
            isDevMode: $this->config->devMode,
            proxyDir: $this->config->proxyDir,
            cache: $cache,
        );

        $ormConfig->enableNativeLazyObjects(true);

        // 3. Create Connection
        $connection = ConnectionFactory::createConnection($this->config->connection, $ormConfig);

        // 4. Create EntityManager
        $this->entityManager = new EntityManager($connection, $ormConfig);

        // 5. Initialize AutoMigrator
        $this->autoMigrator = new AutoMigrator(
            entityManager: $this->entityManager,
            registry: $this->migrationRegistry,
            eventDispatcher: $this->eventDispatcher,
            logger: $this->logger,
        );
    }

    /**
     * Ensures lazy auto-migration runs if enabled.
     */
    private function ensureMigrated(): void
    {
        if ($this->config->autoMigrate) {
            $this->autoMigrator->autoMigrate();
        }
    }

    public function getEntityManager(): EntityManagerInterface
    {
        $this->ensureMigrated();
        $this->ensureOpen();
        return $this->entityManager;
    }

    private function ensureOpen(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = new EntityManager(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration(),
            );
        }
    }

    public function getConnection(): Connection
    {
        $this->ensureMigrated();
        /** @var Connection */
        return $this->entityManager->getConnection();
    }

    public function getAutoMigrator(): AutoMigrator
    {
        $this->ensureMigrated();
        return $this->autoMigrator;
    }

    public function getMigrationRegistry(): MigrationRegistry
    {
        return $this->migrationRegistry;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getConfig(): DatabaseConfig
    {
        return $this->config;
    }

    /**
     * Registers an additional entity directory (for custom plugins or bot modules).
     */
    public function registerEntityPath(string $path): self
    {
        $this->config = $this->config->withAdditionalEntityPath($path);
        $this->bootstrap();
        return $this;
    }

    /**
     * Registers an additional migration path (for custom plugins or bot modules).
     */
    public function registerMigrationPath(string $namespace, string $path): self
    {
        $this->config = $this->config->withAdditionalMigrationPath($namespace, $path);
        $this->migrationRegistry->register($namespace, $path);
        return $this;
    }

    /**
     * Triggers immediate schema synchronization.
     *
     * @return list<string> Executed SQL statements
     */
    public function syncSchema(bool $safe = true): array
    {
        return $this->autoMigrator->getSynchronizer()->sync($safe);
    }

    // Domain Repositories Accessors

    public function chats(): ChatRepository
    {
        $this->ensureMigrated();
        /** @var ChatRepository */
        return $this->entityManager->getRepository(Chat::class);
    }

    public function getDefaultCurrency(): string
    {
        return $this->config->defaultCurrency;
    }

    public function wallets(): WalletRepository
    {
        $this->ensureMigrated();
        /** @var WalletRepository $repo */
        $repo = $this->entityManager->getRepository(WalletBalance::class);
        $repo->defaultCurrency = $this->config->defaultCurrency;
        return $repo;
    }

    public function options(): OptionRepository
    {
        $this->ensureMigrated();
        /** @var OptionRepository */
        return $this->entityManager->getRepository(Option::class);
    }

    public function orders(): OrderRepository
    {
        $this->ensureMigrated();
        /** @var OrderRepository $repo */
        $repo = $this->entityManager->getRepository(Order::class);
        $repo->defaultCurrency = $this->config->defaultCurrency;
        return $repo;
    }

    public function coupons(): CouponRepository
    {
        $this->ensureMigrated();
        /** @var CouponRepository */
        return $this->entityManager->getRepository(Coupon::class);
    }

    public function subscriptions(): SubscriptionRepository
    {
        $this->ensureMigrated();
        /** @var SubscriptionRepository */
        return $this->entityManager->getRepository(Subscription::class);
    }

    public function broadcasts(): BroadcastRepository
    {
        $this->ensureMigrated();
        /** @var BroadcastRepository */
        return $this->entityManager->getRepository(BroadcastMessage::class);
    }

    public function referrals(): ReferralRepository
    {
        $this->ensureMigrated();
        /** @var ReferralRepository */
        return $this->entityManager->getRepository(Referral::class);
    }

    public function histories(): HistoryRepository
    {
        $this->ensureMigrated();
        /** @var HistoryRepository */
        return $this->entityManager->getRepository(History::class);
    }

    public function withdrawals(): WithdrawalRepository
    {
        $this->ensureMigrated();
        /** @var WithdrawalRepository $repo */
        $repo = $this->entityManager->getRepository(Withdrawal::class);
        $repo->defaultCurrency = $this->config->defaultCurrency;
        return $repo;
    }

    public function treasury(): TreasuryRepository
    {
        $this->ensureMigrated();
        /** @var TreasuryRepository $repo */
        $repo = $this->entityManager->getRepository(Treasury::class);
        $repo->defaultCurrency = $this->config->defaultCurrency;
        return $repo;
    }

    public function treasuries(): TreasuryRepository
    {
        return $this->treasury();
    }

    public function cache(): CacheRepository
    {
        $this->ensureMigrated();
        /** @var CacheRepository */
        return $this->entityManager->getRepository(CacheItem::class);
    }

    public function tmaSessions(): TmaSessionRepository
    {
        $this->ensureMigrated();
        /** @var TmaSessionRepository */
        return $this->entityManager->getRepository(TmaSession::class);
    }

    public function sessions(): TmaSessionRepository
    {
        return $this->tmaSessions();
    }

    public function contacts(): ContactRepository
    {
        $this->ensureMigrated();
        /** @var ContactRepository */
        return $this->entityManager->getRepository(Contact::class);
    }

    public function chatXp(): ChatXpRepository
    {
        $this->ensureMigrated();
        /** @var ChatXpRepository */
        return $this->entityManager->getRepository(ChatXp::class);
    }

    public function entities(): EntityItemRepository
    {
        $this->ensureMigrated();
        /** @var EntityItemRepository */
        return $this->entityManager->getRepository(EntityItem::class);
    }

    public function messageRelays(): MessageRelayRepository
    {
        $this->ensureMigrated();
        /** @var MessageRelayRepository */
        return $this->entityManager->getRepository(MessageRelay::class);
    }

    public function data(): DataRepository
    {
        $this->ensureMigrated();
        /** @var DataRepository */
        return $this->entityManager->getRepository(DataItem::class);
    }
}
