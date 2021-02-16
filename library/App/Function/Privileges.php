<?php

class App_Function_Privileges {
    public static function getLoggedUser()
    {
        if (Zend_Auth::getInstance()->hasIdentity())
            return Zend_Auth::getInstance()->getIdentity();
        return false;
    }

    public static function isProgrammer()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'programmer';
    }

    public static function isAdmin()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'admin' || self::getLoggedUser()->role === 'programmer';
    }

    public static function isUser()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'user';
    }

    public static function isCRPAdmin()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'crp_admin';
    }

    public static function isCenterAdmin()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'center_admin';
    }

    public static function isChildOfAdmin()
    {
        if (self::getLoggedUser()) {
            $_role = self::getLoggedUser()->role;
            $_roleMapper = new Model_Mapper_Role();
            try {
                $roleEntity = $_roleMapper->fetchOne(array('role_name' => $_role));
                if ($roleEntity->parent == 'admin')
                    return true;
            } catch (Exception $e) {
            }
        }
        return false;
    }

    public static function isGuest()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'guest';
    }

    public static function isInstitutionalAdmin()
    {
        if (!self::getLoggedUser())
            return false;
        return self::getLoggedUser()->role === 'institutional_admin';
    }

    /**
     * @param $_groupId integer|array
     * @return bool, if the logged user is a member of a group
     */
    public static function isMemberOf($_groupId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (empty($_groupId))
            return false;

        $_userGroupsMapper = New Model_Mapper_UserGroup();
        try {
            $_userGroupsMapper->fetchOne(array('group_id' => $_groupId, 'user_id' => $_logged_user->user_id));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     */
    public static function CanManageDatabase()
    {
        return false;
    }

    /**
     * @return bool
     */
    public static function CanDownloadDatabase()
    {
        if (self::isProgrammer())
            true;
        return self::isMemberOf(27);
    }

    // decide who can use direct download link as described in the issue #2326
    public static function canDownloadReport($reportFileEntity){
        // if no uploader specified

        //****MEL Admin
        //****uploader
        //authors
        //center coordinator or center admin when uploader from their organization
        //****workflow user
        //****workflow assigned user
        //****data curator when under a workflow of his center
        //CRP admin when under his CRP or report mapped to his CRP
        //CRP manager when under his CRP or report mapped to his CRP

        if($reportFileEntity->user_id == null || !self::getLoggedUser()){
            return false;
        } else {
            $_user = self::getLoggedUser();

            if (self::isAdmin() || $reportFileEntity->user_id == $_user->user_id)
                return true;

            if (isset($reportFileEntity->creator_user_id) && $reportFileEntity->creator_user_id != null && $reportFileEntity->creator_user_id == $_user->user_id)
                return true;
            else{
                $creator_ids = $reportFileEntity->creator_id_json;
                try{
                    $creator_ids = Zend_Json::decode($creator_ids);
                    if(is_array($creator_ids) && in_array($_user->user_id, $creator_ids))
                        return true;
                }catch (Exception $e){}
            }

            if($reportFileEntity->contributor_id_json != null){
                $contributors = json_decode($reportFileEntity->contributor_id_json);
                if(is_array($contributors) && in_array($_user->user_id, $contributors))
                    return true;
            }

            // if center coordinator or center admin
            $userMapper = new Model_Mapper_UserProfile();
            if (isset($reportFileEntity->uploader_organization_id) && $reportFileEntity->uploader_organization_id!= null) {
                $uploader_organization = $reportFileEntity->uploader_organization_id;
            } else {
                try {
                    $uploader_organization = $userMapper->fetchOne(array('profile_id' => $reportFileEntity->user_id))->partner_id;
                } catch (Exception $ex) {
                    $uploader_organization = null;
                }
            }

            $_userGroups = $_user->groups;
            $_userGroupsIds = array();
            foreach ($_userGroups as $_userGroup)
                $_userGroupsIds[] = $_userGroup->group_id;

            if($uploader_organization != null && (in_array(1, $_userGroupsIds) || self::isCenterAdmin()) && $_user->organization_id == $uploader_organization)
                return true;

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from('tbl_report_file', 'tbl_report_file.report_file_id')
                ->join('tbl_report_file_contributors', 'tbl_report_file_contributors.report_file_id = tbl_report_file.report_file_id', '')
                ->join('view_report_files_workflow_total', 'view_report_files_workflow_total.report_file_id = tbl_report_file.report_file_id', '')
                ->join('tbl_workflow_type', 'tbl_workflow_type.report_type = tbl_report_file.report_type_id', '')
                ->joinLeft('tbl_report_file_workflow', 'tbl_report_file_workflow.report_file_id = tbl_report_file.report_file_id', '')
                ->join('tbl_workflow', '((tbl_workflow.partner_id = tbl_report_file_contributors.partner_id AND view_report_files_workflow_total.total = 1) OR tbl_workflow.workflow_id = tbl_report_file_workflow.workflow_id) AND tbl_workflow_type.workflow_id = tbl_workflow.workflow_id', '')
                ->join('tbl_workflow_steps', 'tbl_workflow_steps.workflow_id = tbl_workflow.workflow_id', '')
                ->join('tbl_workflow_step_users', 'tbl_workflow_step_users.step_id = tbl_workflow_steps.step_id', '')
                ->joinLeft('tbl_workflow_user_assigned', 'tbl_workflow_user_assigned.step_id = tbl_workflow_steps.step_id AND tbl_report_file.report_file_id = tbl_workflow_user_assigned.item_id', '')
                ->where('tbl_report_file.report_file_id = ' . $reportFileEntity->report_file_id);
            $_where = 'tbl_workflow_step_users.user_id = ' . $_user->user_id . ' OR tbl_workflow_user_assigned.assigned_user_id = ' . $_user->user_id;
            if (in_array(25, $_userGroupsIds))
                $_where .= ' OR tbl_workflow.partner_id = ' . $_user->organization_id;
            $select->where($_where);
            $workflowApprover = $db->fetchCol($select);
            if (!empty($workflowApprover))
                return true;

            // if crp admin and one of (the reporting crps or mapped crps) is the user organization
            if (self::isCRPAdmin()) {
                try {
                    $crps = Zend_Json::decode($_user->crp_admin);
                    if (!is_array($crps))
                        $crps = array();
                } catch (Exception $e) {
                    $crps = array();
                }
                $crps = array_filter($crps);
                if (!empty($crps)) {
                    $_reportCrpsMapper = new Model_Mapper_ReportFilePublicationReportingCrp();
                    $crps [] = -15;
                    try {
                        $_reportCrpsMapper->fetchOne(array('result_id' => $reportFileEntity->report_file_id, 'crp_id' => $crps));
                        return true;
                    } catch (Exception $e) {
                    }

                    if ($reportFileEntity->deliverable_id != null || $reportFileEntity->training_id != null) {
                        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                        $query = $db->select()
                            ->from('tbl_report_file', 'count(tbl_actionsite.partner_id) AS total')
                            ->where('tbl_actionsite.partner_id IN (?)', $crps)
                            ->where('tbl_report_file.report_file_id = (?)', $reportFileEntity->report_file_id);

                        if ($reportFileEntity->deliverable_id != null) {
                            $query->join('tbl_flagship_activity_result_deliverable', 'tbl_flagship_activity_result_deliverable.deliverable_id = tbl_report_file.deliverable_id', '')
                                ->join('tbl_flagship_activity_result', 'tbl_flagship_activity_result.flagship_activity_id = tbl_flagship_activity_result_deliverable.result_id', '')
                                ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_result.flagship_activity_id', '');
                        } elseif ($reportFileEntity->training_id != null) {
                            $query->join('tbl_flagship_activity_training', 'tbl_flagship_activity_training.training_id = tbl_report_file.training_id', '')
                                ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_training.flagship_activity_id', '');
                        }
                        $query->join('tbl_actionsite', 'tbl_actionsite.actionsite_id = tbl_flagship_activity.actionsite_id', '');
                        if ($db->fetchCol($query)[0] > 0)
                            return true;
                    }
                }
            }
            // if crp manager and one of (the reporting crps or mapped crps) is the user organization
            if (in_array(2, $_userGroupsIds)) {
                $crps = $_user->crp_manager_crps;
                if (!empty($crps)) {
                    $_reportCrpsMapper = new Model_Mapper_ReportFilePublicationReportingCrp();
                    $crps [] = -15;
                    try {
                        $_reportCrpsMapper->fetchOne(array('result_id' => $reportFileEntity->report_file_id, 'crp_id' => $crps));
                        return true;
                    } catch (Exception $e) {
                    }

                    if ($reportFileEntity->deliverable_id != null || $reportFileEntity->training_id != null) {
                        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                        $query = $db->select()
                            ->from('tbl_report_file', 'count(tbl_actionsite.partner_id) AS total')
                            ->where('tbl_actionsite.partner_id IN (?)', $crps)
                            ->where('tbl_report_file.report_file_id = (?)', $reportFileEntity->report_file_id);

                        if ($reportFileEntity->deliverable_id != null) {
                            $query->join('tbl_flagship_activity_result_deliverable', 'tbl_flagship_activity_result_deliverable.deliverable_id = tbl_report_file.deliverable_id', '')
                                ->join('tbl_flagship_activity_result', 'tbl_flagship_activity_result.flagship_activity_id = tbl_flagship_activity_result_deliverable.result_id', '')
                                ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_result.flagship_activity_id', '');
                        } elseif ($reportFileEntity->training_id != null) {
                            $query->join('tbl_flagship_activity_training', 'tbl_flagship_activity_training.training_id = tbl_report_file.training_id', '')
                                ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_training.flagship_activity_id', '');
                        }
                        $query->join('tbl_actionsite', 'tbl_actionsite.actionsite_id = tbl_flagship_activity.actionsite_id', '');
                        if ($db->fetchCol($query)[0] > 0)
                            return true;
                    }
                }
            }

            if ($reportFileEntity->deliverable_id != null || $reportFileEntity->training_id != null) {
                try {
                    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $query = $db->select()
                        ->from('tbl_user_profile', 'count(tbl_user_profile.profile_id) AS total');
                    if ($reportFileEntity->training_id != null) {
                        $query->join('tbl_flagship_activity_training', 'tbl_flagship_activity_training.training_id = ' . $reportFileEntity->training_id, '')
                            ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_training.flagship_activity_id', '');
                    } else if ($reportFileEntity->deliverable_id != null) {
                        $query->join('tbl_flagship_activity_result_deliverable', 'tbl_flagship_activity_result_deliverable.deliverable_id = ' . $reportFileEntity->deliverable_id, '')
                            ->join('tbl_flagship_activity_result', 'tbl_flagship_activity_result.result_id = tbl_flagship_activity_result_deliverable.result_id', '')
                            ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_result.flagship_activity_id', '');
                    }
                    $query->join('tbl_actionsite', 'tbl_actionsite.actionsite_id = tbl_flagship_activity.actionsite_id', '')
                        ->join('tbl_flagship', 'tbl_flagship.flagship_id = tbl_actionsite.flagship_id', '');

                    $query->where($_user->user_id . ' IN (tbl_flagship_activity.focalpoint_id, tbl_flagship_activity.coleader_id, tbl_actionsite.coordinator_id, tbl_actionsite.cocoordinator_id, tbl_flagship.focalpoint, tbl_flagship.partner_contact_co_leader_id)');
                    $_profiles = $db->fetchCol($query);
                    if ((int)$_profiles[0] > 0)
                        return true;
                } catch (Exception $e) {
                }
            }
        }

        $_canEditReportFile = self::CanEditReportFile($reportFileEntity);
        if ($_canEditReportFile)
            return true;
        return false;
    }

	// return styled download buttons as described in the issue #2326
    public static function getDownloadBtn($reportFileEntity = null, $_report_file_id = 0, $source = null, $with_title = false)
    {
        try{
            if ($reportFileEntity == null && $_report_file_id != 0) {
                $reportFileMapper = new Model_Mapper_ReportFile();
                $reportFileEntity = $reportFileMapper->fetchOne(array('report_file_id' => $_report_file_id));
            }

            if ($reportFileEntity != null || $_report_file_id != 0) {
                $information = $reportFileEntity->information;
                if (!is_array($information) && is_string($information))
                    $information = json_decode($information, true);

                $_isOpenAccess = false;
                $_limitedAccess = true;
                if (isset($information['copyright']) && $information['copyright'] == "yes") {
                    if (isset($information['timeless_access']) && $information['timeless_access'] == '1') {

                    } elseif (!empty($information['embargo_date'])) {
                        //check embargo date if expired => Open access
                        $embargo_date_time = strtotime($information['embargo_date']);
                        if (strtotime(date('Y-m-d')) > $embargo_date_time)
                            $_isOpenAccess = true;
                    } else {
                        $_isOpenAccess = true;
                    }
                } else {
                    $_isOpenAccess = true;
                    $_limitedAccess = false;
                }

                $_externally_approved = ($reportFileEntity->externally_approved == 1) ? true : false;
                $_internallyApproved = ($reportFileEntity->internal_approved == 1) ? true : false;

                if (!$_isOpenAccess) {
                    $_canDownload = self::canDownloadReport($reportFileEntity);
                } else {
                    $_canDownload = true;
                }

                if ($source == 'export') {
                    $url = APPLICATION_BASE_URL;

                    if ($_externally_approved){
                        try {
                            $_reportFileRepositoriesMapper = new Model_Mapper_ReportFileRepositories();
                            $_reportFileRepository = $_reportFileRepositoriesMapper->fetchOne(array('report_file_id' => $reportFileEntity->report_file_id, '!repository_id' => 5), 'repository_id ASC'); // repository_id: 5 Internal
                            $_repository = $_reportFileRepository->repository;
                            $_link = $_repository->permanent_link . $_reportFileRepository->link_body;
                        } catch (Exception $e) {
                            $_link = null;
                        }

                        return ($_canDownload || $_isOpenAccess) ? $_link->link : APPLICATION_BASE_URL . '/dspace/limited';
                    } else{
                        return ($_canDownload || $_isOpenAccess) ? $url . '/reporting/download/report_file_id/' . $reportFileEntity->report_file_id : APPLICATION_BASE_URL . '/dspace/limited';
                    }
                } else if($source == 'candownloadrestricted'){
                    return $_isOpenAccess;
                } else {
                    return self::generateHTML($reportFileEntity->title, $reportFileEntity->report_file_id, $with_title, $_isOpenAccess, $_limitedAccess, $_externally_approved, $_internallyApproved, $_canDownload);
                }
            } else {
                return '';
            }
        }catch (Exception $e){
            return '';
        }
    }

    private static function generateHTML($title, $report_file_id, $with_title, $_isOpenAccess, $_limitedAccess, $_externally_approved, $_internallyApproved, $_canDownload){
        $_title = '';
        $_btn_lock = '<a href="' . APPLICATION_BASE_URL . '/dspace/limited" target="_blank" class="btn btn-danger btn-xs report-download-btn"> '
            . '<span class="glyphicon glyphicon-lock"></span>'
            . '</a>';
        $_btn_eye = '<button type="button" class="btn btn-default btn-xs show-report_links popovers report-download-btn" data-container="body" data-trigger="focus" data-placement="top" data-id="'. $report_file_id .'" >'
            . '<span class="glyphicon glyphicon-eye-open"></span>'
            . '<span class="fa fa-spinner" style="display: none"></span>'
            . '</button>';
        $_btn_download = '<a href="/reporting/download/report_file_id/'. $report_file_id .'" class="btn btn-download btn-xs report-download-btn" target="_blank"> '
            . '<span class="glyphicon glyphicon-download-alt"></span>'
            . '</a>';
        $_btn_refresh = '<a href="javascript:void(0);" class="btn btn-warning UnderApprovalMessage btn-xs report-download-btn"> '
            . '<span class="glyphicon glyphicon-refresh"></span>'
            . '</a>';
        $_btn_unlock = '<button type="button" class="btn btn-open-access btn-xs popovers report-download-btn" data-container="body" data-trigger="focus" data-placement="top" data-content="This item is open access document!" data-original-title="Open Access Document">'
            . '<i class="fa fa-unlock" aria-hidden="true"></i>'
            . '</button>';
        $_btn_info = '<button type="button" data-discussion="0" data-loaded="false" data-id="' . $report_file_id . '" class="btn btn-primary btn-xs checkReportDiscussionUsage popovers report-download-btn" data-container="body" data-trigger="focus" data-placement="top" data-content="The access to this file has been restricted by the author" data-original-title="Information Required">'
            . '<span class="glyphicon glyphicon-info-sign"></span>'
            . '<span class="fa fa-spinner" style="display: none"></span>'
            . '</button>';
        $btn_refresh2 = '<a href="javascript:void(0);" class="btn btn-warning UnderApprovalMessage2 btn-xs report-download-btn"> '
            . '<span class="glyphicon glyphicon-refresh"></span>'
            . '</a>';
        $_btn_bars = '<button type="button" class="btn btn-default btn-xs popovers report-download-btn" data-container="body" data-trigger="focus" data-placement="top" data-content="This item is approved internally" data-original-title="Internally approved Document">'
            . '<i class="fa fa-bars" aria-hidden="true"></i>'
            . '</button>';
        if ($with_title)
            $_title = '<i style="color: #ed6b75;font-size: 24px;padding-top: 3px;margin-left: 6px;" 
                                class="fa fa-info-circle popovers" aria-hidden="true" data-trigger="hover" title="Report title"
                                data-placement="top" data-content="' . $title . '" data-container="body"></i>';

        $_downloadBtn = '<div class="btn-group">';
        $_downloadBtn .= $_limitedAccess ? $_btn_lock : $_btn_unlock;
        $_downloadBtn .= ($_externally_approved ? $_btn_eye : ($_internallyApproved ? $_btn_bars : ($_isOpenAccess ? $btn_refresh2 : $_btn_refresh)));
        $_downloadBtn .= ($_isOpenAccess || $_canDownload) ? $_btn_download : $_btn_info;
        $_downloadBtn .= $_title;
        $_downloadBtn .= '</div>';
        return $_downloadBtn;
    }

	public static function hasPrivilege($subsection,$action,$sectionid = null){
		$_user = self::getLoggedUser();
//		$_contactId = $_user->contact_id;
//		$contactCondition = "";
//		if($_contactId != NULL){
//			$contactCondition = "or (user_id = $_user->contact_id and type like 'contact')";
//		}
		switch($subsection):
			case 'training':
                $_activityResearchTeamMapper = new Model_Mapper_ActivityResearchTeam();
                try{
                    $Activityusers = $_activityResearchTeamMapper->fetchOne(array('flagship_activity_id' => $sectionid))->flagship_activity_scientists;
                    $Activityusers = explode(',', $Activityusers);
                }catch(Exception $e){
                    $Activityusers = array();
                }

				if($action == 'add' && in_array($_user->user_id, $Activityusers))
					return true;
				break;
		endswitch;
		return false;
	}

    public static function CanEditReportFile($_reportFile) {
        //****MEL Admin
        //deliverable.leader_id
        //result.leader_id
        //result.coleader_id
        //training.supervisor_id
        //training.owner_user_id
        //****uploader
        //activity leader
        //activity co-leader
        //cluster leader
        //cluster co-leader
        //****workflow user
        //****workflow assigned user
        //Center admin when same uploader_organization when DEL CAPDEV,  project_implemented_by when DONOR
        //CRP admin when under product under his crps
        //CapDev focal point when uploader_organization when Capdev
        //PMO when project implemented by his center and mapped to his CRp OR uploaded from his center under a product under his CRP
        //****data curator when under a workflow of his center
        //****donor officer, financial officer, donor project officer

        if (self::getLoggedUser()){
            if(self::isAdmin())
                return true;
            $_user = self::getLoggedUser();
            $_userId = $_user->user_id;
            $_orgId = $_user->organization_id;

            $_userGroups = $_user->groups;
            $_userGroupsIds = array();
            foreach ($_userGroups as $_userGroup)
                $_userGroupsIds[] = $_userGroup->group_id;

            if (is_numeric($_reportFile)) {
                $_reportFileMapper = new Model_Mapper_ReportFile();
                try {
                    $_reportFile = $_reportFileMapper->fetchOne(array('report_file_id' => $_reportFile));
                } catch (Exception $e) {
                    try {
                        $_reportFile = $_reportFileMapper->fetchOneDeleted(array('report_file_id' => $_reportFile));
                    } catch (Exception $e) {
                        return false;
                    }
                }
            }

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            $_partnersQueriesArray = array();
            $_contributorsPartnersQuery = $db->select()
                ->from('tbl_report_file_contributors', 'tbl_report_file_contributors.partner_id')
                ->where('tbl_report_file_contributors.report_file_id = (?)', $_reportFile->report_file_id);
            $_partnersQueriesArray[] = $_contributorsPartnersQuery;

            if ($_reportFile->training_id != null) {
                $_trainingProjectPartnersQuery = $db->select()
                    ->from('tbl_flagship_activity_training', 'tbl_project.implemented_by')
                    ->join('tbl_project', 'tbl_project.activity_id = tbl_flagship_activity_training.flagship_activity_id', '')
                    ->where('tbl_flagship_activity_training.training_id = (?)', $_reportFile->training_id);
                $_partnersQueriesArray[] = $_trainingProjectPartnersQuery;

                $_trainingProductPartnersQuery = $db->select()
                    ->from('tbl_flagship_activity_training', 'tbl_user_profile.partner_id')
                    ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_training.flagship_activity_id AND isnull(tbl_flagship_activity.is_project)', '')
                    ->join('tbl_flagship_activity_training_supervisors', 'tbl_flagship_activity_training_supervisors.training_id = tbl_flagship_activity_training.training_id', '')
                    ->join('tbl_user_profile', 'tbl_user_profile.profile_id IN (tbl_flagship_activity_training_supervisors.profile_id, tbl_flagship_activity.focalpoint_id, tbl_flagship_activity.coleader_id)', '')
                    ->where('tbl_flagship_activity_training.training_id = (?)', $_reportFile->training_id);
                $_partnersQueriesArray[] = $_trainingProductPartnersQuery;
            }
            $_partnersQuery = $db->select()->union($_partnersQueriesArray);

            $select = $db->select()
                ->from('tbl_report_file', 'tbl_report_file.report_file_id')
                ->join('tbl_user_profile', 'tbl_user_profile.profile_id = tbl_report_file.user_id', '')
                ->joinLeft('view_report_files_workflow_total', 'view_report_files_workflow_total.report_file_id = tbl_report_file.report_file_id', '')
                ->joinLeft('tbl_workflow_type', 'tbl_workflow_type.report_type = tbl_report_file.report_type_id', '')
                ->joinLeft('tbl_report_file_workflow', 'tbl_report_file_workflow.report_file_id = tbl_report_file.report_file_id', '')
                ->joinLeft('tbl_workflow', '((tbl_workflow.partner_id IN (' . $_partnersQuery->__toString() . ') AND view_report_files_workflow_total.total = 1) OR tbl_workflow.workflow_id = tbl_report_file_workflow.workflow_id) AND tbl_workflow_type.workflow_id = tbl_workflow.workflow_id', '')
                ->joinLeft('tbl_workflow_steps', 'tbl_workflow_steps.workflow_id = tbl_workflow.workflow_id', '')
                ->joinLeft('tbl_workflow_step_users', 'tbl_workflow_step_users.step_id = tbl_workflow_steps.step_id', '')
                ->joinLeft('tbl_workflow_user_assigned', 'tbl_workflow_user_assigned.step_id = tbl_workflow_steps.step_id AND tbl_report_file.report_file_id = tbl_workflow_user_assigned.item_id', '')
                ->where('tbl_report_file.is_deleted = (?)', 0);

            if ($_reportFile->deliverable_id != null) {
                $select
                    ->join('tbl_flagship_activity_result_deliverable', 'tbl_flagship_activity_result_deliverable.deliverable_id = tbl_report_file.deliverable_id', '')
                    ->join('tbl_flagship_activity_result', 'tbl_flagship_activity_result.result_id = tbl_flagship_activity_result_deliverable.result_id', '')
                    ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_result.flagship_activity_id', '');

                $_whereUser = array(
                    'tbl_flagship_activity_result_deliverable.leader_id',
                    'tbl_flagship_activity_result.leader_id',
                    'tbl_flagship_activity_result.coleader_id'
                );
            } else if ($_reportFile->training_id != null) {
                $select
                    ->join('tbl_flagship_activity_training', 'tbl_flagship_activity_training.training_id = tbl_report_file.training_id', '')
                    ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_flagship_activity_training.flagship_activity_id', '')
                    ->join('tbl_flagship_activity_training_supervisors', 'tbl_flagship_activity_training_supervisors.training_id = tbl_report_file.training_id', '');

                $_whereUser = array(
                    'tbl_flagship_activity_training_supervisors.profile_id',
                    'tbl_flagship_activity_training.owner_user_id'
                );
            } else if ($_reportFile->donor_report_id != null) {
                $select
                    ->join('tbl_project_report', 'tbl_project_report.report_id = tbl_report_file.donor_report_id', '')
                    ->join('tbl_project', 'tbl_project.project_id = tbl_project_report.project_id', '')
                    ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_project.activity_id', '');
            } else if ($_reportFile->product_id != null) {
                $select
                    ->join('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_report_file.product_id', '');
            }
            if ($_reportFile->product_id == null)
                $select->joinLeft('tbl_project AS tbl_project2', 'tbl_project2.activity_id = tbl_flagship_activity.flagship_activity_id', '');
            if (in_array(18, $_userGroupsIds))
                $select->joinLeft('tbl_project_mapped_crps', 'tbl_project_mapped_crps.project_id = tbl_project2.project_id', '');
            $select->joinLeft('tbl_donors_projects', 'tbl_donors_projects.project_id = tbl_project2.project_id', '');

            $select->where('tbl_report_file.report_file_id = (?)', $_reportFile->report_file_id);
            $_whereUser[] = 'tbl_report_file.user_id'; //uploader
            if ($_reportFile->deliverable_id != null || $_reportFile->training_id != null || $_reportFile->donor_report_id != null || $_reportFile->product_id != null) {
                $_whereUser[] = 'tbl_flagship_activity.focalpoint_id'; //activity leader
                $_whereUser[] = 'tbl_flagship_activity.coleader_id'; //activity co-leader
                if ($_reportFile->product_id != null) {
                    $_whereUser[] = 'tbl_actionsite.coordinator_id'; //Cluster leader
                    $_whereUser[] = 'tbl_actionsite.cocoordinator_id'; //Cluster co-leader
                }

                $select
                    ->joinLeft('tbl_actionsite', 'tbl_actionsite.actionsite_id = tbl_flagship_activity.actionsite_id', '')
                    ->joinLeft('tbl_user_profile AS tbl_user_profile_activity_leader', 'tbl_user_profile_activity_leader.profile_id = tbl_flagship_activity.focalpoint_id', '')
                    ->joinLeft('tbl_user_profile AS tbl_user_profile_activity_coleader', 'tbl_user_profile_activity_coleader.profile_id = tbl_flagship_activity.coleader_id', '');
            }
            $_whereUser[] = 'tbl_workflow_step_users.user_id'; //workflow user
            $_whereUser[] = 'tbl_workflow_user_assigned.assigned_user_id'; //workflow assigned user
            $_where = $_userId . ' IN (' . implode(',', $_whereUser) . ')';

            if (self::isCenterAdmin()) {
                $_where .= ' OR (tbl_user_profile.partner_id = ' . $_orgId . ' AND (tbl_report_file.deliverable_id IS NOT NULL OR tbl_report_file.training_id IS NOT NULL))'; //uploader_organization
                $_where .= ' OR (tbl_project2.implemented_by = ' . $_orgId . ' AND tbl_report_file.donor_report_id IS NOT NULL)'; //project_implemented_by
            }
            if (self::isCRPAdmin()) {
                try {
                    $_crpAdminIds = Zend_Json::decode($_user->crp_admin);
                    $_crpAdminIds[] = -15;
                    $_crpAdminIds = implode(',', array_filter($_crpAdminIds));
                    $_where .= ' OR tbl_actionsite.partner_id IN (' . $_crpAdminIds . ')'; //report_organization
                } catch (Exception $e) {
                }
            }
            if (count(array_intersect(array(9, 10), $_userGroupsIds)) > 0)
                $_where .= ' OR (tbl_user_profile.partner_id = ' . $_orgId . ' AND tbl_report_file.training_id IS NOT NULL)'; //uploader_organization
            if (in_array(18, $_userGroupsIds)) {
                try {
                    $_pmoCrpsIds = Zend_Json::decode($_user->pmo_crps);
                    $_pmoCrpsIds[] = -15;
                    $_pmoCrpsIds = array_filter($_pmoCrpsIds);
                    $_pmoCrpsIds = implode(',', $_pmoCrpsIds);

                    $_where .= ' OR (tbl_project2.implemented_by = ' . $_orgId . ' AND tbl_project_mapped_crps.crp_id IN(' . $_pmoCrpsIds . '))'; //project_implemented_by, project_crps
                    $_where .= ' OR (tbl_actionsite.partner_id IN (' . $_pmoCrpsIds . ') AND tbl_project2.implemented_by IS NULL AND tbl_user_profile.partner_id = ' . $_orgId . ')'; //report_organization, project_implemented_by, uploader_organization
                    if ($_reportFile->deliverable_id != null || $_reportFile->training_id != null || $_reportFile->donor_report_id != null || $_reportFile->product_id != null)
                        $_where .= ' OR (tbl_actionsite.partner_id IN (' . $_pmoCrpsIds . ') AND (tbl_user_profile_activity_leader.partner_id = ' . $_orgId . ' OR tbl_user_profile_activity_coleader.partner_id = ' . $_orgId . '))'; //report_organization, activity_leader_organization, activity_coleader_organization
                } catch (Exception $e) {
                }
            }

            if (in_array(31, $_userGroupsIds) || in_array(3, $_userGroupsIds)) //user group 31: Donor officer, 3: Finance Focal Point
                $_where .= ' OR tbl_donors_projects.partner_id = ' . $_orgId; //Donor officer
            $_where .= ' OR tbl_donors_projects.officer_id = ' . $_userId; //Donor project officer

            if (in_array(25, $_userGroupsIds) || self::isInstitutionalAdmin())
                $_where .= ' OR tbl_workflow.partner_id = ' . $_orgId;

            $select->where($_where);

            $_reportIds = $db->fetchCol($select);
            return !empty($_reportIds);
        }
    }

	public static function generateAcl(){
		$myfile = fopen("library/App/Acl/Acl.php", "w") or die("Can't create file");

		$content = '<?php
						class App_Acl_Acl extends Zend_Acl{

							/**
							 * Array holding the current context.
							 * This could be the currently loaded page, article, user, etc...
							 *
							 * @var array
							 */
							protected $_context = array();

							/**
							 * Set the context array
							 *
							 * @param array $context
							 */
							public function setContextArray($context = array())
							{
								$this->_context = $context;
							}

							/**
							 * Get the context array
							 *
							 * @return array $context
							 */
							public function getContextArray()
							{
								return $this->_context;
							}

							/**
							 * Set a context value
							 *
							 * @param string $key
							 *            context item name
							 * @param mixed $value
							 */
							public function setContextValue($key, $value = null)
							{
								$this->_context [$key] = $value;
							}

							/**
							 * Get the context array
							 *
							 * @param string $key
							 *            context item name
							 * @return mixed $value
							 */
							public function getContextValue($key)
							{
								if (isset ($this->_context [$key])) {
									return $this->_context [$key];
								} else {
									throw new Zend_Acl_Exception ("Context value [" . $key . "] not set");
								}
							}

							/**
							 *
							 * @param string $key
							 * @return boolean
							 */
							public function hasValue($key)
							{
								if (isset ($this->_context [$key])) {
									return true;
								} else {
									return false;
								}
							}

							public function __construct()
							{';


		$_resourceMapper = new Model_Mapper_Resource();
		$_resourceCollection  = $_resourceMapper->fetchMany();
		foreach($_resourceCollection as $_resource){
			$_resources[$_resource->resource_id] = $_resource;
			$content.= '$'.$_resource->controller_name.'Resource =  new App_Acl_Resource ("'.$_resource->controller_name.'");';
			$content.= '$this->addResource($'.$_resource->controller_name.'Resource); ';
		}

		$_roleMapper = new Model_Mapper_Role();
		$_roleCollection = $_roleMapper->fetchMany();


		foreach($_roleCollection as $_role){
			$content.= '$'.$_role->role_name.' = new Zend_Acl_Role ("'.$_role->role_name.'"); ';

			if($_role->parent != null){
				$content.='$this->addRole($'.$_role->role_name.',"'.$_role->parent.'" );';
			}
			else{
				$content.='$this->addRole($'.$_role->role_name.'); ';
			}

		}
		foreach($_roleCollection as $role){
			$_roleResourcesArray = $role->all_resources;
			$_roleActionsCollection = $role->specific_privileges;
			if(!empty($_roleActionsCollection)){
				foreach($_roleResourcesArray as $_roleResource) {
					$_allowedActions = array();
					foreach ($_roleResource->specified_actions as $_roleAction) {
						$_allowedActions[] = $_roleAction->action->action_name;
					}
					if(empty($_allowedActions)){
						$content.='$this->allow($'.$role->role_name.',$'.$_resources[$_roleResource->resource_id]->controller_name.'Resource); ';

					}
					else{
						$content.='$this->allow($'.$role->role_name.',$'.$_resources[$_roleResource->resource_id]->controller_name.'Resource,array("'.implode('","',$_allowedActions).'")); ';

					}
				}
			}
			else{
				foreach($_roleResourcesArray as $_roleResource){
					$content.='$this->allow($'.$role->role_name.',$'.$_resources[$_roleResource->resource_id]->controller_name.'Resource);';
				}
			}
		}

		$_groupsMapper = new Model_Mapper_Group();
        $_groups = $_groupsMapper->fetchMany();

        foreach ($_groups as $_group) {
            $content .= '$user_group_' . $_group->group_id . ' = new Zend_Acl_Role ("user_group_' . $_group->group_id . '"); ';
            $content .= '$this->addRole($user_group_' . $_group->group_id . '); ';
        }

        foreach ($_groups as $_group) {
            $_groupResourcesArray = $_group->all_resources;
            $_groupActionsCollection = $_group->specific_privileges;
            if (!empty($_groupActionsCollection)) {
                foreach ($_groupResourcesArray as $_groupResource) {
                    $_allowedActions = array();
                    foreach ($_groupResource->specified_actions as $_groupAction)
                        $_allowedActions[] = $_groupAction->action->action_name;

                    if (empty($_allowedActions)) {
                        $content .= '$this->allow($user_group_' . $_group->group_id . ',$' . $_resources[$_groupResource->resource_id]->controller_name . 'Resource); ';
                    } else {
                        $content .= '$this->allow($user_group_' . $_group->group_id . ',$' . $_resources[$_groupResource->resource_id]->controller_name . 'Resource,array("' . implode('","', $_allowedActions) . '")); ';
                    }
                }
            } else {
                foreach ($_groupResourcesArray as $_groupResource)
                    $content .= '$this->allow($user_group_' . $_group->group_id . ',$' . $_resources[$_groupResource->resource_id]->controller_name . 'Resource);';
            }
        }

		$content .= '
					}
				}
				';
        fwrite($myfile, $content);
        fclose($myfile);
    }

    public static function canViewUserEssentialData($_termsAccepted, $infoVisibilty)
    {
        return $_termsAccepted == 1 && (($infoVisibilty == 1) || self::isAdmin() || self::isChildOfAdmin());
    }

    /**
     * @param $_flagshipEntity Model_Flagship | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** CRP Admin and the flagship is under one of their CRPs
     ** Center Admin and the flagship is under one of their organization or lead/co-lead by a user from their organization
     */
    public static function canEditFlagship($_flagshipEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        //MEL Admin
        if (self::isAdmin())
            return true;

        //CRP Admin and the flagship is under one of their CRPs
        if (self::isCRPAdmin()) {
            try {
                $crps_array = Zend_Json::decode($_logged_user->crp_admin);
            } catch (Exception $e) {
                $crps_array = array();
            }
            $crps_array[] = -15;
            $crps_array = array_filter($crps_array);
            if (in_array($_flagshipEntity->partner_id, $crps_array))
                return true;
        }

        //Center Admin and if the flagship is under one of their organization or lead/co-lead by a user from their organization
        if (self::isCenterAdmin()) {
            if ($_logged_user->organization_id == $_flagshipEntity->partner_id)
                return true;
            if ($_logged_user->organization_id == $_flagshipEntity->partner_leader_id)
                return true;
            if ($_logged_user->organization_id == $_flagshipEntity->partner_co_leader_id)
                return true;
        }
        return false;
    }

    /**
     * @param $_flagshipEntity Model_Flagship | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** Leader/co-leader
     */
    public static function canViewFlagship($_flagshipEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        $_auth = Zend_Auth::getInstance();
        $_logged_user = $_auth->getIdentity();
        return $_logged_user->user_id == $_flagshipEntity->focalpoint || $_logged_user->user_id == $_flagshipEntity->partner_contact_co_leader_id;
    }

    /**
     * @param $_actionsiteEntity Model_Actionsite | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** CRP Admin and the actionsite is under one of their CRPs
     ** Center Admin and the actionsite is under one of their organization or lead/co-lead by a user from their organization
     ** Can edit/view flagship
     */
    public static function canEditActionsite($_actionsiteEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        //MEL Admin
        if (self::isAdmin())
            return true;

        try {
            //CRP Admin and the actionsite is under one of their CRPs
            if (self::isCRPAdmin()) {
                try {
                    $crps_array = Zend_Json::decode($_logged_user->crp_admin);
                } catch (Exception $e) {
                    $crps_array = array();
                }
                $crps_array[] = -15;
                $crps_array = array_filter($crps_array);
                if (in_array($_actionsiteEntity->partner_id, $crps_array))
                    return true;
            }

            //Center Admin and the actionsite is under one of their organization or lead/co-lead by a user from their organization
            if (self::isCenterAdmin()) {
                if ($_logged_user->organization_id == $_actionsiteEntity->partner_id)
                    return true;

                $_actionsiteLeaders = array($_actionsiteEntity->coordinator_id, $_actionsiteEntity->cocoordinator_id, -15);
                $_actionsiteLeaders = array_filter($_actionsiteLeaders);
                $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $_query = $_db->select()
                    ->from('tbl_user_profile', 'profile_id')
                    ->where('tbl_user_profile.partner_id = (?)', $_logged_user->organization_id)
                    ->where('tbl_user_profile.profile_id IN (?)', $_actionsiteLeaders);
                if (!empty($_db->fetchCol($_query)))
                    return true;
            }

            //If can edit/view flagship, then can edit actionsite
            $_flagship = $_actionsiteEntity->flagship;
            if (self::canViewFlagship($_flagship) || self::canEditFlagship($_flagship))
                return true;
        } catch (Exception $e) {
        }

        return false;
    }

    /**
     * @param $_actionsiteEntity Model_Actionsite | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** Leader/co-leader
     */
    public static function canViewActionsite($_actionsiteEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        try {
            if ($_actionsiteEntity->coordinator_id == $_logged_user->user_id || $_actionsiteEntity->cocoordinator_id == $_logged_user->user_id)
                return true;
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * @param $_activityEntity Model_FlagshipActivity | App_Model_ModelAbstract
     * @param $_actionsiteEntity Model_Actionsite | App_Model_ModelAbstract
     * @param $_PMOusersArray array
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** Leader/co-leader
     ** PMO and the product is lead/co-lead by a user from their organization
     ** Center Admin and the product is lead/co-lead by a user from their organization
     ** Can edit/view actionsite (and flagship which included in self::canEditActionsite)
     */
    public static function canEditProduct($_activityEntity, $_actionsiteEntity, $_PMOusersArray = array())
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        //MEL Admin
        if (self::isAdmin())
            return true;

        //Product leader/co-leader
        if ($_activityEntity->focalpoint_id == $_logged_user->user_id || $_activityEntity->coleader_id == $_logged_user->user_id)
            return true;

        //PMO and the product is lead/co-lead by a user from their organization
        if (App_Function_Privileges::isMemberOf(18) && array_intersect(array($_activityEntity->focalpoint_id, $_activityEntity->coleader_id), $_PMOusersArray))
            return true;

        //Center Admin and the product is lead/co-lead by a user from their organization
        if (self::isCenterAdmin()) {
            $_productLeaders = array($_activityEntity->focalpoint_id, $_activityEntity->coleader_id, -15);
            $_productLeaders = array_filter($_productLeaders);
            $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $_query = $_db->select()
                ->from('tbl_user_profile', 'profile_id')
                ->where('tbl_user_profile.partner_id = (?)', $_logged_user->organization_id)
                ->where('tbl_user_profile.profile_id IN (?)', $_productLeaders);
            if (!empty($_db->fetchCol($_query)))
                return true;
        }

        //If can edit/view actionsite (and flagship which included in self::canEditActionsite), then can edit product
        if ($_actionsiteEntity && (self::canViewActionsite($_actionsiteEntity) || self::canEditActionsite($_actionsiteEntity)))
            return true;

        return false;
    }

    /**
     * @param $_activityEntity Model_FlagshipActivity | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** Can edit/view actionsite (and flagship which included in self::canEditActionsite)
     */
    public static function canDeleteProduct($_activityEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        //MEL Admin
        if (self::isAdmin())
            return true;

        //If can edit/view actionsite (and flagship which included in self::canEditActionsite), then can edit product
        $_actionsite = $_activityEntity->actionsite;
        if (self::canViewActionsite($_actionsite) || self::canEditActionsite($_actionsite))
            return true;

        return false;
    }

    public static function canEditProject($_projectEntity, $_PMOusersArray)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;
        if ($_projectEntity->project_manager_id == $_logged_user->user_id)
            return true;
        if ($_projectEntity->project_co_manager_id == $_logged_user->user_id)
            return true;
        if (self::isCenterAdmin() && $_projectEntity->implemented_by == $_logged_user->organization_id)
            return true;
        if ($_logged_user->isMemberOf(18)) {
            if (empty($_PMOusersArray))
                $_PMOusersArray = $_logged_user->partner_users;
            if (in_array($_projectEntity->project_manager_id, $_PMOusersArray))
                return true;
            if (in_array($_projectEntity->project_co_manager_id, $_PMOusersArray))
                return true;
        }

        if (is_a($_projectEntity, 'Model_ProjectSimpleDetails')) {
            try {
                $_projectMapper = new Model_Mapper_ProjectIndexData();
                $_projectEntity = $_projectMapper->fetchOne(array('project_id' => $_projectEntity->project_id));
            } catch (Exception $e) {
                return false;
            }
        }

        if (self::IsDonorOfficer($_projectEntity))
            return true;

        if (self::IsProjectFinancialOfficer($_projectEntity))
            return true;

        if (self::IsDonorProjectOfficer($_projectEntity))
            return true;

        return false;
    }

    public static function CanEditSubmittedProject($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                return true;
            }
        } elseif ($_projectEntity == null && $_flagshipActivityId == null) {
            return false;
        }

        try {
            $_projectPlanningReviewMapper = new Model_Mapper_ProjectPlanningReview();
            $_projectPlanningReview = $_projectPlanningReviewMapper->fetchOne(array('flagship_activity_id' => $_projectEntity->activity_id));

            if ($_projectPlanningReview->planning_is_draft != '0')
                return true;

            if (self::IsDonorOfficer($_projectEntity))
                return true;

            if (self::IsDonorProjectOfficer($_projectEntity))
                return true;
        } catch (Exception $e) {
            return true;
        }
        return false;
    }

    public static function CanReviewProjectPlanning($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                return true;
            }
        } elseif ($_projectEntity == null && $_flagshipActivityId == null) {
            return false;
        }

        if (self::IsDonorOfficer($_projectEntity))
            return true;

        if (self::IsDonorProjectOfficer($_projectEntity))
            return true;

        return false;
    }

    public static function canDeleteProject($_projectEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;
        if (self::isCenterAdmin() && $_projectEntity->implemented_by == $_logged_user->organization_id)
            return true;

        if (is_a($_projectEntity, 'Model_ProjectSimpleDetails')) {
            try {
                $_projectMapper = new Model_Mapper_ProjectIndexData();
                $_projectEntity = $_projectMapper->fetchOne(array('project_id' => $_projectEntity->project_id));
            } catch (Exception $e) {
                return false;
            }
        }

        if (self::IsDonorProjectOfficer($_projectEntity))
            return true;

        if (self::IsDonorOfficer($_projectEntity))
            return true;

        if (self::IsProjectFinancialOfficer($_projectEntity))
            return true;

        return false;
    }

    public static function canViewEditProject($_projectData)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isCenterAdmin() && $_projectData['implemented_by'] == $_logged_user->organization_id)
            return true;
        $_isCRPAdmin = false;
        if (self::isCRPAdmin()) {
            try {
                $crps_array = Zend_Json::decode($_logged_user->crp_admin);
            } catch (Exception $e) {
                $crps_array = array();
            }
            $crps_array[] = -15;
            $crps_array = array_filter($crps_array);
            if (array_key_exists('project_crps', $_projectData)) {
                $_isCRPAdmin = array_intersect($crps_array, $_projectData['project_crps']);
            } else {
                try {
                    $_projectCrpsMapper = new Model_Mapper_ProjectMappedCrps();
                    $_projectCrpsMapper->fetchOne(array('project_id' => $_projectData['project_id'], 'crp_id' => $crps_array));
                    $_isCRPAdmin = true;
                } catch (Exception $e) {
                }
            }
        }
        if ($_isCRPAdmin)
            return true;

        if ($_logged_user->isMemberOf(18))
            return true;

        $_isCRPManager = false;
        if ($_logged_user->isMemberOf(2)) {
            $crps_array = $_logged_user->crp_manager_crps;
            $crps_array[] = -15;
            $crps_array = array_filter($crps_array);
            if (array_key_exists('project_crps', $_projectData)) {
                $_isCRPManager = array_intersect($crps_array, $_projectData['project_crps']);
            } else {
                try {
                    $_projectCrpsMapper = new Model_Mapper_ProjectMappedCrps();
                    $_projectCrpsMapper->fetchOne(array('project_id' => $_projectData['project_id'], 'crp_id' => $crps_array));
                    $_isCRPManager = true;
                } catch (Exception $e) {
                }
            }
        }
        if ($_isCRPManager)
            return true;

        $_isMCM = false;
        if ($_logged_user->isMemberOf(17)) {
            $crps_array = $_logged_user->mcm_crps;
            $crps_array[] = -15;
            $crps_array = array_filter($crps_array);
            if (array_key_exists('project_crps', $_projectData)) {
                $_isMCM = array_intersect($crps_array, $_projectData['project_crps']);
            } else {
                try {
                    $_projectCrpsMapper = new Model_Mapper_ProjectMappedCrps();
                    $_projectCrpsMapper->fetchOne(array('project_id' => $_projectData['project_id'], 'crp_id' => $crps_array));
                    $_isMCM = true;
                } catch (Exception $e) {
                }
            }
        }
        if ($_isMCM)
            return true;

        return false;
    }

    public static function CanEditIndicator($indicator_id = null, $item_indicator_id = null)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isAdmin())
            return true;

        $_itemsIndicatorsMapper = new Model_Mapper_ItemsIndicators();
        $_where = array('indicator_id' => $indicator_id);
        if ($indicator_id == null && $item_indicator_id != null)
            $_where = array('item_indicator_id' => $item_indicator_id);

        $_itemsIndicators = $_itemsIndicatorsMapper->fetchMany($_where);

        $_flagshipIds = array();
        $_actionsiteIds = array();
        $_productIds = array();
        $_productOutcomeIds = array();
        $_projectOutcomeIds = array();

        foreach ($_itemsIndicators as $_itemsIndicator) {
            $_flagshipIds[] = $_itemsIndicator->flagship_id;
            $_actionsiteIds[] = $_itemsIndicator->actionsite_id;
            $_productIds[] = $_itemsIndicator->product_id;
            $_productOutcomeIds[] = $_itemsIndicator->product_outcome_id;
            $_projectOutcomeIds[] = $_itemsIndicator->project_outcome_id;
        }

        $_flagshipIds = array_filter($_flagshipIds);
        if (!empty($_flagshipIds)) {
            $_flagshipMapper = new Model_Mapper_Flagship();
            $_flagships = $_flagshipMapper->fetchMany(array('flagship_id' => $_flagshipIds));
            foreach ($_flagships as $_flagship)
                if (self::canEditFlagship($_flagship) || self::canViewFlagship($_flagship))
                    return true;
        }

        $_productIds = array_filter($_productIds);
        if (!empty($_productIds)) {
            $_activityMapper = new Model_Mapper_FlagshipActivity();
            $_activities = $_activityMapper->fetchMany(array('flagship_activity_id' => $_productIds));
            $_PMOusersArray = array();
            if ($_logged_user->isMemberOf(18))
                $_PMOusersArray = $_logged_user->partner_users;
            foreach ($_activities as $_activity)
                if (self::canEditProduct($_activity, $_activity->actionsite, $_PMOusersArray))
                    return true;
        }

        $_productOutcomeIds = array_filter($_productOutcomeIds);
        if (!empty($_productOutcomeIds)) {
            $_productOutcomesMapper = new Model_Mapper_Outcome();
            $_productOutcomes = $_productOutcomesMapper->fetchMany('outcome_id IN (' . implode(',', $_productOutcomeIds) . ')');
            foreach ($_productOutcomes as $_productOutcome)
                $_actionsiteIds[] = $_productOutcome->actionsite_id;
        }

        $_actionsiteIds = array_filter($_actionsiteIds);

        if (!empty($_actionsiteIds)) {
            $_actionsiteMapper = new Model_Mapper_Actionsite();
            $_actionsites = $_actionsiteMapper->fetchMany(array('actionsite_id' => $_actionsiteIds));
            foreach ($_actionsites as $_actionsite)
                if (self::canEditActionsite($_actionsite) || self::canViewActionsite($_actionsite))
                    return true;
        }

        $_projectOutcomeIds = array_filter($_projectOutcomeIds);
        if (!empty($_projectOutcomeIds)) {
            $_resultMapper = new Model_Mapper_FlagshipActivityResult();

            $_where = 'result_id IN (' . implode(',', $_projectOutcomeIds) . ') AND (leader_id = ' . $_logged_user->user_id . ' OR coleader_id = ' . $_logged_user->user_id . ')';
            try {
                $_resultMapper->fetchOne($_where);
                return true;
            } catch (Exception $e) {
            }

            $_results = $_resultMapper->fetchMany('result_id IN (' . implode(',', $_projectOutcomeIds) . ')');

            $_activitiesArray = array(-15);
            foreach ($_results as $_result)
                $_activitiesArray[] = $_result->flagship_activity_id;

            $_activityMapper = new Model_Mapper_FlagshipActivity();
            $_where = 'flagship_activity_id IN (' . implode(',', $_activitiesArray) . ') AND (focalpoint_id = ' . $_logged_user->user_id . ' OR coleader_id = ' . $_logged_user->user_id . ')';
            try {
                $_activityMapper->fetchOne($_where);
                return true;
            } catch (Exception $e) {
            }

            $_PMOusersArray = array();
            if ($_logged_user->isMemberOf(18))
                $_PMOusersArray = $_logged_user->partner_users;
            $_projectMapper = new Model_Mapper_ProjectIndexData();
            $_projects = $_projectMapper->fetchMany(array('activity_id' => $_activitiesArray));
            foreach ($_projects as $_project) {
                if (self::canEditProject($_project, $_PMOusersArray))
                    return true;
            }
        }

        return false;
    }

    /**
     * @param $_deliverable Model_FlagshipActivityResultDeliverable | App_Model_ModelAbstract
     * @param $_outputApprovedCancel bool
     * @return bool
     * TRUE if the output is not canceled and approved and the logged-in user is:
     ** MEL Admin
     ** Center Admin and the project is implemented by their organization
     ** Center Admin and is under a product and the leader is from their organization
     ** Center Admin and can edit product
     ** CRP Admin and can edit product
     ** Following are subject to $_deadLineFlag (the 1st of Apr of the delivery year) in case under a product:
     ** Output leader/co-leader
     ** leader
     ** PMO and the project is implemented by their organization and the project is mapped to one of their CRPs
     ** PMO and the product is under one of their CRPs and the leader is from their organization
     ** Can edit project/product
     */
    public static function canEditDeliverable($_deliverable, $_outputApprovedCancel)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        $_auth = Zend_Auth::getInstance();
        $_logged_user = $_auth->getIdentity();

        if ($_outputApprovedCancel)
            return false;
        if (self::isAdmin())
            return true;

        $_result = $_deliverable->result;
        $_activity = $_result->activity;

        $_project = false;
        $_actionsite = false;
        if ($_activity->is_project == 1) {
            $_project = $_activity->project;
        } else {
            $_actionsite = $_activity->actionsite;
        }

        if (self::isCenterAdmin()) {
            if (($_project)) {
                if ($_project->implemented_by == $_logged_user->organization_id)
                    return true;
            } else {
                $_deliverableLeader_new = $_deliverable->leader_new;
                if (!empty($_deliverableLeader_new) && $_logged_user->organization_id == $_deliverableLeader_new['organization_id'])
                    return true;
                if ($_actionsite && self::canEditProduct($_activity, $_actionsite))
                    return true;
            }
        }
        if (self::isCRPAdmin()) {
            try {
                if ($_actionsite && self::canEditProduct($_activity, $_actionsite))
                    return true;
            } catch (Exception $e) {
            }
        }

        $_isPrima = false;
        if (!$_project) {
            $_year = Date('Y');
            $_deadLineDate = strtotime($_year . '-04-01');
            $_now = time();
            $_deliverableTime = strtotime($_deliverable->date);
            $_deliverableYear = Date('Y', $_deliverableTime);

            $_deadLineFlag = ($_deadLineDate < $_now && $_deliverableYear == $_year) || $_deliverableYear < $_year;
        } else {
            $_isPrima = $_project->is_prima_project;
            $_deadLineFlag = false;
        }

        if ($_deadLineFlag && !$_isPrima)
            return false;

        if (!$_isPrima && ($_result->leader_id == $_logged_user->user_id || $_result->coleader_id == $_logged_user->user_id || $_deliverable->leader_id == $_logged_user->user_id))
            return true;

        if ($_logged_user->isMemberOf(18)) {
            try {
                $_pmoCrpsIds = Zend_Json::decode($_logged_user->pmo_crps);
                $_pmoCrpsIds[] = -15;
                if ($_project) {
                    if ($_project->implemented_by == $_logged_user->organization_id) {
                        try {
                            $_projectMappedCrps = new Model_Mapper_ProjectMappedCrps();
                            $_projectMappedCrps->fetchOne(array('project_id' => $_project->project_id, 'crp_id' => $_pmoCrpsIds));
                            return true;
                        } catch (Exception $e) {
                        }
                    }
                } else {
                    $_deliverableLeader_new = $_deliverable->leader_new;
                    if (!empty($_deliverableLeader_new) && $_logged_user->organization_id == $_deliverableLeader_new['organization_id'] && in_array($_actionsite->partner_id, $_pmoCrpsIds))
                        return true;
                }
            } catch (Exception $e) {
            }
        }

        if ($_actionsite && self::canEditProduct($_activity, $_actionsite))
            return true;
        if ($_project && self::canEditProject($_project, array()))
            return true;

        return false;
    }

    public static function CanReportDeliverable($_deliverable, $_project, $_activity, $_actionsite, $_PMOusersArray = array())
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_project && $_project->is_prima_project) {
            $_donorReporting = $_project->_getDonorReporting(null, $_deliverable->date);
            if ($_donorReporting->is_draft != '1')
                return false;
            return self::CanEditDonorReporting($_project, null);
        } else {
            if($_deliverable->leader_id == $_logged_user->user_id)
                return true;

            if ($_logged_user->isMemberOf(18)) {
                if (empty($_PMOusersArray))
                    $_PMOusersArray = $_logged_user->partner_users;

                try {
                    $_pmoCrpsIds = Zend_Json::decode($_logged_user->pmo_crps);
                    $_pmoCrpsIds[] = -15;
                    if ($_project) {
                        if ($_project->implemented_by == $_logged_user->organization_id) {
                            try {
                                $_projectMappedCrps = new Model_Mapper_ProjectMappedCrps();
                                $_projectMappedCrps->fetchOne(array('project_id' => $_project->project_id, 'crp_id' => $_pmoCrpsIds));
                                return true;
                            } catch (Exception $e) {
                            }
                        }
                    } else {
                        if (in_array($_deliverable->leader_id, $_PMOusersArray) && in_array($_actionsite->partner_id, $_pmoCrpsIds))
                            return true;
                    }
                } catch (Exception $e) {
                }
            }

            if ($_actionsite && self::canEditProduct($_activity, $_actionsite, $_PMOusersArray))
                return true;
            if ($_project && self::canEditProject($_project, $_PMOusersArray))
                return true;
        }

    }

    public static function canEditOutputTask($_task, $_outputApprovedCancel)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        $_auth = Zend_Auth::getInstance();
        $_logged_user = $_auth->getIdentity();

        if ($_outputApprovedCancel)
            return false;
        if (self::isAdmin())
            return true;
        else {
            $_result = $_task->result;
            $_activity = $_result->activity;

            $_project = false;
            $_actionsite = false;
            $_flagship = false;
            if ($_activity->is_project == 1) {
                $_project = $_activity->project;
            } else {
                $_actionsite = $_activity->actionsite;
                $_flagship = $_actionsite->flagship;
            }
        }

        $_taskLeader = null;
        if (self::isCenterAdmin()) {
            if (($_project)) {
                if ($_project->implemented_by == $_logged_user->organization_id)
                    return true;
            } else {
                $_taskLeader = $_task->leader;
                if (!empty($_taskLeader) && $_logged_user->organization_id == $_taskLeader->organization_id)
                    return true;
            }
        }
        if (self::isCRPAdmin()) {
            try {
                $_crpAdminIds = Zend_Json::decode($_logged_user->crp_admin);
                $_crpAdminIds[] = -15;
                if ($_project) {
//                    try {
//                        $_projectMappedCrps = new Model_Mapper_ProjectMappedCrps();
//                        $_projectMappedCrps->fetchOne(array('project_id' => $_project->project_id, 'crp_id' => $_crpAdminIds));
//                        return true;
//                    } catch (Exception $e) {
//                    }
                } else {
                    if (in_array($_actionsite->partner_id, $_crpAdminIds))
                        return true;
                }
            } catch (Exception $e) {
            }
        }

        if ($_logged_user->isMemberOf(18)) {
            try {
                $_pmoCrpsIds = Zend_Json::decode($_logged_user->pmo_crps);
                $_pmoCrpsIds[] = -15;
                if ($_project) {
                    if ($_project->implemented_by == $_logged_user->organization_id) {
                        try {
                            $_projectMappedCrps = new Model_Mapper_ProjectMappedCrps();
                            $_projectMappedCrps->fetchOne(array('project_id' => $_project->project_id, 'crp_id' => $_pmoCrpsIds));
                            return true;
                        } catch (Exception $e) {
                        }
                    }
                } else {
                    if ($_taskLeader == null)
                        $_taskLeader = $_task->leader;
                    if ($_logged_user->organization_id == $_taskLeader->organization_id && in_array($_actionsite->partner_id, $_pmoCrpsIds))
                        return true;
                }
            } catch (Exception $e) {
            }
        }

        if ($_result->leader_id == $_logged_user->user_id || $_result->coleader_id == $_logged_user->user_id || $_task->leader_id == $_logged_user->user_id)
            return true;
        if (($_flagship && self::canEditFlagship($_flagship)) || ($_flagship && self::canViewFlagship($_flagship)))
            return true;
        if (($_actionsite && self::canEditActionsite($_actionsite)) || ($_actionsite && self::canViewActionsite($_actionsite)))
            return true;
        if ($_actionsite && self::canEditProduct($_activity, $_actionsite))
            return true;
        if ($_project && self::canEditProject($_project, array()))
            return true;

        return false;
    }

    public static function canManageUsers($_userId = null, $_profilesPartner = null)
    {
        $_operations = array(
            'can_edit_user' => false,
            'can_edit_profile' => false,
            'can_delete_user' => false,
            'can_delete_profile' => false,
            'can_approve_user' => false,
            'can_approve_profile' => false,
            'can_assign_user' => false,
            'can_assign_profile' => false
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return $_operations;

        $_melAdmin = self::isAdmin();
        $_childOfAdmin = ((self::isCenterAdmin() || self::isCRPAdmin()) && $_profilesPartner == $_logged_user->organization_id);
        $_owner = $_userId == $_logged_user->profile_user_id;
        $_knowledge_curator = $_logged_user->isMemberOf(25) && $_profilesPartner == $_logged_user->organization_id;// 25: Knowledge Curator

        //MEL Admin, the profile owner and the CRP/Center Admin or Knowledge Curator if the profile from their organization
        $_operations['can_edit_user'] = $_melAdmin || $_childOfAdmin || $_owner || $_knowledge_curator;
        //MEL Admin, the profile owner and the CRP/Center Admin or Knowledge Curator if the profile from their organization
        $_operations['can_edit_profile'] = $_melAdmin || $_childOfAdmin || $_owner || $_knowledge_curator;
        //MEL Admin
        $_operations['can_delete_user'] = $_melAdmin;
        //MEL Admin and the profile owner
        $_operations['can_delete_profile'] = $_melAdmin || $_owner;
        //MEL Admin
        $_operations['can_approve_user'] = $_melAdmin;
        //MEL Admin and the CRP/Center Admin or Knowledge Curator if the profile from their organization
        $_operations['can_approve_profile'] = $_melAdmin || $_childOfAdmin || $_knowledge_curator;
        //MEL Admin
        $_operations['can_assign_user'] = $_melAdmin;
        //MEL Admin
        $_operations['can_assign_profile'] = $_melAdmin;

        return $_operations;
    }

    public static function canManagePartners($partnerEntity = null, $_partnerIds = array())
    {
        $_operations = array(
            'can_edit_partner' => false,
            'can_delete_partner' => false,
            'can_approve_partner' => false,
            'can_assign_partner' => false
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return $_operations;

        $_melAdmin = self::isAdmin();
        $_childOfAdmin = self::isCenterAdmin() || self::isCRPAdmin();

        $_appointedResponsible = $partnerEntity != null ? $partnerEntity->appointed_responsible : null;
        $_organization_id = $partnerEntity != null ? $partnerEntity->partner_id : null;

        $_responsible = $_appointedResponsible == $_logged_user->user_id;
        $_knowledge_curator = $_logged_user->isMemberOf(25);// 25: Knowledge Curator
        $_institutionalAdmin = self::isInstitutionalAdmin() && $_logged_user->organization_id == $_organization_id;

        //MEL Admin, CRP Admin, Center Admin, the appointed responsible and the Knowledge Curator
        $_operations['can_edit_partner'] = $_melAdmin || $_childOfAdmin || $_responsible || $_knowledge_curator || $_institutionalAdmin;
        //MEL Admin
        $_operations['can_delete_partner'] = $_melAdmin;
        //MEL Admin
        $_operations['can_approve_partner'] = $_melAdmin;
        //MEL Admin
        $_operations['can_assign_partner'] = $_melAdmin;

        if (!empty($_partnerIds)) {
            $_partnerIds = array_filter($_partnerIds);
            $_partnerIds[] = -15;

            $_db = Zend_Db_Table::getDefaultAdapter();
            $sqlQuery = $_db->select()
                ->from('tbl_partner', 'partner_id')
                ->where('tbl_partner.appointed_responsible = (?)', $_logged_user->user_id)
                ->where('tbl_partner.partner_id IN (?)', $_partnerIds);
            $_partners = $_db->fetchCol($sqlQuery);

            $_institutionalAdmin = self::isInstitutionalAdmin() && in_array($_logged_user->organization_id, $_partnerIds);
            if ($_institutionalAdmin && !in_array($_logged_user->organization_id, $_partners))
                $_partners[] = $_logged_user->organization_id;

            //MEL Admin, CRP Admin, Center Admin, the appointed responsible and the Knowledge Curator
            $_operations['can_edit_partner_ids'] = ($_melAdmin || $_childOfAdmin || $_knowledge_curator) ? $_partnerIds : array_values(array_intersect($_partnerIds, $_partners));
            //MEL Admin
            $_operations['can_delete_partner_ids'] = $_melAdmin ? $_partnerIds : array();
            //MEL Admin
            $_operations['can_approve_partner_ids'] = $_melAdmin ? $_partnerIds : array();
            //MEL Admin
            $_operations['can_assign_partner_ids'] = $_melAdmin ? $_partnerIds : array();
        }
        return $_operations;
    }

    public static function canConfigureRisks()
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isAdmin())
            return true;
        if ($_logged_user->isMemberOf(24) != null)
            return true;
        return false;
    }

    public static function canManageClosedSessionRiskElements($_rights, $_session_closed)
    {
        //If the session is closed prevent all actions except for Admins
        if (!self::canConfigureRisks() && $_session_closed == '1') {
            foreach ($_rights as $_right => $_rightValue)
                $_rights[$_right] = false;
        }
        return $_rights;
    }

    public static function canManageRisksStatements($_risksStatement)
    {
        $_operations = array(
            'can_edit_risk_statement' => false,
            'can_delete_risk_statement' => false,
            'can_approve_risk_statement' => false,
            'can_assign_risk_statement' => false
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_risksStatement == null)
            return $_operations;

        if (!is_array($_risksStatement))
            $_risksStatement = $_risksStatement->toArray();

        $_canConfigureRisks = self::canConfigureRisks();
        $_submitter = $_risksStatement['submitted_by'] == $_logged_user->user_id;
        $_owner = $_risksStatement['owner_id'] == $_logged_user->user_id;

        //New, Submitted by, Owner or Can configure Risks
        $_operations['can_edit_risk_statement'] = $_risksStatement['id'] == null || $_submitter || $_owner || $_canConfigureRisks;
        //Submitted by, Owner or Can configure Risks
        $_operations['can_delete_risk_statement'] = $_submitter || $_owner || $_canConfigureRisks;
        //Can configure Risks
        $_operations['can_approve_risk_statement'] = $_canConfigureRisks;
        //Can configure Risks
        $_operations['can_assign_risk_statement'] = $_canConfigureRisks;

        return $_operations;
    }

    public static function canManageRisksStatementsSources($_risksStatementSource, $_risksStatement = null)
    {
        $_operations = array(
            'can_edit_risk_statement_source' => false,
            'can_delete_risk_statement_source' => false,
            'can_approve_risk_statement_source' => false,
            'can_assign_risk_statement_source' => false
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_risksStatementSource == null)
            return $_operations;

        if (!is_array($_risksStatementSource))
            $_risksStatementSource = $_risksStatementSource->toArray();

        $_canConfigureRisks = self::canConfigureRisks();
        $_submitter = $_risksStatementSource['submitted_by'] == $_logged_user->user_id;
        $_owner = $_risksStatementSource['owner_id'] == $_logged_user->user_id;

        //New, Submitted by, Owner or MEL Admin
        $_operations['can_edit_risk_statement_source'] = $_risksStatementSource['id'] == null || $_canConfigureRisks || $_submitter || $_owner;
        //Submitted by, Owner or Can configure Risks
        $_operations['can_delete_risk_statement_source'] = $_canConfigureRisks || $_submitter || $_owner;
        //Can configure Risks
        $_operations['can_approve_risk_statement_source'] = $_canConfigureRisks;
        //Can configure Risks
        $_operations['can_assign_risk_statement_source'] = $_canConfigureRisks;

        //To inherit the Risk Statement rights
        $_checkForParentRights = false;
        foreach ($_operations as $_operation) {
            if (!$_operation)
                $_checkForParentRights = true;
        }
        if ($_checkForParentRights && $_risksStatementSource['risk_statement_id'] != null) {
            try {
                if ($_risksStatement == null) {
                    $_risksStatementMapper = new Model_Mapper_RisksStatements();
                    $_risksStatement = $_risksStatementMapper->fetchOne(array('id' => $_risksStatementSource['risk_statement_id']));
                }
                $_risksStatementRights = self::canManageRisksStatements($_risksStatement);

                if (!$_operations['can_edit_risk_statement_source'])
                    $_operations['can_edit_risk_statement_source'] = $_risksStatementRights['can_edit_risk_statement'];
                if (!$_operations['can_delete_risk_statement_source'])
                    $_operations['can_delete_risk_statement_source'] = $_risksStatementRights['can_delete_risk_statement'];
                if (!$_operations['can_approve_risk_statement_source'])
                    $_operations['can_approve_risk_statement_source'] = $_risksStatementRights['can_approve_risk_statement'];
                if (!$_operations['can_assign_risk_statement_source'])
                    $_operations['can_assign_risk_statement_source'] = $_risksStatementRights['can_assign_risk_statement'];
            } catch (Exception $e) {
            }
        }

        return $_operations;
    }

    public static function canManageRisksStatementsSourcesActions($_risksStatementSourceAction, $_risksStatementSource = null, $_risksStatement = null)
    {
        $_operations = array(
            'can_edit_risk_statement_source_action' => false,
            'can_edit_risk_statement_source_action_progress' => false,
            'can_edit_risk_statement_source_action_assignee' => false,
            'can_edit_risk_statement_source_action_extend' => false,
            'can_delete_risk_statement_source_action' => false,
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_risksStatementSourceAction == null)
            return $_operations;

        if (!is_array($_risksStatementSourceAction))
            $_risksStatementSourceAction = $_risksStatementSourceAction->toArray();

        $_canConfigureRisks = self::canConfigureRisks();
        $_submitter = $_risksStatementSourceAction['submitted_by'] == $_logged_user->user_id;
        $_owner = $_risksStatementSourceAction['assigned_id'] == $_logged_user->user_id;

        $_sessionClosed = $_risksStatementSourceAction['session_closed'] == '1';
        $_dateEnded = time() > strtotime($_risksStatementSourceAction['action_date']);

        //Submitted by, Assigned
        $_operations['can_edit_risk_statement_source_action'] = $_submitter || $_owner;
        //Submitted by, Assigned
        $_operations['can_delete_risk_statement_source_action'] = $_submitter || $_owner;
        //Submitted by, Assigned
        $_operations['can_edit_risk_statement_source_action_progress'] = $_operations['can_edit_risk_statement_source_action'];
        //Submitted by, Assigned
        $_operations['can_edit_risk_statement_source_action_assignee'] = $_operations['can_edit_risk_statement_source_action'];
        //Submitted by, Assigned
        $_operations['can_edit_risk_statement_source_action_extend'] = $_operations['can_edit_risk_statement_source_action'];

        //To inherit the Risk Statement Source rights
        $_checkForParentRights = false;
        foreach ($_operations as $_operation) {
            if (!$_operation)
                $_checkForParentRights = true;
        }

        if ($_checkForParentRights && $_risksStatementSourceAction['risk_statement_source_id'] != null) {
            try {
                if ($_risksStatementSource == null) {
                    $_risksStatementSourceMapper = new Model_Mapper_RisksStatementsSources();
                    $_risksStatementSource = $_risksStatementSourceMapper->fetchOne(array('id' => $_risksStatementSourceAction['risk_statement_source_id']));
                }
                $_risksStatementSourceRights = self::canManageRisksStatementsSources($_risksStatementSource, $_risksStatement);

                if (!$_operations['can_edit_risk_statement_source_action']) {
                    $_operations['can_edit_risk_statement_source_action'] = $_risksStatementSourceRights['can_edit_risk_statement_source'];
                    $_operations['can_edit_risk_statement_source_action_progress'] = $_risksStatementSourceRights['can_edit_risk_statement_source'];
                    $_operations['can_edit_risk_statement_source_action_assignee'] = $_risksStatementSourceRights['can_edit_risk_statement_source'];
                    $_operations['can_edit_risk_statement_source_action_extend'] = $_risksStatementSourceRights['can_edit_risk_statement_source'];
                }
                if (!$_operations['can_delete_risk_statement_source_action'])
                    $_operations['can_delete_risk_statement_source_action'] = $_risksStatementSourceRights['can_delete_risk_statement_source'];
            } catch (Exception $e) {
            }
        }

        //Can edit action or Can configure Risks and the session is open
        $_operations['can_edit_risk_statement_source_action'] = (!$_sessionClosed && $_operations['can_edit_risk_statement_source_action']) || $_canConfigureRisks;
        //Can delete action or Can configure Risks and the session is open
        $_operations['can_delete_risk_statement_source_action'] = (!$_sessionClosed && $_operations['can_delete_risk_statement_source_action']) || $_canConfigureRisks;
        //Can edit action or Can configure Risks and the session is open or the date is not ended
        $_operations['can_edit_risk_statement_source_action_progress'] = ((!$_sessionClosed || !$_dateEnded) && $_operations['can_edit_risk_statement_source_action_progress']) || $_canConfigureRisks;
        //Can edit action or Can configure Risks and the session is open or the date is not ended
        $_operations['can_edit_risk_statement_source_action_assignee'] = ((!$_sessionClosed || !$_dateEnded) && $_operations['can_edit_risk_statement_source_action_assignee']) || $_canConfigureRisks;
        //Can edit action or Can configure Risks
        $_operations['can_edit_risk_statement_source_action_extend'] = $_operations['can_edit_risk_statement_source_action_extend'] || $_canConfigureRisks;

        return $_operations;
    }

    public static function canManageRisksStatementsSourcesScores($_risksStatementSourceScore, $_risksStatementSource = null, $_risksStatement = null)
    {
        $_operations = array(
            'can_edit_risk_statement_source_score' => false,
            'can_delete_risk_statement_source_score' => false,
        );
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_risksStatementSourceScore == null)
            return $_operations;

        if (!is_array($_risksStatementSourceScore))
            $_risksStatementSourceScore = $_risksStatementSourceScore->toArray();

        $_canConfigureRisks = self::canConfigureRisks();
        $_submitter = $_risksStatementSourceScore['submitted_by'] == $_logged_user->user_id;

        //Submitted by or Can configure Risks
        $_operations['can_edit_risk_statement_source_score'] = $_submitter || $_canConfigureRisks;
        //Submitted by or Can configure Risks
        $_operations['can_delete_risk_statement_source_score'] = $_submitter || $_canConfigureRisks;

        //To inherit the Risk Statement Source rights
        $_checkForParentRights = false;
        foreach ($_operations as $_operation) {
            if (!$_operation)
                $_checkForParentRights = true;
        }
        if ($_checkForParentRights && $_risksStatementSourceScore['risk_statement_source_id'] != null) {
            try {
                if ($_risksStatementSource == null) {
                    $_risksStatementSourceMapper = new Model_Mapper_RisksStatementsSources();
                    $_risksStatementSource = $_risksStatementSourceMapper->fetchOne(array('id' => $_risksStatementSourceScore['risk_statement_source_id']));
                }
                $_risksStatementSourceRights = self::canManageRisksStatementsSources($_risksStatementSource, $_risksStatement);

                if (!$_operations['can_edit_risk_statement_source_score'])
                    $_operations['can_edit_risk_statement_source_score'] = $_risksStatementSourceRights['can_edit_risk_statement_source'];
                if (!$_operations['can_delete_risk_statement_source_score'])
                    $_operations['can_delete_risk_statement_source_score'] = $_risksStatementSourceRights['can_delete_risk_statement_source'];
            } catch (Exception $e) {
            }
        }

        return $_operations;
    }

    /**
     * @param $_report Model_Report | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** Type is flagship and can edit/view flagship
     ** Type is cluster and can edit/view actionsite (and flagship which included in self::canEditActionsite)
     ** Type is product and can edit product (and flagship/actionsite which included in self::canEditProduct and self::canEditActionsite)
     */
    public static function canEditEntityReport($_report)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_report == null || $_report->entity_class == null || $_report->entity_id == null)
            return false;
        if (self::isAdmin())
            return true;

        try {
            if ($_report->entity_class == 'flagship') {
                $_flagshipMapper = new Model_Mapper_Flagship();
                $_flagship = $_flagshipMapper->fetchOne(array('flagship_id' => $_report->entity_id));
                return self::canViewFlagship($_flagship) || self::canEditFlagship($_flagship);
            } elseif ($_report->entity_class == 'cluster') {
                $_actionsiteMapper = new Model_Mapper_Actionsite();
                $_actionsite = $_actionsiteMapper->fetchOne(array('actionsite_id' => $_report->entity_id));
                return self::canViewActionsite($_actionsite) || self::canEditActionsite($_actionsite);
            } elseif ($_report->entity_class == 'product') {
                $_flagshipActivityMapper = new Model_Mapper_FlagshipActivity();
                $_flagshipActivity = $_flagshipActivityMapper->fetchOne(array('flagship_activity_id' => $_report->entity_id));
                $_actionsite = $_flagshipActivity->actionsite;

                $_PMOusersArray = array();
                if ($_logged_user->isMemberOf(18)) // PMO
                    $_PMOusersArray = $_logged_user->partner_users;
                return self::canEditProduct($_flagshipActivity, $_actionsite, $_PMOusersArray);
            }
        } catch (Exception $e) {
        }
        return false;
    }

    /**
     * @param $_report Model_Report | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** Type is flagship and CRP Admin and the flagship is under one of their CRPs
     ** Type is cluster and can edit/view flagship
     ** Type is product and can edit/view actionsite (and flagship which included in self::canEditActionsite)
     */
    public static function canApproveEntityReport($_report)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || $_report == null)
            return false;
        if (self::isAdmin())
            return true;

        $crps_array = array();
        if (self::isCRPAdmin()) {
            try {
                $crps_array = Zend_Json::decode($_logged_user->crp_admin);
            } catch (Exception $e) {
                $crps_array = array();
            }
            $crps_array[] = -15;
            $crps_array = array_filter($crps_array);
        }

        try {
            if ($_report->entity_class == 'flagship') { //CRP Admin
                $_flagshipMapper = new Model_Mapper_Flagship();
                $_flagship = $_flagshipMapper->fetchOne(array('flagship_id' => $_report->entity_id));
                if (in_array($_flagship->partner_id, $crps_array))
                    return true;
            } elseif ($_report->entity_class == 'cluster') {
                $_actionsiteMapper = new Model_Mapper_Actionsite();
                $_actionsite = $_actionsiteMapper->fetchOne(array('actionsite_id' => $_report->entity_id));

                $_flagship = $_actionsite->flagship;
                return self::canEditFlagship($_flagship) || self::canViewFlagship($_flagship);
            } elseif ($_report->entity_class == 'product') {
                $_flagshipActivityMapper = new Model_Mapper_FlagshipActivity();
                $_flagshipActivity = $_flagshipActivityMapper->fetchOne(array('flagship_activity_id' => $_report->entity_id));

                $_actionsite = $_flagshipActivity->actionsite;
                return self::canViewActionsite($_actionsite) || self::canEditActionsite($_actionsite);
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $_report
     * @return bool
     * TRUE if the logged-in user is:
     ** Can approve entity report self::canApproveEntityReport
     */
    public static function canDeleteEntityReport($_report)
    {
        if (self::canEditEntityReport($_report))
            return true;
        elseif (self::canApproveEntityReport($_report))
            return true;
        return false;
    }

    public static function institutionalAdminCanManageBlog($blog_id)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user || !self::isInstitutionalAdmin())
            return false;

        if (!($blog_id > 0))
            return true;

        $adapter = Zend_Db_Table::getDefaultAdapter();
        $query = $adapter->select()
            ->from('tbl_blog', 'blog_id')
            ->joinLeft('tbl_blog_projects', 'tbl_blog_projects.blog_id = tbl_blog.blog_id', '')
            ->joinLeft('tbl_project', 'tbl_project.project_id = tbl_blog_projects.project_id', '')
            ->joinLeft('tbl_flagship_activity', 'tbl_flagship_activity.flagship_activity_id = tbl_blog_projects.flagship_activity_id', '')
            ->joinLeft('tbl_user_profile AS tbl_user_profile_product_leaders', 'tbl_user_profile_product_leaders.profile_id = tbl_flagship_activity.focalpoint_id OR tbl_user_profile_product_leaders.profile_id = tbl_flagship_activity.coleader_id', '')
            ->joinLeft('tbl_user_profile', 'tbl_user_profile.profile_id = coalesce(tbl_blog.contact_person, tbl_blog.author)', '')
            ->where("tbl_project.implemented_by = $_logged_user->organization_id OR tbl_user_profile_product_leaders.partner_id = $_logged_user->organization_id OR tbl_user_profile.partner_id = $_logged_user->organization_id")
            ->where('tbl_blog.blog_id = (?)', $blog_id);
        return !empty($adapter->fetchCol($query));
    }

    public static function canEditBlog($_blogEntity, $_isOutcomeStory, $_blogCrpsArray = null)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;
        if (self::isInstitutionalAdmin())
            return self::institutionalAdminCanManageBlog($_blogEntity->blog_id);

        $_author = $_blogEntity->author;
        $_contact_person = $_blogEntity->contact_person;
        $_user_id = $_blogEntity->user_id;
        $_reviewer = $_blogEntity->reviewer;

        if ($_logged_user->user_id == $_author || $_logged_user->user_id == $_user_id || $_logged_user->user_id == $_reviewer || $_logged_user->user_id == $_contact_person)
            return true;

        if (self::canPublishBlog($_blogEntity, $_isOutcomeStory, $_blogCrpsArray))
            return true;
        return false;
    }

    public static function canPublishBlog($_blogEntity, $_isOutcomeStory, $_blogCrpsArray = null)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        $_blogId = is_array($_blogEntity) ? $_blogEntity['blog_id'] : $_blogEntity->blog_id;

        if (self::isInstitutionalAdmin())
            return self::institutionalAdminCanManageBlog($_blogId);

        $adapter = Zend_Db_Table::getDefaultAdapter();
        $queryWorkflows = $adapter->select()
            ->from('view_blog_workflows_users', 'count(blog_id) AS total')
            ->where('blog_id = (?)', $_blogId);

        if ($_isOutcomeStory == 1)
            $queryWorkflows->where('assigned_user_id = (?)', $_logged_user->user_id);
        else
            $queryWorkflows->where('step_user = ' . $_logged_user->user_id . ' OR assigned_user_id = ' . $_logged_user->user_id);
        $workflowCollection = $adapter->fetchAll($queryWorkflows);
        if ($workflowCollection[0]['total'] > 0)
            return true;

        if ($_isOutcomeStory == 1) {
            //Outcome story reviewers MEL Admin, Center Admin if the contact person from her/his organization
            //CRP Admin and Program Manager if the story is mapper to of her/his CRPs

            $_contact_person_organization_id = is_array($_blogEntity) ? $_blogEntity['contact_person_organization_id'] : $_blogEntity->contact_person_user->organization_id;
            if (self::isCenterAdmin()) {
                if ($_logged_user->organization_id == $_contact_person_organization_id)
                    return true;
            }

            if ($_blogCrpsArray == null && !is_array($_blogEntity)) {
                $_blogCrpsArray = array();
                $_blogCrps = $_blogEntity->blog_crps;
                foreach ($_blogCrps as $_blogCrp)
                    $_blogCrpsArray[] = $_blogCrp->partner_id;
            } elseif (is_array($_blogEntity) && isset($_blogEntity['blog_crps'])) {
                $_blogCrpsArray = explode(',', $_blogEntity['blog_crps']);
            }

            if (self::isCRPAdmin()) {
                try {
                    $crps_array = Zend_Json::decode($_logged_user->crp_admin);
                } catch (Exception $e) {
                    $crps_array = array();
                }
                $crps_array = array_filter($crps_array);

                if (count(array_intersect($_blogCrpsArray, $crps_array)) > 0)
                    return true;
            }
            if (self::isMemberOf(20)) { //Program Manager
                $_program_manager_type = $_logged_user->profile_entity->program_manager_type;
                if (($_program_manager_type == 1 || $_program_manager_type == 3) && $_logged_user->organization_id == $_contact_person_organization_id) //for center or both
                    return true;
                if (($_program_manager_type == 2 || $_program_manager_type == 3) && count(array_intersect($_blogCrpsArray, $_logged_user->program_manager_crps)) > 0) // for crp or both
                    return true;
            }
        } else {
            $_reviewer = is_array($_blogEntity) ? $_blogEntity['reviewer'] : $_blogEntity->reviewer;
            if ($_reviewer === $_logged_user->user_id)
                return true;
        }
        return false;
    }

    public static function canEditCalendarEvent($_calendarEvent = array(), $_calendarEventId = null, $_profiles = array())
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (!isset($_calendarEvent) && isset($_calendarEventId)) {
            $_calendarEventMapper = new Model_Mapper_CalendarEvents();
            try {
                $_calendarEvent = $_calendarEventMapper->fetchOne(array('event_id' => $_calendarEventId));
            } catch (Exception $e) {
            }
        }

        if (!isset($_calendarEvent))
            return false;

        if (!is_array($_calendarEvent))
            $_calendarEvent = $_calendarEvent->toArray();
        if ($_logged_user->user_id === $_calendarEvent['submitted_by'])
            return true;

        if (!isset($_profiles)) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $_profilesQuery = $db->select()->from('tbl_user_profile', 'profile_id');
            if (App_Function_Privileges::isInstitutionalAdmin()) {
                $_profilesQuery->where('tbl_user_profile.partner_id = (?)', $_logged_user->organization_id)
                    ->where('tbl_user_profile.role = (?)', 'institutional_admin');
            } else {
                $_profilesQuery->where('tbl_user_profile.user_id = (?)', $_logged_user->profile_user_id)
                    ->where('tbl_user_profile.role != (?)', 'institutional_admin');
            }
            $_profiles = $db->fetchCol($_profilesQuery);
        }
        return in_array($_calendarEvent['submitted_by'], $_profiles);
    }

    public static function CanEditMediaCenter($_mediaCenterEntity = null, $_mediaCenterId = null, $_mediCenterMapper = null)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_mediaCenterEntity == null && ($_mediaCenterId == null || $_mediCenterMapper == null))
            return false;

        if ($_mediaCenterEntity == null) {
            try {
                $_mediaCenterEntity = $_mediCenterMapper->fetchone(array('id' => $_mediaCenterId));
            } catch (Exception $e) {
                return false;
            }
        }

        if ($_mediaCenterEntity->organization_id != null)
            return self::isInstitutionalAdmin() && $_logged_user->organization_id == $_mediaCenterEntity->organization_id;

        return $_logged_user->user_id == $_mediaCenterEntity->submitted_by;
    }

    public static function canEditTeams($_teamEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;
        if (self::isCenterAdmin() && $_logged_user->organization_id == $_teamEntity->partner_id)
            return true;
        if ($_logged_user->user_id == $_teamEntity->teamlead_id)
            return true;
        return false;
    }
    public static function canViewTeam()
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        return false;
    }
    public static function canDeleteTeams($_teamEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;
        if (self::isCenterAdmin() && $_logged_user->organization_id == $_teamEntity->partner_id)
            return true;
        return false;
    }

    /**
     * @param $_training Model_FlagshipActivityTraining | App_Model_ModelAbstract
     * @param $_trainingId int
     * @param $_activity Model_FlagshipActivity | App_Model_ModelAbstract
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** Add new
     ** Supervisor
     ** The one who submitted it
     ** Can edit project/product
     ** CapDev focal point for one of the supervisors
     ** Can approve CapDev self::CanApproveCapDev
     */
    public static function CanEditCapDev($_training, $_trainingId, $_activity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        //Add new
        if ($_training == null && $_trainingId == null)
            return true;

        if ($_training == null && $_trainingId != null) {
            $_trainingMapper = new Model_Mapper_FlagshipActivityTraining();
            try {
                $_training = $_trainingMapper->fetchOne(array('training_id' => $_trainingId));
            } catch (Exception $e) {
                return false;
            }
        }

        //CapDev supervisor or owner
        if (in_array($_logged_user->user_id, $_training->supervisor_ids) || $_logged_user->user_id == $_training->owner_user_id)
            return true;

        //CapDev focal point
        if (self::isMemberOf(9) && in_array($_logged_user->organization_id, $_training->supervisor_organization))
            return true;

        if ($_activity == null)
            $_activity = $_training->activity;

        if ($_activity->is_project != '1' && self::canEditProduct($_activity, $_activity->actionsite))
            return true;
        if ($_activity->is_project == '1' && self::canEditProject($_activity->project, array()))
            return true;

        if (self::CanApproveCapDev($_trainingId))
            return true;

        return false;
    }

    public static function CanApproveCapDev($_trainingId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        //TODO: check if view_workflow_capdev is covering all "Member of the CapDev workflow", remove the other query
        $WorkflowItemUserMapper = new Model_Mapper_WorkflowCapdev();
        $where = 'training_id = ' . $_trainingId . ' AND (step_user_id = ' . $_logged_user->user_id . ' OR step_user_id2 = ' . $_logged_user->user_id . ' OR assigned_user_id = ' . $_logged_user->user_id . ')';
        try{
            $WorkflowItemUserMapper->fetchOne($where);
            return true;
        }catch (Exception $e){
        }

        //Member of the CapDev workflow
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_flagship_activity_training_supervisors', 'COUNT(tbl_flagship_activity_training_supervisors.training_id) AS count')
            ->join('tbl_user_profile', 'tbl_user_profile.profile_id = tbl_flagship_activity_training_supervisors.profile_id', '')
            ->join('tbl_workflow', 'tbl_workflow.partner_id = tbl_user_profile.partner_id AND tbl_workflow.type = 3', '')
            ->join('tbl_workflow_steps', 'tbl_workflow_steps.workflow_id = tbl_workflow.workflow_id', '')
            ->joinLeft('tbl_workflow_step_users', 'tbl_workflow_step_users.step_id = tbl_workflow_steps.step_id', '')
            ->joinLeft('tbl_user_profile AS tbl_user_profile_2', '(tbl_user_profile_2.profile_id IN
                                                            (SELECT tbl_user_group.user_id
                                                            FROM tbl_user_group
                                                            WHERE (tbl_user_group.group_id = tbl_workflow_step_users.group_id)) AND
                                                           (tbl_user_profile.partner_id = tbl_user_profile_2.partner_id))', '')
            ->joinLeft('tbl_capdev_workflow_user_assigned', 'tbl_capdev_workflow_user_assigned.item_id = tbl_flagship_activity_training_supervisors.training_id AND tbl_capdev_workflow_user_assigned.step_id = tbl_workflow_steps.step_id', '')
            ->where('tbl_flagship_activity_training_supervisors.training_id = (?)', $_trainingId)
            ->where('tbl_workflow_step_users.user_id = ' . $_logged_user->user_id . ' OR tbl_user_profile_2.profile_id = ' . $_logged_user->user_id . ' OR tbl_capdev_workflow_user_assigned.assigned_user_id = ' . $_logged_user->user_id);
        if ($db->fetchRow($_query)['count'] > 0)
            return true;

        return false;
    }

    public static function canCancelCapDev($_trainingId)
    {
        $_logged_user = self::getLoggedUser();
        try {
            $_trainingMapper = new Model_Mapper_FlagshipActivityTraining();
            $_trainingEntity = $_trainingMapper->fetchOne(array('training_id' => $_trainingId));

            $_trainingCanceled = $_trainingEntity->status == 4;
            $_isSupervisor = in_array($_logged_user->user_id, $_trainingEntity->supervisor_ids);
            $_isFocalPoint = App_Function_Privileges::isMemberOf(9) && in_array($_logged_user->organization_id, $_trainingEntity->supervisor_organization);

            return (App_Function_Privileges::isAdmin() || $_isFocalPoint || $_isSupervisor) && !$_trainingCanceled;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $_training Model_FlagshipActivityTraining | App_Model_ModelAbstract
     * @param $_trainingId int
     * @param $_statusCheckOnly bool
     * @return bool
     * TRUE if the logged-in user is:
     ** MEL Admin
     ** can edit/approve CapDev and the CapDev is draft or revision requested or not reviewed yet
     */
    public static function CanDeleteCapDev($_training, $_trainingId, $_statusCheckOnly)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if (!$_statusCheckOnly) {
            if ($_training == null && $_trainingId != null) {
                $_trainingMapper = new Model_Mapper_FlagshipActivityTraining();
                try {
                    $_training = $_trainingMapper->fetchOne(array('training_id' => $_trainingId));
                } catch (Exception $e) {
                    return false;
                }
            }
            if (!self::CanEditCapDev($_training, $_trainingId, null))
                return false;
        }

        if ($_training->is_draft == '1' || $_training->status == 5 || $_training->approval_action_taken != '1')
            return true;

        return false;
    }

    /**
     * @param $_iaReport Model_IaReport | App_Model_ModelAbstract | array
     * @param $_iaReportId integer | null
     * @return bool
     */
    public static function canViewIAReport($_iaReport, $_iaReportId = null)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::canReviewIAReport(null)) {
            if ($_iaReport == null && $_iaReportId != null) {
                $_iaReportMapper = new Model_Mapper_IaReport();
                try {
                    $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
                } catch (Exception $e) {
                    return false;
                }
            }
            if ($_iaReport->is_draft == '0')
                return true;
        }

        if (self::canEditIAReport($_iaReport, $_iaReportId, false))
            return true;
        return false;
    }

    /**
     * @param $_iaReport Model_IaReport | App_Model_ModelAbstract | array
     * @param $_iaReportId integer | null
     * @param $_fullEdit bool
     * @return bool
     */
    public static function canEditIAReport($_iaReport, $_iaReportId, $_fullEdit)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if ($_iaReport == null && $_iaReportId != null) {
            $_iaReportMapper = new Model_Mapper_IaReport();
            try {
                $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            } catch (Exception $e) {
                return false;
            }
        } elseif ($_iaReport == null && $_iaReportId == null && self::isMemberOf(11)) { //User group 11 Intellectual Property Focal Point //Add new
            return true;
        } elseif ($_iaReport == null && $_iaReportId == null) { //Add new
            return false;
        }

        if (!is_array($_iaReport))
            $_iaReport = $_iaReport->toArray();

        if ($_fullEdit && $_iaReport['is_draft'] == '0') {
            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            //If the report is submitted, to be able to re-edit it it should be reviewed and sent back to Center (user_group_to = User group 11 Intellectual Property Focal Point)
            try {
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array('ia_report_id' => $_iaReport['ia_report_id'], 'is_draft' => '0'), 'review_id DESC');
                if ($_iaReportReviewLast->user_group_to != 11)
                    return false;
            } catch (Exception $e) {
                return false;
            }
        }

        if (self::isAdmin())
            return true;

        if ($_iaReport['partner_id'] == $_logged_user->organization_id && self::isMemberOf(11)) //User group 11 Intellectual Property Focal Point
            return true;

        if (self::isIaReportContact($_iaReport['partner_id']))
            return true;

        return false;
    }

    /**
     * @param $_iaReportAgreement Model_IaReportAgreements | App_Model_ModelAbstract | array
     * @param $_iaAgreementId integer | null
     * @param $_iaReport Model_IaReport | App_Model_ModelAbstract | array
     * @param $_fullEdit bool
     * @return bool
     */
    public static function canEditIAReportAgreement($_iaReportAgreement, $_iaAgreementId, $_iaReport, $_fullEdit)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if ($_iaReportAgreement == null && $_iaAgreementId != null) {
            $_iaReportAgreementMapper = new Model_Mapper_IaReportAgreements();
            try {
                $_iaReportAgreement = $_iaReportAgreementMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
            } catch (Exception $e) {
                return false;
            }
        }

        if ($_iaReport == null)
            $_iaReport = $_iaReportAgreement->ia_report;
        if (!is_array($_iaReport))
            $_iaReport = $_iaReport->toArray();

        if (!is_array($_iaReportAgreement))
            $_iaReportAgreement = $_iaReportAgreement->toArray();

        if ($_iaReportAgreement['ia_agreement_id'] == null)
            return self::canEditIAReport($_iaReport, null, $_fullEdit);

        if ($_fullEdit && $_iaReport['is_draft'] == '0') {
            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            //If the report is submitted, to be able to re-edit it it should be reviewed and sent back to Center (user_group_to = User group 11 Intellectual Property Focal Point)
            try {
                //The review is submitted for center to revise
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array('ia_agreement_id' => $_iaReportAgreement['ia_agreement_id'], 'is_draft' => '0'), 'review_id DESC');
                if ($_iaReportReviewLast->user_group_to != 11)
                    return false;
            } catch (Exception $e) {
                try {
                    //The center revise is draft (for the newly added items)
                    $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array('ia_agreement_id' => $_iaReportAgreement['ia_agreement_id'], 'is_draft' => '1'), 'review_id DESC');
                    if ($_iaReportReviewLast->user_group_from != 11)
                        return false;
                } catch (Exception $e) {
                    return false;
                }
            }
        }

        if (self::isAdmin())
            return true;

        if ($_iaReport['partner_id'] == $_logged_user->organization_id && self::isMemberOf(11)) //User group 11 Intellectual Property Focal Point
            return true;

        if (self::isIaReportContact($_iaReport['partner_id']))
            return true;

        return false;
    }

    /**
     * @param $_iaReportAgreement Model_IaReportAgreements | App_Model_ModelAbstract | array
     * @param $_iaAgreementId integer | null
     * @param $_iaReport Model_IaReport | App_Model_ModelAbstract | array
     * @param $_fullEdit bool
     * @return bool
     */
    public static function canEditIAReportAgreementPublicDisclosure($_iaReportAgreementPublicDisclosure, $_iaAgreementPublicDisclosureId, $_iaReport, $_fullEdit)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if ($_iaReportAgreementPublicDisclosure == null && $_iaAgreementPublicDisclosureId != null) {
            $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
            try {
                $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));
            } catch (Exception $e) {
                return false;
            }
        }

        if ($_iaReport == null)
            $_iaReport = $_iaReportAgreementPublicDisclosure->ia_report;
        if (!is_array($_iaReport))
            $_iaReport = $_iaReport->toArray();

        if (!is_array($_iaReportAgreementPublicDisclosure))
            $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosure->toArray();

        if ($_iaReportAgreementPublicDisclosure['ia_agreement_public_disclosure_id'] == null)
            return self::canEditIAReport($_iaReport, null, $_fullEdit);

        if ($_fullEdit && $_iaReport['is_draft'] == '0') {
            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            //If the report is submitted, to be able to re-edit it it should be reviewed and sent back to Center (user_group_to = User group 11 Intellectual Property Focal Point)
            try {
                //The review is submitted for center to revise
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure['ia_agreement_public_disclosure_id'], 'is_draft' => '0'), 'review_id DESC');
                if ($_iaReportReviewLast->user_group_to != 11)
                    return false;
            } catch (Exception $e) {
                try {
                    //The center revise is draft (for the newly added items)
                    $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure['ia_agreement_public_disclosure_id'], 'is_draft' => '1'), 'review_id DESC');
                    if ($_iaReportReviewLast->user_group_from != 11)
                        return false;
                } catch (Exception $e) {
                    return false;
                }
            }
        }

        if (self::isAdmin())
            return true;

        if ($_iaReport['partner_id'] == $_logged_user->organization_id && self::isMemberOf(11)) //User group 11 Intellectual Property Focal Point
            return true;

        if (self::isIaReportContact($_iaReport['partner_id']))
            return true;

        return false;
    }

    /**
     * @param $_partnerId int
     * @return bool
     */
    public static function isIaReportContact($_partnerId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_ia_report', 'ia_report_id')
            ->join('tbl_ia_report_contacts', 'tbl_ia_report_contacts.ia_report_id = tbl_ia_report.ia_report_id', '')
            ->where('tbl_ia_report_contacts.profile_id = (?)', $_logged_user->user_id);
        if ($_partnerId != null)
            $_query->where('tbl_ia_report.partner_id = (?)', $_partnerId);
        return count($db->fetchCol($_query)) > 0;
    }

    /**
     * @param $_userGroupTo int
     * @return bool
     */
    public static function canReviewIAReport($_userGroupTo)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isAdmin())
            return true;

        //User group 12: Intellectual Property Focal Point (PMU)
        //User group 28: MLS
        //User group 29: SCIPG
        if ($_userGroupTo === null)
            return self::isMemberOf(array(12, 28, 29));

        return App_Function_Privileges::isMemberOf($_userGroupTo);
    }

    /**
     * @param $_userGroupTo int
     * @return bool
     */
    public static function canReviewIAReportAgreement($_userGroupTo)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isAdmin())
            return true;

        return App_Function_Privileges::isMemberOf($_userGroupTo);
    }

    public static function CanEditComment($_comment, $_commentId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if ($_comment == null && $_commentId != null) {
            $_reviewCommentsMapper = new Model_Mapper_ReviewComments();
            try {
                $_comment = $_reviewCommentsMapper->fetchOne(array('comment_id' => $_commentId));
            } catch (Exception $e) {
                return false;
            }
        } elseif ($_comment == null && $_commentId == null) {
            return true;
        }

        if (!is_array($_comment))
            $_comment = $_comment->toArray();
        return $_comment['profile_id'] == $_logged_user->user_id;
    }

    public static function isKnowledgeCuratorQAP()
    {
        $_wosQuotaMapper  = new Model_Mapper_WosInstitutionsQuota();
        try {
            $_wosQuotaMapper->fetchOne(array('organization_id' => self::getLoggedUser()->organization_id));
            return self::isMemberOf(33); //Group ID 33: Knowledge curator (QAP)
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $_projectEntity Model_Project | App_Model_ModelAbstract
     * @return bool
     */
    public static function CanEditActivityMilestone($_projectEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        $_PMOusersArray = array();
        if (self::isMemberOf(18))
            $_PMOusersArray = $_logged_user->partner_users;

        return self::canEditProject($_projectEntity, $_PMOusersArray);
    }

    /**
     * @param $_projectEntity Model_Project | App_Model_ModelAbstract
     * @param $_flagshipActivityId int
     * @return bool
     */
    public static function CanEditActivityRisk($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                $_projectEntity = null;
            }
        }
        if ($_projectEntity != null) {
            $_PMOusersArray = array();
            if (self::isMemberOf(18))
                $_PMOusersArray = $_logged_user->partner_users;

            return self::canEditProject($_projectEntity, $_PMOusersArray);
        }

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | App_Model_ModelAbstract
     * @param $_flagshipActivityId int
     * @return bool
     */
    public static function CanViewDonorReporting($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

//        $_db = Zend_Db_Table::getDefaultAdapter();
//
//        $_query = $_db->select()
//            ->from('tbl_flagship_activity_partner', 'tbl_flagship_activity_co_partner.co_partner_id')
//            ->join('tbl_flagship_activity_co_partner', 'tbl_flagship_activity_partner.flagship_activity_partner_id = tbl_flagship_activity_co_partner.flagship_activity_partner_id', '')
//            ->where('tbl_flagship_activity_partner.flagship_activity_id = (?)', $_flagshipActivityId)
//            ->where('tbl_flagship_activity_co_partner.co_partner_id = (?)', $_logged_user->user_id);
//        $_users = $_db->fetchCol($_query);
//
//        if (count($_users) > 0)
//            return true;

        if (self::CanEditDonorReporting($_projectEntity, $_flagshipActivityId))
            return true;

        if (self::CanReviewDonorReporting($_projectEntity, $_flagshipActivityId))
            return true;

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | App_Model_ModelAbstract
     * @param $_flagshipActivityId int
     * @return bool
     */
    public static function CanEditDonorReporting($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                $_projectEntity = null;
            }
        }
        if ($_projectEntity != null)
            return self::canEditProject($_projectEntity, array());
        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract | Model_ProjectSimpleDetails
     * @param $_flagshipActivityId int
     * @return bool
     */
    public static function CanReviewDonorReporting($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                $_projectEntity = null;
            }
        }
        if ($_projectEntity != null) {
            if (is_a($_projectEntity, 'Model_ProjectSimpleDetails')) {
                try {
                    $_projectMapper = new Model_Mapper_ProjectIndexData();
                    $_projectEntity = $_projectMapper->fetchOne(array('project_id' => $_projectEntity->project_id));
                } catch (Exception $e) {
                    return false;
                }
            }

            if (self::IsDonorProjectOfficer($_projectEntity))
                return true;

            if (self::IsDonorOfficer($_projectEntity))
                return true;
        }
        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract
     * @return bool
     */
    public static function IsDonorOfficer($_projectEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isMemberOf(31)) { //user group 31: Donor officer
            $_donors = self::GetAllProjectDonors($_projectEntity);

            if (in_array($_logged_user->organization_id, $_donors))
                return true;
        }

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract
     * @return bool
     */
    public static function IsDonorProjectOfficer($_projectEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        $_donorContacts = $_projectEntity->predefined_donor_contact;
        if (!is_array($_donorContacts))
            try {
                $_donorContacts = Zend_Json::decode($_donorContacts);
            } catch (Exception $e) {
            }
        if (!is_array($_donorContacts))
            $_donorContacts = array();

        $_viaDonorContacts = $_projectEntity->predefined_donor_contact_via;
        if (!is_array($_viaDonorContacts))
            try {
                $_viaDonorContacts = Zend_Json::decode($_viaDonorContacts);
            } catch (Exception $e) {
            }
        if (!is_array($_viaDonorContacts))
            $_viaDonorContacts = array();

        $_donorContacts = array_merge($_donorContacts, $_viaDonorContacts);

        if (in_array($_logged_user->user_id, $_donorContacts))
            return true;
        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract | Model_ProjectSimpleDetails
     * @param $_flagshipActivityId int
     * @param $_flagshipActivityPartnerId int | array
     * @return bool
     */
    public static function CanEditPrimaBudget($_projectEntity, $_flagshipActivityId, $_flagshipActivityPartnerId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                $_projectEntity = null;
            }
        }

        if ($_projectEntity != null) {
            if (self::CanReviewPrimaBudget($_projectEntity, null))
                return true;

            $_PMOusersArray = array();
            if (self::isMemberOf(18))
                $_PMOusersArray = $_logged_user->partner_users;

            if (self::canEditProject($_projectEntity, $_PMOusersArray))
                return true;

            if ($_flagshipActivityPartnerId != null) {
                try {
                    $_activityPartnerContactMapper = new Model_Mapper_FlagshipActivityPartnerContact();
                    $_activityPartnerContactMapper->fetchOne(array(
                        'flagship_activity_partner_id' => $_flagshipActivityPartnerId,
                        'contact_id' => self::getLoggedUser()->user_id
                    ));
                    return true;
                } catch (Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract | Model_ProjectSimpleDetails
     * @param $_flagshipActivityId int
     * @return bool
     */
    public static function CanReviewPrimaBudget($_projectEntity, $_flagshipActivityId)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;
        if (self::isAdmin())
            return true;

        if ($_projectEntity == null && $_flagshipActivityId != null) {
            try {
                $_projectMapper = new Model_Mapper_Project();
                $_projectEntity = $_projectMapper->fetchOne(array('activity_id' => $_flagshipActivityId));
            } catch (Exception $e) {
                $_projectEntity = null;
            }
        }
        if ($_projectEntity != null)
            if(self::IsProjectFinancialOfficer($_projectEntity))
                return true;

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract
     * @return bool
     */
    public static function IsProjectFinancialOfficer($_projectEntity)
    {
        $_logged_user = self::getLoggedUser();
        if (!$_logged_user)
            return false;

        if (self::isMemberOf(3)) { //user group  3: Finance Focal Point
            $_donors = self::GetAllProjectDonors($_projectEntity);

            if (in_array($_logged_user->organization_id, $_donors))
                return true;
        }

        return false;
    }

    /**
     * @param $_projectEntity Model_Project | Model_ProjectIndexData | App_Model_ModelAbstract
     * @return array
     */
    public static function GetAllProjectDonors($_projectEntity)
    {
        try {
            $_donors = $_projectEntity->predefined_donor;
        } catch (Exception $e) {
            try {
                $_donors = $_projectEntity->predefined_donors;
            } catch (Exception $e) {
                return array();
            }
        }
        if (!is_array($_donors))
            try {
                $_donors = Zend_Json::decode($_donors);
            } catch (Exception $e) {
            }
        if (!is_array($_donors))
            $_donors = array();

        $_viaDonors = $_projectEntity->predefined_donor_via;
        if (!is_array($_viaDonors))
            try {
                $_viaDonors = Zend_Json::decode($_viaDonors);
            } catch (Exception $e) {
            }
        if (!is_array($_viaDonors))
            $_viaDonors = array();

        $_donors = array_merge($_donors, $_viaDonors);
        $_donors = array_values(array_unique(array_filter($_donors)));
        return $_donors;
    }
}
