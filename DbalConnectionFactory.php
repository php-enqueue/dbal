<?php

declare(strict_types=1);

namespace Enqueue\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Enqueue\Dsn\Dsn;
use Interop\Queue\ConnectionFactory;
use Interop\Queue\Context;

class DbalConnectionFactory implements ConnectionFactory
{
    /**
     * @var array
     */
    private $config;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * The config could be an array, string DSN or null. In case of null it will attempt to connect to mysql localhost with default credentials.
     *
     * $config = [
     *   'connection' => []             - dbal connection options. see http://docs.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html
     *   'table_name' => 'enqueue',     - database table name.
     *   'polling_interval' => '1000',  - How often query for new messages (milliseconds)
     *   'lazy' => true,                - Use lazy database connection (boolean)
     * ]
     *
     * or
     *
     * mysql://user:pass@localhost:3606/db?charset=UTF-8
     *
     * @param array|string|null $config
     */
    public function __construct($config = 'mysql:')
    {
        if (empty($config)) {
            $config = $this->parseDsn('mysql:');
        } elseif (is_string($config)) {
            $config = $this->parseDsn($config);
        } elseif (is_array($config)) {
            if (array_key_exists('dsn', $config)) {
                $config = array_replace_recursive($config, $this->parseDsn($config['dsn'], $config));
                unset($config['dsn']);
            }
        } else {
            throw new \LogicException('The config must be either an array of options, a DSN string or null');
        }

        $this->config = array_replace_recursive([
            'connection' => [],
            'table_name' => 'enqueue',
            'polling_interval' => 1000,
            'lazy' => true,
        ], $config);
    }

    /**
     * @return DbalContext
     */
    public function createContext(): Context
    {
        if ($this->config['lazy']) {
            return new DbalContext(function () {
                return $this->establishConnection();
            }, $this->config);
        }

        return new DbalContext($this->establishConnection(), $this->config);
    }

    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    private function establishConnection(): Connection
    {
        if (false == $this->connection) {
            $this->connection = DriverManager::getConnection($this->config['connection']);
            if (
                method_exists($this->connection, 'connect')
                && (new \ReflectionMethod($this->connection, 'connect'))->isPublic()
            ) {
                // TODO: remove check when dropping support for DBAL < 4
                // DBAL < 4
                $this->connection->connect();
            } else {
                // DBAL >= 4
                $this->connection->getServerVersion(); // calls connect() internally
            }
        }

        return $this->connection;
    }

    private function parseDsn(string $dsn, ?array $config = null): array
    {
        $parsedDsn = Dsn::parseFirst($dsn);

        // enqueue scheme => dbal scheme
        // out of the box dbal 4 schemes: pdo_mysql, pdo_sqlite, pdo_pgsql, pdo_oci, oci8, ibm_db2, pdo_sqlsrv, mysqli, pgsql, sqlsrv, sqlite3
        // in case of multiple drivers like mysqli vs pdo_mysql the pdo variant is preferred
        $supported = [
            'db2' => 'ibm_db2',
            'ibm-db2' => 'ibm_db2',
            'mssql' => 'pdo_sqlsrv',
            'sqlsrv+pdo' => 'pdo_sqlsrv',
            'mysql' => 'pdo_mysql',
            'mysql2' => 'mysqli',
            'mysql+pdo' => 'pdo_mysql',
            'pgsql' => 'pgsql',
            'postgres' => 'pdo_pgsql',
            'pgsql+pdo' => 'pdo_pgsql',
            'sqlite' => 'sqlite3',
            'sqlite3' => 'sqlite3',
            'sqlite+pdo' => 'pdo_sqlite',
        ];

        if ($parsedDsn && false == isset($supported[$parsedDsn->getScheme()])) {
            throw new \LogicException(sprintf('The given DSN schema "%s" is not supported. There are supported schemes: "%s".', $parsedDsn->getScheme(), implode('", "', array_keys($supported))));
        }

        $doctrineScheme = $supported[$parsedDsn->getScheme()];
        $dsnHasProtocolOnly = $parsedDsn->getScheme().':' === $dsn;
        if ($dsnHasProtocolOnly && is_array($config) && array_key_exists('connection', $config)) {
            $default = [
                'driver' => $doctrineScheme,
                'host' => 'localhost',
                'port' => '3306',
                'user' => 'root',
                'password' => '',
            ];

            return [
                'lazy' => true,
                'connection' => array_replace_recursive($default, $config['connection']),
            ];
        }

        if (class_exists(DsnParser::class)) { // DBAL >= 4
            if ($dsnHasProtocolOnly) {
                $dsn = $parsedDsn->getScheme().'://root@localhost';
            }

            $dsnParser = new DsnParser($supported);

            // replace scheme with the matching one from the supported list, but the prefixes there are "pdo-" not "pdo_"
            $dsn = preg_replace(
                '/^'.preg_quote($parsedDsn->getScheme(), '/').'(:|:\/\/)/',
                str_replace('_', '-', $supported[$parsedDsn->getScheme()]).'$1',
                $dsn,
            );

            return [
                'lazy' => true,
                'connection' => $dsnParser->parse($dsn),
            ];
        }

        $dsn = $dsnHasProtocolOnly ?
            $doctrineScheme.'://root@localhost' :
            str_replace($parsedDsn->getScheme(), $doctrineScheme, $dsn);

        return [
            'lazy' => true,
            'connection' => ['url' => $dsn],
        ];
    }
}
