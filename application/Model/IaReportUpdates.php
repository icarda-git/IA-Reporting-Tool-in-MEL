<?php

/**
 *  Ia Report Updates domain model
 */
class Model_IaReportUpdates extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_updates';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_report_update_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'ia_report_crp_id' => COLUMN_INT,
        'ia_management_document_id' => COLUMN_INT,
        'ia_portfolio_document_id' => COLUMN_INT,
        'ia_agreement_id' => COLUMN_INT,
        'ia_agreement_public_disclosure_id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'reporting_year' => COLUMN_STRING,
        'update_text' => COLUMN_STRING,
        'policy_status' => COLUMN_STRING,
        'policy_revision_date' => COLUMN_DATE,
        'public_disclosure_update_link' => COLUMN_STRING,
        'public_disclosure_update_document' => COLUMN_FILE,
        'previous_version' => COLUMN_JSON,
        'is_deleted' => COLUMN_INT
    );

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
            $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
            return $_iaReportCrpMapper->fetchOne(array('ia_report_crp_id' => $this->ia_report_crp_id));
        } catch (Exception $e) {
            return new Model_IaReportCrp();
        }
    }

    public function _getPolicy()
    {
        try {
            $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
            return $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $this->ia_management_document_id, 'other_document_type' => null));
        } catch (Exception $e) {
            return new Model_IaReportManagementDocuments();
        }
    }

    public function _getManagementDocument()
    {
        try {
            $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
            return $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $this->ia_management_document_id, '!other_document_type' => null));
        } catch (Exception $e) {
            return new Model_IaReportManagementDocuments();
        }
    }

    public function _getPatent()
    {
        try {
            $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
            return $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $this->ia_portfolio_document_id, 'ia_portfolio_type' => 'Patent'));
        } catch (Exception $e) {
            return new Model_IaReportPortfolioDocuments();
        }
    }

    public function _getPvp()
    {
        try {
            $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
            return $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $this->ia_portfolio_document_id, 'ia_portfolio_type' => 'PVP'));
        } catch (Exception $e) {
            return new Model_IaReportPortfolioDocuments();
        }
    }

    public function _getTrademark()
    {
        try {
            $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
            return $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $this->ia_portfolio_document_id, 'ia_portfolio_type' => 'Trademark'));
        } catch (Exception $e) {
            return new Model_IaReportPortfolioDocuments();
        }
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array();
        $_yesNoColumns = array();
        $_ignoredColumns = array(
            'ia_report_update_id',
            'ia_report_id',
            'ia_report_crp_id',
            'ia_management_document_id',
            'ia_portfolio_document_id',
            'ia_agreement_id',
            'ia_agreement_public_disclosure_id',
            'policy_status',
            'public_disclosure_update_link',
            'public_disclosure_update_document',
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
                $_dataArray[$_column] = array($_value);
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
