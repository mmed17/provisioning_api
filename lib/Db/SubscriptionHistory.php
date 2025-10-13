<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getSubscriptionId()
 * @method void setSubscriptionId(int $id)
 * @method string getChangedByUserId()
 * @method void setChangedByUserId(string $userId)
 * @method string getPreviousStatus()
 * @method void setPreviousStatus(string $status)
 * @method string getNewStatus()
 * @method void setNewStatus(string $status)
 * @method int|null getPreviousPlanId()
 * @method void setPreviousPlanId(?int $id)
 * @method int getNewPlanId()
 * @method void setNewPlanId(int $id)
 * @method string getPreviousStartedAt()
 * @method void setPreviousStartedAt(string $date)
 * @method string getNewStartedAt()
 * @method void setNewStartedAt(string $date)
 * @method string|null getPreviousEndedAt()
 * @method void setPreviousEndedAt(?string $date)
 * @method string|null getNewEndedAt()
 * @method void setNewEndedAt(?string $date)
 * @method string getChangeTimestamp()
 * @method void setChangeTimestamp(string $timestamp)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 */
class SubscriptionHistory extends Entity implements \JsonSerializable {
    protected int $subscriptionId;
    protected string $changedByUserId;
    protected string $previousStatus;
    protected string $newStatus;
    protected ?int $previousPlanId = null;
    protected int $newPlanId;
    protected string $previousStartedAt;
    protected string $newStartedAt;
    protected ?string $previousEndedAt = null;
    protected ?string $newEndedAt = null;
    protected string $changeTimestamp;
    protected ?string $notes = null;

    public function __construct() {
        $this->addType('subscription_id', Types::INTEGER);
        $this->addType('changed_by_user_id', Types::STRING);
        $this->addType('previous_status', Types::STRING);
        $this->addType('new_status', Types::STRING);
        $this->addType('previous_plan_id', Types::INTEGER, true);
        $this->addType('new_plan_id', Types::INTEGER);
        $this->addType('previous_started_at', Types::STRING);
        $this->addType('new_started_at', Types::STRING);
        $this->addType('previous_ended_at', Types::STRING, true);
        $this->addType('new_ended_at', Types::STRING, true);
        $this->addType('change_timestamp', Types::STRING);
        $this->addType('notes', Types::STRING, true);
    }

    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
