<?php

/**
 *  Ia Report domain model
 */
class Model_IaReport extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'ia_report_id' => COLUMN_INT,
        'is_draft' => COLUMN_INT,
        'added_by' => COLUMN_PROFILE_ID,
        'added_date' => COLUMN_DATE,
        'updated_by' => COLUMN_PROFILE_ID,
        'updated_date' => COLUMN_DATE,
        'version' => COLUMN_INT,
        'submitted_version' => COLUMN_INT,
        'partner_id' => COLUMN_PARTNER_ID,
        'reporting_year' => COLUMN_STRING,
        'supplementary_information' => COLUMN_FILE,
        'ia_description' => COLUMN_STRING,
        'ia_related_activities' => COLUMN_STRING,
        'ia_capacity' => COLUMN_INT,
        'ia_capacity_explain' => COLUMN_STRING,
        'ia_partnerships' => COLUMN_STRING,
        'ia_management_approach' => COLUMN_STRING,
        'ia_general_highlights' => COLUMN_STRING,
        'ia_additional_notes_1' => COLUMN_STRING,
        'ia_additional_notes_2' => COLUMN_STRING,
        'ia_germplasm' => COLUMN_INT,
        'ia_germplasm_explain' => COLUMN_STRING,
        'ia_smta' => COLUMN_INT,
        'ia_smta_explain' => COLUMN_STRING,
        'ia_policies' => COLUMN_INT,
        'ia_policies_explain' => COLUMN_STRING,
        'ia_highlights_explain' => COLUMN_STRING,
        'previous_version' => COLUMN_JSON,
        'is_deleted' => COLUMN_INT
    );

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

    public function _getOrganizationName()
    {
        return trim($this->organization->full_name);
    }

    public function _getOrganization()
    {
        try {
            $_partnerMapper = new Model_Mapper_Partner();
            return $_partnerMapper->fetchOne(array('partner_id' => $this->partner_id));
        } catch (Exception $e) {
            return new Model_Partner();
        }
    }

    public function _getIaReportContacts()
    {
        $_iaReportContactsMapper = new Model_Mapper_IaReportContacts();
        return $_iaReportContactsMapper->fetchMany(array('ia_report_id' => $this->ia_report_id));
    }

    public function PreviousIaReportsIds()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_ia_report', 'ia_report_id')
            ->where('partner_id = (?)', $this->partner_id)
            ->where('reporting_year <= (?)', $this->reporting_year);
        $_previousIaReportsIds = $db->fetchCol($_query);
        $_previousIaReportsIds[] = -15;
        return $_previousIaReportsIds;
    }

    public function _getCrps($_getPreviousYears = true)
    {
        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportCrpMapper->fetchMany(array('ia_report_id' => $_previousIaReportsIds), 'reporting_year');
    }

    public function _getPolicies($_getPreviousYears = true)
    {
        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportManagementDocumentsMapper->fetchMany(array(
            'ia_report_id' => $_previousIaReportsIds,
            'other_document_type' => null
        ), 'reporting_year');
    }

    public function _getOtherManagementDocuments($_getPreviousYears = true)
    {
        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportManagementDocumentsMapper->fetchMany(array(
            'ia_report_id' => $_previousIaReportsIds,
            '!other_document_type' => null
        ), 'reporting_year');
    }

    public function _getPatents($_getPreviousYears = true)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportPortfolioDocumentsMapper->fetchMany(array(
            'ia_report_id' => $_previousIaReportsIds,
            'ia_portfolio_type' => 'Patent'
        ), 'reporting_year');
    }

    public function _getPvps($_getPreviousYears = true)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportPortfolioDocumentsMapper->fetchMany(array(
            'ia_report_id' => $_previousIaReportsIds,
            'ia_portfolio_type' => 'PVP'
        ), 'reporting_year');
    }

    public function _getTrademarks($_getPreviousYears = true)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;
        return $_iaReportPortfolioDocumentsMapper->fetchMany(array(
            'ia_report_id' => $_previousIaReportsIds,
            'ia_portfolio_type' => 'Trademark'
        ), 'reporting_year');
    }

    public function GetAgreementsForExport($_type, $_unique = false, $_getPreviousYears = true)
    {
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        //Agreements in $_previousIaReportsIds
        //Or agreements have an update in $_previousIaReportsIds
        //Or agreements have a public disclosure in $_previousIaReportsIds
        //Or agreements have a public disclosure that have an update $_previousIaReportsIds
        $_agreementsIdsQuery = $_db->select()
            ->from('tbl_ia_report_agreements', 'tbl_ia_report_agreements.ia_agreement_id')
            ->joinLeft('tbl_ia_report_updates AS agreement_updates', 'agreement_updates.ia_agreement_id = tbl_ia_report_agreements.ia_agreement_id')
            ->joinLeft('tbl_ia_report_agreement_public_disclosure', 'tbl_ia_report_agreement_public_disclosure.ia_agreement_id = tbl_ia_report_agreements.ia_agreement_id')
            ->joinLeft('tbl_ia_report_updates AS public_disclosure_updates', 'public_disclosure_updates.ia_agreement_public_disclosure_id = tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id')
            ->where('tbl_ia_report_agreements.ia_report_id IN (?)', $_previousIaReportsIds)
            ->orWhere('agreement_updates.ia_report_id IN (?)', $_previousIaReportsIds)
            ->orWhere('tbl_ia_report_agreement_public_disclosure.ia_report_id IN (?)', $_previousIaReportsIds)
            ->orWhere('public_disclosure_updates.ia_report_id IN (?)', $_previousIaReportsIds);
        $_agreementsIds = $_db->fetchCol($_agreementsIdsQuery);
        $_agreementsIds[] = -15;

        //Center/year/Type/Sequential
        $_uniqueIdentifierQuery = $_db->select()
            ->from('tbl_ia_report_agreements AS tbl_ia_report_agreements_2', 'concat(coalesce(tbl_partner.abbreviation, tbl_partner.name), "-", tbl_ia_report_agreements_2.reporting_year, "-", tbl_ia_report_agreements_2.ia_agreement_type, "-", count(tbl_ia_report_agreements_2.ia_agreement_id))')
            ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreements_2.ia_report_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '')
            ->where('tbl_ia_report_agreements_2.ia_agreement_id <= tbl_ia_report_agreements.ia_agreement_id')
            ->where('tbl_ia_report_agreements_2.ia_report_id = tbl_ia_report_agreements.ia_report_id')
            ->where('tbl_ia_report_agreements_2.ia_agreement_type = tbl_ia_report_agreements.ia_agreement_type')
            ->where('tbl_ia_report_agreements_2.reporting_year = tbl_ia_report_agreements.reporting_year');
        $_uniqueIdentifierRelatedQuery = $_db->select()
            ->from('tbl_ia_report_agreements AS tbl_ia_report_agreements_2', 'concat(coalesce(tbl_partner.abbreviation, tbl_partner.name), "-", tbl_ia_report_agreements_2.reporting_year, "-", tbl_ia_report_agreements_2.ia_agreement_type, "-", count(tbl_ia_report_agreements_2.ia_agreement_id))')
            ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreements_2.ia_report_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '')
            ->where('tbl_ia_report_agreements_2.ia_agreement_id <= tbl_ia_report_agreements_related.ia_agreement_id')
            ->where('tbl_ia_report_agreements_2.ia_report_id = tbl_ia_report_agreements_related.ia_report_id')
            ->where('tbl_ia_report_agreements_2.ia_agreement_type = tbl_ia_report_agreements_related.ia_agreement_type')
            ->where('tbl_ia_report_agreements_2.reporting_year = tbl_ia_report_agreements_related.reporting_year');

        $_partiesNamesQuery = $_db->select()
            ->from('tbl_partner', 'group_concat(DISTINCT tbl_partner.full_name SEPARATOR "; ")')
            ->where('JSON_CONTAINS(tbl_ia_report_agreements.parties_name, concat(\'"\', tbl_partner.partner_id, \'"\'))');

        $_applicantsNamesQuery = $_db->select()
            ->from('tbl_partner', 'group_concat(DISTINCT tbl_partner.full_name SEPARATOR "; ")')
            ->where('JSON_CONTAINS(tbl_ia_report_agreements.applicant_name, concat(\'"\', tbl_partner.partner_id, \'"\'))');

        $_relatedAgreementsQuery = $_db->select()
            ->from('tbl_ia_report_agreements AS tbl_ia_report_agreements_related', 'group_concat((' . $_uniqueIdentifierRelatedQuery->__toString() . ') SEPARATOR "; ")')
            ->where('tbl_ia_report_agreements.agreement_related_post = tbl_ia_report_agreements_related.ia_agreement_id');

        $_countriesNamesQuery = $_db->select()
            ->from('tbl_wipo_countries', 'group_concat(DISTINCT tbl_wipo_countries.name SEPARATOR "; ")')
            ->where('JSON_CONTAINS(tbl_ia_report_agreements.country_id, concat(\'"\', tbl_wipo_countries.country_id, \'"\'))');

        $_query = $_db->select()
            ->from('tbl_ia_report_agreements', array(
                'tbl_ia_report_agreements.ia_agreement_id',
                'tbl_ia_report_agreements.ia_report_id',
                'tbl_ia_report_agreements.added_by',
                'tbl_ia_report_agreements.added_date',
                'tbl_ia_report_agreements.updated_by',
                'tbl_ia_report_agreements.updated_date',
                'tbl_ia_report_agreements.version',
                'tbl_ia_report_agreements.submitted_version',
                'tbl_ia_report_agreements.ia_agreement_type',
                'organization_name' => 'tbl_partner.abbreviation',
                'tbl_ia_report_agreements.reporting_year',
                'unique_identifier' => '(' . $_uniqueIdentifierQuery->__toString() . ')',
                'tbl_ia_report_agreements.ip_type',
                'agreement_related_portfolio' => 'tbl_ia_report_agreements.agreement_related_portfolio',
                'agreement_related_portfolio_string' => 'concat(tbl_ia_report_portfolio_documents.ia_portfolio_document_id, " - (", tbl_ia_report_portfolio_documents.reporting_year, "-", tbl_ia_report_portfolio_documents.ia_portfolio_type, ") ", tbl_ia_report_portfolio_documents.portfolio_title)',
                'tbl_ia_report_agreements.agreement_title',
                'tbl_ia_report_agreements.parties_name',
                'parties_name_string' => '(' . $_partiesNamesQuery->__toString() . ')',
                'tbl_ia_report_agreements.filing_type_1',
                'tbl_ia_report_agreements.filing_type_2',
                'tbl_ia_report_agreements.country_id',
                'country' => '(' . $_countriesNamesQuery->__toString() . ')',
                'tbl_ia_report_agreements.applicant_name',
                'applicant_name_string' => '(' . $_applicantsNamesQuery->__toString() . ')',
                'tbl_ia_report_agreements.approximate_costs',
                'tbl_ia_report_agreements.start_date',
                'tbl_ia_report_agreements.end_date',
                'tbl_ia_report_agreements.project_collaboration',
                'tbl_ia_report_agreements.third_party_ia',
                'tbl_ia_report_agreements.arrangements_exclusivity',
                'tbl_ia_report_agreements.protected_subject_matter',
                'tbl_ia_report_agreements.application_related_project',
                'tbl_ia_report_agreements.intellectual_value_explain',
                'tbl_ia_report_agreements.application_status',
                'tbl_ia_report_agreements.is_agreement_related',
                'is_agreement_related_string' => 'if(tbl_ia_report_agreements.is_agreement_related = "1", "Yes", "No")',
                'tbl_ia_report_agreements.agreement_related_post',
                'agreement_related_post_string' => '(' . $_relatedAgreementsQuery->__toString() . ')',
                'tbl_ia_report_agreements.collaboration_exclusivity',
                'tbl_ia_report_agreements.exclusivity_explain',
                'tbl_ia_report_agreements.research_exemption',
                'tbl_ia_report_agreements.emergency_exemption',
                'tbl_ia_report_agreements.equivalent_intellectual_availability',
                'tbl_ia_report_agreements.intellectual_impact',
                'tbl_ia_report_agreements.intellectual_use_measures',
                'tbl_ia_report_agreements.application_necessary',
                'tbl_ia_report_agreements.dissemination_strategy',
                'tbl_ia_report_agreements.applicable_smta_respect',
                'tbl_ia_report_agreements.restricted_agreement',
                'restricted_agreement_string' => 'if(tbl_ia_report_agreements.restricted_agreement = "1", "Yes", "No")',
                'tbl_ia_report_agreements.restricted_agreement_no_explain',
                'tbl_ia_report_agreements.collection_information',
                'tbl_ia_report_agreements.is_monetary_benefit',
                'is_monetary_benefit_string' => 'if(tbl_ia_report_agreements.is_monetary_benefit = "1", "Yes", "No")',
                'tbl_ia_report_agreements.monetary_benefit_explain',
                'tbl_ia_report_agreements.is_ia_limitations',
                'is_ia_limitations_string' => 'if(tbl_ia_report_agreements.is_ia_limitations = "1", "Yes", "No")',
                'tbl_ia_report_agreements.ia_limitations_clearance',
                'tbl_ia_report_agreements.germplasm_incorporated',
                'germplasm_incorporated_string' => 'if(tbl_ia_report_agreements.germplasm_incorporated = "1", "Yes", "No")',
                'tbl_ia_report_agreements.commercializing_benefit',
                'tbl_ia_report_agreements.no_commercializing_benefit',
                'tbl_ia_report_agreements.germplasm_incorporated_no_explain',
                'tbl_ia_report_agreements.is_biological_resources_utilized',
                'is_biological_resources_utilized_string_string' => 'if(tbl_ia_report_agreements.is_biological_resources_utilized = "1", "Yes", "No")',
                'tbl_ia_report_agreements.biological_resources_utilized_benefit',
                'biological_resources_utilized_benefit_string' => 'if(tbl_ia_report_agreements.biological_resources_utilized_benefit = "1", "Yes", "No")',
                'tbl_ia_report_agreements.abs_obligations_compliance',
                'tbl_ia_report_agreements.no_abs_obligations_apply',
                'tbl_ia_report_agreements.licensing_plan',
                'tbl_ia_report_agreements.is_deleted',
                'tbl_ia_report_agreements.previous_version'
            ))
            ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreements.ia_report_id', '')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '')
            ->joinLeft('tbl_ia_report_portfolio_documents', 'tbl_ia_report_portfolio_documents.ia_portfolio_document_id = tbl_ia_report_agreements.agreement_related_portfolio', '')
            ->where('tbl_ia_report_agreements.ia_agreement_id IN (?)', $_agreementsIds)
            ->where('tbl_ia_report_agreements.ia_agreement_type = (?)', $_type)
            ->order('tbl_ia_report_agreements.reporting_year');
        if ($_unique)
            $_query->where('tbl_ia_report_agreements.agreement_related_post IS NULL');
        return $_db->fetchAll($_query);
    }

    public function GetPublicDisclosuresForExport($_iaReportAgreementId = null, $_unique = false, $_getPreviousYears = true)
    {
        if ($_getPreviousYears)
            $_previousIaReportsIds = $this->PreviousIaReportsIds();
        else
            $_previousIaReportsIds = $this->ia_report_id;

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        //Public disclosure in $_previousIaReportsIds
        //Or public disclosure have an update $_previousIaReportsIds
        $_publicDisclosuresIdsQuery = $_db->select()
            ->from('tbl_ia_report_agreement_public_disclosure', 'tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id')
            ->joinLeft('tbl_ia_report_updates', 'tbl_ia_report_updates.ia_agreement_public_disclosure_id = tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id')
            ->where('tbl_ia_report_agreement_public_disclosure.ia_report_id IN (?)', $_previousIaReportsIds)
            ->orWhere('tbl_ia_report_updates.ia_report_id IN (?)', $_previousIaReportsIds);
        $_publicDisclosuresIds = $_db->fetchCol($_publicDisclosuresIdsQuery);
        $_publicDisclosuresIds[] = -15;

        $_query = $_db->select()
            ->from('tbl_ia_report_agreement_public_disclosure', array(
                'tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id',
                'tbl_ia_report_agreement_public_disclosure.ia_report_id',
                'tbl_ia_report_agreement_public_disclosure.ia_agreement_id',
                'tbl_ia_report_agreement_public_disclosure.added_by',
                'tbl_ia_report_agreement_public_disclosure.added_date',
                'tbl_ia_report_agreement_public_disclosure.updated_by',
                'tbl_ia_report_agreement_public_disclosure.updated_date',
                'tbl_ia_report_agreement_public_disclosure.version',
                'tbl_ia_report_agreement_public_disclosure.submitted_version',
                'tbl_ia_report_agreement_public_disclosure.reporting_year',
                'no_public_disclosure' => 'tbl_ia_report_agreement_public_disclosure.no_public_disclosure',
                'no_public_disclosure_string' => 'if(tbl_ia_report_agreement_public_disclosure.no_public_disclosure = "1", "No", "Yes")',
                'tbl_ia_report_agreement_public_disclosure.no_public_disclosure_explain',
                'tbl_ia_report_agreement_public_disclosure.public_disclosure_link',
                'tbl_ia_report_agreement_public_disclosure.public_disclosure_document',
                'tbl_ia_report_agreement_public_disclosure.other_links',
                'tbl_ia_report_agreement_public_disclosure.is_public_disclosure_provided',
                'is_public_disclosure_provided_string' => 'if(tbl_ia_report_agreement_public_disclosure.is_public_disclosure_provided = "1", "Yes", "No")',
                'tbl_ia_report_agreement_public_disclosure.public_disclosure_not_provided_explain',
                'tbl_ia_report_agreement_public_disclosure.anticipated_public_disclosure',
                'anticipated_public_disclosure_string' => new Zend_Db_Expr('(CASE WHEN tbl_ia_report_agreement_public_disclosure.anticipated_public_disclosure = "1" THEN "Yes, a disclosure or update is reasonably anticipated in the near future (i.e. within the next 3 months)." WHEN tbl_ia_report_agreement_public_disclosure.anticipated_public_disclosure = "2" THEN "Yes, a disclosure or update is anticipated, however, its timing cannot be reasonably anticipated or will not occur in the near future, as per the circumstances explained below." ELSE "No, a disclosure or update is not anticipated, as per the circumstances explained below." END)'),
                'anticipated_public_disclosure_date',
                'tbl_ia_report_agreement_public_disclosure.is_deleted',
                'tbl_ia_report_agreement_public_disclosure.previous_version'
            ))
            ->where('tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id IN (?)', $_publicDisclosuresIds)
            ->order('tbl_ia_report_agreement_public_disclosure.reporting_year');

        if ($_iaReportAgreementId != null)
            $_query->where('tbl_ia_report_agreement_public_disclosure.ia_agreement_id = (?)', $_iaReportAgreementId);

        if ($_unique)
            $_query->group('tbl_ia_report_agreement_public_disclosure.ia_agreement_id');

        return $_db->fetchAll($_query);
    }

    public function GetEntityUpdates($_type, $_id = null)
    {
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        $_where = array(
            'ia_report_id' => $this->PreviousIaReportsIds(),
            '!' . $_type => null,
            '<=reporting_year' => $this->reporting_year
        );
        $_where[$_type] = $_id;

        return $_iaReportUpdatesMapper->fetchMany($_where, 'reporting_year');
    }

    public function GetIaReportAgreementReviews($_iaAgreementId, $_iaAgreementPublicDisclosureId, $_lastReviewOnly = false)
    {
        try {
            if ($_iaAgreementId != null || $_iaAgreementPublicDisclosureId != null) {
                if ($_iaAgreementPublicDisclosureId != null) {
                    $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
                    $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));
                    $_iaReportAgreement = $_iaReportAgreementPublicDisclosure->agreement;
                } else {
                    $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
                    $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
                }
            } else {
                throw new Zend_Acl_Exception('Entity Not defined', 404);
            }

            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            try {
                $_where = array('is_draft' => '0');
                if ($_iaAgreementPublicDisclosureId != null)
                    $_where['ia_agreement_public_disclosure_id'] = $_iaAgreementPublicDisclosureId;
                else
                    $_where['ia_agreement_id'] = $_iaAgreementId;
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne($_where, 'review_id DESC');
            } catch (Exception $e) {
                $_iaReportReviewLast = new Model_IaReportReview();
                $_iaReportReviewLast->user_group_to = 12; //User group 12: Intellectual Property Focal Point (PMU)
            }

            $_iaReport = $_iaReportAgreement->ia_report;
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null)) {
                return array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );
            }

            $_where = array();
            if ($_iaAgreementPublicDisclosureId != null)
                $_where['ia_agreement_public_disclosure_id'] = $_iaAgreementPublicDisclosureId;
            else
                $_where['ia_agreement_id'] = $_iaAgreementId;
            $_iaReportReviews = $_iaReportReviewMapper->fetchMany($_where);

            $_canReviewIaReport = App_Function_Privileges::canReviewIAReport(null);

            if (($_iaAgreementPublicDisclosureId != null && $_iaReportAgreementPublicDisclosure->is_deleted == '1') || $_iaReportAgreement->is_deleted == '1')
                $_canReviewAgreement = false;
            else
                $_canReviewAgreement = App_Function_Privileges::canReviewIAReportAgreement($_iaReportReviewLast->user_group_to);

            $_reviewsData = array();

            foreach ($_iaReportReviews as $_iaReportReview) {
                $_reviewFrom = App_Function_Privileges::isMemberOf($_iaReportReview->user_group_from) || App_Function_Privileges::isAdmin();
                $_reviewTo = App_Function_Privileges::isMemberOf($_iaReportReview->user_group_to) || App_Function_Privileges::isAdmin();
                $_finalReview = $_iaReportReview->user_group_to == null && $_iaReportReview->is_draft == '0';

                //If the review is from or for Intellectual Property Focal Point, check if the logged in user is a contact in the report
                if (!$_reviewFrom && $_iaReportReview->user_group_from == 11) //user_group_to = User group 11 Intellectual Property Focal Point
                    $_reviewFrom = App_Function_Privileges::isIaReportContact($_iaReport->partner_id);
                if (!$_reviewTo && $_iaReportReview->user_group_to == 11) //user_group_to = User group 11 Intellectual Property Focal Point
                    $_reviewTo = App_Function_Privileges::isIaReportContact($_iaReport->partner_id);

                //If the review is submitted from/for center, allow SCIPG to see it
                if (($_iaReportReview->user_group_to == 11 || $_iaReportReview->user_group_from == 11) && App_Function_Privileges::isMemberOf(29)) //user_group_to = User group 11 Intellectual Property Focal Point, isMemberOf(29) is SCIPG
                    $_reviewTo = true;

                if (!$_reviewFrom && !$_reviewTo && !$_finalReview)
                    continue;

                $_review = $_iaReportReview->toArray();

                if (!$_reviewFrom)
                    $_review['comments'] = null;

                //The reviewer name is only visible between reviewers (SMO, IT/MLS and SCIPG) and for who added the review
                if ($_reviewFrom || $_iaReportReview->user_group_from == 28 || $_iaReportReview->user_group_from == 29 || ($_iaReportReview->user_group_from == 12 && ($_iaReportReview->user_group_to == 28 || $_iaReportReview->user_group_from == 29)))
                    $_review['reviewed_by'] = $_iaReportReview->reviewed_by;

                if (!$_finalReview && !$_reviewFrom && !$_reviewTo) {
                    $_review['reviewed_by'] = null;
                    $_review['justification_comments'] = null;
                    $_review['mls_abs_comments'] = null;
                    $_review['evaluation_comments'] = null;
                    $_review['external_comments'] = null;
                }

                $_review['grade_display_name'] = $_iaReportReview->grade_display_name;
                $_review['from_display_name'] = $_iaReportReview->ReviewerDisplayName($_iaReportReview->user_group_from);
                $_review['to_display_name'] = $_iaReportReview->ReviewerDisplayName($_iaReportReview->user_group_to);

                //replace -highlight_text- TEXT -!highlight_text- to <span style="color: #0a5ab2; font-weight: bold;"></span>
                // to highlight parts of the text, if the comment is draft and $_lastReviewOnly (means PDF export) is
                // false don't replace it as it will go into textarea.
                if ($_review['is_draft'] != '1' || $_lastReviewOnly)
                    foreach ($_review as $_column => $_value)
                        $_review[$_column] = preg_replace('/-highlight_text-/', '<span style="color: #0a5ab2; font-weight: bold;">', preg_replace('/-!highlight_text-/', '</span>', $_value));

                $_reviewsData[] = $_review;
            }

            if ($_lastReviewOnly) {
                $_lastReview = array_pop($_reviewsData);
                $_reviewsData = $_lastReview != null ? array($_lastReview) : array();
            }

            return array(
                'result' => true,
                'can_review_ia_report' => $_canReviewIaReport,
                'can_review_step' => $_canReviewAgreement,
                'user_group_to' => $_iaReportReviewLast->user_group_to,
                'data' => $_reviewsData
            );
        } catch (Exception $e) {
            return array(
                'result' => false,
                'message' => 'Oops! Something went wrong.',
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * @param $_overview bool
     * @param $_reportingYears array
     * @param $_partnerIds array
     * @return array
     */
    public function GetIaReportSummary($_overview, $_reportingYears, $_partnerIds)
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $_iaReportsQuery = $_query = $_db->select()
            ->from('tbl_ia_report', 'ia_report_id')
            ->where('tbl_ia_report.is_deleted = 0');

        //Get only submitted items for overview
        if ($_overview)
            $_iaReportsQuery->where('tbl_ia_report.is_draft = 0');

        if (!empty($_reportingYears))
            $_iaReportsQuery->where('tbl_ia_report.reporting_year IN (?)', $_reportingYears);
        if (!empty($_partnerIds))
            $_iaReportsQuery->where('tbl_ia_report.partner_id IN (?)', $_partnerIds);

        if (!$_overview) {
            if ($this->ia_report_id == null)
                $this->ia_report_id = -15;
            $_iaReportsQuery = array($this->ia_report_id);
        }

        $_agreementsPublicDisclosuresQuery = $_db->select()
            ->from('tbl_ia_report_agreements', array(
                'tbl_ia_report_agreements.ia_agreement_id',
                'tbl_ia_report_agreements.ia_agreement_type',
                'tbl_ia_report_agreements.ip_type'
            ))
            ->join('tbl_ia_report_agreement_public_disclosure', 'tbl_ia_report_agreement_public_disclosure.ia_agreement_id = tbl_ia_report_agreements.ia_agreement_id', '')
            ->where('tbl_ia_report_agreement_public_disclosure.no_public_disclosure IS NULL')
            ->where('tbl_ia_report_agreement_public_disclosure.is_deleted = 0')
            ->group('tbl_ia_report_agreements.ia_agreement_id');
        if (!empty($_reportingYears))
            $_agreementsPublicDisclosuresQuery->where('tbl_ia_report_agreements.reporting_year IN (?)', $_reportingYears);

        $_agreementsQuery = $_db->select()
            ->from('tbl_ia_report_agreements', array(
                'lea' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "LEA", 1, 0))',
                'lea_mls' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "LEA" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'lea_public_disclosure' => 'SUM(IF(agreements_issued_public_disclosure.ia_agreement_type = "LEA", 1, 0))',

                'lea_unique' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "LEA", 1, 0))',
                'lea_unique_mls' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "LEA" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'lea_unique_public_disclosure' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND agreements_issued_public_disclosure.ia_agreement_type = "LEA", 1, 0))',

                'rua' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "RUA", 1, 0))',
                'rua_mls' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "RUA" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'rua_public_disclosure' => 'SUM(IF(agreements_issued_public_disclosure.ia_agreement_type = "RUA", 1, 0))',

                'rua_unique' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "RUA", 1, 0))',
                'rua_unique_mls' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "RUA" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'rua_unique_public_disclosure' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND agreements_issued_public_disclosure.ia_agreement_type = "RUA", 1, 0))',

                'ip_patent' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "Patent", 1, 0))',
                'ip_patent_mls' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "Patent" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'ip_patent_public_disclosure' => 'SUM(IF(agreements_issued_public_disclosure.ia_agreement_type = "IP Application" AND agreements_issued_public_disclosure.ip_type = "Patent", 1, 0))',

                'ip_patent_unique' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "Patent", 1, 0))',
                'ip_patent_unique_mls' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "Patent" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'ip_patent_unique_public_disclosure' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND agreements_issued_public_disclosure.ia_agreement_type = "IP Application" AND agreements_issued_public_disclosure.ip_type = "Patent", 1, 0))',

                'ip_pvp' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "PVP", 1, 0))',
                'ip_pvp_mls' => 'SUM(IF(tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "PVP" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'ip_pvp_public_disclosure' => 'SUM(IF(agreements_issued_public_disclosure.ia_agreement_type = "IP Application" AND agreements_issued_public_disclosure.ip_type = "PVP", 1, 0))',

                'ip_pvp_unique' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "PVP", 1, 0))',
                'ip_pvp_unique_mls' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND tbl_ia_report_agreements.ia_agreement_type = "IP Application" AND tbl_ia_report_agreements.ip_type = "PVP" AND tbl_ia_report_agreements.restricted_agreement = 1, 1, 0))',
                'ip_pvp_unique_public_disclosure' => 'SUM(IF(tbl_ia_report_agreements.agreement_related_post IS NULL AND agreements_issued_public_disclosure.ia_agreement_type = "IP Application" AND agreements_issued_public_disclosure.ip_type = "PVP", 1, 0))'
            ))
            ->joinLeft(array('agreements_issued_public_disclosure' => $_agreementsPublicDisclosuresQuery), 'agreements_issued_public_disclosure.ia_agreement_id = tbl_ia_report_agreements.ia_agreement_id', '')
            ->where('tbl_ia_report_agreements.ia_report_id IN (?)', $_iaReportsQuery)
            ->where('tbl_ia_report_agreements.is_deleted = 0');
        if (!empty($_reportingYears))
            $_agreementsQuery->where('tbl_ia_report_agreements.reporting_year IN (?)', $_reportingYears);
        $_summary = $_db->fetchRow($_agreementsQuery);

        $_summary['total_agreements'] = $_summary['lea'] + $_summary['rua'] + $_summary['ip_patent'] + $_summary['ip_pvp'];
        $_summary['total_agreements_public_disclosure_issued'] = $_summary['lea_public_disclosure'] + $_summary['rua_public_disclosure'] + $_summary['ip_patent_public_disclosure'] + $_summary['ip_pvp_public_disclosure'];
        $_summary['total_agreements_public_disclosure_missing'] = $_summary['total_agreements'] - $_summary['total_agreements_public_disclosure_issued'];

        $_summary['total_agreements_unique'] = $_summary['lea_unique'] + $_summary['rua_unique'] + $_summary['ip_patent_unique'] + $_summary['ip_pvp_unique'];
        $_summary['total_agreements_unique_public_disclosure_issued'] = $_summary['lea_unique_public_disclosure'] + $_summary['rua_unique_public_disclosure'] + $_summary['ip_patent_unique_public_disclosure'] + $_summary['ip_pvp_unique_public_disclosure'];
        $_summary['total_agreements_unique_public_disclosure_missing'] = $_summary['total_agreements_unique'] - $_summary['total_agreements_unique_public_disclosure_issued'];

        $_managementDocumentsQuery = $_db->select()
            ->from('tbl_ia_report_management_documents', array(
                'policy' => 'SUM(IF(tbl_ia_report_management_documents.ia_management_document_id IS NOT NULL AND tbl_ia_report_management_documents.other_document_type IS NULL, 1, 0))',
                'other_document' => 'SUM(IF(tbl_ia_report_management_documents.other_document_type IS NOT NULL, 1, 0))',
            ))
            ->where('tbl_ia_report_management_documents.ia_report_id IN (?)', $_iaReportsQuery)
            ->where('tbl_ia_report_management_documents.is_deleted = 0');
        if (!empty($_reportingYears))
            $_managementDocumentsQuery->where('tbl_ia_report_management_documents.reporting_year IN (?)', $_reportingYears);
        $_summary = array_merge($_summary, $_db->fetchRow($_managementDocumentsQuery));

        $_portfolioDocumentsQuery = $_db->select()
            ->from('tbl_ia_report_portfolio_documents', array(
                'patent' => 'SUM(IF(tbl_ia_report_portfolio_documents.ia_portfolio_type = "Patent", 1, 0))',
                'pvp' => 'SUM(IF(tbl_ia_report_portfolio_documents.ia_portfolio_type = "PVP", 1, 0))',
                'trademark' => 'SUM(IF(tbl_ia_report_portfolio_documents.ia_portfolio_type = "Trademark", 1, 0))'
            ))
            ->where('tbl_ia_report_portfolio_documents.ia_report_id IN (?)', $_iaReportsQuery)
            ->where('tbl_ia_report_portfolio_documents.is_deleted = 0');
        if (!empty($_reportingYears))
            $_portfolioDocumentsQuery->where('tbl_ia_report_portfolio_documents.reporting_year IN (?)', $_reportingYears);
        $_summary = array_merge($_summary, $_db->fetchRow($_portfolioDocumentsQuery));
        return $_summary;
    }

    public function HandleIaReportingVersioning($_entity, $_newItem = false)
    {
        try {
            $_previousVersions = Zend_Json::decode($_entity->previous_version);
        } catch (Exception $e) {
            $_previousVersions = array();
        }
        if (!is_array($_previousVersions))
            $_previousVersions = array();
        $_versions = array_column($_previousVersions, 'version');

        $_referenceEntity = $this;
        if (is_a($_entity, 'Model_IaReportAgreements') || is_a($_entity, 'Model_IaReportAgreementPublicDisclosure'))
            $_referenceEntity = $_entity;

        if (in_array($_referenceEntity->submitted_version, $_versions))
            return $_entity->previous_version;

        $_previousVersions[] = array(
            'data' => $_newItem ? array() : $_entity->GetPlainTextData(),
            'version' => $_newItem ? 0 : $_referenceEntity->submitted_version,
            'reporting_year' => $_referenceEntity->reporting_year,
            'added_by' => $_entity->updated_by != null ? $_entity->updated_by_name : $_entity->added_by_name,
            'added_date' => $_entity->updated_by != null ? $_entity->updated_date : $_entity->added_date
        );

        try {
            $_previousVersions = Zend_Json::encode($_previousVersions);
        } catch (Exception $e) {
            $_previousVersions = $_entity->previous_version;
        }
        return $_previousVersions;
    }

    public function GetIaReportingDiff($_entity, $_baseVersion = null, $_baseVersionReportingYear = null, $_compareVersion = null, $_compareVersionReportingYear = null)
    {
        $_versions = array();
        try {
            $_info = App_Function_ReviewComments::GetFormRelatedTable(null, $_entity->getTableName() . '_review_comments');
            $_mainTable = $_info['main_table'];
            $_mainMapper = $_info['main_mapper'];
            $_primaryColumn = $_info['primary_column'];
            $_itemsIds = App_Function_ReviewComments::GetAllRelatedItemsIds($_mainTable, $_primaryColumn, $_entity->$_primaryColumn);
            $_itemsIds[] = -15;
            $_relatedItems = $_mainMapper->fetchMany(array($_primaryColumn => $_itemsIds), 'reporting_year');

            foreach ($_relatedItems as $_relatedItem) {
                try {
                    $_versions = array_merge($_versions, Zend_Json::decode($_relatedItem->previous_version));
                } catch (Exception $e) {
                }

                if (is_a($_entity, 'Model_IaReport') || is_a($_entity, 'Model_IaReportAgreements') || is_a($_entity, 'Model_IaReportAgreementPublicDisclosure'))
                    $_referenceEntity = $_entity;
                else
                    $_referenceEntity = $_relatedItem->ia_report;

                $_versions[] = array(
                    'data' => $_relatedItem->GetPlainTextData(),
                    'version' => $_referenceEntity->version,
                    'reporting_year' => $_referenceEntity->reporting_year,
                    'added_by' => $_entity->updated_by != null ? $_entity->updated_by_name : $_entity->added_by_name,
                    'added_date' => $_entity->updated_by != null ? $_entity->updated_date : $_entity->added_date
                );
            }
            $_versions = $this->GetSequentialVersions($_versions);
        } catch (Exception $e) {
            return array(
                'entity_id' => null,
                'entity_type' => get_class($_entity),
                'available_versions' => array(),
                'base_version' => array(),
                'compare_version' => array(),
                'diff' => array()
            );
        }

        $_compareVersionExists = false;
        $_baseVersionExists = false;
        if ($_baseVersion != null || ($_compareVersion != null && $_compareVersion != -1)) {
            foreach ($_versions as $_version) {
                if ($_baseVersion != null) {
                    if ($_version['reporting_year'] == $_baseVersionReportingYear && $_version['version'] == $_baseVersion)
                        $_baseVersionExists = true;
                }
                if ($_compareVersion != null && $_compareVersion != -1) {
                    if ($_version['reporting_year'] == $_compareVersionReportingYear && $_version['version'] == $_compareVersion)
                        $_compareVersionExists = true;
                }
            }
        }
        if (!$_baseVersionExists)
            $_baseVersion = null;
        if (!$_compareVersionExists)
            $_compareVersion = -1;

        $_versionsTemp = $_versions;
        if ($_baseVersion == null) {
            $_latestPreviousVersion = array_pop($_versionsTemp);
            while ($_latestPreviousVersion['version'] == '0')
                $_latestPreviousVersion = array_pop($_versionsTemp);
            $_baseVersion = $_latestPreviousVersion['reporting_year'] . '-' . $_latestPreviousVersion['version'];
        } else {
            $_baseVersion = $_baseVersionReportingYear . '-' . $_baseVersion;
        }

        if ($_compareVersion == null) {
            $_latestPreviousVersion = array_pop($_versionsTemp);
            while ($_latestPreviousVersion['version'] == '0')
                $_latestPreviousVersion = array_pop($_versionsTemp);
            $_compareVersion = $_latestPreviousVersion['reporting_year'] . '-' . $_latestPreviousVersion['version'];
        } else if ($_compareVersion != -1) {
            $_compareVersion = $_compareVersionReportingYear . '-' . $_compareVersion;
        }

        $_changedColumns = $this->GetIaReportingChangedColumns($_versions);
        $_baseData = array();
        $_compareData = array();
        $_availableVersions = array();
        foreach ($_versions as $_index => $_version) {
            if ($_version['version'] == '0')
                continue;
            if (empty($_baseData) && $_version['reporting_year'] . '-' . $_version['version'] == $_baseVersion) {
                $_baseData = $_version;
                if ($_compareVersion == -1 && isset($_versions[$_index - 1]))
                    $_compareData = $_versions[$_index - 1];
            }
            if (empty($_compareData) && $_version['reporting_year'] . '-' . $_version['version'] == $_compareVersion)
                $_compareData = $_version;

            unset($_version['data']);
            $_availableVersions[] = $_version;
        }

        if (empty($_compareData['data']) || !is_array($_compareData['data']) || empty($_baseData['data']) || !is_array($_baseData['data'])) {
            unset($_baseData['data']);
            unset($_compareData['data']);
            return array(
                'entity_id' => null,
                'entity_type' => get_class($_entity),
                'available_versions' => $_availableVersions,
                'base_version' => $_baseData,
                'compare_version' => $_compareData,
                'diff' => array()
            );
        }

        $_newColumns = array_diff_key($_baseData['data'], $_compareData['data']);
        foreach ($_newColumns as $_newColumn => $_value)
            if (!array_key_exists($_newColumn, $_compareData['data']))
                $_compareData['data'][$_newColumn] = is_array($_value) ? array() : null;
        $_newColumns = array_diff_key($_compareData['data'], $_baseData['data']);
        foreach ($_newColumns as $_newColumn => $_value)
            if (!array_key_exists($_newColumn, $_baseData['data']))
                $_baseData['data'][$_newColumn] = is_array($_value) ? array() : null;

        $_diffArray = array();
        foreach ($_baseData['data'] as $_column => $_value) {
            $_newValue = $_value;
            $_oldValue = array_key_exists($_column, $_compareData['data']) ? $_compareData['data'][$_column] : $_newValue;

            if (is_array($_newValue))
                $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'options');
            else
                $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'text');
            if (strpos($_diff, '<span class=\'text-diff') !== false)
                $_diffArray[$_column] = $_diff;
        }

        unset($_baseData['data']);
        unset($_compareData['data']);

        foreach (array_diff($_changedColumns, array_keys($_diffArray)) as $_column)
            $_diffArray[$_column] = 'There isn’t anything to compare.';

        return array(
            'entity_id' => $_entity->$_primaryColumn,
            'entity_type' => get_class($_entity),
            'available_versions' => $_availableVersions,
            'base_version' => $_baseData,
            'compare_version' => $_compareData,
            'diff' => $_diffArray,
        );
    }

    public function GetSequentialVersions($_versions)
    {
        //This function fills the gaps between versions to insure not having a a year starts with a version other than 1 or having in a year version 1 then 3
        $_versionsNumbers = array_column($_versions, 'version');
        $_zeroVersions = array_filter($_versionsNumbers);
        $_versions = array_values(array_intersect_key($_versions, $_zeroVersions));

        $_versionsYears = array();
        $_sequentialVersions = array();
        foreach ($_versions as $_index => $_version) {
            //The first version in a year is not 1
            if (!in_array($_version['reporting_year'], $_versionsYears) && $_version['version'] != '1') {
                //If it is the first year, fill the missing versions with the first existing version
                //If it is not the first year, fill the missing versions with the previous version
                $_versionToFill = isset($_versions[$_index - 1]) ? $_versions[$_index - 1] : $_version;
                for ($_i = 1; $_i < $_version['version']; $_i++) {
                    $_versionToFill['version'] = (string)$_i;
                    $_versionToFill['reporting_year'] = $_version['reporting_year'];
                    if (!isset($_sequentialVersions[$_versionToFill['version'] . '-' . $_versionToFill['reporting_year']]))
                        $_sequentialVersions[$_versionToFill['version'] . '-' . $_versionToFill['reporting_year']] = $_versionToFill;
                }
            } elseif (isset($_versions[$_index + 1]) && $_version['reporting_year'] == $_versions[$_index + 1]['reporting_year'] && ($_version['version'] + 1) != $_versions[$_index + 1]['version']) {
                //If in the same year the versions are not sequential, fill the missing versions ahead with the current version
                $_versionToFill = $_version;
                for ($_i = 1; $_i < $_versions[$_index + 1]['version']; $_i++) {
                    $_versionToFill['version'] = (string)$_i;
                    if (!isset($_sequentialVersions[$_versionToFill['version'] . '-' . $_versionToFill['reporting_year']]))
                        $_sequentialVersions[$_versionToFill['version'] . '-' . $_versionToFill['reporting_year']] = $_versionToFill;
                }
            }

            if (!isset($_sequentialVersions[$_version['version'] . '-' . $_version['reporting_year']]))
                $_sequentialVersions[$_version['version'] . '-' . $_version['reporting_year']] = $_version;
            $_versionsYears[] = $_version['reporting_year'];
        }
        return array_values($_sequentialVersions);
    }

    public function GetIaReportingChangedColumns($_versions)
    {
        $_changedColumns = array();
        $_comparedVersions = array();
        foreach ($_versions as $_baseVersion) {
            $_comparedVersions[] = $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'];
            foreach ($_versions as $_compareVersion) {
                if (in_array($_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'], $_comparedVersions))
                    continue;
                foreach ($_baseVersion['data'] as $_column => $_value) {
                    $_newValue = $_value;
                    $_oldValue = array_key_exists($_column, $_compareVersion['data']) ? $_compareVersion['data'][$_column] : $_newValue;

                    if (is_array($_newValue))
                        $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'options');
                    else
                        $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'text');
                    if (strpos($_diff, '<span class=\'text-diff') !== false)
                        $_changedColumns[] = $_column;
                }
                $_comparedVersions[] = $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'];
                $_comparedVersions[] = $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'] . '-' . $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'];
            }
        }
        return array_values(array_unique($_changedColumns));
    }

    public function IaReportingItemChanged($_entity)
    {
        //Items with parent ID are updates from previous reporting years
        try {
            if ($_entity->parent_id != null)
                return true;
        } catch (Exception $e) {
        }

        try {
            $_previousVersions = Zend_Json::decode($_entity->previous_version);
        } catch (Exception $e) {
            $_previousVersions = array();
        }

        if (empty($_previousVersions))
            return false;

        $_previousVersions[] = array(
            'data' => $_entity->GetPlainTextData(),
            'version' => 'latest',
            'reporting_year' => $_entity->reporting_year
        );
        $_comparedVersions = array();
        foreach ($_previousVersions as $_baseVersion) {
            $_comparedVersions[] = $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'];
            foreach ($_previousVersions as $_compareVersion) {
                if (in_array($_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'], $_comparedVersions))
                    continue;
                $_baseData = $_baseVersion;
                $_compareData = $_compareVersion;
                foreach ($_baseData['data'] as $_column => $_value) {
                    $_newValue = $_value;
                    $_oldValue = array_key_exists($_column, $_compareData['data']) ? $_compareData['data'][$_column] : $_newValue;

                    if (is_array($_newValue))
                        $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'options');
                    else
                        $_diff = App_Function_ReviewComments::GetInputDiff($_oldValue, $_newValue, 'text');
                    if (strpos($_diff, '<span class=\'text-diff') !== false)
                        return true;
                }
                $_comparedVersions[] = $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'] . '-' . $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'];
                $_comparedVersions[] = $_compareVersion['reporting_year'] . '-' . $_compareVersion['version'] . '-' . $_baseVersion['reporting_year'] . '-' . $_baseVersion['version'];
            }
        }

        return false;
    }

    public function IaReportingItemIsNew($_entity)
    {
        try {
            $_previousVersions = Zend_Json::decode($_entity->previous_version);
        } catch (Exception $e) {
            $_previousVersions = array();
        }

        $_previousVersions = array_column($_previousVersions, 'version');
        return in_array('0', $_previousVersions);
    }

    public function GetPlainTextData($_dataArray = null)
    {
        if ($_dataArray == null)
            $_dataArray = $this->toArray();

        $_optionsColumns = array(
            'supplementary_information'
        );
        $_yesNoColumns = array(
            'ia_capacity',
            'ia_germplasm',
            'ia_smta',
            'ia_policies'
        );
        $_ignoredColumns = array(
            'ia_report_id',
            'is_draft',
            'added_by',
            'added_date',
            'updated_by',
            'updated_date',
            'version',
            'submitted_version',
            'partner_id',
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
                if ($_column == 'supplementary_information') {
                    if ($_value != '')
                        $_value = '<a href="' . APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_value . '" download="' . substr($_value, 33) . '">' . substr($_value, 33) . '</a>';
                    $_dataArray[$_column] = array($_value);
                } else {
                    $_dataArray[$_column] = array($_value);
                }
            } elseif (in_array($_column, $_yesNoColumns)) {
                if ($_value == '1')
                    $_value = 'Yes';
                elseif ($_value == '2')
                    $_value = 'Not applicable as there have been no transfers of MLS materials';
                else
                    $_value = 'No';
                $_dataArray[$_column] = array($_value);
            } else {
                $_dataArray[$_column] = $_value;
            }
        }
        return $_dataArray;
    }

    /**
     * @param $_entity Model_IaReportCrp | Model_IaReportManagementDocuments | Model_IaReportPortfolioDocuments | Model_IaReportAgreements | Model_IaReportAgreementPublicDisclosure | App_Model_ModelAbstract
     * @param $_iaReport Model_IaReport | App_Model_ModelAbstract
     * @param $_showCenterFeedback bool
     * @return array
     */
    public function GetUpdatedRecordInfo($_entity, $_iaReport, $_showCenterFeedback)
    {
        $_reportingYears = array($_entity->reporting_year);
        $_updates = $_entity->_getPreviousUpdates($_iaReport->reporting_year);
        $_updatesText = array();

        foreach ($_updates as $_update) {
            $_reportingYears[] = $_update->reporting_year;
            if ($_showCenterFeedback) {
                $_updateWithDiff = clone $_update;
                $_entityDiff = $_iaReport->GetIaReportingDiff($_update)['diff'];

                foreach ($_entityDiff as $_column => $_value)
                    if ($_value != '' && $_value != 'There isn’t anything to compare.')
                        try {
                            $_updateWithDiff->$_column = $_value;
                        } catch (Exception $e) {
                        }
            } else {
                $_updateWithDiff = $_update;
            }

            $_updateText = nl2br(trim($_updateWithDiff->update_text));
            if ($_updateText != '')
                $_updatesText[] = '<b>' . $_update->reporting_year . ':</b><br>' . nl2br(trim($_updateWithDiff->update_text));
        }

        $_info = App_Function_ReviewComments::GetFormRelatedTable(null, $_entity->getTableName() . '_review_comments');
        $_primaryColumn = $_info['primary_column'];
        $_newIds = App_Function_ReviewComments::GetAllRelatedItemsIds($_info['main_table'], $_primaryColumn, $_entity->$_primaryColumn);

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from($_info['main_table'], array(
                $_info['main_table'] . '.' . $_primaryColumn,
                $_info['main_table'] . '.reporting_year'
            ))
            ->where($_info['main_table'] . '.' . $_primaryColumn . ' IN (?)', $_newIds)
            ->order('reporting_year');
        $_entityInfo = $_db->fetchAll($_query);

        $_entityIds = array_column($_entityInfo, $_primaryColumn);
        $_entityIds[] = $_entity->$_primaryColumn;
        $_entityIds = array_values(array_filter($_entityIds));

        $_reportingYears = array_merge($_reportingYears, array_column($_entityInfo, 'reporting_year'));
        $_reportingYears = array_filter($_reportingYears);

        return array(
            'updates' => !empty($_updatesText) ? implode('<hr>', $_updatesText) : 'NA',
            'first_reporting_year' => min($_reportingYears),
            'last_reporting_year' => max($_reportingYears),
            'display_id' => $_entityIds[0]
        );
    }
}