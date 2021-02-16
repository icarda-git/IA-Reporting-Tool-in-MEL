<?php

/**
 *  Ia Report Management Documents domain model
 */
class Model_IaReportManagementDocuments extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_management_documents';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_management_document_id' => COLUMN_INT,
        'parent_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'reporting_year' => COLUMN_STRING,
        'other_document_type' => COLUMN_STRING,
        'policy_title' => COLUMN_STRING,
        'category' => COLUMN_STRING,
        'manage_status' => COLUMN_STRING,
        'approval_date' => COLUMN_DATE,
        'effective_date' => COLUMN_DATE,
        'is_crp_related' => COLUMN_INT,
        'crp_id' => array(COLUMN_JSON, COLUMN_PARTNER_ID),
        'availability_status' => COLUMN_STRING,
        'availability_status_document' => COLUMN_FILE,
        'is_publicly_available' => COLUMN_INT,
        'public_url' => COLUMN_STRING,
        'previous_version' => COLUMN_JSON,
        'is_deleted' => COLUMN_INT
    );

    public function _getParent()
    {
        try {
            $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
            return $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $this->parent_id));
        } catch (Exception $e) {
            return new Model_IaReportManagementDocuments();
        }
    }

    public function _getIaReport()
    {
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            return $_iaReportMapper->fetchOne(array('ia_report_id' => $this->ia_report_id));
        } catch (Exception $e) {
            return new Model_IaReport();
        }
    }

    public function _getAddedByName()
    {
        try {
            $_userMapper = new Model_Mapper_UserShort();
            return trim($_userMapper->fetchOne(array('user_id' => $this->added_by))->name);
        } catch (Exception $e) {
            return null;
        }
    }

    public function _getUpdatedByName()
    {
        try {
            $_userMapper = new Model_Mapper_UserShort();
            return trim($_userMapper->fetchOne(array('user_id' => $this->updated_by))->name);
        } catch (Exception $e) {
            return null;
        }
    }

    public function _getCrps()
    {
        $_crpIds = array();
        try {
            $_crpIds = Zend_Json::decode($this->crp_id);
            $_crpIds = array_filter($_crpIds);
        } catch (Exception $e) {
        }
        if (is_array($_crpIds) && !empty($_crpIds)) {
            $_partnerMapper = new Model_Mapper_Partner();
            return $_partnerMapper->fetchMany(array('partner_id' => $_crpIds));
        } else {
            return new Model_PartnerCollection();
        }
    }

    public function _getPreviousUpdates()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_management_document_id', $this->ia_management_document_id);
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        return $_iaReportUpdatesMapper->fetchMany(array(
            'ia_management_document_id' => $_itemsIds
        ), 'reporting_year');
    }

    public function _getPreviousUpdatesYears()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_management_document_id', $this->ia_management_document_id);

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_updates', 'reporting_year')
            ->where('ia_management_document_id IN (?)', $_itemsIds);
        return $_db->fetchCol($_query);
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'other_document_type',
            'category',
            'manage_status',
            'crp_id',
            'availability_status',
            'availability_status_document',
            'public_url'
        );
        $_yesNoColumns = array(
            'is_crp_related',
            'is_publicly_available'
        );
        $_ignoredColumns = array(
            'ia_management_document_id',
            'parent_id',
            'ia_report_id',
            'added_by',
            'added_date',
            'updated_by',
            'updated_date',
            'reporting_year',
            'previous_version',
            'is_deleted'
        );

        foreach ($_dataArray as $_column => $_value) {
            if (in_array($_column, $_ignoredColumns)) {
                unset($_dataArray[$_column]);
                continue;
            }

            if (in_array($_column, $_optionsColumns)) {
                if ($_column === 'crp_id') {
                    $_partnersMapper = new Model_Mapper_Partner();
                    try {
                        $_value = Zend_Json::decode($_value);
                    } catch (Exception $e) {
                    }
                    if (empty($_value))
                        $_value = array(-15);

                    $_partners = $_partnersMapper->fetchMany(array('partner_id' => $_value));
                    $_value = array();
                    foreach ($_partners as $_partner)
                        $_value[] = $_partner->full_name;
                    $_dataArray[$_column] = $_value;
                } elseif ($_column == 'availability_status_document') {
                    if ($_value != '')
                        $_value = '<a href="' . APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_value . '" download="' . substr($_value, 33) . '">' . substr($_value, 33) . '</a>';
                    $_dataArray[$_column] = array($_value);
                } else {
                    $_dataArray[$_column] = array($_value);
                }
            } elseif (in_array($_column, $_yesNoColumns)) {
                if ($_value == '1')
                    $_value = 'Yes';
                else
                    $_value = 'No';
                $_dataArray[$_column] = array($_value);
            } else {
                $_dataArray[$_column] = $_value;
            }
        }
        return $_dataArray;
    }
}
