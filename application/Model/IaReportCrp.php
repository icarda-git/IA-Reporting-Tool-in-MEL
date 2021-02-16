<?php

/**
 *  Ia Report Crp domain model
 */
class Model_IaReportCrp extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_crp';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_report_crp_id' => COLUMN_INT,
        'parent_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'crp_id' => COLUMN_PARTNER_ID,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'reporting_year' => COLUMN_INT,
        'ia_crp_management_capacity' => COLUMN_STRING,
        'ia_crp_management_oversight' => COLUMN_STRING,
        'ia_crp_management_policies' => COLUMN_STRING,
        'ia_crp_management_committees' => COLUMN_STRING,
        'previous_version' => COLUMN_JSON,
        'is_deleted' => COLUMN_INT
    );

    public function _getParent()
    {
        try {
            $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
            return $_iaReportCrpMapper->fetchOne(array('ia_report_crp_id' => $this->parent_id));
        } catch (Exception $e) {
            return new Model_IaReportCrp();
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

    public function _getCrp()
    {
        try {
            $_partnerMapper = new Model_Mapper_Partner();
            return $_partnerMapper->fetchOne(array('partner_id' => $this->crp_id));
        } catch (Exception $e) {
            return new Model_Partner();
        }
    }

    public function _getPreviousUpdates()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_report_crp_id', $this->ia_report_crp_id);

        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        return $_iaReportUpdatesMapper->fetchMany(array(
            'ia_report_crp_id' => $_itemsIds
        ), 'reporting_year');
    }

    public function _getPreviousUpdatesYears()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_report_crp_id', $this->ia_report_crp_id);

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_updates', 'reporting_year')
            ->where('ia_report_crp_id IN (?)', $_itemsIds);
        return $_db->fetchCol($_query);
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'crp_id'
        );
        $_yesNoColumns = array();
        $_ignoredColumns = array(
            'ia_report_crp_id',
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
                    $_partnerMapper = new Model_Mapper_Partner();

                    try {
                        $_partner = $_partnerMapper->fetchOne(array('partner_id' => $_value));
                        $_dataArray[$_column] = array($_partner->full_name);
                    } catch (Exception $e) {
                        $_dataArray[$_column] = array();
                    }
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
