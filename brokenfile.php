<?php
/*
 You may not change or alter any portion of this comment or credits
 of supporting developers from this source code or any supporting source code
 which is considered copyrighted (c) material of the original comment or credit authors.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
/**
 * Wfdownloads module
 *
 * @copyright       The XOOPS Project http://sourceforge.net/projects/xoops/
 * @license         GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html)
 * @package         wfdownload
 * @since           3.23
 * @author          Xoops Development Team
 */
$currentFile = basename(__FILE__);
include_once __DIR__ . '/header.php';

$lid         = XoopsRequest::getInt('lid', 0);
$downloadObj = $wfdownloads->getHandler('download')->get($lid);
if (empty($downloadObj)) {
    redirect_header('index.php', 3, _CO_WFDOWNLOADS_ERROR_NODOWNLOAD);
}
$cid         = XoopsRequest::getInt('cid', $downloadObj->getVar('cid'));
$categoryObj = $wfdownloads->getHandler('category')->get($cid);
if (empty($categoryObj)) {
    redirect_header('index.php', 3, _CO_WFDOWNLOADS_ERROR_NOCATEGORY);
}

// Download not published, expired or taken offline - redirect
if ($downloadObj->getVar('published') == 0 || $downloadObj->getVar('published') > time() || $downloadObj->getVar('offline') === true || ($downloadObj->getVar('expired') == true && $downloadObj->getVar('expired') < time()) || $downloadObj->getVar('status') == _WFDOWNLOADS_STATUS_WAITING) {
    redirect_header('index.php', 3, _MD_WFDOWNLOADS_NODOWNLOAD);
}

// Check permissions
if ($wfdownloads->getConfig('enable_brokenreports') === false && !WfdownloadsUtilities::userIsAdmin()) {
    redirect_header('index.php', 3, _NOPERM);
}

// Breadcrumb
include_once XOOPS_ROOT_PATH . '/class/tree.php';
$categoryObjsTree = new XoopsObjectTree($wfdownloads->getHandler('category')->getObjects(), 'cid', 'pid');
$breadcrumb       = new WfdownloadsBreadcrumb();
$breadcrumb->addLink($wfdownloads->getModule()->getVar('name'), WFDOWNLOADS_URL);
foreach (array_reverse($categoryObjsTree->getAllParent($cid)) as $parentCategory) {
    $breadcrumb->addLink($parentCategory->getVar('title'), 'viewcat.php?cid=' . $parentCategory->getVar('cid'));
}
$breadcrumb->addLink($categoryObj->getVar('title'), "viewcat.php?cid={$cid}");
$breadcrumb->addLink($downloadObj->getVar('title'), "singlefile.php?lid={$lid}");

