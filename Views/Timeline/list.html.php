<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
if (isset($tmpl) && 'index' == $tmpl) {
    $view->extend('MauticContactSourceBundle:Timeline:index.html.php');
}

$toggleDir = 'DESC' == $order[1] ? 'ASC' : 'DESC';
//display filters if we have filter values
$filterDisplay = 'style="display:none;"';
if (
    (isset($transactions['filters']['message']) && !empty($transactions['filters']['message']))
    || (isset($transactions['filters']['type']) && !empty($transactions['filters']['type']))
    || (isset($transactions['filters']['utm_source']) && !empty($transactions['filters']['utm_source']))
    || (isset($transactions['filters']['contact_id']) && !empty($transactions['filters']['contact_id']))
) {
    $filterDisplay = ''; // visible
}

$contactSourceTimelineVars = [
    'id'                => $view->escape($contactSource->getId()),
    'transactionsTotal' => $transactions['total'],
];
$view['assets']->addScriptDeclaration(
    'var contactSource = '.json_encode($contactSourceTimelineVars),
    'tabClose'
);
?>

<!-- filter form -->
<form method="post" id="transactions-filters"
      data-toggle="ajax"
      data-target="#transactions-table"
      data-overlay="true"
      data-overlay-target="#sourceTransactions-builder-overlay"
      data-action="<?php echo $view['router']->path(
          'mautic_contactsource_timeline_action',
          ['contactSourceId' => $contactSource->getId(), 'objectAction' => 'view']
      ); ?>">
    <input type="hidden" name="message" id="transaction_message"
           value="<?php echo isset($transactions['filters']['message']) ? $transactions['filters']['message'] : null; ?>">
    <input type="hidden" name="contact_id" id="transaction_contact_id"
           value="<?php echo isset($transactions['filters']['contact_id']) ? $transactions['filters']['contact_id'] : null; ?>">
    <input type="hidden" name="type" id="transaction_type"
           value="<?php echo isset($transactions['filters']['type']) ? $transactions['filters']['type'] : null; ?>">
    <input type="hidden" name="objectId" id="objectId" value="<?php echo $view->escape($contactSource->getId()); ?>"/>
    <input type="hidden" name="orderby" id="orderby" value="<?php echo $transactions['order'][0]; ?>">
    <input type="hidden" name="orderbydir" id="orderbydir" value="<?php echo $transactions['order'][1]; ?>">
    <input type="hidden" name="dateFrom" id="transactions_dateFrom"
           value="<?php echo isset($transactions['dateFrom']) ? $transactions['dateFrom'] : null; ?>">
    <input type="hidden" name="dateTo" id="transactions_dateTo"
           value="<?php echo isset($transactions['dateTo']) ? $transactions['dateTo'] : null; ?>">
    <input type="hidden" name="campaignId" id="transactions_campaignId"
           value="<?php echo isset($transactions['filters']['campaignId']) ? $transactions['filters']['campaignId'] : null; ?>">
    <input type="hidden" name="page" id="transactions_page" value="<?php echo $transactions['page']; ?>">
</form>

<script>
    // put correct sort icons on timeline table headers
    var sortField = '<?php echo $order[0]; ?>';
    var sortDirection = '<?php echo strtolower($order[1]); ?>';
</script>

