<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow"/>
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?php echo isset($title) ? $title : ''; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $view['assets']->getUrl('media/images/favicon.ico'); ?>"/>
    <link rel="icon" sizes="192x192" href="<?php echo $view['assets']->getUrl('media/images/favicon.ico'); ?>">
    <link rel="apple-touch-icon" href="<?php echo $view['assets']->getUrl('media/images/apple-touch-icon.png'); ?>"/>
    <link href="<?php echo $view['assets']->getUrl(
        'plugins/MauticContactSourceBundle/Assets/slate/stylesheets/screen.css'
    ); ?>" rel="stylesheet" media="screen"/>
    <link href="<?php echo $view['assets']->getUrl(
        'plugins/MauticContactSourceBundle/Assets/slate/stylesheets/custom.css'
    ); ?>" rel="stylesheet" media="screen"/>
    <link href="<?php echo $view['assets']->getUrl(
        'plugins/MauticContactSourceBundle/Assets/slate/stylesheets/print.css'
    ); ?>" rel="stylesheet" media="print"/>
    <script src="<?php echo $view['assets']->getUrl(
        'plugins/MauticContactSourceBundle/Assets/slate/javascripts/all.js'
    ); ?>"></script>
    <?php // echo $view['assets']->outputSystemStylesheets();?> <?php // echo $view->render('MauticCoreBundle:Default:script.html.php');?>
</head>
<body class="index" data-languages="[&quot;shell&quot;,&quot;php&quot;]">

<!-- SIDEBAR ------------------------------>
<a href="#" id="nav-button"> <span> NAV <img src="<?php echo $view['assets']->getUrl(
            'plugins/MauticContactSourceBundle/Assets/slate/images/navbar.png'
        ); ?>" alt="Navbar"/> </span></a>
<div class="toc-wrapper">
    <img src="<?php echo $view['assets']->getUrl(
        'media/images/mautic_logo_db200.png'
    ); ?>" class="logo" alt="Logo"/>
    <div class="lang-selector">
        <a href="#" data-language-name="shell">shell</a>
        <a href="#" data-language-name="php">php</a>
    </div>
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search"></div>
    <ul class="search-results"></ul>
    <div id="toc" class="toc-list-h1">
        <?php // todo: use translation for these values?>
        <li><a href="#introduction" class="toc-h1 toc-link" data-title="Introduction">Introduction</a></li>
        <li><a href="#authentication" class="toc-h1 toc-link" data-title="Authentication">Authentication</a></li>
        <li><a href="#campaigns" class="toc-h1 toc-link" data-title="Campaigns">Campaigns</a>
            <?php if (!empty($campaign)): ?>
                <ul class="toc-list-h2">
                    <li>
                        <a href="#<?php echo str_replace(
                            ' ',
                            '',
                            $campaign['name']
                        ); ?>" class="toc-h2 toc-link" data-title="<?php echo $campaign['name']; ?>"><?php echo $campaign['name']; ?></a>
                    </li>
                </ul>
            <?php endif; ?>
        </li>
        <li><a href="#contacts" class="toc-h1 toc-link" data-title="Contacts">Contacts</a>
            <ul class="toc-list-h2">
                <li>
                    <a href="#create-contact" class="toc-h2 toc-link" data-title="Create Contact">Crxeate Contact</a>
                </li>
            </ul>
        </li>
        <li><a href="#errors" class="toc-h1 toc-link" data-title="Errors">Errors</a></li>
    </div>
    <ul class="toc-footer">
        <li><a href='<?php echo $global['assistance']; ?>'>Contact us for assistance</a></li>
    </ul>
</div>

<!-- Content Columns ------------------------------>
<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">

        <h1 id='introduction'><?php echo $title; ?></h1>
        <!-- # Introduction ------------------------------>
        <?php if (!empty($global['introduction'])): ?>
            <p><?php echo $global['introduction']; ?></p>
        <?php endif; ?>



        <?php /* <h2><?php echo $source['name']; ?></h2> */ ?>

        <?php if (!empty($source['description'])): ?>
            <p><?php echo $source['description']; ?></p>
        <?php endif; ?>

        <!-- # Authentication ------------------------------>
        <h1 id='authentication'>Authentication</h1>
        <!-- Dark Block ------------------------------------->
        <!-- SHELL EXAMPLE-->
        <pre class="highlight shell tab-shell"><p>Example Shell script "create contacts" call</p>
            <code>
  curl --request POST <span class="se">\</span>
  --url <?php echo $global['domain']; ?>/source/<?php echo $source['id']; ?>/campaign/<?php echo array_keys($campaignList)[0]; ?> <span class="se">\</span>
  --header <span class="s1">'Cache-Control: no-cache'</span> <span class="se">\</span>
  --header <span class="s1">'Content-Type: application/x-www-form-urlencoded'</span> <span class="se">\</span>
  --header <span class="s1">'content-type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW'</span> <span class="se">\</span>
  --header <span class="s1">'token: <?php echo $token; ?>'</span> <span class="se">\</span>
