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
<body class="index" data-languages="[&quot;shell&quot;,&quot;php&quot;,&quot;javascript&quot;]">

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
        <a href="#" data-language-name="javascript">javascript</a>
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
                    <a href="#create-contact" class="toc-h2 toc-link" data-title="Create Contact">Create Contact</a>
                </li>
            </ul>
        </li>
        <li><a href="#errors" class="toc-h1 toc-link" data-title="Errors">Errors</a></li>
    </div>
    <ul class="toc-footer">
        <li><a href='#'>Contact us for assistance</a></li>
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
        <!-- Light Block ---------------------------------->
        <p>Pushing Contacts to Engage requires the use of personalized API token. This token was created when the Data Source
            was added to the Engage portal.</p>
        <p>To learn how to manage your source configuration via a Private API, please visit our
            <a target="_new" href="https://github.com/TheDMSGroup/engage-documentation/blob/master/API_Documentation.md">developer documentation portal</a>.
        </p>
        <p>Contact API calls expect the API token to be included in all API requests to the server, like the following:</p>
        <ul>
            <li>As a Header (Recommended) - <code>token: 000000000000000000000000000000</code></li>
            <li>As form-data (Alternate) - <code>Content-Disposition: form-data; name="token"

                    000000000000000000000000000000</code></li>
        </ul>

        <aside class="notice"> You must replace
            <code>000000000000000000000000000000</code> with the API token we have provided for you.
        </aside>
        <aside class="warning"> If you dont know your Token, please contact your sales or operations representative.</aside>

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
<!--                    <th>Required?</th>-->
                </tr>
                </thead>
                <tbody>
                <?php if (empty($campaignFields)) {
                            $campaignFields = $FieldList;
                        } ?>
                <?php foreach ($campaignFields as $campaignFieldName => $campaignFieldDescription): ?>
                    <tr>
                        <td>
                            <?php echo $campaignFieldName; ?>
                        </td>
                        <td>
                            <?php echo $campaignFieldDescription; ?>
                        </td>