<!-- timeline -->
<div class="table-responsive">
    <table class="table table-hover table-bordered contactsource-timeline" id="contactsource-timeline" style="z-index: 2; position: relative;">
        <thead>
        <tr>
            <th class="visible-md visible-lg timeline-icon">
                <a class="btn btn-sm btn-nospin btn-default" data-activate-details="all" data-toggle="tooltip"
                   title="<?php echo $view['translator']->trans(
                       'mautic.contactsource.timeline.toggle_all_details'
                   ); ?>">
                    <span class="fa fa-fw fa-level-down"></span>
                </a>
            </th>
            <th class="visible-md visible-lg timeline-message">
                <a class="timeline-header-sort" data-toggle="tooltip" data-sort="message"
                   data-sort_dir="<?php echo 'message' === $order[0] ? $toggleDir : 'DESC'; ?>"
                   title="<?php echo $view['translator']->trans(
                       'mautic.contactsource.timeline.message'
                   ); ?>">
                    <?php echo $view['translator']->trans(
                        'mautic.contactsource.timeline.message'
                    ); ?>
                    <i class="fa fa-sort<?php echo 'message' === $order[0] ? '-amount-'.strtolower(
                            $order[1]
                        ) : ''; ?>"></i>
                </a>
                <input class="transaction-filter" id="filter-message"
                       name="filter-message" <?php echo $filterDisplay; ?>
                       size="20"
                       placeholder="Message contains..."
                       value="<?php echo isset($transactions['filters']['message']) ? $transactions['filters']['message'] : null; ?>">
            </th>
            <th class="visible-md visible-lg timeline-contact-id">
                <a class="timeline-header-sort" data-toggle="tooltip" data-sort="contact_id"
                   data-sort_dir="<?php echo 'contact_id' === $order[0] ? $toggleDir : 'DESC'; ?>"
                   title="<?php echo $view['translator']->trans(
                       'mautic.contactsource.timeline.contact_id'
                   ); ?>">
                    <?php echo $view['translator']->trans(
                        'mautic.contactsource.timeline.contact_id'
                    ); ?>
                    <i class="fa fa-sort<?php echo 'contact_id' === $order[0] ? '-amount-'.strtolower(
                            $order[1]
                        ) : ''; ?>"></i>
                </a>
                <input class="transaction-filter" id="filter-contact_id"
                       name="filter-contact_id" <?php echo $filterDisplay; ?>
                       size="10"
                       placeholder="Contact ID ="
                       value="<?php echo isset($transactions['filters']['contact_id']) ? $transactions['filters']['contact_id'] : null; ?>">
            </th>
            <th class="visible-md visible-lg timeline-event-type">
                <a class="timeline-header-sort" data-toggle="tooltip" data-sort="type"
                   data-sort_dir="<?php echo 'type' === $order[0] ? $toggleDir : 'DESC'; ?>"
                   title="<?php echo $view['translator']->trans(
                       'mautic.contactsource.timeline.event_type'
                   ); ?>">
                    <?php echo $view['translator']->trans(
                        'mautic.contactsource.timeline.event_type'
                    ); ?>
                    <i class="fa fa-sort<?php echo 'type' === $order[0] ? '-amount-'.strtolower(
                            $order[1]
                        ) : ''; ?>"></i>
                </a>
                <input class="transaction-filter" id="filter-type" name="filter-type" <?php echo $filterDisplay; ?>
                       size="10"
                       placeholder="Type ="
                       value="<?php echo isset($transactions['filters']['type']) ? $transactions['filters']['type'] : null; ?>">
            </th>
            <th class="visible-md visible-lg timeline-timestamp">
                <a class="timeline-header-sort" data-toggle="tooltip" data-sort="date_added"
                   data-sort_dir="<?php echo 'date_added' === $order[0] ? $toggleDir : 'DESC'; ?>"
                   title="<?php echo $view['translator']->trans(
                       'mautic.contactsource.timeline.event_timestamp'
                   ); ?>">
                    <?php echo $view['translator']->trans(
                        'mautic.contactsource.timeline.event_timestamp'
                    ); ?>
                    <i class="fa fa-sort<?php echo 'date_added' === $order[0] ? '-amount-'.strtolower(
                            $order[1]
                        ) : ''; ?>"></i>
                </a>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions['events'] as $counter => $event): ?>
            <?php
            ++$counter; // prevent 0
            $icon       = (isset($event['icon'])) ? $event['icon'] : 'fa-history';
            $eventLabel = (isset($event['eventLabel'])) ? $event['eventLabel'] : $event['eventType'];
            $message    = (isset($event['message'])) ? $event['message'] : null;
            $contact    = (isset($event['contactId'])) ? "<a href=\"/s/contacts/view/{$event['contactId']}\" data-toggle=\"ajax\">{$event['contactId']}</a>" : null;
            if (is_array($eventLabel)):
                $linkType   = empty($eventLabel['isExternal']) ? 'data-toggle="ajax"' : 'target="_new"';
                $eventLabel = isset($eventLabel['href']) ? "<a href=\"{$eventLabel['href']}\" $linkType>{$eventLabel['label']}</a>" : "{$eventLabel['label']}";
            endif;

            $details = '';
            if (isset($event['contentTemplate']) && $view->exists($event['contentTemplate'])):
                $details = trim(
                    $view->render($event['contentTemplate'], ['event' => $event, 'contactSource' => $contactSource])
                );
            endif;

            $rowStripe = (0 === $counter % 2) ? ' timeline-row-highlighted' : '';
            ?>
            <tr class="timeline-row<?php echo $rowStripe; ?><?php if (!empty($event['featured'])) {
                echo ' timeline-featured';
            } ?>">
                <td class="timeline-icon">
                    <a href="javascript:void(0);" data-activate-details="<?php echo $counter; ?>"
                       class="btn btn-sm btn-nospin btn-default<?php if (empty($details)) {
                echo ' disabled';
            } ?>" data-toggle="tooltip" title="<?php echo $view['translator']->trans(
                        'mautic.contactsource.timeline.toggle_details'
                    ); ?>">
                        <span class="fa fa-fw <?php echo $icon; ?>"></span>
                    </a>
                </td>
                <td class="timeline-message"><?php echo $message; ?></td>
                <td class="timeline-contact-id"><?php echo $contact; ?></td>
                <td class="timeline-type"><?php if (isset($event['eventType'])) {
                        echo $event['eventType'];
                    } ?></td>
                <td class="timeline-timestamp"><?php echo $view['date']->toText(
                        $event['timestamp'],
                        'local',
                        'Y-m-d H:i:s',
                        true
                    ); ?></td>
            </tr>
            <?php if (!empty($details)): ?>
                <tr class="timeline-row<?php echo $rowStripe; ?> timeline-details hide"
                    id="timeline-details-<?php echo $counter; ?>">
                    <td colspan="5">
                        <?php echo $details; ?>
                    </td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $view->render(
    'MauticCoreBundle:Helper:pagination.html.php',
    [
        'page'       => $transactions['page'],
        'fixedPages' => $transactions['maxPages'],
        'fixedLimit' => true,
        'baseUrl'    => '/page',
        'target'     => '',
        'totalItems' => $transactions['total'],
    ]
); ?>
<?php $view['assets']->outputScripts('tabClose'); ?>