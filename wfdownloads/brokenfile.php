<?php/* You may not change or alter any portion of this comment or credits of supporting developers from this source code or any supporting source code which is considered copyrighted (c) material of the original comment or credit authors. This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. *//** * Wfdownloads module * * @copyright       The XOOPS Project http://sourceforge.net/projects/xoops/ * @license         GNU GPL 2 or later (http://www.gnu.org/licenses/gpl-2.0.html) * @package         wfdownload * @since           3.23 * @author          Xoops Development Team * @version         svn:$id$ */$currentFile = basename(__FILE__);include 'header.php';$lid      = WfdownloadsRequest::getInt('lid', 0);$download = $wfdownloads->getHandler('download')->get($lid);if (empty($download)) {    redirect_header('index.php', 3, _CO_WFDOWNLOADS_ERROR_NODOWNLOAD);}$cid      = WfdownloadsRequest::getInt('cid', $download->getVar('cid'));$category = $wfdownloads->getHandler('category')->get($cid);if (empty($category)) {    redirect_header('index.php', 3, _CO_WFDOWNLOADS_ERROR_NOCATEGORY);}// Download not published, expired or taken offline - redirectif (    $download->getVar('published') == 0 ||    $download->getVar('published') > time() ||    $download->getVar('offline') == true ||    ($download->getVar('expired') == true && $download->getVar('expired') < time()) ||    $download->getVar('status') == _WFDOWNLOADS_STATUS_WAITING) {    redirect_header('index.php', 3, _MD_WFDOWNLOADS_NODOWNLOAD);}// Check permissionsif ($wfdownloads->getConfig('enable_brokenreports') == false && !wfdownloads_userIsAdmin()) {    redirect_header('index.php', 3, _NOPERM);}// Breadcrumbinclude_once XOOPS_ROOT_PATH . "/class/tree.php";$categoriesTree = new XoopsObjectTree($wfdownloads->getHandler('category')->getObjects(), 'cid', 'pid');$breadcrumb     = new WfdownloadsBreadcrumb();$breadcrumb->addLink($wfdownloads->getModule()->getVar('name'), WFDOWNLOADS_URL);foreach (array_reverse($categoriesTree->getAllParent($cid)) as $parentCategory) {    $breadcrumb->addLink($parentCategory->getVar('title'), "viewcat.php?cid=" . $parentCategory->getVar('cid'));}$breadcrumb->addLink($category->getVar('title'), "viewcat.php?cid={$cid}");$breadcrumb->addLink($download->getVar('title'), "singlefile.php?lid={$lid}");$op = WfdownloadsRequest::getString('op', 'report.add');switch ($op) {    case "report.add" :    default :        // Get report sender 'uid'        $senderUid = is_object($xoopsUser) ? (int) $xoopsUser->getVar('uid') : 0;        $senderIp  = getenv('REMOTE_ADDR');        if (!empty($_POST['submit'])) {            // Check if REG user is trying to report twice            $criteria     = new Criteria('lid', $lid);            $reportsCount = $wfdownloads->getHandler('report')->getCount($criteria);            if ($reportsCount > 0) {                redirect_header('index.php', 2, _MD_WFDOWNLOADS_ALREADYREPORTED);            } else {                $report = $wfdownloads->getHandler('report')->create();                $report->setVar('lid', $lid);                $report->setVar('sender', $senderUid);                $report->setVar('ip', $senderIp);                $report->setVar('date', time());                $report->setVar('confirmed', 0);                $report->setVar('acknowledged', 0);                if ($wfdownloads->getHandler('report')->insert($report)) {                    // All is well                    // Send notification                    $tags                      = array();                    $tags['BROKENREPORTS_URL'] = WFDOWNLOADS_URL . '/admin/reportsmodifications.php?op=reports.modifications.list';                    $notification_handler->triggerEvent('global', 0, 'file_broken', $tags);                    // Send email to the owner of the download stating that it is broken                    $user    = $member_handler->getUser($download->getVar('submitter'));                    $subdate = formatTimestamp($download->getVar('published'), $wfdownloads->getConfig('dateformat'));                    $cid     = $download->getVar('cid');                    $title   = $download->getVar('title');                    $subject = _MD_WFDOWNLOADS_BROKENREPORTED;                    $xoopsMailer = & getMailer();                    $xoopsMailer->useMail();                    $template_dir = WFDOWNLOADS_ROOT_PATH . "/language/" . $xoopsConfig['language'] . "/mail_template";                    $xoopsMailer->setTemplateDir($template_dir);                    $xoopsMailer->setTemplate('filebroken_notify.tpl');                    $xoopsMailer->setToEmails($user->email());                    $xoopsMailer->setFromEmail($xoopsConfig['adminmail']);                    $xoopsMailer->setFromName($xoopsConfig['sitename']);                    $xoopsMailer->assign("X_UNAME", $user->uname());                    $xoopsMailer->assign("SITENAME", $xoopsConfig['sitename']);                    $xoopsMailer->assign("X_ADMINMAIL", $xoopsConfig['adminmail']);                    $xoopsMailer->assign('X_SITEURL', XOOPS_URL . '/');                    $xoopsMailer->assign("X_TITLE", $title);                    $xoopsMailer->assign("X_SUB_DATE", $subdate);                    $xoopsMailer->assign('X_DOWNLOAD', WFDOWNLOADS_URL . "/singlefile.php?cid={$cid}&lid={$lid}");                    $xoopsMailer->setSubject($subject);                    $xoopsMailer->send();                    redirect_header('index.php', 2, _MD_WFDOWNLOADS_BROKENREPORTED);                    exit();                } else {                    echo $report->getHtmlErrors();                }            }        } else {            $xoopsOption['template_main'] = "{$wfdownloads->getModule()->dirname()}_brokenfile.html";            include XOOPS_ROOT_PATH . '/header.php';            // Begin Main page Heading etc            $catarray['imageheader'] = wfdownloads_headerImage();            $xoopsTpl->assign('catarray', $catarray);            $xoTheme->addScript(XOOPS_URL . '/browse.php?Frameworks/jquery/jquery.js');            $xoTheme->addScript(WFDOWNLOADS_URL . '/assets/js/magnific/jquery.magnific-popup.min.js');            $xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/js/magnific/magnific-popup.css');            $xoTheme->addStylesheet(WFDOWNLOADS_URL . '/assets/css/module.css');            $xoopsTpl->assign('wfdownloads_url', WFDOWNLOADS_URL . '/');            // Breadcrumb            $breadcrumb->addLink(_MD_WFDOWNLOADS_REPORTBROKEN, '');            $xoopsTpl->assign('wfdownloads_breadcrumb', $breadcrumb->render());            // Generate form            include XOOPS_ROOT_PATH . '/class/xoopsformloader.php';            $sform = new XoopsThemeForm(_MD_WFDOWNLOADS_RATETHISFILE, 'reportform', xoops_getenv('PHP_SELF'));            $sform->addElement(new XoopsFormHidden('lid', $lid));            $sform->addElement(new XoopsFormHidden('cid', $cid));            $sform->addElement(new XoopsFormHidden('uid', $senderUid));            $button_tray   = new XoopsFormElementTray('', '');            $submit_button = new XoopsFormButton('', 'submit', _MD_WFDOWNLOADS_SUBMITBROKEN, 'submit');            $button_tray->addElement($submit_button);            $cancel_button = new XoopsFormButton('', '', _CANCEL, 'button');            $cancel_button->setExtra('onclick="history.go(-1)"');            $button_tray->addElement($cancel_button);            $sform->addElement($button_tray);            $xoopsTpl->assign('reportform', $sform->render());            $xoopsTpl->assign(                'download',                array('lid' => $lid, 'cid' => $cid, 'title' => $download->getVar('title'), 'description' => $download->getVar('description'))            );            $criteria = new Criteria('lid', $lid);            $reports  = $wfdownloads->getHandler('report')->getObjects($criteria);            //print_r($reports);            if (count($reports) > 0) {                $report = $reports[0];                $broken['title']        = trim($download->getVar('title'));                $broken['id']           = $report->getVar('reportid');                $broken['reporter']     = XoopsUserUtility::getUnameFromId((int) $report->getVar('sender'));                $broken['date']         = formatTimestamp($report->getVar('published'), $wfdownloads->getConfig('dateformat'));                $broken['acknowledged'] = ($report->getVar('acknowledged') == 1) ? _YES : _NO;                $broken['confirmed']    = ($report->getVar('confirmed') == 1) ? _YES : _NO;                $xoopsTpl->assign('brokenreportexists', true);                $xoopsTpl->assign('broken', $broken);                $xoopsTpl->assign('brokenreport', true); // this definition is not removed for backward compatibility issues            } else {                // file info                $down['title']     = trim($download->getVar('title'));                $down['homepage']  = $myts->makeClickable(formatURL(trim($download->getVar('homepage'))));                $time              = ($download->getVar('updated') != false) ? $download->getVar('updated') : $download->getVar('published');                $down['updated']   = formatTimestamp($time, $wfdownloads->getConfig('dateformat'));                $is_updated        = ($download->getVar('updated') != false) ? _MD_WFDOWNLOADS_UPDATEDON : _MD_WFDOWNLOADS_SUBMITDATE;                $down['publisher'] = XoopsUserUtility::getUnameFromId((int) $download->getVar('submitter'));                $xoopsTpl->assign('brokenreportexists', false);                $xoopsTpl->assign('file_id', $lid);                $xoopsTpl->assign('lang_subdate', $is_updated);                $xoopsTpl->assign('is_updated', $download->getVar('updated'));                $xoopsTpl->assign('lid', $lid);                $xoopsTpl->assign('down', $down);            }            include 'footer.php';        }        break;}