<?php foreach ($commonFields as $fieldName => $fieldValue): ?>
  --form <span class="nv">'<?php echo $fieldName; ?>'</span><span class="o">=</span>'<?php echo $fieldValue; ?>' <span class="se">\</span>
<?php endforeach; ?>
            </code>
        </pre>

        <!-- PHP EXAMPLE -->
        <pre class="highlight php tab-php"><p>Example PHP "create contacts" call</p>
            <code>

<span class="na">&lt;?php</span>
    <span class="cp">//Define Source API Endpoint Url</span>
    <span class="nv">$url</span> <span class="k">=</span> <span class="s1">'<?php echo $global['domain']; ?>http://[EXAMPLE-DOMAIN.COM]/source/<?php echo $source['id']; ?>/campaign/<?php echo array_keys($campaignList)[0]; ?>'</span><span class="na">;</span>

    <span class="cp">//Define Lead Data</span>
    <span class="nv">$fields</span> <span class="k">=</span> <span class="na">array(</span>
    <?php foreach ($commonFields as $fieldName => $fieldValue): ?>
    <span class="s1">'<?php echo $fieldName; ?>'</span><span class="k"> => </span><span class="s1">'<?php echo $fieldValue; ?>'</span>,
    <?php endforeach; ?>
<span class="na">);</span>

    <span class="cp">//Initiate cURL Object</span>
    <span class="nv">$ch</span> <span class="k">=</span> <span class="na">curl_init();</span>
    <span class="cp">//Set Token Header</span>
    <span class="na">curl_setopt(</span><span class="nv">$ch</span><span class="na">, </span><span class="s1">CURLOPT_HTTPHEADER</span><span class="na">,</span> <span class="na">array(</span><span class="s1">'token: 1ca18c631a02c63124fb03ce7e3619eb5d9af54d'</span><span class="na">));</span>
    <span class="cp">//Set Endpoint Url</span>
    <span class="na">curl_setopt(</span><span class="nv">$ch</span><span class="na">, </span><span class="s1">CURLOPT_URL</span><span class="na">,</span> <span class="nv">$url</span><span class="na">);</span>
    <span class="cp">//Set POST fields (Lead Data)</span>
    <span class="na">curl_setopt(<span class="nv">$ch, <span class="s1">CURLOPT_POST</span>, <span class="na">count(</span><span class="nv">$fields</span><span class="na">));</span>
    <span class="na">curl_setopt(<span class="nv">$ch, <span class="s1">CURLOPT_POSTFIELDS</span>, <span class="na">http_build_query(</span><span class="nv">$fields</span><span class="na">));</span>
    <span class="cp">//Initiate cURL Call (Send Lead)</span>
    <span class="nv">$result</span> <span class="k">=</span> <span class="na">curl_exec(</span><span class="nv">$ch<span class="na">);</span>
    <span class="cp">//Close the Connection</span>
    <span class="na">curl_close(</span><span class="nv">$ch</span><span class="na">);</span>

            </code>
        </pre>
        <!-- END EXAMPLE(S) -->

        <!-- Light Block ---------------------------------->
        <p>Pushing Contacts to Engage requires the use of personalized API token. This token was created when the Data Source was added to the Engage portal.</p>
        <p>Contact API calls expect the API token to be included in all API requests to the server, like the following:</p>
        <ul>
            <li>As a Header (Recommended) - <code>token: <?php echo $token; ?></code></li>
            <li>As form-data (Alternate) - <code>Content-Disposition: form-data; name="token" <?php echo $token; ?></code></li>
        </ul>

        <?php if (!empty($token)): ?>
            <aside class="notice">
                Your Token is <strong><code><?php echo $token; ?></code></strong>
            </aside>
        <?php else: ?>
            <aside class="warning"> If you dont know your Token, please contact your sales or operations representative.</aside>
        <?php endif; ?>

        <?php if (!empty($source['id'])): ?>
            <aside class="notice">
                Your SourceID is <strong><code><?php echo $source['id']; ?></code></strong>
            </aside>
        <?php endif; ?>

        <p>To learn how to manage your source configuration via a Private API, please visit our
            <a target="_new" href="https://github.com/TheDMSGroup/engage-documentation/blob/master/API_Documentation.md">developer documentation portal</a>.
        </p>

        <!-- # Campaigns ------------------------------>
        <h1 id='campaigns'>Campaigns</h1>

        <?php if (empty($campaignList)): ?>
            <p>There are no campaigns configured for this Source. You can push contacts into campaigns by
                editing the
                <a href="/s/contactsource/edit/<?php echo $source['id']; ?>">Source Configuration</a> to add campaigns.
            </p>
            <aside class="notice">Managing the Source Configuration requires portal login credentials.</aside>
        <?php else: ?>
            <ul>
                <?php foreach ($campaignList as $campaignId => $campaignName): ?>
                    <li>
                        <a href="<?php echo $global['domain']; ?>/source/<?php echo $source['id']; ?>/campaign/<?php echo $campaignId; ?>"><?php echo $campaignName; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <!-- # <Campaign Name> ------------------------------>
        <?php if (!empty($campaign)): ?>
            <h2 id='<?php echo str_replace(' ', '', $campaign['name']); ?>'><?php echo $campaign['name']; ?></h2>
            <!-- Dark Block ------------------------------------->
            <!-- Light Block ------------------------------------->
            <p><?php echo $campaign['description']; ?></p>
            <h3 id='http-request'>Field List</h3>
            <p>Fields available for <?php echo $campaign['description']; ?></p>
            <table>
                <thead>
                <tr>
                    <th>Field Name</th>
                    <th>Field Description</th>
                    <th>Type</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($campaignFields)) {
                            $campaignFields = $FieldList;
                        } ?>
                <?php foreach ($campaignFields as $campaignFieldName => $details): ?>
                    <tr>
                        <td>
                            <?php echo $campaignFieldName; ?>
                        </td>
                        <td>
                            <?php echo $details['label']; ?>
                        </td>
                        <td>
                            <a href="#<?php echo $details['type']; ?>"><?php echo $details['type']; ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        <?php endif; ?>


        <!-- # Contacts ------------------------------>
        <h1 id='contacts'>Contacts</h1>
        <!-- # Create Contact ------------------------------>
        <h2 id='create-contact'>Create Contact</h2>
        <!-- Dark Block ------------------------------------->

        <!-- Light Block ------------------------------------->
        <p>This endpoint submits a contact for processing.</p>
        <h3 id='http-request'>HTTP Request</h3>
        <p><code>POST <?php echo $global['domain']; ?>/source/{sourceId}/campaign/{campaignId}</code></p>
        <p><code>PUT <?php echo $global['domain']; ?>/source/{sourceId}/campaign/{campaignId}</code></p>
        <h3 id='query-parameters'>Form Data Options</h3>
        <p>The full list of available fields to send as form-data:</p>
        <table id="FieldList" style="height:400px; overflow:hidden;">
            <thead>
            <tr>
                <th>Field Name</th>
                <th>Field Description</th>
                <th>Type</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($FieldList as $fieldName => $details): ?>
                <tr>
                    <td>
                        <?php echo $fieldName; ?>
                    </td>
                    <td>
                        <?php echo $details['label']; ?>
                    </td>
                    <td>
                        <a href="#<?php echo $details['type']; ?>"><?php echo $details['type']; ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Light Block ------------------------------------->
        <h3 id='field-type'>Field Types</h3>
        <p>The full list of available fields types and what they mean:</p>
        <table id="FieldTypes" style="">
            <thead>
            <tr>
                <th width="15%">Type</th>
                <th width="35%">Description</th>
                <th width="50%">Example</th>
            </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><a name="text">Text</a></strong>
                    </td>
                    <td>
                        Small amount of plain text.
                    </td>
                    <td>
                        <i>John Doe</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="textarea">Textarea</a></strong>
                    </td>
                    <td>
                        Larger amount of plain text, spanning multiple lines.
                    </td>
                    <td>
                        <i>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="boolean">Boolean</a></strong>
                    </td>
                    <td>
                        True OR False (0 / 1)
                    </td>
                    <td>
                        <i>True</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="number">Number</a></strong>
                    </td>
                    <td>
                        An Integer or Floating Point
                    </td>
                    <td>
                        <i>42 or 13.75</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="tel">Tel</a></strong>
                    </td>
                    <td>
                        Telephone Number
                    </td>
                    <td>
                        <i>+17272870426</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="date">Date</a></strong>
                    </td>
                    <td>
                        Calendar Date
                    </td>
                    <td>
                        <i>03/15/2010</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="datetime">DateTime</a></strong>
                    </td>
                    <td>
                        Calendar Date and Time of Day
                    </td>
                    <td>
                        <i>03/15/2010 12:15:32</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="region">Region</a></strong>
                    </td>
                    <td>
                        State or Province
                    </td>
                    <td>
                        <i>FL</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="country">Country</a></strong>
                    </td>
                    <td>
                        Internation Country Name
                    </td>
                    <td>
                        <i>United States of America</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="timezone">Timezone</a></strong>
                    </td>
                    <td>
                        International Timezone
                    </td>
                    <td>
                        <i>+5 GMT</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="email">Email</a></strong>
                    </td>
                    <td>
                        Email Address
                    </td>
                    <td>
                        <i>help@dmsengage.com</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="locale">Locale</a></strong>
                    </td>
                    <td>
                        Location
                    </td>
                    <td>
                        <i>Home</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="lookup">Lookup</a></strong>
                    </td>
                    <td>
                        Multiple Choices By Number
                    </td>
                    <td>
                        <i>1</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="select">Select</a></strong>
                    </td>
                    <td>
                        Multiple Choices By Text
                    </td>
                    <td>
                        <i>Example</i>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong><a name="url">Url</a></strong>
                    </td>
                    <td>
                        Internet Address
                    </td>
                    <td>
                        <i>http://google.com</i>
                    </td>
                </tr>
            </tbody>
        </table>

        <p>
            <span style="margin:auto; width:75%; text-align:center; display:block;">
                <a id="toggleFieldList" onClick="toggleFieldListEvent(this)" style="cursor: pointer;"><< show more >></a>
            </span>
        </p>

        <!-- # Errors ------------------------------>
        <h1 id='errors'>Errors</h1>
        <p>The Contact API uses the following error codes:</p>
        <table>
            <thead>
            <tr>
                <th>Error Code</th>
                <th>Meaning</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>400</td>
                <td>Bad Request.</td>
            </tr>
            <tr>
                <td>401</td>
                <td>Unauthorized -- Your API key is wrong.</td>
            </tr>
            <tr>
                <td>403</td>
                <td>Forbidden -- The contact requested is hidden for administrators only.</td>
            </tr>
            <tr>
                <td>404</td>
                <td>Not Found -- The specified contact could not be found.</td>
            </tr>
            <tr>
                <td>405</td>
                <td>Method Not Allowed -- You tried to access a contact with an invalid method.</td>
            </tr>
            <tr>
                <td>406</td>
                <td>Not Acceptable -- You requested a format that isn&#39;t json.</td>
            </tr>
            <tr>
                <td>410</td>
                <td>Gone -- The contact requested has been removed from our servers.</td>
            </tr>
            <tr>
                <td>429</td>
                <td>Too Many Requests -- You&#39;re requesting too many contacts! Slow down!</td>
            </tr>
            <tr>
                <td>500</td>
                <td>Internal Server Error -- We had a problem with our server. Try again later.</td>
            </tr>
            <tr>
                <td>503</td>
                <td>Service Unavailable -- We&#39;re temporarily offline for maintenance. Please try again later.</td>
            </tr>
            </tbody>
        </table>
    </div>
    <div class="dark-box">
        <div class="lang-selector">
            <a href="#" data-language-name="shell">shell</a>
            <a href="#" data-language-name="php">php</a>
        </div>
    </div>
</div>
<script>
    function toggleFieldListEvent (elem) {
        var table = document.getElementById('FieldList');
        var anchor = document.getElementById('toggleFieldList');
        if (table.classList.contains('expanded')) {
            table.classList.remove('expanded');
            table.style.height = '400px';
            anchor.innerText = '<< show more >>';
        }
        else {
            table.classList.add('expanded');
            table.style.height = 'auto';
            anchor.innerText = '<< show less >>';
        }
    };
</script>
</body>
</html>
