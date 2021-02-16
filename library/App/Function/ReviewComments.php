<?php

class App_Function_ReviewComments
{
    /**
     * @param $_formId string
     * @param $_tableName string
     * @return string|array|null when $_formId is sent it will return the table name, when the $_tableName is sent it
     * will return the information array, else it will return null
     */
    public static function GetFormRelatedTable($_formId, $_tableName)
    {
        $_mappingArray = array(
            'tbl_ia_report_review_comments' => array(
                'form' => 'ia_form',
                'main_table' => 'tbl_ia_report',
                'main_mapper' => new Model_Mapper_IaReport(),
                'primary_column' => 'ia_report_id',
                'review_primary_column' => 'ia_report_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_crp_review_comments' => array(
                'form' => 'ia_report_crp_form',
                'main_table' => 'tbl_ia_report_crp',
                'main_mapper' => new Model_Mapper_IaReportCrp(),
                'primary_column' => 'ia_report_crp_id',
                'review_primary_column' => 'ia_report_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_management_documents_review_comments' => array(
                'form' => 'ia_management_document_form',
                'main_table' => 'tbl_ia_report_management_documents',
                'main_mapper' => new Model_Mapper_IaReportManagementDocuments(),
                'primary_column' => 'ia_management_document_id',
                'review_primary_column' => 'ia_report_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_portfolio_documents_review_comments' => array(
                'form' => 'ia_portfolio_document_form',
                'main_table' => 'tbl_ia_report_portfolio_documents',
                'main_mapper' => new Model_Mapper_IaReportPortfolioDocuments(),
                'primary_column' => 'ia_portfolio_document_id',
                'review_primary_column' => 'ia_report_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_agreements_review_comments' => array(
                'form' => 'ia_agreement_form',
                'main_table' => 'tbl_ia_report_agreements',
                'main_mapper' => new Model_Mapper_IaReportAgreements(),
                'primary_column' => 'ia_agreement_id',
                'review_primary_column' => 'ia_agreement_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_agreement_public_disclosure_review_comments' => array(
                'form' => 'ia_agreement_public_disclosure_form',
                'main_table' => 'tbl_ia_report_agreement_public_disclosure',
                'main_mapper' => new Model_Mapper_IaReportAgreementPublicDisclosure(),
                'primary_column' => 'ia_agreement_public_disclosure_id',
                'review_primary_column' => 'ia_agreement_public_disclosure_id',
                'review_table_name' => 'tbl_ia_report_review'
            ),
            'tbl_ia_report_updates_review_comments' => array(
                'form' => 'ia_report_updates_form',
                'main_table' => 'tbl_ia_report_updates',
                'main_mapper' => new Model_Mapper_IaReportUpdates(),
                'primary_column' => 'ia_report_update_id',
                'review_primary_column' => 'ia_report_id',
                'review_table_name' => 'tbl_ia_report_review'
            )
        );

        if ($_formId != null) {
            foreach ($_mappingArray as $_tableName => $_info) {
                if ($_info['form'] === $_formId)
                    return $_tableName;
            }
        } else {
            if (isset($_mappingArray[$_tableName])) {
                return $_mappingArray[$_tableName];
            }
        }
        return null;
    }

    /**
     * @param $_type string
     * @param $_userType array
     * @return array
     */
    public static function GetUserPossibleShareWithGroups($_type, $_userTypes)
    {
        if ($_type === 'ia') {
            /**
             * User groups
             * 11: Intellectual Property Focal Point
             * 12: Intellectual Property Focal Point (PMU)
             * 28: ABS
             * 29: SCIPG
             */

            $_shareWith = array(
                11 => array(
                    array(
                        'group_id' => 11,
                        'label' => 'Center',
                        'popover' => 'Share with Center',
                        'default' => true,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 12,
                        'label' => 'SMO',
                        'popover' => 'Share with SMO',
                        'default' => false,
                        'disabled' => false
                    )
                ),
                12 => array(
                    array(
                        'group_id' => 12,
                        'label' => 'SMO',
                        'popover' => 'Share with SMO',
                        'default' => true,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 11,
                        'label' => 'Center',
                        'popover' => 'Share with Center',
                        'default' => false,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 28,
                        'label' => 'ABS',
                        'popover' => 'Share with ABS',
                        'default' => false,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 29,
                        'label' => 'SCIPG',
                        'popover' => 'Share with SCIPG',
                        'default' => false,
                        'disabled' => false
                    )
                ),
                28 => array(
                    array(
                        'group_id' => 28,
                        'label' => 'ABS',
                        'popover' => 'Share with ABS',
                        'default' => true,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 12,
                        'label' => 'SMO',
                        'popover' => 'Share with SMO',
                        'default' => false,
                        'disabled' => false
                    )
                ),
                29 => array(
                    array(
                        'group_id' => 29,
                        'label' => 'SCIPG',
                        'popover' => 'Share with SCIPG',
                        'default' => true,
                        'disabled' => false
                    ),
                    array(
                        'group_id' => 12,
                        'label' => 'SMO',
                        'popover' => 'Share with SMO',
                        'default' => false,
                        'disabled' => false
                    )
                )
            );

            $_possibleUserGroups = array();
            if (App_Function_Privileges::isMemberOf(11))
                $_possibleUserGroups = array_merge($_possibleUserGroups, $_shareWith[11]);
            if (App_Function_Privileges::isMemberOf(12))
                $_possibleUserGroups = array_merge($_possibleUserGroups, $_shareWith[12]);
            if (App_Function_Privileges::isMemberOf(28))
                $_possibleUserGroups = array_merge($_possibleUserGroups, $_shareWith[28]);
            if (App_Function_Privileges::isMemberOf(29))
                $_possibleUserGroups = array_merge($_possibleUserGroups, $_shareWith[29]);

            $_possibleUserGroups = array_values($_possibleUserGroups);
            return $_possibleUserGroups;
        }

        return array();
    }

    /**
     * @param $_tableName string
     * @param $_visibleCommentsQuery Zend_Db_Select
     * @return array
     */
    public static function FormFieldsHaveComments($_tableName, $_visibleCommentsQuery)
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from($_tableName, $_tableName . '.field_name')
            ->join('tbl_review_comments', 'tbl_review_comments.is_deleted = 0 AND tbl_review_comments.comment_id = ' . $_tableName . '.comment_id', '')
            ->where('tbl_review_comments.comment_id IN (?)', $_visibleCommentsQuery)
            ->group('field_name');

        return $_db->fetchCol($_query);
    }

    /**
     * @param $_mainTable string
     * @param $_primaryColumn string
     * @param $_id int
     * @return array
     */
    public static function GetAllRelatedItemsIds($_mainTable, $_primaryColumn, $_id)
    {
        $_newIds = array($_id);
        $_ids = array();

        try {
            $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

            while (count($_newIds) != count($_ids)) {
                $_ids = $_newIds;
                $_query = $_db->select()
                    ->from($_mainTable, array(
                        $_mainTable . '.' . $_primaryColumn,
                        $_mainTable . '.parent_id'
                    ))
                    ->where($_mainTable . '.' . $_primaryColumn . ' IN (?)', $_ids)
                    ->orWhere($_mainTable . '.parent_id' . ' IN (?)', $_ids);
                $_relatedIds = $_db->fetchAll($_query);
                $_newIds = array_filter(array_unique(array_merge(array_column($_relatedIds, 'parent_id'), array_column($_relatedIds, $_primaryColumn))));
            }
            $_query = $_db->select()
                ->from($_mainTable, array(
                    $_mainTable . '.' . $_primaryColumn
                ))
                ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = ' . $_mainTable . '.ia_report_id AND tbl_ia_report.is_draft != "1"', '')
                ->where($_mainTable . '.' . $_primaryColumn . ' IN (?)', $_newIds);
            $_newIds = $_db->fetchCol($_query);
            $_newIds[] = $_id;
            $_newIds = array_filter(array_unique($_newIds));
        } catch (Exception $e) {
            $_newIds = array($_id);
        }
        return $_newIds;
    }

    /**
     * @param $_tableName string
     * @param $_info array
     * @param $_itemId array
     * @param $_iaReportPartnerId int
     * @param $_type string
     * return Zend_Db_Adapter_Abstract
     */
    private static function GetItemVisibleComments($_tableName, $_primaryColumn, $_itemId, $_fieldName, $_iaReportPartnerId, $_type = 'ia')
    {
        if (empty($_itemId))
            $_itemId[] = -15;

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_visibleCommentsQuery = $_db->select()
            ->from($_tableName, $_tableName . '.comment_id')
            ->join('tbl_review_comments', 'tbl_review_comments.comment_id = ' . $_tableName . '.comment_id', '')
            ->where($_tableName . '.' . $_primaryColumn . ' IN (?)', $_itemId);

        $_where = 'tbl_review_comments.profile_id = ' . App_Function_Privileges::getLoggedUser()->user_id;
        if ($_type === 'ia') {
            $_visibleCommentsQuery
                ->joinLeft('tbl_ia_report_comments_user_groups', 'tbl_ia_report_comments_user_groups.comment_id = ' . $_tableName . '.comment_id', '')
                ->joinLeft('tbl_user_group', 'tbl_user_group.group_id = tbl_ia_report_comments_user_groups.group_id', '');
            $_where .= ' OR tbl_user_group.user_id = ' . App_Function_Privileges::getLoggedUser()->user_id;

            if (App_Function_Privileges::isIaReportContact($_iaReportPartnerId))
                $_where .= ' OR tbl_ia_report_comments_user_groups.group_id = 11';
        } else {
            $_visibleCommentsQuery
                ->joinLeft('tbl_review_comments_shared_with', 'tbl_review_comments_shared_with.comment_id = ' . $_tableName . '.comment_id AND tbl_review_comments_shared_with.type = "' . $_type . '"', '');

            $_userRoles = array();
            $_userRoles[] = -15;
            $_where .= ' OR tbl_review_comments_shared_with.shared_with IN ("' . implode('","', $_userRoles) . '")';
        }
        $_visibleCommentsQuery->where($_where);

        if ($_fieldName != null)
            $_visibleCommentsQuery->where($_tableName . '.field_name = (?)', $_fieldName);

        return array(
            'visible_comments_query' => $_visibleCommentsQuery,
            'table_name' => $_tableName
        );
    }

    /**
     * @param $_visibleCommentsQuery Zend_Db_Select
     * @param $_tableName string
     * @param $_type string
     * @return array
     */
    public static function GetAllComments($_visibleCommentsQuery, $_tableName, $_type = 'ia')
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $_query = $_db->select()
            ->from('tbl_review_comments', array(
                'tbl_review_comments.comment_id',
                $_tableName . '.field_name',
                'tbl_review_comments.modified_date',
                'comment' => 'if(tbl_review_comments.is_deleted = 1, "Deleted comment", tbl_review_comments.comment)',
                'tbl_review_comments.is_deleted',
                'commented_by' => 'concat_ws(" ", concat(trim(tbl_user_new.name), " - ", coalesce(tbl_partner.abbreviation, tbl_partner.name)), concat("(", tbl_review_comments.display_name, ")"))',
                'comment_owner' => new Zend_Db_Expr('if(tbl_review_comments.profile_id = ' . App_Function_Privileges::getLoggedUser()->user_id . ', 1, 0)')
            ))
            ->join($_tableName, $_tableName . '.comment_id = tbl_review_comments.comment_id', '')
            ->join('tbl_user_profile', 'tbl_review_comments.profile_id = tbl_user_profile.profile_id', '')
            ->join('tbl_user_new', 'tbl_user_new.user_id = tbl_user_profile.user_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_user_profile.partner_id', '')
            ->where('tbl_review_comments.comment_id IN (?)', $_visibleCommentsQuery)
            ->order('tbl_review_comments.comment_id DESC')
            ->group('tbl_review_comments.comment_id');

        if ($_type === 'ia')
            $_query->joinLeft('tbl_ia_report_comments_user_groups', 'tbl_ia_report_comments_user_groups.comment_id = tbl_review_comments.comment_id', 'group_concat(tbl_ia_report_comments_user_groups.group_id) AS user_groups');
        else
            $_query->joinLeft('tbl_review_comments_shared_with', 'tbl_review_comments_shared_with.comment_id = tbl_review_comments.comment_id', 'group_concat(tbl_review_comments_shared_with.shared_with) AS user_groups');

        $_comments = $_db->fetchAll($_query);
        foreach ($_comments as $_index => $_comment)
            $_comments[$_index]['history'] = self::GetCommentHistory($_comment['comment_id']);

        return $_comments;
    }

    /**
     * @param $_itemId int
     * @param $_fieldName string
     * @param $_formId string
     * @param $_type string
     * @param $_type string
     * @return array
     */
    public static function GetAllReviewComments($_itemId, $_fieldName, $_formId, $_type = 'ia')
    {
        $_tableName = self::GetFormRelatedTable($_formId, null);
        $_info = self::GetFormRelatedTable(null, $_tableName);
        $_mainTable = $_info['main_table'];
        $_primaryColumn = $_info['primary_column'];
        $_itemId = self::GetAllRelatedItemsIds($_mainTable, $_primaryColumn, $_itemId);

        $_partnerId = -15;
        if ($_type === 'ia') {
            $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
            try {
                $_query = $_db->select()
                    ->from($_info['main_table'], 'ia_report_id')
                    ->where($_primaryColumn . ' IN (?)', $_itemId);
                $_iaReportId = $_db->fetchCol($_query);
                $_iaReportId = count($_iaReportId) > 0 ? $_iaReportId[0] : null;

                $_iaReportMapper = new Model_Mapper_IaReport();
                $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            } catch (Exception $e) {
                return array();
            }

            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                return array();

            $_partnerId = $_iaReport->partner_id;
        }

        $_commentsQuery = self::GetItemVisibleComments($_tableName, $_primaryColumn, $_itemId, $_fieldName, $_partnerId, $_type);
        return self::GetAllComments($_commentsQuery['visible_comments_query'], $_commentsQuery['table_name'], $_type);
    }

    /**
     * @param $_formId string
     * @param $_itemId int
     * @param $_fieldName string
     * @param $_iaReportPartnerId int
     * @param $_type string
     * @return array
     */
    public static function HasReviewComments($_formId, $_itemId, $_fieldName, $_iaReportPartnerId, $_type = 'ia')
    {
        $_tableName = self::GetFormRelatedTable($_formId, null);
        $_info = self::GetFormRelatedTable(null, $_tableName);
        $_mainTable = $_info['main_table'];
        $_primaryColumn = $_info['primary_column'];
        $_itemId = self::GetAllRelatedItemsIds($_mainTable, $_primaryColumn, $_itemId);

        $_commentsQuery = self::GetItemVisibleComments($_tableName, $_primaryColumn, $_itemId, $_fieldName, $_iaReportPartnerId, $_type);
        return self::FormFieldsHaveComments($_tableName, $_commentsQuery['visible_comments_query']);
    }

    /**
     * @param $_commentId int
     * @param $_type string
     * @return mixed
     */
    public static function GetComment($_commentId, $_type = 'ia')
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_review_comments', array(
                'tbl_review_comments.comment_id',
                'tbl_review_comments.modified_date',
                'tbl_review_comments.comment',
                'tbl_review_comments.is_deleted',
                'commented_by' => 'concat_ws(" ", concat(trim(tbl_user_new.name), " - ", coalesce(tbl_partner.abbreviation, tbl_partner.name)), concat("(", tbl_review_comments.display_name, ")"))'
            ))
            ->join('tbl_user_profile', 'tbl_review_comments.profile_id = tbl_user_profile.profile_id', '')
            ->join('tbl_user_new', 'tbl_user_new.user_id = tbl_user_profile.user_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_user_profile.partner_id', '')
            ->where('tbl_review_comments.comment_id = (?)', $_commentId)
            ->group('tbl_review_comments.comment_id');

        if ($_type === 'ia')
            $_query->joinLeft('tbl_ia_report_comments_user_groups', 'tbl_ia_report_comments_user_groups.comment_id = tbl_review_comments.comment_id', 'group_concat(tbl_ia_report_comments_user_groups.group_id) AS user_groups');
        else
            $_query->joinLeft('tbl_review_comments_shared_with', 'tbl_review_comments_shared_with.comment_id = tbl_review_comments.comment_id', 'group_concat(tbl_review_comments_shared_with.shared_with) AS user_groups');
        return $_db->fetchRow($_query);
    }

