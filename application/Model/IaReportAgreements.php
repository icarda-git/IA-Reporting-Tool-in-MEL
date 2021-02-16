<?php

/**
 *  Ia Report Agreements domain model
 */
class Model_IaReportAgreements extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_agreements';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_agreement_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'version' => COLUMN_INT,
        'submitted_version' => COLUMN_INT,
        'reporting_year' => COLUMN_STRING,
        'ia_agreement_type' => COLUMN_STRING,
        'ip_type' => COLUMN_STRING,
        'agreement_title' => COLUMN_STRING,
        'agreement_related_portfolio' => COLUMN_INT,
        'filing_type_1' => COLUMN_STRING,
        'filing_type_2' => COLUMN_STRING,
        'country_id' => COLUMN_JSON,
        'parties_name' => array(COLUMN_JSON, COLUMN_PARTNER_ID),
        'start_date' => COLUMN_DATE,
        'end_date' => COLUMN_DATE,
        'applicant_name' => array(COLUMN_JSON, COLUMN_PARTNER_ID),
        'approximate_costs' => COLUMN_STRING,
        'project_collaboration' => COLUMN_STRING,
        'arrangements_exclusivity' => COLUMN_STRING,
        'third_party_ia' => COLUMN_STRING,
        'protected_subject_matter' => COLUMN_STRING,
        'application_related_project' => COLUMN_STRING,
        'intellectual_value_explain' => COLUMN_STRING,
        'collaboration_exclusivity' => COLUMN_STRING,
        'exclusivity_explain' => COLUMN_STRING,
        'research_exemption' => COLUMN_STRING,
        'emergency_exemption' => COLUMN_STRING,
        'equivalent_intellectual_availability' => COLUMN_STRING,
        'intellectual_impact' => COLUMN_STRING,
        'intellectual_use_measures' => COLUMN_STRING,
        'application_necessary' => COLUMN_STRING,
        'dissemination_strategy' => COLUMN_STRING,
        'applicable_smta_respect' => COLUMN_STRING,
        'is_agreement_related' => COLUMN_INT,
        'agreement_related_post' => COLUMN_INT,
        'application_status' => COLUMN_STRING,
        'restricted_agreement' => COLUMN_INT,
        'restricted_agreement_no_explain' => COLUMN_STRING,
        'collection_information' => COLUMN_STRING,
        'is_monetary_benefit' => COLUMN_INT,
        'monetary_benefit_explain' => COLUMN_STRING,
        'is_ia_limitations' => COLUMN_INT,
        'ia_limitations_clearance' => COLUMN_STRING,
        'germplasm_incorporated' => COLUMN_INT,
        'commercializing_benefit' => COLUMN_STRING,
        'no_commercializing_benefit' => COLUMN_STRING,
        'germplasm_incorporated_no_explain' => COLUMN_STRING,
        'is_biological_resources_utilized' => COLUMN_INT,
        'biological_resources_utilized_benefit' => COLUMN_INT,
        'abs_obligations_compliance' => COLUMN_STRING,
        'no_abs_obligations_apply' => COLUMN_STRING,
        'licensing_plan' => COLUMN_STRING,
        'non_confidential' => COLUMN_JSON,
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

    public function _getPreviousUpdates()
    {
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        return $_iaReportUpdatesMapper->fetchMany(array(
            'ia_agreement_id' => $this->ia_agreement_id
        ), 'reporting_year');
    }

    public function _getPreviousUpdatesYears()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_updates', 'reporting_year')
            ->where('ia_agreement_id = (?)', $this->ia_agreement_id);
        return $_db->fetchCol($_query);
    }

    public function _getUniqueIdentifier()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_agreements', array(
                'count(tbl_ia_report_agreements.ia_agreement_id) AS sequential_id',
                'coalesce(tbl_partner.abbreviation, tbl_partner.name) AS partner'
            ))
            ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreements.ia_report_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '')
            ->where('tbl_ia_report_agreements.ia_agreement_id <= (?)', $this->ia_agreement_id)
            ->where('tbl_ia_report_agreements.ia_report_id = (?)', $this->ia_report_id)
            ->where('tbl_ia_report_agreements.ia_agreement_type = (?)', $this->ia_agreement_type)
            ->where('tbl_ia_report_agreements.reporting_year = (?)', $this->reporting_year);
        $_data = $_db->fetchRow($_query);

        $_iaAgreementType = $this->ia_agreement_type;
        //Center/year/Type/Sequential
        return $_data['partner'] . '-' . $this->reporting_year . '-' . $_iaAgreementType . '-' . $_data['sequential_id'];
    }

    public function _getCountry()
    {
        try {
            $_countries = Zend_Json::decode($this->country_id);
        } catch (Exception $e) {
            $_countries = null;
        }
        $_countryMapper = new Model_Mapper_WipoCountries();
        return $_countryMapper->fetchMany(array('country_id' => $_countries));
    }

    public function _getPublicDisclosures()
    {
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        return $_iaReportAgreementPublicDisclosureMapper->fetchMany(array('ia_agreement_id' => $this->ia_agreement_id));
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'agreement_related_portfolio',
            'filing_type_1',
            'filing_type_2',
            'country_id',
            'parties_name',
            'applicant_name',
            'agreement_related_post'
        );
        $_yesNoColumns = array(
            'is_agreement_related',
            'restricted_agreement',
            'is_monetary_benefit',
            'is_ia_limitations'
        );
        $_ignoredColumns = array(
            'ia_agreement_id',
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
                if ($_column === 'agreement_related_portfolio') {
                    $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
                    try {
                        $_iaReportPortfolioDocument = $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_value));
                        $_value = $_iaReportPortfolioDocument->ia_portfolio_document_id . ' - (' . $_iaReportPortfolioDocument->reporting_year . '-' . $_iaReportPortfolioDocument->ia_portfolio_type . ') ' . $_iaReportPortfolioDocument->portfolio_title;
                        $_dataArray[$_column] = array($_value);
                    } catch (Exception $e) {
                        $_dataArray[$_column] = array();
                    }
                } elseif ($_column === 'country_id') {
                    try {
                        $_countries = Zend_Json::decode($this->country_id);
                    } catch (Exception $e) {
                        $_countries = null;
                    }
                    $_countryMapper = new Model_Mapper_WipoCountries();
                    $_countries = $_countryMapper->fetchMany(array('country_id' => $_countries));
                    $_dataArray[$_column] = array();
                    foreach ($_countries as $_country)
                        $_dataArray[$_column][] = $_country->name;
                } elseif ($_column === 'parties_name' || $_column === 'applicant_name') {
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
                } elseif ($_column === 'agreement_related_post') {
                    $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();

                    $_iaReportAgreements = $_iaReportAgreementsMapper->fetchMany(array('ia_agreement_id' => $_value));
                    $_value = array();
                    foreach ($_iaReportAgreements as $_iaReportAgreement)
                        $_value[] = $_iaReportAgreement->unique_identifier;
                    $_dataArray[$_column] = $_value;
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
