<?php

declare(strict_types=1);

namespace Hug\EuLabel\Service;

use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Prüft, ob ein Flow-Mail-Event die Bestellbestätigung ist — gemeinsam
 * genutzt vom EU-Label- und vom GARAN-Mail-Subscriber.
 */
class OrderConfirmationMailChecker
{
    private const TEMPLATE_TYPE_ORDER_CONFIRMATION = 'order_confirmation_mail';

    /**
     * @param EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository
     */
    public function __construct(
        private readonly EntityRepository $mailTemplateTypeRepository,
    ) {
    }

    public function isOrderConfirmation(FlowSendMailActionEvent $event): bool
    {
        $typeId = $event->getMailTemplate()->getMailTemplateTypeId();

        if ($typeId === null) {
            return false;
        }

        $criteria = new Criteria([$typeId]);
        $criteria->setTitle('hug-eu-label::mail-template-type');

        $type = $this->mailTemplateTypeRepository
            ->search($criteria, $event->getContext())
            ->getEntities()
            ->first();

        return $type !== null && $type->getTechnicalName() === self::TEMPLATE_TYPE_ORDER_CONFIRMATION;
    }
}
