<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Digital Media Solutions, LLC
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-tab-container">
    <div>
        <blockquote>
            <i class="fa fa-info-circle"></i>
            <!--Tab's helper text to explain this tab -->
            <?php echo $view['translator']->trans('mautic.contactsource.campaign_budgets_tab'); ?>

        </blockquote>
    </div>

    <ul class="list-group campaign-event-list">
        <li class="list-group-item bg-auto bg-light-xs">
            <div class="box-layout">
                <div class="col-md-2 va-m">
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.'.$group); ?>
                        </span>
                </div>
                <div class="col-md-6 va-m">
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.description'); ?>
                        </span>
                </div>
                <div class="col-md-1 va-m">
                        <span class="fw-sb text-primary mb-xs" data-toggle="tooltip"
                              data-container="body"
                              data-placement="top"
                              title=""
                              data-original-title="<?php echo $view['translator']->trans(
                                  'mautic.campaign.source.limit.cap_count_tooltip'
                              ); ?>"
                        >
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.cap_count'); ?>
                            <i class="fa fa-question-circle"></i>
                        </span>
                </div>
                <div class="col-md-1 va-m">
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.cap_percent'); ?>
                        </span>
                </div>
                <div class="col-md-2 va-m text-right">
                        <span class="fw-sb text-primary mb-xs"
                              data-toggle="tooltip"
                              data-container="body"
                              data-placement="top"
                              title=""
                              data-original-title="<?php echo $view['translator']->trans(
                                  'mautic.campaign.source.limit.projection_tooltip'
                              ); ?>"
                        >
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.projection'); ?>
                            <i class="fa fa-question-circle"></i>
                        </span>
                </div>
            </div>
        </li>
        <?php if (empty($limits)): ?>
            <!-- NO DATA TO SHOW -->
            <li class="list-group-item bg-auto bg-light-xs">
                <i class="fa fa-frown-o"></i>
                <?php echo $view['translator']->trans('mautic.contactsource.campaign_budgets_no_data'); ?>
            </li>
        <?php endif; ?>

        <?php if (!empty($limits) && is_array($limits)) : ?>

            <!-- start: trigger type event -->

            <?php foreach ($limits as $limitKey => $limitData) : ?>
                <?php foreach ($limitData['limits'] as $limit) : ?>
                    <?php
                    // put placeholders in empty values to prevent log warnings
                    $Limit['percent']          = isset($limit['percent']) && !empty($limit['percent']) ? $limit['percent'] : 0;
                    $Limit['noPercent']        = isset($limit['noPercent']) && !empty($limit['noPercent']) ? $limit['noPercent'] : 0;
                    $Limit['yesPercent']       = isset($limit['yesPercent']) && !empty($limit['yesPercent']) ? $limit['yesPercent'] : 0;
                    $Limit['rule']['duration'] = isset($limit['rule']['duration']) && !empty($limit['rule']['duration']) ? $limit['rule']['duration'] : null;
                    ?>
                    <li class="list-group-item bg-auto bg-light-xs">
                        <?php $yesClass = (90 <= $limit['percent']) ? 'danger' : 'success'; ?>
                        <div class="progress-bar progress-bar-<?php echo $yesClass; ?>"
                             style="width:<?php echo $limit['yesPercent']; ?>%; left: 0;"></div>
                        <div class="progress-bar progress-bar-danger"
                             style="width:<?php echo $limit['noPercent']; ?>%; left: <?php echo $limit['yesPercent']; ?>%"></div>
                        <div class="box-layout">
                            <div class="col-md-2 va-m">
                            <span class="fw-sb text-primary mb-xs">
                                <a href="<?php echo $limitData['link']; ?>">
                                    <?php echo $limitData['name']; ?>
                                </a>
                            </span>
                            </div>
                            <div class="col-md-6 va-m">
                                <?php if (isset($limit['description'])): ?>
                                    <h5 class="fw-sb text-primary mb-xs">
                                        <?php echo $limit['description']; ?>
                                    </h5>
                                <?php endif; ?>
                                <h6 class="text-white dark-sm"><?php echo $limit['name']; ?></h6>
                            </div>
                            <?php if (isset($limit['logCount'])): ?>
                                <div class="col-md-1 va-m">
                                    <span class="label label-warning"><?php echo $limit['logCount']; ?></span>
                                </div>
                                <div class="col-md-1 va-m">
                                <span class="label label-<?php echo $yesClass; ?>"><?php echo round(
                                            $limit['percent']
                                        ).'%'; ?></span>
                                </div>
                            <?php else: // Unlimited so show no numbers?>
                                <div class="col-md-1 va-m">
                                    <span class="label label-info">N/A</span>
                                </div>
                                <div class="col-md-1 va-m">
                                    <span class="label label-info">N/A</span>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-2 va-m text-right">
                                <?php $forecastValue = '';
                                $forecastClass       = 'success'; ?>
                                <?php if ($limit['rule']['duration'] == 'P1D' && $limit['logCount'] > 0): ?>
                                    <?php $pending = floatval(
                                        ($limit['logCount'] / $forecast['elapsedHoursInDaySoFar']) * $forecast['hoursLeftToday']
                                    );
                                    $forecastValue = number_format(
                                            ($pending + $limit['logCount']) / $limit['rule']['quantity'],
                                            2,
                                            '.',
                                            ','
                                        ) * 100;
                                    $forecastClass = $forecastValue >= 90 ? 'danger' : 'success';
                                    $forecastValue = $forecastValue.'%';
                                    ?>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo intval(
                                            $pending + $limit['logCount']
                                        ); ?></span>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo $forecastValue; ?></span>

                                <?php endif; ?>
                                <?php if ($limit['rule']['duration'] == '1M' && $limit['logCount'] > 0): ?>
                                    <?php $pending = floatval(
                                        ($limit['logCount'] / $forecast['currentDayOfMonth']) * $forecast['daysInMonthLeft']
                                    );
                                    $forecastValue = number_format(
                                            ($pending + $limit['logCount']) / $limit['rule']['quantity'],
                                            2,
                                            '.',
                                            ','
                                        ) * 100;
                                    $forecastClass = $forecastValue >= 90 ? 'danger' : 'success';
                                    $forecastValue = $forecastValue.'%';
                                    ?>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo intval(
                                            $pending + $limit['logCount']
                                        ); ?></span>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo $forecastValue; ?></span>

                                <?php endif; ?>

                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <!--/ end: trigger type event -->
        <?php endif; ?>
    </ul>
</div>
<script>
    mQuery(document).ready(function () {
        mQuery('[data-toggle="tooltip"]').tooltip();
    });
</script>
