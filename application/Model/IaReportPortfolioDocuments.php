<?php

/**
 *  Ia Report Portfolio Documents domain model
 */
class Model_IaReportPortfolioDocuments extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_portfolio_documents';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_portfolio_document_id' => COLUMN_INT,
        'parent_id' => COLUMN_INT,
        'ia_report_id' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'reporting_year' => COLUMN_STRING,
        'portfolio_title' => COLUMN_STRING,
        'short_title' => COLUMN_STRING,
        'ia_portfolio_type' => COLUMN_STRING,
        'trademark_type' => COLUMN_STRING,
        'is_crp_related' => COLUMN_INT,
        'crp_id' => array(COLUMN_JSON, COLUMN_PARTNER_ID),
        'owner_applicant' => array(COLUMN_JSON, COLUMN_PARTNER_ID),
        'filing_type_1' => COLUMN_STRING,
        'filing_type_2' => COLUMN_STRING,
        'crop_id' => COLUMN_INT,
        'application_number' => COLUMN_STRING,
        'filing_date' => COLUMN_DATE,
        'registration_date' => COLUMN_DATE,
        'expiry_date' => COLUMN_DATE,
        'external_link' => COLUMN_STRING,
        'claims_categories' => COLUMN_STRING,
        'innovation_summary' => COLUMN_STRING,
        'claims_summary' => COLUMN_STRING,
        'previous_version' => COLUMN_JSON,
        'is_deleted' => COLUMN_INT,
        'country_id' => COLUMN_STRING //Not in the table, but used in this class
    );

    public function _getParent()
    {
        try {
            $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
            return $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $this->parent_id));
        } catch (Exception $e) {
            return new Model_IaReportPortfolioDocuments();
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

    public function _getOwners()
    {
        $_ownerApplicantIds = array();
        try {
            $_ownerApplicantIds = Zend_Json::decode($this->owner_applicant);
            $_ownerApplicantIds = array_filter($_ownerApplicantIds);
        } catch (Exception $e) {
        }
        if (is_array($_ownerApplicantIds) && !empty($_ownerApplicantIds)) {
            $_partnerMapper = new Model_Mapper_Partner();
            return $_partnerMapper->fetchMany(array('partner_id' => $_ownerApplicantIds));
        } else {
            return new Model_PartnerCollection();
        }
    }

    public function _getCrops()
    {
        $_cropIds = array();
        try {
            $_cropIds = Zend_Json::decode($this->crop_id);
            $_cropIds = array_filter($_cropIds);
        } catch (Exception $e) {
        }
        if (is_array($_cropIds) && !empty($_cropIds)) {
            $_cropMapper = new Model_Mapper_Crop();
            return $_cropMapper->fetchMany(array('crop_id' => $_cropIds));
        } else {
            return new Model_CropCollection();
        }
    }

    public function _getCountriesIds()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_portfolio_documents_countries', 'country_id')
            ->where('ia_portfolio_document_id = (?)', $this->ia_portfolio_document_id)
            ->order('id');
        return $_db->fetchCol($_query);
    }

    public function _getCountry()
    {
        $_countries = $this->countries_ids;
        $_countries[] = -15;
        $_countryMapper = new Model_Mapper_WipoCountries();
        return $_countryMapper->fetchMany(array('country_id' => $_countries), 'FIELD(country_id, ' . implode(',', $_countries) . ')');
    }

    public function _getCountriesData()
    {
        $_iaReportPortfolioDocumentsCountriesMapper = new Model_Mapper_IaReportPortfolioDocumentsCountries();
        return $_iaReportPortfolioDocumentsCountriesMapper->fetchMany(array('ia_portfolio_document_id' => $this->ia_portfolio_document_id), 'id');
    }

    public function _getPreviousUpdates()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_portfolio_document_id', $this->ia_portfolio_document_id);
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        return $_iaReportUpdatesMapper->fetchMany(array(
            'ia_portfolio_document_id' => $_itemsIds
        ), 'reporting_year');
    }

    public function _getPreviousUpdatesYears()
    {
        $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_portfolio_document_id', $this->ia_portfolio_document_id);

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_updates', 'reporting_year')
            ->where('ia_portfolio_document_id IN (?)', $_itemsIds);
        return $_db->fetchCol($_query);
    }

    public function _getLatestAgreement()
    {
        try {
            $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($this->getTableName(), 'ia_portfolio_document_id', $this->ia_portfolio_document_id);
            $_agreementsMapper = new Model_Mapper_IaReportAgreements();
            return $_agreementsMapper->fetchOne(array('agreement_related_portfolio' => $_itemsIds), 'ia_agreement_id DESC');
        } catch (Exception $e) {
            return new Model_IaReportAgreements();
        }
    }

    public function _getLatestPublicDisclosure()
    {
        try {
            $_agreementPublicDisclosuresMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
            return $_agreementPublicDisclosuresMapper->fetchOne(array('ia_agreement_id' => $this->latest_agreement->ia_agreement_id), 'ia_agreement_public_disclosure_id DESC');
        } catch (Exception $e) {
            return new Model_IaReportAgreementPublicDisclosure();
        }
    }

    public function _getActiveStatus()
    {
        try {
            $_iaReportPortfolioDocumentsCountriesMapper = new Model_Mapper_IaReportPortfolioDocumentsCountries();
            $_iaReportPortfolioDocumentsCountriesMapper->fetchOne(array(
                'ia_portfolio_document_id' => $this->ia_portfolio_document_id,
                '!status' => 'Discontinued'
            ));
            return 'Yes';
        } catch (Exception $e) {
            return 'No';
        }
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'ia_portfolio_type',
            'trademark_type',
            'crp_id',
            'owner_applicant',
            'filing_type_1',
            'filing_type_2',
            'country_id',
            'crop_id',
            'external_link',
            'claims_categories'
        );
        $_yesNoColumns = array(
            'is_crp_related'
        );
        $_ignoredColumns = array(
            'ia_portfolio_document_id',
            'parent_id',
            'ia_report_id',
            'added_by',
            'added_date',
            'updated_by',
            'updated_date',
            'reporting_year',
            'previous_version',
            'status',
            'is_deleted'
        );

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report_portfolio_documents_countries', array(
                'country_id',
                'status',
                'reporting_year'
            ))
            ->where('ia_portfolio_document_id = (?)', $_dataArray['ia_portfolio_document_id'])
            ->order('id');
        $_countriesData = $_db->fetchAll($_query);
        $_countries = array_column($_countriesData, 'country_id');
        $_countriesData = array_combine($_countries, $_countriesData);
        $_countries[] = -15;
        $_countryMapper = new Model_Mapper_WipoCountries();
        $_countries = $_countryMapper->fetchMany(array('country_id' => $_countries), 'FIELD(country_id, ' . implode(',', $_countries) . ')');

        $_dataArray['country_id'] = null;
        $_dataArray['status'] = null;
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
                } elseif ($_column === 'owner_applicant') {
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
                } elseif ($_column === 'country_id') {
                    $_dataArray[$_column] = array();
                    foreach ($_countries as $_country)
                        $_dataArray[$_column][] = $_country->name . (isset($_countriesData[$_country->country_id]['status']) && $_countriesData[$_country->country_id]['status'] != '' ? ' (' . $_countriesData[$_country->country_id]['status'] . ' - ' . $_countriesData[$_country->country_id]['reporting_year'] . ')' : '');
                } elseif ($_column === 'crop_id') {
                    $_cropsMapper = new Model_Mapper_Crop();
                    try {
                        $_value = Zend_Json::decode($_value);
                    } catch (Exception $e) {
                    }
                    if (empty($_value))
                        $_value = array(-15);

                    $_crops = $_cropsMapper->fetchMany(array('crop_id' => $_value));
                    $_value = array();
                    foreach ($_crops as $_crop)
                        $_value[] = $_crop->crop_name;
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
