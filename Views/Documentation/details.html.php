<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
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
<body class="index" data-languages="[&quot;shell&quot;,&quot;ruby&quot;,&quot;python&quot;,&quot;javascript&quot;]">

<!-- SIDEBAR ------------------------------>
<a href="#" id="nav-button"> <span> NAV <img src="<?php echo $view['assets']->getUrl(
            'plugins/MauticContactSourceBundle/Assets/slate/images/navbar.png'
        ); ?>" alt="Navbar"/> </span></a>
<div class="toc-wrapper"><img src="<?php echo $view['assets']->getUrl(
        'media/images/mautic_logo_db200.png'
    ); ?>" class="logo" alt="Logo"/>
    <div class="lang-selector"><a href="#" data-language-name="shell">shell</a>
        <a href="#" data-language-name="ruby">ruby</a> <a href="#" data-language-name="python">python</a>
        <a href="#" data-language-name="javascript">javascript</a></div>
    <div class="search"><input type="text" class="search" id="input-search" placeholder="Search"></div>
    <ul class="search-results"></ul>
    <div id="toc" class="toc-list-h1">
        <?php // todo: use translation for these values?>
        <li><a href="#introduction" class="toc-h1 toc-link" data-title="Introduction">Introduction</a></li>
        <li><a href="#authentication" class="toc-h1 toc-link" data-title="Authentication">Authentication</a></li>
        <li><a href="#campaigns" class="toc-h1 toc-link" data-title="Campaigns">Campaigns</a>
            <?php if (!empty($campaign)): ?>
                <ul class="toc-list-h2">
                    <li>
                        <a href="#<?php echo str_replace(' ', '', $campaign['name']); ?>" class="toc-h2 toc-link" data-title="<?php echo $campaign['name']; ?>"><?php echo $campaign['name']; ?></a>
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
        <h1><?php echo $title; ?></h1>
        <!-- # Introduction ------------------------------>
        <h1 id='introduction'>Introduction</h1> <?php if (!empty($global['introduction'])): ?>
            <h4 class="mt-15"><?php echo $global['introduction']; ?></h4> <?php endif; ?>
        <h2><?php echo $source['name']; ?></h2> <?php if (!empty($source['description'])): ?>
            <h4 class="mt-15"><?php echo $source['description']; ?></h4> <?php endif; ?>

        <!-- # Authentication ------------------------------>
        <h1 id='authentication'>Authentication</h1>

        <!-- Dark Block ------------------------------------->
        <blockquote><p>To authorize, use this code:</p></blockquote>
        <pre class="highlight ruby tab-ruby"><code><span class="nb">require</span> <span
                        class="s1">'contact'</span><span class="n">api</span> <span class="o">=</span> <span
                        class="no">Contact</span><span class="o">::</span><span class="no">APIClient</span><span
                        class="p">.</span><span class="nf">authorize!</span><span class="p">(</span><span
                        class="s1">'000000000000000000000000000000'</span><span class="p">)</span></code></pre>
        <pre class="highlight python tab-python"><code><span class="kn">import</span> <span
                        class="nn">contact</span><span class="n">api</span> <span class="o">=</span> <span
                        class="n">contact</span><span class="o">.</span><span class="n">authorize</span><span
                        class="p">(</span><span class="s">'000000000000000000000000000000'</span><span
                        class="p">)</span></code></pre>
        <pre class="highlight shell tab-shell"><code><span
                        class="c"># With shell, you can just pass the correct header with each request</span>curl <span
                        class="s2">"api_endpoint_here"</span> -H <span
                        class="s2">"Authorization: 000000000000000000000000000000"</span></code></pre>
        <pre class="highlight javascript tab-javascript"><code><span class="kr">const</span> <span
                        class="nx">contact</span> <span class="o">=</span> <span class="nx">require</span><span
                        class="p">(</span><span class="s1">'contact'</span><span class="p">);</span><span
                        class="kd">let</span> <span class="nx">api</span> <span class="o">=</span> <span
                        class="nx">contact</span><span class="p">.</span><span class="nx">authorize</span><span
                        class="p">(</span><span class="s1">'000000000000000000000000000000'</span><span
                        class="p">);</span></code></pre>
        <blockquote><p>Make sure to replace <code>000000000000000000000000000000</code> with your API key.</p>
        </blockquote>

        <!-- Light Block ---------------------------------->
        <p>Contact uses API keys to allow access to the API. You can register a new Contact API key at our
            <a href="http://example.com/developers">developer portal</a>.</p>
        <p>Contact expects for the API key to be included in all API requests to the server in a header that looks like the following:</p>
        <p><code>Authorization: 000000000000000000000000000000</code></p>
        <aside class="notice"> You must replace
            <code>000000000000000000000000000000</code> with the API token we have provided for you.
        </aside>

        <!-- # Campaigns ------------------------------>
        <h1 id='campaigns'>Campaigns</h1>
        <ul>
            <?php foreach ($campaignList as $campaignItem): ?>
                <li><a href = "/source/<?php echo $source['id']; ?>/campaign/<?php echo $campaignItem['campaign_id']; ?>"><?php echo $campaignItem['name']; ?></a></li>
            <?php endforeach; ?>
        </ul>

        <!-- # <Campaign Name> ------------------------------>
        <?php if (!empty($campaign)): ?>
            <h2 id='<?php echo str_replace(' ', '', $campaign['name']); ?>'><?php echo $campaign['name']; ?></h2>
        <!-- Dark Block ------------------------------------->
        <!-- Light Block ------------------------------------->
            <p><?php echo $campaign['description']; ?></p>
            <h3 id='http-request'>Field List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Field Name</th>
                        <th>Field Description</th>
                        <th>Required?</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($campaignFields as $fieldName=>$fieldDescription): ?>
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
        <?php endif; ?>


        <!-- # Contacts ------------------------------>
        <h1 id='contacts'>Contacts</h1>
        <!-- # Create Contact ------------------------------>
        <h2 id='create-contact'>Create Contact</h2>
        <!-- Dark Block ------------------------------------->
        <pre class="highlight ruby tab-ruby"><code><span class="nb">require</span> <span
                        class="s1">'contact'</span><span class="n">api</span> <span class="o">=</span> <span
                        class="no">Contact</span><span class="o">::</span><span class="no">APIClient</span><span
                        class="p">.</span><span class="nf">authorize!</span><span class="p">(</span><span
                        class="s1">'000000000000000000000000000000'</span><span class="p">)</span><span
                        class="n">api</span><span class="p">.</span><span class="nf">contacts</span><span
                        class="p">.</span><span class="nf">get</span></code></pre>
        <pre class="highlight python tab-python"><code><span class="kn">import</span> <span
                        class="nn">contact</span><span class="n">api</span> <span class="o">=</span> <span
                        class="n">contact</span><span class="o">.</span><span class="n">authorize</span><span
                        class="p">(</span><span class="s">'000000000000000000000000000000'</span><span
                        class="p">)</span><span class="n">api</span><span class="o">.</span><span
                        class="n">contacts</span><span class="o">.</span><span class="n">get</span><span
                        class="p">()</span></code></pre>
        <pre class="highlight shell tab-shell"><code>curl <span
                        class="s2">"http://example.com/api/contacts"</span> -H <span
                        class="s2">"Authorization: 000000000000000000000000000000"</span></code></pre>
        <pre class="highlight javascript tab-javascript"><code><span class="kr">const</span> <span
                        class="nx">contact</span> <span class="o">=</span> <span class="nx">require</span><span
                        class="p">(</span><span class="s1">'contact'</span><span class="p">);</span><span
                        class="kd">let</span> <span class="nx">api</span> <span class="o">=</span> <span
                        class="nx">contact</span><span class="p">.</span><span class="nx">authorize</span><span
                        class="p">(</span><span class="s1">'000000000000000000000000000000'</span><span
                        class="p">);</span><span class="kd">let</span> <span class="nx">contacts</span> <span
                        class="o">=</span> <span class="nx">api</span><span class="p">.</span><span
                        class="nx">contacts</span><span class="p">.</span><span class="nx">get</span><span
                        class="p">();</span></code></pre>
        <blockquote><p>The above command returns JSON structured like this:</p></blockquote>
        <pre class="highlight json tab-json"><code><span class="p">[</span><span class="w"> </span><span
                        class="p">{</span><span class="w"> </span><span class="s2">"id"</span><span
                        class="p">:</span><span class="w"> </span><span class="mi">1</span><span class="p">,</span><span
                        class="w"> </span><span class="s2">"name"</span><span class="p">:</span><span class="w"> </span><span
                        class="s2">"Fluffums"</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"breed"</span><span class="p">:</span><span class="w"> </span><span
                        class="s2">"calico"</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"fluffiness"</span><span class="p">:</span><span class="w"> </span><span
                        class="mi">6</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"cuteness"</span><span class="p">:</span><span class="w"> </span><span
                        class="mi">7</span><span class="w"> </span><span class="p">},</span><span
                        class="w"> </span><span class="p">{</span><span class="w"> </span><span
                        class="s2">"id"</span><span class="p">:</span><span class="w"> </span><span
                        class="mi">2</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"name"</span><span class="p">:</span><span class="w"> </span><span
                        class="s2">"Max"</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"breed"</span><span class="p">:</span><span class="w"> </span><span
                        class="s2">"unknown"</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"fluffiness"</span><span class="p">:</span><span class="w"> </span><span
                        class="mi">5</span><span class="p">,</span><span class="w"> </span><span
                        class="s2">"cuteness"</span><span class="p">:</span><span class="w"> </span><span
                        class="mi">10</span><span class="w"> </span><span class="p">}</span><span class="w"></span><span
                        class="p">]</span><span class="w"></span></code></pre>

        <!-- Light Block ------------------------------------->
        <p>This endpoint retrieves all contacts.</p>
        <h3 id='http-request'>HTTP Request</h3>
        <p><code>GET http://example.com/api/contacts</code></p>
        <h3 id='query-parameters'>Query Parameters</h3>
        <table>
            <thead>
            <tr>
                <th>Parameter</th>
                <th>Default</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>include_cats</td>
                <td>false</td>
                <td>If set to true, the result will also include cats.</td>
            </tr>
            <tr>
                <td>available</td>
                <td>true</td>
                <td>If set to false, the result will include contacts that have already been adopted.</td>
            </tr>
            </tbody>
        </table>
        <aside class="success"> Remember — a happy contact is an authenticated contact!</aside>

        <!-- # Errors ------------------------------>
        <h1 id='errors'>Errors</h1>
        <aside class="notice">This error section is stored in a separate file in `includes/_errors.md`. Slate allows you to optionally separate out your docs into many files...just save them to the `includes` folder and add them to the top of your `index.md`'s frontmatter. Files are included in the order listed.</aside>
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
                <td>Bad Request -- Your request sucks.</td>
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
                <td>418</td>
                <td>I&#39;m a teapot.</td>
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
        <div class="lang-selector"><a href="#" data-language-name="shell">shell</a>
            <a href="#" data-language-name="ruby">ruby</a> <a href="#" data-language-name="python">python</a>
            <a href="#" data-language-name="javascript">javascript</a></div>
    </div>
</div>
</body>
</html>