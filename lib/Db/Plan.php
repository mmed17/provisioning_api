<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method int getMaxProjects()
 * @method void setMaxProjects(int $maxProjects)
 * @method int getMaxMembers()
 * @method void setMaxMembers(int $maxMembers)
 * @method int getSharedStoragePerProject()
 * @method void setSharedStoragePerProject(int $storage)
 * @method int getPrivateStoragePerUser()
 * @method void setPrivateStoragePerUser(int $storage)
 * @method float|null getPrice()
 * @method void setPrice(?float $price)
 * @method string|null getCurrency()
 * @method void setCurrency(?string $currency)
 * @method bool getIsPublic()
 */
class Plan extends Entity implements \JsonSerializable {
    /** @var string The public name of the plan. */
    protected $name;

    /** @var int The number of allowed projects (Group Folders). */
    protected $maxProjects;

    /** @var int The total number of users allowed in the organization. */
    protected $maxMembers;
    
    /** @var int The storage limit for each shared project in bytes. */
    protected $sharedStoragePerProject;

    /** @var int The storage limit for each private user in bytes. */
    protected $privateStoragePerUser;

    /** @var bool Whether to show this plan on the public pricing page. */
    protected $isPublic = false;

    /** @var float|null The price of the plan. */
    protected $price = null;

    /** @var string|null The currency code for the plan price. */
    protected $currency = 'EUR';
    
    public function __construct() {
        $this->addType('name', Types::STRING);
        $this->addType('max_projects', Types::INTEGER);
        $this->addType('max_members', Types::INTEGER);
        $this->addType('shared_storage_per_project', Types::INTEGER);
        $this->addType('private_storage_per_user', Types::INTEGER);
        $this->addType('is_public', Types::BOOLEAN);
        $this->addType('price', Types::FLOAT, true);
        $this->addType('currency', Types::STRING, true);
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'maxProjects' => $this->maxProjects,
            'maxMembers' => $this->maxMembers,
            'sharedStoragePerProject' => $this->sharedStoragePerProject,
            'privateStoragePerUser' => $this->privateStoragePerUser,
            'price' => $this->price,
            'isPublic' => $this->isPublic,
            'currency' => $this->currency,
        ];
    }
}