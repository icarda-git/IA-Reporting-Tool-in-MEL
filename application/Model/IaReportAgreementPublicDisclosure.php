<?php

/**
 *  Ia Report Agreement Public Disclosure domain model
 */
class Model_IaReportAgreementPublicDisclosure extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_agreement_public_disclosure';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_agreement_public_disclosure_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'ia_agreement_id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'version' => COLUMN_INT,
        'submitted_version' => COLUMN_INT,
        'reporting_year' => COLUMN_STRING,
        'no_public_disclosure' => COLUMN_INT,
        'no_public_disclosure_explain' => COLUMN_STRING,
        'public_disclosure_link' => COLUMN_STRING,
        'public_disclosure_document' => COLUMN_FILE,
        'is_public_disclosure_provided' => COLUMN_INT,
        'public_disclosure_not_provided_explain' => COLUMN_STRING,
        'anticipated_public_disclosure' => COLUMN_STRING,
        'anticipated_public_disclosure_date' => COLUMN_DATE,
        'other_links' => COLUMN_JSON,
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

    public function _getAgreement()
    {
        try {
            $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
            return $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $this->ia_agreement_id));
        } catch (Exception $e) {
            return new Model_IaReportAgreements();
        }
    }

    public function _getPreviousUpdates()
    {
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        return $_iaReportUpdatesMapper->fetchMany(array(
            'ia_agreement_public_disclosure_id' => $this->ia_agreement_public_disclosure_id
        ), 'reporting_year');
    }

    public function _getPreviousUpdatesYears()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_updates', 'reporting_year')
            ->where('ia_agreement_public_disclosure_id = (?)', $this->ia_agreement_public_disclosure_id);
        return $_db->fetchCol($_query);
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'ia_agreement_id',
            'public_disclosure_link',
            'public_disclosure_document',
            'other_links'
        );
        $_yesNoColumns = array(
            'no_public_disclosure',
            'is_public_disclosure_provided',
            'anticipated_public_disclosure'
        );
        $_ignoredColumns = array(
            'ia_agreement_public_disclosure_id',
            'ia_report_id',
            'ia_agreement_id',
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
                if ($_column === 'ia_agreement_id') {
                    $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();

                    try {
                        $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_value));
                        $_value = $_iaReportAgreement->unique_identifier;
                        $_dataArray[$_column] = array($_value);
                    } catch (Exception $e) {
                        $_dataArray[$_column] = array();
                    }
                } elseif ($_column == 'public_disclosure_document') {
                    if ($_value != '')
                        $_value = '<a href="' . APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_value . '" download="' . substr($_value, 33) . '">' . substr($_value, 33) . '</a>';
                    $_dataArray[$_column] = array($_value);
                } elseif ($_column == 'other_links') {
                    try {
                        $_value = Zend_Json::decode($_dataArray[$_column]);
                    } catch (Exception $e) {
                        $_value = array();
                    }
                    $_dataArray[$_column] = $_value;
                } else {
                    $_dataArray[$_column] = array($_value);
                }
            } elseif (in_array($_column, $_yesNoColumns)) {
                if ($_column == 'no_public_disclosure') {
                    if ($_value == '1')
                        $_value = 'No public disclosure issued';
                    else
                        $_value = 'Public disclosure issued';
                } elseif ($_column == 'anticipated_public_disclosure') {
                    if ($_value == '1')
                        $_value = 'Yes, a disclosure or update is reasonably anticipated in the near future (i.e. within the next 3 months).';
                    elseif ($_value == '2')
                        $_value = 'Yes, a disclosure or update is anticipated, however, its timing cannot be reasonably anticipated or will not occur in the near future, as per the circumstances explained below.';
                    else
                        $_value = 'No, a disclosure or update is not anticipated, as per the circumstances explained below.';
                } else {
                    if ($_value == '1')
                        $_value = 'Yes';
                    else
                        $_value = 'No';
                }
                $_dataArray[$_column] = array($_value);
            } else {
                $_dataArray[$_column] = $_value;
            }
        }
        return $_dataArray;
    }
}