$op = XoopsRequest::getString('op', 'report.add');
switch ($op) {
    case 'report.add':
    default:
        // Get report sender 'uid'
        $senderUid = is_object($GLOBALS['xoopsUser']) ? (int)$GLOBALS['xoopsUser']->getVar('uid') : 0;
        $senderIp  = getenv('REMOTE_ADDR');

        if (!empty($_POST['submit'])) {
            // Check if REG user is trying to report twice
            $criteria    = new Criteria('lid', $lid);
            $reportCount = $wfdownloads->getHandler('report')->getCount($criteria);
            if ($reportCount > 0) {
                redirect_header('index.php', 2, _MD_WFDOWNLOADS_ALREADYREPORTED);
            } else {
                $reportObj = $wfdownloads->getHandler('report')->create();
                $reportObj->setVar('lid', $lid);
                $reportObj->setVar('sender', $senderUid);
                $reportObj->setVar('ip', $senderIp);
                $reportObj->setVar('date', time());
                $reportObj->setVar('confirmed', 0);
                $reportObj->setVar('acknowledged', 0);
                if ($wfdownloads->getHandler('report')->insert($reportObj)) {
                    // All is well
                    // Send notification
                    $tags                      = array();
                    $tags['BROKENREPORTS_URL'] = WFDOWNLOADS_URL . '/admin/reportsmodifications.php?op=reports.modifications.list';
                    $notificationHandler->triggerEvent('global', 0, 'file_broken', $tags);

                    // Send email to the owner of the download stating that it is broken
                    $user    = $memberHandler->getUser($downloadObj->getVar('submitter'));
                    $subdate = formatTimestamp($downloadObj->getVar('published'), $wfdownloads->getConfig('dateformat'));
                    $cid     = $downloadObj->getVar('cid');
                    $title   = $downloadObj->getVar('title');
                    $subject = _MD_WFDOWNLOADS_BROKENREPORTED;

                    $xoopsMailer = &getMailer();
                    $xoopsMailer->useMail();
                    $template_dir = WFDOWNLOADS_ROOT_PATH . '/language/' . $GLOBALS['xoopsConfig']['language'] . '/mail_template';

                    $xoopsMailer->setTemplateDir($template_dir);
                    $xoopsMailer->setTemplate('filebroken_notify.tpl');
                    $xoopsMailer->setToEmails($user->email());
                    $xoopsMailer->setFromEmail($GLOBALS['xoopsConfig']['adminmail']);
                    $xoopsMailer->setFromName($GLOBALS['xoopsConfig']['sitename']);
                    $xoopsMailer->assign('X_UNAME', $user->uname());
                    $xoopsMailer->assign('SITENAME', $GLOBALS['xoopsConfig']['sitename']);
                    $xoopsMailer->assign('X_ADMINMAIL', $GLOBALS['xoopsConfig']['adminmail']);
                    $xoopsMailer->assign('X_SITEURL', XOOPS_URL . '/');
                    $xoopsMailer->assign('X_TITLE', $title);
                    $xoopsMailer->assign('X_SUB_DATE', $subdate);
                    $xoopsMailer->assign('X_DOWNLOAD', WFDOWNLOADS_URL . "/singlefile.php?cid={$cid}&lid={$lid}");
                    $xoopsMailer->setSubject($subject);
                    $xoopsMailer->send();
                    redirect_header('index.php', 2, _MD_WFDOWNLOADS_BROKENREPORTED);
                } else {
                    echo $reportObj->getHtmlErrors();
                }
            }
        } else {
            $xoopsOption['template_main'] = "{$wfdownloads->getModule()->dirname()}_brokenfile.tpl";
            include_once XOOPS_ROOT_PATH . '/header.php';

            // Begin Main page Heading etc
            $catarray['imageheader'] = WfdownloadsUtilities::headerImage();
            $xoopsTpl->assign('catarray', $catarray);

            $xoTheme->addScript(XOOPS_URL . '/browse.php?Frameworks/jquery/jquery.js');
            $xoTheme->addScript(WFDOWNLOADS_URL . '/assets/js/magnific/jquery.magnific-popup.min.js');
            $xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/js/magnific/magnific-popup.css');
            $xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/css/module.css');

            $xoopsTpl->assign('wfdownloads_url', WFDOWNLOADS_URL . '/');

            // Breadcrumb
            $breadcrumb->addLink(_MD_WFDOWNLOADS_REPORTBROKEN, '');
            $xoopsTpl->assign('wfdownloads_breadcrumb', $breadcrumb->render());

            // Generate form
            include_once XOOPS_ROOT_PATH . '/class/xoopsformloader.php';
            $sform = new XoopsThemeForm(_MD_WFDOWNLOADS_RATETHISFILE, 'reportform', xoops_getenv('PHP_SELF'));
            $sform->addElement(new XoopsFormHidden('lid', $lid));
            $sform->addElement(new XoopsFormHidden('cid', $cid));
            $sform->addElement(new XoopsFormHidden('uid', $senderUid));
            $button_tray   = new XoopsFormElementTray('', '');
            $submit_button = new XoopsFormButton('', 'submit', _MD_WFDOWNLOADS_SUBMITBROKEN, 'submit');
            $button_tray->addElement($submit_button);
            $cancel_button = new XoopsFormButton('', '', _CANCEL, 'button');
            $cancel_button->setExtra('onclick="history.go(-1)"');
            $button_tray->addElement($cancel_button);
            $sform->addElement($button_tray);
            $xoopsTpl->assign('reportform', $sform->render());
            $xoopsTpl->assign('download', array('lid' => $lid, 'cid' => $cid, 'title' => $downloadObj->getVar('title'), 'description' => $downloadObj->getVar('description')));

            $criteria = new Criteria('lid', $lid);

            $reportObjs = $wfdownloads->getHandler('report')->getObjects($criteria);

            if (count($reportObjs) > 0) {
                $reportObj = $reportObjs[0];

                $broken['title']        = trim($downloadObj->getVar('title'));
                $broken['id']           = $reportObj->getVar('reportid');
                $broken['reporter']     = XoopsUserUtility::getUnameFromId((int)$reportObj->getVar('sender'));
                $broken['date']         = formatTimestamp($reportObj->getVar('published'), $wfdownloads->getConfig('dateformat'));
                $broken['acknowledged'] = ($reportObj->getVar('acknowledged') == 1) ? _YES : _NO;
                $broken['confirmed']    = ($reportObj->getVar('confirmed') == 1) ? _YES : _NO;

                $xoopsTpl->assign('brokenreportexists', true);
                $xoopsTpl->assign('broken', $broken);
                $xoopsTpl->assign('brokenreport', true); // this definition is not removed for backward compatibility issues
            } else {
                // file info
                $down['title']     = trim($downloadObj->getVar('title'));
                $down['homepage']  = $myts->makeClickable(formatURL(trim($downloadObj->getVar('homepage'))));
                $time              = ($downloadObj->getVar('updated') !== false) ? $downloadObj->getVar('updated') : $downloadObj->getVar('published');
                $down['updated']   = formatTimestamp($time, $wfdownloads->getConfig('dateformat'));
                $is_updated        = ($downloadObj->getVar('updated') !== false) ? _MD_WFDOWNLOADS_UPDATEDON : _MD_WFDOWNLOADS_SUBMITDATE;
                $down['publisher'] = XoopsUserUtility::getUnameFromId((int)$downloadObj->getVar('submitter'));

                $xoopsTpl->assign('brokenreportexists', false);
                $xoopsTpl->assign('file_id', $lid);
                $xoopsTpl->assign('lang_subdate', $is_updated);
                $xoopsTpl->assign('is_updated', $downloadObj->getVar('updated'));
                $xoopsTpl->assign('lid', $lid);
                $xoopsTpl->assign('down', $down);
            }
            include_once __DIR__ . '/footer.php';
        }
        break;
}
