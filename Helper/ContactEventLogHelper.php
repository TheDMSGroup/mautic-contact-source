<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticContactSourceBundle\Helper;

use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Lead as Contact;
use Mautic\LeadBundle\Entity\LeadEventLog as ContactEventLog;
use Mautic\LeadBundle\Entity\LeadEventLogRepository as ContactEventLogRepository;
use Mautic\LeadBundle\Model\LeadModel as ContactModel;
use MauticPlugin\MauticContactSourceBundle\Entity\ContactSource;

/**
 * Class ContactEventLogHelper.
 *
 * @todo    - To be used to log where a contact was sourced (imported) when it has been created by a contact source.
 */
class ContactEventLogHelper
{
    /** @var ContactModel */
    protected $contactModel;

    /** @var ContactEventLogRepository */
    protected $ContactEventLogRepo;

    /** @var Translator */
    protected $translator;

    public function __construct(ContactModel $contactModel, Translator $translator)
    {
        $this->contactModel        = $contactModel;
        $this->translator          = $translator;
        $this->ContactEventLogRepo = $contactModel->getEventLogRepository();
    }

    /**
     * Save log about errored line.
     *
     * @param ContactEventLog $eventLog
     * @param string          $errorMessage
     */
    public function logError(ContactEventLog $eventLog, $errorMessage)
    {
        $eventLog->addProperty('error', $this->translator->trans($errorMessage))
            ->setAction('failed');
        $this->ContactEventLogRepo->saveEntity($eventLog);
        $this->logDebug('Line '. 1 .' error: '.'err', []);
    }

    /**
     * Initialize ContactEventLog object and configure it as the import event.
     *
     * @param ContactSource $source
     * @param Contact       $contact
     * @param               $lineNumber
     *
     * @return ContactEventLog
     */
    public function initEventLog(ContactSource $source, Contact $contact, $lineNumber)
    {
        $eventLog = new ContactEventLog();
        $eventLog->setUserId($source->getCreatedBy())
            ->setUserName($source->getCreatedByUser())
            ->setBundle('lead')
            ->setObject('import')
            ->setObjectId($contact->getId())
            ->setProperties(
                [
                    'line' => $lineNumber,
                    'file' => $source->getOriginalFile(),
                ]
            );

        return $eventLog;
    }
}