<!--                        <td></td>-->
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

        <pre class="highlight shell tab-shell"><p>Example Shell script "create contacts" call</p>
            <code>
  curl --request POST <span class="se">\</span>
  --url <?php echo $global['domain']; ?>/source/3/campaign/1 <span class="se">\</span>
  --header <span class="s1">'Cache-Control: no-cache'</span> <span class="se">\</span>
  --header <span class="s1">'Content-Type: application/x-www-form-urlencoded'</span> <span class="se">\</span>
  --header <span class="s1">'content-type: multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW'</span> <span class="se">\</span>
  --header <span class="s1">'token: 000000000000000000000000000000'</span> <span class="se">\</span>
  --form <span class="nv">firstname</span><span class="o">=</span>Greg <span class="se">\</span>
  --form <span class="nv">lastname</span><span class="o">=</span>Scott <span class="se">\</span>
  --form <span class="nv">email</span><span class="o">=</span>gregscott@email.com <span class="se">\</span>

            </code>
        </pre>
        <pre class="highlight php tab-php"><p>Example PHP "create contacts" call</p>
            <code>
      <span class="cp">&lt;?php</span>
  <span class="nv">$request</span> <span class="o">=</span> <span class="k">new</span> <span class="nx">HttpRequest</span><span class="p">();</span>
  <span class="nv">$request</span><span class="o">-&gt;</span><span class="na">setUrl</span><span class="p">(</span><span class="s1">'<?php echo $global['domain']; ?>/source/3/campaign/1'</span><span class="p">);</span>
  <span class="nv">$request</span><span class="o">-&gt;</span><span class="na">setMethod</span><span class="p">(</span><span class="nx">HTTP_METH_POST</span><span class="p">);</span>

  <span class="nv">$request</span><span class="o">-&gt;</span><span class="na">setHeaders</span><span class="p">(</span><span class="k">array</span><span class="p">(</span>
    <span class="s1">'Cache-Control'</span> <span class="o">=&gt;</span> <span class="s1">'no-cache'</span><span class="p">,</span>
    <span class="s1">'Content-Type'</span> <span class="o">=&gt;</span> <span class="s1">'application/x-www-form-urlencoded'</span><span class="p">,</span>
    <span class="s1">'token'</span> <span class="o">=&gt;</span> <span class="s1">'000000000000000000000000000000'</span><span class="p">,</span>
    <span class="s1">'content-type'</span> <span class="o">=&gt;</span> <span class="s1">'multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW'</span>
  <span class="p">));</span>

  <span class="nv">$request</span><span class="o">-&gt;</span><span class="na">setBody</span><span class="p">(</span><span class="s1">'------WebKitFormBoundary7MA4YWxkTrZu0gW
  Content-Disposition: form-data; name="firstname"

  Greg
  ------WebKitFormBoundary7MA4YWxkTrZu0gW
  Content-Disposition: form-data; name="lastname"

  Scott
  ------WebKitFormBoundary7MA4YWxkTrZu0gW
  Content-Disposition: form-data; name="email"

  gergscott@email.com
  ------WebKitFormBoundary7MA4YWxkTrZu0gW--'</span><span class="p">);</span>

  <span class="k">try</span> <span class="p">{</span>
    <span class="nv">$response</span> <span class="o">=</span> <span class="nv">$request</span><span class="o">-&gt;</span><span class="na">send</span><span class="p">();</span>

    <span class="k">echo</span> <span class="nv">$response</span><span class="o">-&gt;</span><span class="na">getBody</span><span class="p">();</span>
  <span class="p">}</span> <span class="k">catch</span> <span class="p">(</span><span class="nx">HttpException</span> <span class="nv">$ex</span><span class="p">)</span> <span class="p">{</span>
    <span class="k">echo</span> <span class="nv">$ex</span><span class="p">;</span>
  <span class="p">}</span>

            </code>
        </pre>
        <pre class="highlight javascript tab-javascript"><p>Example Javascript "create contacts" call</p>
            <code>
        <span class="kd">var</span> <span class="nx">data</span> <span class="o">=</span> <span class="k">new</span> <span class="nx">FormData</span><span class="p">();</span>
        <span class="nx">data</span><span class="p">.</span><span class="nx">append</span><span class="p">(</span><span class="s2">"firstname"</span><span class="p">,</span> <span class="s2">"Greg"</span><span class="p">);</span>
        <span class="nx">data</span><span class="p">.</span><span class="nx">append</span><span class="p">(</span><span class="s2">"lastname"</span><span class="p">,</span> <span class="s2">"Scott"</span><span class="p">);</span>
        <span class="nx">data</span><span class="p">.</span><span class="nx">append</span><span class="p">(</span><span class="s2">"email"</span><span class="p">,</span> <span class="s2">"gregscott@email.com"</span><span class="p">);</span>

        <span class="kd">var</span> <span class="nx">xhr</span> <span class="o">=</span> <span class="k">new</span> <span class="nx">XMLHttpRequest</span><span class="p">();</span>
        <span class="nx">xhr</span><span class="p">.</span><span class="nx">withCredentials</span> <span class="o">=</span> <span class="kc">true</span><span class="p">;</span>

        <span class="nx">xhr</span><span class="p">.</span><span class="nx">addEventListener</span><span class="p">(</span><span class="s2">"readystatechange"</span><span class="p">,</span> <span class="kd">function</span> <span class="p">()</span> <span class="p">{</span>
          <span class="k">if</span> <span class="p">(</span><span class="k">this</span><span class="p">.</span><span class="nx">readyState</span> <span class="o">===</span> <span class="mi">4</span><span class="p">)</span> <span class="p">{</span>
            <span class="nx">console</span><span class="p">.</span><span class="nx">log</span><span class="p">(</span><span class="k">this</span><span class="p">.</span><span class="nx">responseText</span><span class="p">);</span>
          <span class="p">}</span>
        <span class="p">});</span>

        <span class="nx">xhr</span><span class="p">.</span><span class="nx">open</span><span class="p">(</span><span class="s2">"POST"</span><span class="p">,</span> <span class="s2">"<?php echo $global['domain']; ?>/source/3/campaign/1"</span><span class="p">);</span>
        <span class="nx">xhr</span><span class="p">.</span><span class="nx">setRequestHeader</span><span class="p">(</span><span class="s2">"token"</span><span class="p">,</span> <span class="s2">"000000000000000000000000000000"</span><span class="p">);</span>
        <span class="nx">xhr</span><span class="p">.</span><span class="nx">setRequestHeader</span><span class="p">(</span><span class="s2">"Content-Type"</span><span class="p">,</span> <span class="s2">"application/x-www-form-urlencoded"</span><span class="p">);</span>
        <span class="nx">xhr</span><span class="p">.</span><span class="nx">setRequestHeader</span><span class="p">(</span><span class="s2">"Cache-Control"</span><span class="p">,</span> <span class="s2">"no-cache"</span><span class="p">);</span>

        <span class="nx">xhr</span><span class="p">.</span><span class="nx">send</span><span class="p">(</span><span class="nx">data</span><span class="p">);</span>
    </code>
        </pre>


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
                <th>Required?</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($FieldList as $fieldName => $fieldDescription): ?>
                <tr>
                    <td>
                        <?php echo $fieldName; ?>
                    </td>
                    <td>
                        <?php echo $fieldDescription; ?>
                    </td>
                    <td>
                        &nbsp;
                    </td>
                </tr>
            <?php endforeach; ?>
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
            <a href="#" data-language-name="javascript">javascript</a>
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