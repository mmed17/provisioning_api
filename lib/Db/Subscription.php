<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getOrganizationId()
 * @method void setOrganizationId(int $id)
 * @method int getPlanId()
 * @method void setPlanId(int $id)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string getStartedAt()
 * @method void setStartedAt(string $date)
 * @method string|null getEndedAt()
 * @method void setEndedAt(?string $date)
 */
class Subscription extends Entity implements \JsonSerializable {
    /** @var int The ID of the organization this subscription belongs to. */
    protected $organizationId;

    /** @var int The ID of the plan this subscription is for. */
    protected $planId;

    /** @var string The current status of the subscription. */
    protected $status = 'active';

    /** @var string The start date and time of the subscription. */
    protected $startedAt;

    /** @var string|null The end date and time of the subscription. NULL if active. */
    protected ?string $endedAt = null;

    public function __construct() {
        $this->addType('organization_id', Types::INTEGER);
        $this->addType('plan_id', Types::INTEGER);
        $this->addType('status', Types::STRING);
        $this->addType('started_at', Types::STRING);
        $this->addType('ended_at', Types::STRING, true); // `true` allows it to be nullable
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'organizationId' => $this->organizationId,
            'planId' => $this->planId,
            'status' => $this->status,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
        ];
    }
}