    /**
     * @param $_commentId int
     * @return array
     */
    public static function GetCommentHistory($_commentId)
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_review_comments_history', array(
                'tbl_review_comments_history.history_comment_id',
                'tbl_review_comments_history.comment_id',
                'tbl_review_comments_history.created_date',
                'tbl_review_comments_history.comment',
            ))
            ->where('tbl_review_comments_history.comment_id = (?)', $_commentId)
            ->order('tbl_review_comments_history.comment_id DESC');

        return $_db->fetchAll($_query);
    }

    /**
     * @param $_commentArray array
     * @param $_tableName string
     * @param $_itemId int
     * @param $_userGroupsVisible array
     * @param $_fieldName string
     * @param $_type string
     * @return bool
     */
    public static function CreateComment($_commentArray, $_tableName, $_itemId, $_fieldName, $_userGroupsVisible, $_type = 'ia')
    {
        $_reviewCommentsMapper = new Model_Mapper_ReviewComments();
        $_commentData = array(
            'profile_id' => App_Function_Privileges::getLoggedUser()->user_id,
            'display_name' => $_commentArray['display_name'],
            'comment' => $_commentArray['comment']
        );

        $_commentId = $_reviewCommentsMapper->insert($_commentData);

        if ($_type === 'ia') {
            $_iaReportCommentsUserGroupsMapper = new Model_Mapper_IaReportCommentsUserGroups();
            foreach ($_userGroupsVisible as $_userGroupVisible)
                $_iaReportCommentsUserGroupsMapper->insert(array(
                    'comment_id' => $_commentId,
                    'group_id' => $_userGroupVisible
                ));
        } else {
            $_iaReportCommentsSharedWithMapper = new Model_Mapper_ReviewCommentsSharedWith();
            foreach ($_userGroupsVisible as $_userGroupVisible)
                $_iaReportCommentsSharedWithMapper->insert(array(
                    'comment_id' => $_commentId,
                    'type' => $_type,
                    'shared_with' => $_userGroupVisible
                ));
        }
        return self::CreateItemComment($_commentId, $_tableName, $_itemId, $_fieldName);
    }

    /**
     * @param $_commentId int
     * @param $_tableName string
     * @param $_itemId int
     * @param $_fieldName string
     * @return bool
     */
    public static function CreateItemComment($_commentId, $_tableName, $_itemId, $_fieldName)
    {
        $_mapperName = 'Model_Mapper_' . str_replace('Tbl', '', implode('', array_map('ucfirst', explode('_', $_tableName))));
        $_mapper = null;

        try {
            eval('$_mapper = new ' . $_mapperName . ';');
        } catch (Exception $e) {
            return false;
        }
        if (!$_mapper)
            return false;

        $_primaryColumn = self::GetFormRelatedTable(null, $_tableName)['primary_column'];
        if (!$_primaryColumn)
            return false;

        $_commentData = array(
            $_primaryColumn => $_itemId,
            'comment_id' => $_commentId,
            'field_name' => $_fieldName
        );

        $_mapper->insert($_commentData);
        return true;
    }

    /**
     * @param $_commentArray array
     * @param $_commentId int
     * @param $_userGroupsVisible array
     * @return bool
     * @param $_type string
     * @throws Zend_Acl_Exception
     */
    public static function UpdateComment($_commentArray, $_commentId, $_userGroupsVisible, $_type = 'ia')
    {
        $_reviewCommentsMapper = new Model_Mapper_ReviewComments();
        $_reviewCommentsHistoryMapper = new Model_Mapper_ReviewCommentsHistory();
        $_reviewComment = $_reviewCommentsMapper->fetchOne(array('comment_id' => $_commentId));

        if ($_type === 'ia')
            if (!App_Function_Privileges::CanEditComment($_reviewComment, null))
                return false;

        $_history = array(
            'comment_id' => $_reviewComment->comment_id,
            'created_date' => $_reviewComment->modified_date,
            'comment' => $_reviewComment->comment
        );
        $_reviewCommentsHistoryMapper->insert($_history);

        $_commentData = array(
            'comment' => $_commentArray['comment']
        );
        $_reviewCommentsMapper->update($_commentData, 'comment_id = ' . $_commentId);

        if ($_type === 'ia') {
            $_iaReportCommentsUserGroupsMapper = new Model_Mapper_IaReportCommentsUserGroups();
            $_iaReportCommentsUserGroupsMapper->delete('comment_id = ' . $_commentId);
            foreach ($_userGroupsVisible as $_userGroupVisible)
                $_iaReportCommentsUserGroupsMapper->insert(array(
                    'comment_id' => $_commentId,
                    'group_id' => $_userGroupVisible
                ));
        } else {
            $_iaReportCommentsSharedWithMapper = new Model_Mapper_ReviewCommentsSharedWith();
            $_iaReportCommentsSharedWithMapper->delete('comment_id = ' . $_commentId);
            foreach ($_userGroupsVisible as $_userGroupVisible)
                $_iaReportCommentsSharedWithMapper->insert(array(
                    'comment_id' => $_commentId,
                    'type' => $_type,
                    'shared_with' => $_userGroupVisible
                ));
        }
        return true;
    }

    /**
     * @param $_commentId int
     * @return bool
     */
    public static function DeleteComment($_commentId)
    {
        if (!App_Function_Privileges::CanEditComment(null, $_commentId))
            return false;
        $_reviewCommentsMapper = new Model_Mapper_ReviewComments();
        $_reviewCommentsMapper->update(array('is_deleted' => 1), 'comment_id = ' . $_commentId);
        return true;
    }

    public static function GetInputDiff($_old, $_new, $_type)
    {
        $_finalText = array();
        if ($_type === 'text') {
            $_old = preg_replace('/\s+/', ' ', trim($_old));
            $_new = preg_replace('/\s+/', ' ', trim($_new));
            $_old = explode(' ', $_old);
            $_new = explode(' ', $_new);
            $_diffArray = self::GetTextDiffArray($_old, $_new);
            foreach ($_diffArray as $_diff) {
                if (is_array($_diff)) {
                    if (!empty($_diff['removed']))
                        $_finalText[] = '<span class=\'text-diff text-diff-removed\'>' . htmlspecialchars(implode(' ', $_diff['removed']), ENT_QUOTES) . '</span>';
                    if (!empty($_diff['added']))
                        $_finalText[] = '<span class=\'text-diff text-diff-added\'>' . htmlspecialchars(implode(' ', $_diff['added']), ENT_QUOTES) . '</span>';
                } else {
                    $_finalText[] = htmlspecialchars($_diff, ENT_QUOTES);
                }
            }
            return implode(' ', $_finalText);
        } elseif ($_type === 'options') {
            if (is_array($_old) || is_array($_new)) {
                if (!is_array($_old))
                    $_old = explode(',', $_old);
                if (!is_array($_new))
                    $_new = explode(',', $_new);

                foreach ($_old as $_key => $_oldValue)
                    $_old[$_key] = preg_replace('/\s+/', ' ', trim($_oldValue));

                foreach ($_new as $_newValue) {
                    $_newValue = preg_replace('/\s+/', ' ', trim($_newValue));
                    $_oldIndex = array_search($_newValue, $_old);
                    if ($_oldIndex === false) {
                        $_finalText[] = '<span class=\'text-diff text-diff-added\'>' . htmlspecialchars($_newValue, ENT_QUOTES) . '</span>';
                    } else {
                        unset($_old[$_oldIndex]);
                        $_finalText[] = htmlspecialchars($_newValue, ENT_QUOTES);
                    }
                }
                foreach ($_old as $_oldValue)
                    $_finalText[] = '<span class=\'text-diff text-diff-removed\'>' . htmlspecialchars($_oldValue, ENT_QUOTES) . '</span>';
                return '- ' . implode('<br>- ', $_finalText);
            } else {
                $_old = preg_replace('/\s+/', ' ', trim($_old));
                $_new = preg_replace('/\s+/', ' ', trim($_new));
                if ($_old !== $_new) {
                    $_finalText[] = '<span class=\'text-diff text-diff-removed\'>' . htmlspecialchars($_old, ENT_QUOTES) . '</span>';
                    $_finalText[] = '<span class=\'text-diff text-diff-added\'>' . htmlspecialchars($_new, ENT_QUOTES) . '</span>';
                    return '- ' . implode('<br>- ', $_finalText);
                }
            }
        }
        return null;
    }

    public static function GetTextDiffArray($_old, $_new)
    {
        $_matrix = array();
        $_maxlen = 0;
        foreach ($_old as $_oindex => $_ovalue) {
            $_nkeys = array_keys($_new, $_ovalue);
            foreach ($_nkeys as $_nindex) {
                $_matrix[$_oindex][$_nindex] = isset($_matrix[$_oindex - 1][$_nindex - 1]) ?
                    $_matrix[$_oindex - 1][$_nindex - 1] + 1 : 1;
                if ($_matrix[$_oindex][$_nindex] > $_maxlen) {
                    $_maxlen = $_matrix[$_oindex][$_nindex];
                    $_omax = $_oindex + 1 - $_maxlen;
                    $_nmax = $_nindex + 1 - $_maxlen;
                }
            }
        }
        if ($_maxlen == 0) return array(array('removed' => $_old, 'added' => $_new));
        return array_merge(
            self::GetTextDiffArray(array_slice($_old, 0, $_omax), array_slice($_new, 0, $_nmax)),
            array_slice($_new, $_nmax, $_maxlen),
            self::GetTextDiffArray(array_slice($_old, $_omax + $_maxlen), array_slice($_new, $_nmax + $_maxlen)));
    }
}
