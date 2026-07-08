<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      TheMarketer
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

declare(strict_types=1);

namespace Mktr\Tracker\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class EncryptSensitiveConfigValues implements DataPatchInterface
{
    private const CONFIG_PATHS = [
        'mktr_tracker/tracker/tracking_key',
        'mktr_tracker/tracker/rest_key',
        'mktr_tracker/tracker/customer_id'
    ];

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        ResourceConnection $resourceConnection,
        EncryptorInterface $encryptor
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->encryptor = $encryptor;
    }

    public function apply()
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('core_config_data');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['config_id', 'value'])
                ->where('path IN (?)', self::CONFIG_PATHS)
                ->where('value IS NOT NULL')
                ->where('value != ?', '')
        );

        foreach ($rows as $row) {
            $value = (string) $row['value'];

            if (preg_match('/^\d+:\d+:/', $value)) {
                continue;
            }

            $connection->update(
                $table,
                ['value' => $this->encryptor->encrypt($value)],
                ['config_id = ?' => (int) $row['config_id']]
            );
        }

        return $this;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
