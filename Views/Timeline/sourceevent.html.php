<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Mautic\CoreBundle\Helper\InputHelper;

$message = $event['extra']['message'];
$logs = $event['extra']['logs'];
?>

<dl class="dl-horizontal small">
    <dt>Message:</dt>
    <dd><?=$message?></dd>
    <div class="small" style="max-width: 100%;">
        <strong><?php echo $view['translator']->trans('mautic.contactsource.timeline.logs.heading'); ?></strong>
        <br/>
        <textarea class="codeMirror-json"><?php echo $logs; ?></textarea>
    </div>
</dl>

<script defer>
$el = mQuery('div');
            var $textarea = $el.find('textarea.codeMirror-json');
            if ($textarea.length) {
                CodeMirror.fromTextArea($textarea[0], {
                    mode: {
                        name: 'javascript',
                        json: true
                    },
                    theme: 'cc',
                    gutters: [],
                    lineNumbers: false,
                    lineWrapping: true,
                    readOnly: true
                });
            }
            $el.addClass('codemirror-active');
</script>
