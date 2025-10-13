<?php
/**
 * SPDX-FileCopyrightText: 2024 Your Name
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** @var \OCP\IL10N $l */
/** @var \OCP\ITheme $theme */

$urlGenerator = \OCP\Server::get(\OCP\IURLGenerator::class);
$dashboardUrl = $urlGenerator->getAbsoluteURL('/');

$logoutUrl = $urlGenerator->linkToRoute('core.login.logout') . '?requesttoken=' . urlencode($_['requesttoken']);
?>

<div class="body-login-container update">
    <div class="icon-big icon-password"></div>
    <h2>
        <?php 
        if (isset($_['title'])) {
            p($_['title']);
        } else {
            p($l->t('Access Unauthorized'));
        }
        ?>
    </h2>
    <p class="hint">
        <?php
        if (isset($_['message'])) {
            p($_['message']);
        } else {
            p($l->t('You are not allowed to access this page.'));
        }
        ?>
    </p>
    <div class="buttons">
        <a class="button" href="<?php p($logoutUrl); ?>">
            <?php p($l->t('Log out')); ?>
        </a>
    </div>
</div>