<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
?>
<div class="tab-pane fade in bdr-w-0 page-list" id="budgets-tab-container">
    <?php if (!empty($limits) && is_array($limits)) : ?>

    <!-- start: trigger type event -->
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
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.cap_count'); ?>
                        </span>
                    </div>
                    <div class="col-md-1 va-m">
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.cap_percent'); ?>
                        </span>
                    </div>
                    <div class="col-md-2 va-m text-right">
                        <span class="fw-sb text-primary mb-xs">
                            <?php echo $view['translator']->trans('mautic.campaign.source.limit.projection'); ?>
                        </span>
                    </div>
                </div>
            </li>
            <?php foreach ($limits as $limitKey => $limitData) : ?>
            <?php foreach ($limitData['limits'] as $limit) : ?>
                <li class="list-group-item bg-auto bg-light-xs">
                    <?php $yesClass = (90 <= $limit['percent']) ? 'danger' : 'success'; ?>
                    <div class="progress-bar progress-bar-<?php echo $yesClass; ?>" style="width:<?php echo $limit['yesPercent']; ?>%; left: 0;"></div>
                    <div class="progress-bar progress-bar-danger" style="width:<?php echo $limit['noPercent']; ?>%; left: <?php echo $limit['yesPercent']; ?>%"></div>
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
                        <div class="col-md-1 va-m">
                            <span class="label label-warning"><?php echo $limit['logCount']; ?></span>
                        </div>
                        <div class="col-md-1 va-m">
                            <span class="label label-<?php echo $yesClass; ?>"><?php echo round($limit['percent']).'%'; ?></span>
                        </div>
                        <div class="col-md-2 va-m text-right">
                            <?php $forecastValue = ''; $forecastClass = 'success'; ?>
                            <?php if ($limit['rule']['duration'] == 'P1D' && $limit['logCount'] > 0): ?>
                                <?php $pending       = floatval(($limit['logCount'] / $forecast['elapsedHoursInDaySoFar']) * $forecast['hoursLeftToday']);
                                      $forecastValue = number_format(($pending + $limit['logCount']) / $limit['rule']['quantity'], 2, '.', ',') * 100;
                                      $forecastClass = $forecastValue >= 90 ? 'danger' : 'success';
                                      $forecastValue = $forecastValue.'%';
                                ?>
                                      <span class="label label-<?php echo $forecastClass; ?>"><?php echo intval($pending + $limit['logCount']); ?></span>
                                      <span class="label label-<?php echo $forecastClass; ?>"><?php echo $forecastValue; ?></span>

                            <?php endif; ?>
                            <?php if ($limit['rule']['duration'] == '1M' && $limit['logCount'] > 0): ?>
                                <?php $pending     = floatval(($limit['logCount'] / $forecast['currentDayOfMonth']) * $forecast['daysInMonthLeft']);
                                    $forecastValue = number_format(($pending + $limit['logCount']) / $limit['rule']['quantity'], 2, '.', ',') * 100;
                                    $forecastClass = $forecastValue >= 90 ? 'danger' : 'success';
                                    $forecastValue = $forecastValue.'%';
                                ?>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo intval($pending + $limit['logCount']); ?></span>
                                    <span class="label label-<?php echo $forecastClass; ?>"><?php echo $forecastValue; ?></span>

                            <?php endif; ?>

                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    <!--/ end: trigger type event -->
    <?php endif; ?>
</div>
