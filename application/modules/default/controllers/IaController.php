<?php

/*
 * @IaController
 * */

class IaController extends Zend_Controller_Action
{
    public function init()
    {
        $_accessAllowed = App_Function_Privileges::isAdmin();
        /**
         * User group 12: Intellectual Property Focal Point
         * User group 12: Intellectual Property Focal Point (PMU)
         * //User group 28: MLS
         * User group 29: SCIPG
         */
        if (!$_accessAllowed && App_Function_Privileges::isMemberOf(array(11, 12, 28, 29)))
            $_accessAllowed = true;

        if (!$_accessAllowed) {
            /**
             * User assigned as Center Point(s) of Contact
             */
            $_iaReportContactsMapper = new Model_Mapper_IaReportContacts();
            try {
                $_iaReportContactsMapper->fetchOne(array('profile_id' => App_Function_Privileges::getLoggedUser()->user_id));
                $_accessAllowed = true;
            } catch (Exception $e) {
            }
        }
        if (!$_accessAllowed)
            $this->_helper->redirector('denied', 'user');
    }

    private function GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditEntity, $_iaReport)
    {
        //In case adding new item, set the reporting year as the main report
        if ($_entity->reporting_year == null)
            $_entity->reporting_year = $_iaReport->reporting_year;
        return array(
            'edit' => $_entity->is_deleted != '1' && $_canEditEntity && $_iaReport->reporting_year == $_entity->reporting_year,
            'update' => $_entity->is_deleted != '1' && $_canEditMainIAReport && $_iaReport->reporting_year > $_entity->reporting_year,
            'delete' => $_entity->is_deleted != '1' && $_canEditEntity && $_iaReport->reporting_year == $_entity->reporting_year
        );
    }

    private function GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear)
    {
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            return $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
        } catch (Exception $e) {
            $_iaReport = new Model_IaReport();
            $_logged_user = App_Function_Privileges::getLoggedUser();
            if (!((App_Function_Privileges::isMemberOf(12) || App_Function_Privileges::isAdmin()) && (int)$_iaPartnerId > 0))
                $_iaPartnerId = $_logged_user->organization_id;

            $_iaReport->partner_id = $_iaPartnerId;
            $_iaReport->reporting_year = $_reportingYear;
            return $_iaReport;
        }
    }

    private function GetCenterIaReportIds()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_ia_report', 'ia_report_id')
            ->where('tbl_ia_report.partner_id = (?)', App_Function_Privileges::getLoggedUser()->organization_id);
        return $db->fetchCol($_query);
    }

    public function indexAction()
    {

    }

    public function iareportingAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        if (!(App_Function_Privileges::isAdmin() && (int)$_iaPartnerId > 0))
            $_iaPartnerId = $_logged_user->organization_id;

        App_Navigation::AddLink($this->view->url(array(
            'module' => 'default',
            'controller' => 'ia',
            'action' => 'iareporting'
        ), null, false), 'IA Reporting');

        $this->view->mainTitle = 'Center IA Report';
        $this->view->subTitle = '';

        $_iaReportMapper = new Model_Mapper_IaReport();
        $_iaReport = new Model_IaReport();

        if ($_iaReportId != null) {
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
        } else {
            try {
                $_iaReport = $_iaReportMapper->fetchOne(array('partner_id' => $_iaPartnerId, 'reporting_year' => date('Y') - 1));

                $_requestParams = $this->getRequest()->getParams();
                unset($_requestParams['controller']);
                unset($_requestParams['action']);
                unset($_requestParams['module']);
                $_requestParams['ia_report_id'] = $_iaReport->ia_report_id;

                $this->_helper->redirector('iareporting', 'ia', null, $_requestParams);
                return;
            } catch (Exception $e) {
            }

            $_iaReport->is_draft = 1;
            $_iaReport->partner_id = $_iaPartnerId;
            $_iaReport->reporting_year = date('Y') - 1;
        }

        $this->view->ia_report = $_iaReport->toArray();
        $this->view->ia_report['ia_report_contacts'] = $_iaReport->ia_report_contacts->toArray();
        if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
            return $this->forward('denied', 'user');

        $_countriesMapper = new Model_Mapper_Countries();
        $this->view->countries = $_countriesMapper->fetchMany(null, 'name');
        $_cropsMapper = new Model_Mapper_Crop();
        $this->view->crops = $_cropsMapper->fetchMany(null, 'crop_name');

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $_portfolioTitles = $db->select()->distinct()
            ->from('tbl_ia_report_portfolio_documents', array('portfolio_title', 'short_title', 'ia_portfolio_type'))
            ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_portfolio_documents.ia_report_id', '')
            ->where('tbl_ia_report.partner_id = (?)', $_iaReport->partner_id);
        $this->view->portfolio_titles = $db->fetchAll($_portfolioTitles);

        $this->view->can_edit_ia_report = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        $this->view->ia_report['can_edit_ia_report'] = $this->view->can_edit_ia_report;
        $this->view->ia_report['history'] = $_iaReport->GetIaReportingDiff($_iaReport);

        try {
            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            $this->view->ia_report_review = $_iaReportReviewMapper->fetchOne(array(
                'ia_report_id' => $_iaReport->ia_report_id,
                'is_draft' => '0'
            ), 'review_id DESC');
        } catch (Exception $e) {
            $this->view->ia_report_review = new Model_IaReportReview();
            $this->view->ia_report_review->user_group_to = 12; //User group 12: Intellectual Property Focal Point (PMU)
        }

        $this->view->can_review_ia_report = App_Function_Privileges::canReviewIAReport($this->view->ia_report_review->user_group_to);
    }

    public function submitiareportAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_isDraft = $this->getRequest()->getParam('is_draft', 1);
        $_submitReview = $this->getRequest()->getParam('submit_review', 0);
        $_iaContactsIds = $this->getRequest()->getParam('ia_report_contacts', array());
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_supplementaryInformation = $this->getRequest()->getParam('supplementary_information', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_iaDescription = $this->getRequest()->getParam('ia_description', null);
        $_iaRelatedActivities = $this->getRequest()->getParam('ia_related_activities', null);
        $_iaCapacity = $this->getRequest()->getParam('ia_capacity', null);
        $_iaCapacityExplain = $this->getRequest()->getParam('ia_capacity_explain', null);
        $_iaPartnerships = $this->getRequest()->getParam('ia_partnerships', null);
        $_iaManagementApproach = $this->getRequest()->getParam('ia_management_approach', null);
        $_iaGeneralHighlights = $this->getRequest()->getParam('ia_general_highlights', null);
        $_iaAdditionalNotes1 = $this->getRequest()->getParam('ia_additional_notes_1', null);
        $_iaAdditionalNotes2 = $this->getRequest()->getParam('ia_additional_notes_2', null);
        $_iaGermplasm = $this->getRequest()->getParam('ia_germplasm', null);
        $_iaGermplasmExplain = $this->getRequest()->getParam('ia_germplasm_explain', null);
        $_iaSmta = $this->getRequest()->getParam('ia_smta', null);
        $_iaSmtaExplain = $this->getRequest()->getParam('ia_smta_explain', null);
        $_iaPolicies = $this->getRequest()->getParam('ia_policies', null);
        $_iaPoliciesExplain = $this->getRequest()->getParam('ia_policies_explain', null);
        $_iaHighlightsExplain = $this->getRequest()->getParam('ia_highlights_explain', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        if (!(App_Function_Privileges::isAdmin() && (int)$_iaPartnerId > 0))
            $_iaPartnerId = $_logged_user->organization_id;

        $_iaReportMapper = new Model_Mapper_IaReport();
        $_iaReport = new Model_IaReport();
        if ($_iaReportId != null) {
            try {
                $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
                $_isDraft = $_iaReport->is_draft == '0' ? $_iaReport->is_draft : $_isDraft;
            } catch (Exception $e) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'IA report not found.',
                ));
            }
        } else {
            $_iaReport->partner_id = $_iaPartnerId;
            $_iaReport->reporting_year = $_iaReportingYear;
        }

        if (!App_Function_Privileges::canEditIAReport($_iaReport, null, true))
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
            ));
        $_isRevise = $_iaReport->is_draft == '0';

        try {
            $_iaReportMapper->fetchOne(array(
                '!ia_report_id' => $_iaReportId,
                'partner_id' => $_iaReport->partner_id,
                'reporting_year' => $_iaReportingYear
            ));
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'The year “' . $_iaReportingYear . '” has been already reported.'
            ));
        } catch (Exception $e) {
        }

        $_iaContactsIds = array_filter($_iaContactsIds);
        if (empty($_iaContactsIds))
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Please select at least one Point of Contact.'
            ));

        $_data = array(
            'is_draft' => $_isDraft,
            'ia_description' => $_iaDescription,
            'partner_id' => $_iaReport->partner_id,
            'reporting_year' => $_iaReportingYear,
            'supplementary_information' => $_supplementaryInformation,
            'ia_related_activities' => $_iaRelatedActivities,
            'ia_capacity' => $_iaCapacity,
            'ia_capacity_explain' => $_iaCapacityExplain,
            'ia_partnerships' => $_iaPartnerships,
            'ia_management_approach' => $_iaManagementApproach,
            'ia_general_highlights' => $_iaGeneralHighlights,
            'ia_additional_notes_1' => $_iaAdditionalNotes1,
            'ia_additional_notes_2' => $_iaAdditionalNotes2,
            'ia_germplasm' => $_iaGermplasm,
            'ia_germplasm_explain' => $_iaGermplasmExplain,
            'ia_smta' => $_iaSmta,
            'ia_smta_explain' => $_iaSmtaExplain,
            'ia_policies' => $_iaPolicies,
            'ia_policies_explain' => $_iaPoliciesExplain,
            'ia_highlights_explain' => $_iaHighlightsExplain,
        );

        $_missingAnticipatedMessage = '';
        if ((!$_isRevise && $_isDraft != '1') || ($_isRevise && $_submitReview == '1')) {
            $_missingAnticipated = $this->validatePublicDisclosuresAnticipated($_iaReportId);
            if (!empty($_missingAnticipated)) {
                $_missingAnticipatedMessage = 'But not submitted for final review, please answer the question "<b>Anticipated public disclosure or updated disclosure</b>" for the following public disclosures (Section 3.2) then submit again:';
                $_missingAnticipatedMessage .= '<ul><li>ID: ' . implode('</li><li>ID: ', $_missingAnticipated) . '</li></ul>';

                if (!$_isRevise)
                    $_data['is_draft'] = $_isDraft = '1';
                else
                    $_submitReview = '0';
            }
        }

        try {
            if ($_iaReportId != null) {
                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['partner_id']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReport);

                if ((!$_isRevise && $_isDraft != '1') || ($_isRevise && $_submitReview == '1'))
                    $_data['submitted_version'] = $_iaReport->version;

                $_iaReportMapper->update($_data, 'ia_report_id = ' . $_iaReportId);

                if ($_isDraft != '1')
                    $_action = 'submitted';

                if ($_isRevise && $_submitReview == '1' && empty($_missingAnticipated)) {
                    $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
                    $_iaReportReviewMapper->insert(array(
                        'ia_report_id' => $_iaReportId,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => 0,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ));

                    $this->alignSection3CenterReviseReview($_iaReportId);
                    if (!empty($_missingAnticipated))
                        $_action = 'updated';
                } else {
                    $_action = 'updated';
                }

            } else {
                $_data['version'] = 1;
                $_data['submitted_version'] = $_data['version'];
                $_data['added_by'] = $_logged_user->user_id;
                $_iaReportId = $_iaReportMapper->insert($_data);
                $_action = 'added';
                if ($_isDraft != '1')
                    $_action = 'submitted';
            }

            $this->addIAReportContacts($_iaContactsIds, $_iaReportId);

            if ($_iaReport->reporting_year != $_iaReportingYear)
                $this->AdjustIAReportRelatedItemsReportingYear($_iaReportingYear, $_iaReportId);

            $this->_helper->json->sendJson(array(
                'result' => $_missingAnticipatedMessage === '',
                'message' => 'IA report ' . $_action . ' successfully.',
                'is_draft' => $_isDraft,
                'ia_report_id' => $_iaReportId,
                'reporting_year' => $_iaReportingYear,
                'missing_public_disclosures_anticipated_message' => $_missingAnticipatedMessage
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    private function addIAReportContacts($_iaContactsIds, $_iaReportId)
    {
        $_iaReportContactsMapper = new Model_Mapper_IaReportContacts();
        $_contactEntity = new Model_PartnerContact();

        $_iaReportContactsMapper->delete('ia_report_id = ' . $_iaReportId);
        $_data = array(
            'ia_report_id' => $_iaReportId,
            'is_primary' => 1
        );

        foreach ($_iaContactsIds as $_iaContactId) {
            if (!((int)$_iaContactId > 0))
                continue;
            $_iaContactId = $_contactEntity->beUser($_iaContactId);
            $_data['profile_id'] = $_iaContactId;
            try {
                $_iaReportContactsMapper->insert($_data);
                $_data['is_primary'] = 0;
            } catch (Exception $e) {
            }
        }
    }

    private function AdjustIAReportRelatedItemsReportingYear($_iaReportingYear, $_iaReportId)
    {
        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
        $_iaReportCrpMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        $_iaReportManagementDocumentsMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        $_iaReportPortfolioDocumentsMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreementsMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        $_iaReportAgreementPublicDisclosureMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();
        $_iaReportUpdatesMapper->update(array('reporting_year' => $_iaReportingYear), 'ia_report_id = ' . $_iaReportId);
    }

    private function alignSection3CenterReviseReview($_iaReportId)
    {
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();

        $_iaReportAgreements = $_iaReportAgreementsMapper->fetchMany(array('ia_report_id' => $_iaReportId));
        $_iaReportAgreementPublicDisclosures = $_iaReportAgreementPublicDisclosureMapper->fetchMany(array('ia_report_id' => $_iaReportId));

        foreach ($_iaReportAgreements as $_iaReportAgreement) {
            if ($_iaReportAgreement->version != $_iaReportAgreement->submitted_version)
                try {
                    $_iaReportAgreementsMapper->update(array(
                        'submitted_version' => $_iaReportAgreement->version,
                        'updated_date' => $_iaReportAgreement->updated_date
                    ), 'ia_agreement_id = ' . $_iaReportAgreement->ia_agreement_id);
                } catch (Exception $e) {
                }
            try {
                $_iaReportLastReview = $_iaReportReviewMapper->fetchOne(array(
                    'ia_agreement_id' => $_iaReportAgreement->ia_agreement_id
                ), 'review_id DESC');

                //If the review record is draft, update it
                if ($_iaReportLastReview->user_group_from == 11 && $_iaReportLastReview->is_draft == '1')
                    $_iaReportReviewMapper->update(array(
                        'ia_agreement_id' => $_iaReportAgreement->ia_agreement_id,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => 0,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ), 'review_id = ' . $_iaReportLastReview->review_id);

                //If the review record is is submitted to the center, insert the center review record
                elseif ($_iaReportLastReview->user_group_to == 11 && $_iaReportLastReview->is_draft == '0')
                    $_iaReportReviewMapper->insert(array(
                        'ia_agreement_id' => $_iaReportAgreement->ia_agreement_id,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => 0,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ));
            } catch (Exception $e) {
            }
        }
        foreach ($_iaReportAgreementPublicDisclosures as $_iaReportAgreementPublicDisclosure) {
            if ($_iaReportAgreementPublicDisclosure->version != $_iaReportAgreementPublicDisclosure->submitted_version)
                try {
                    $_iaReportAgreementPublicDisclosureMapper->update(array(
                        'submitted_version' => $_iaReportAgreementPublicDisclosure->version,
                        'updated_date' => $_iaReportAgreementPublicDisclosure->updated_date
                    ), 'ia_agreement_public_disclosure_id = ' . $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id);
                } catch (Exception $e) {
                }
            try {
                $_iaReportLastReview = $_iaReportReviewMapper->fetchOne(array(
                    'ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id
                ), 'review_id DESC');

                //If the review record is draft, update it
                if ($_iaReportLastReview->user_group_from == 11 && $_iaReportLastReview->is_draft == '1')
                    $_iaReportReviewMapper->update(array(
                        'ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => 0,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ), 'review_id = ' . $_iaReportLastReview->review_id);

                //If the review record is is submitted to the center, insert the center review record
                elseif ($_iaReportLastReview->user_group_to == 11 && $_iaReportLastReview->is_draft == '0')
                    $_iaReportReviewMapper->insert(array(
                        'ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => 0,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ));
            } catch (Exception $e) {
            }
        }
    }

    private function validatePublicDisclosuresAnticipated($_iaReportId)
    {
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        $_iaReportAgreementPublicDisclosures = $_iaReportAgreementPublicDisclosureMapper->fetchMany(array('ia_report_id' => $_iaReportId));

        $_missingAnticipated = array();
        foreach ($_iaReportAgreementPublicDisclosures as $_iaReportAgreementPublicDisclosure)
            if ($_iaReportAgreementPublicDisclosure->anticipated_public_disclosure == null)
                $_missingAnticipated[] = $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id;

        return $_missingAnticipated;
    }

    public function getiareportAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        if (!(App_Function_Privileges::isAdmin() && (int)$_iaPartnerId > 0))
            $_iaPartnerId = $_logged_user->organization_id;

        $_iaReportMapper = new Model_Mapper_IaReport();

        try {
            $_iaReport = $_iaReportMapper->fetchOne(array(
                '!ia_report_id' => $_iaReportId,
                'reporting_year' => $_iaReportingYear,
                'partner_id' => $_iaPartnerId));
        } catch (Exception $e) {
            $_iaReport = new Model_IaReport();
        }

        $this->_helper->json->sendJson(array(
            'ia_report_id' => $_iaReport->ia_report_id
        ));
    }

    public function getalliareportsAction()
    {
        $_where = null;

        $_logged_user = App_Function_Privileges::getLoggedUser();
        $_isIpPmu = App_Function_Privileges::isMemberOf(12) || App_Function_Privileges::isAdmin();
        if (!$_isIpPmu)
            $_where = array('partner_id' => $_logged_user->organization_id);

        $_iaReportsMapper = new Model_Mapper_IaReport();
        $_iaReports = $_iaReportsMapper->fetchMany($_where, array('partner_id', 'reporting_year'));

        $_dataArray = array();
        foreach ($_iaReports as $_iaReport) {
            $_iaReportArray = $_iaReport->toArray();
            $_iaReportArray['organization_name'] = $_iaReport->organization_name;
            $_iaReportArray['added_by_name'] = $_iaReport->added_by_name;
            $_iaReportArray['updated_by_name'] = $_iaReport->updated_by_name;
            $_iaReportArray['added_date'] = $_iaReport->added_date != null ? date('Y-m-d', strtotime($_iaReport->added_date)) : null;
            $_iaReportArray['updated_date'] = $_iaReport->updated_date != null ? date('Y-m-d', strtotime($_iaReport->updated_date)) : null;
            $_iaReportArray['status'] = $_iaReport->is_draft == '1' ? 'Draft' : 'Submitted';
            $_iaReportArray['can_edit_ia_report'] = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
            $_iaReportArray['can_review_ia_report'] = App_Function_Privileges::canReviewIAReport(null);

            $_dataArray[] = $_iaReportArray;
        }
        $this->_helper->json->sendJson(array(
            'data' => $_dataArray
        ));
    }

    public function deleteiareportAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);

        $_iaReportMapper = new Model_Mapper_IaReport();

        $_iaReport = $this->GetIaReport($_iaReportId, null, null);
        if ($_iaReport->is_deleted != '1' || !App_Function_Privileges::canEditIAReport($_iaReport, null, true))
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
            ));

        try {
            $_iaReportMapper->delete('ia_report_id = ' . $_iaReportId);
            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'IA report deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function submitiareportcrpAction()
    {
        $_mainIaReportId = $this->getRequest()->getParam('main_ia_report_id', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaCrpReportId = $this->getRequest()->getParam('ia_report_crp_id', null);
        $_crpId = $this->getRequest()->getParam('crp_id', null);
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaCrpManagementCapacity = $this->getRequest()->getParam('ia_crp_management_capacity', null);
        $_iaCrpManagementOversight = $this->getRequest()->getParam('ia_crp_management_oversight', null);
        $_iaCrpManagementPolicies = $this->getRequest()->getParam('ia_crp_management_policies', null);
        $_iaCrpManagementCommittees = $this->getRequest()->getParam('ia_crp_management_committees', null);
        $_updateText = $this->getRequest()->getParam('update_text', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
        $_iaReportCrp = new Model_IaReportCrp();
        if ($_iaCrpReportId != null) {
            try {
                $_iaReportCrp = $_iaReportCrpMapper->fetchOne(array('ia_report_crp_id' => $_iaCrpReportId));
                $_iaReportId = $_iaReportCrp->ia_report_id;
            } catch (Exception $e) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'IA management CRP not found.'
                ));
            }
        }

        $_isRevise = false;
        $_parentId = null;
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_mainIaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_mainIaReportId));

            if ($_iaReportCrp->ia_report_crp_id != null) {
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_mainIaReport, null, true);
                $_operations = $this->GetEntityOperations($_iaReportCrp, $_canEditMainIAReport, $_canEditMainIAReport, $_mainIaReport);
                $_canEdit = $_operations['edit'] || $_operations['update'];
            } else {
                $_canEdit = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
            }
            if (!$_canEdit)
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_mainIaReport->is_draft == '0')
                $_isRevise = true;

            if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id) {
                $_parentId = $_iaReportCrp->ia_report_crp_id;
                $_iaReportId = $_mainIaReport->ia_report_id;
                $_iaReportingYear = $_mainIaReport->reporting_year;
                $_iaReportCrp = new Model_IaReportCrp();
                $_isRevise = false;
            } else {
                $_parentId = $_iaReportCrp->parent_id;
            }
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'IA report not found.'
            ));
            exit;
        }

        $_data = array(
            'parent_id' => $_parentId == '' ? null : $_parentId,
            'ia_report_id' => $_iaReportId,
            'crp_id' => $_crpId,
            'reporting_year' => $_iaReportingYear,
            'ia_crp_management_capacity' => $_iaCrpManagementCapacity,
            'ia_crp_management_oversight' => $_iaCrpManagementOversight,
            'ia_crp_management_policies' => $_iaCrpManagementPolicies,
            'ia_crp_management_committees' => $_iaCrpManagementCommittees
        );

        try {
            if ($_iaReportCrp->ia_report_crp_id != null) {
                if ($_iaReportCrp->is_deleted == '1')
                    $this->_helper->json->sendJson(array(
                        'result' => true,
                        'message' => 'Cannot submit, is already deleted.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['reporting_year']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportCrp);

                $_iaReportCrpMapper->update($_data, 'ia_report_crp_id = ' . $_iaReportCrp->ia_report_crp_id);

                $_action = 'updated';
            } else {
                $_data['added_by'] = $_logged_user->user_id;
                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportCrp, true);
                $_iaReportCrp->ia_report_crp_id = $_iaReportCrpMapper->insert($_data);
                if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id)
                    $_action = 'updated';
                else
                    $_action = 'added';
            }

            $this->SubmitIaReportUpdate($_mainIaReport, $_iaReportingYear, $_iaReportCrp->ia_report_crp_id, 'ia_report_crp_id', $_updateText, $_isRevise);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'IA management CRP ' . $_action . ' successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function getalliareportcrpsAction()
    {
        $_iaReportIds = $this->getRequest()->getParam('ia_report_ids', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();

        if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null) {
            $_iaReport = new Model_IaReport();
            $_where = array();
            $_canEditMainIAReport = false;
        } else {
            $_iaReport = $this->GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear);
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'data' => array()
                ));
            $_where = array('ia_report_id' => $_iaReport->PreviousIaReportsIds());
            $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        }
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['ia_report_id'] = $this->GetCenterIaReportIds();

        if (!is_array($_iaReportIds))
            $_iaReportIds = explode(',', $_iaReportIds);
        $_iaReportIds = array_filter($_iaReportIds);
        if (!empty($_iaReportIds))
            $_where['ia_report_id'] = $_iaReportIds;

        $_iaReportCrps = $_iaReportCrpMapper->fetchMany($_where, 'reporting_year');

        $_iaReports = array();
        $_dataArray = array();
        foreach ($_iaReportCrps as $_entity) {
            if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null)
                $_iaReport = new Model_IaReport();

            $_data = $_entity->toArray();

            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);
            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            if (!array_key_exists($_entity->ia_report_id, $_iaReports))
                $_iaReports[$_entity->ia_report_id] = $_entity->ia_report;
            $_data['partner_id'] = $_iaReports[$_entity->ia_report_id]->partner_id;

            $_data['crp'] = $_entity->crp->full_name;

            if ($_iaReport->ia_report_id == null)
                $_iaReport = $_itemIaReport;

            $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
            $_data['can_edit_ia_report'] = $_operations['edit'];
            $_data['can_update_ia_report'] = $_operations['update'];
            $_data['can_delete_ia_report'] = $_operations['delete'];

            $_data['has_comments'] = count(App_Function_ReviewComments::HasReviewComments('ia_report_crp_form', $_entity->ia_report_crp_id, null, $_itemIaReport->partner_id)) > 0;

            $_data['is_modified'] = $_iaReport->IaReportingItemChanged($_entity);
            $_data['is_new'] = $_iaReport->IaReportingItemIsNew($_entity);
            $_data['is_draft'] = $_itemIaReport->is_draft == '1';
            $_data['reporting_years'][] = $_data['reporting_year'];

            $_data['reporting_years'] = $_entity->_getPreviousUpdatesYears();
            $_data['display_id'] = $_entity->ia_report_crp_id;
            if (isset($_dataArray[$_entity->parent_id])) {
                $_data['reporting_years'] = array_merge($_data['reporting_years'], $_dataArray[$_entity->parent_id]['reporting_years']);
                unset($_dataArray[$_entity->parent_id]);
                $_data['display_id'] = $_entity->parent_id;
            }
            $_data['reporting_years'][] = $_data['reporting_year'];
            $_data['reporting_years'] = array_values(array_unique(array_filter($_data['reporting_years'])));

            $_dataArray[$_entity->ia_report_crp_id] = $_data;
        }
        $this->_helper->json->sendJson(array(
            'data' => array_values($_dataArray)
        ));
    }

    public function getiareportcrpAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaReportCrpId = $this->getRequest()->getParam('ia_report_crp_id', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();
        if ($_iaReportCrpId != null) {
            try {
                $_entity = $_iaReportCrpMapper->fetchOne(array('ia_report_crp_id' => $_iaReportCrpId));

                $_iaReport = $_entity->ia_report;
                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = $_entity->toArray();

                $_iaReport = $this->GetIaReport($_iaReportId, null, null);
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
                $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
                $_data['can_edit_ia_report'] = $_operations['edit'] && $_iaReportReviewPage != '1';
                $_data['can_update_ia_report'] = $_operations['update'] && $_iaReportReviewPage != '1';

                $_data['history'] = $_iaReport->GetIaReportingDiff($_entity, $_iaReport->version, $_iaReport->reporting_year, -1, null);

                $_data['updates'] = $this->PrepareEntityUpdates($_entity, $_iaReport,  $_data['can_edit_ia_report'] || $_data['can_update_ia_report']);

                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'IA management CRP not found.'
        ));
    }

    public function deleteiareportcrpAction()
    {
        $_iaReportCrpId = $this->getRequest()->getParam('ia_report_crp_id', null);

        $_iaReportCrpMapper = new Model_Mapper_IaReportCrp();

        try {
            $_iaReportCrp = $_iaReportCrpMapper->fetchOne(array('ia_report_crp_id' => $_iaReportCrpId));

            $_iaReport = $this->GetIaReport($_iaReportCrp->ia_report_id, null, null);
            if (!App_Function_Privileges::canEditIAReport($_iaReport, null, true))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_iaReport->is_draft == '1')
                $_iaReportCrpMapper->delete('ia_report_crp_id = ' . $_iaReportCrpId);
            else
                $_iaReportCrpMapper->update(array('is_deleted' => '1'), 'ia_report_crp_id = ' . $_iaReportCrpId);
            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'IA management CRP deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function submitiamanagementdocumentAction()
    {
        $_mainIaReportId = $this->getRequest()->getParam('main_ia_report_id', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaManagementDocumentId = $this->getRequest()->getParam('ia_management_document_id', null);
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_otherDocumentType = $this->getRequest()->getParam('other_document_type', null);
        $_policyTitle = $this->getRequest()->getParam('policy_title', null);
        $_category = $this->getRequest()->getParam('category', null);
        $_manageStatus = $this->getRequest()->getParam('manage_status', null);
        $_approvalDate = $this->getRequest()->getParam('approval_date', null);
        $_effectiveDate = $this->getRequest()->getParam('effective_date', null);
        $_isCrpRelated = $this->getRequest()->getParam('is_crp_related', null);
        $_crpId = $this->getRequest()->getParam('crp_id', null);
        $_availabilityStatus = $this->getRequest()->getParam('availability_status', null);
        $_availabilityStatusDocument = $this->getRequest()->getParam('availability_status_document', null);
        $_isPubliclyAvailable = $this->getRequest()->getParam('is_publicly_available', null);
        $_publicUrl = $this->getRequest()->getParam('public_url', null);
        $_updateText = $this->getRequest()->getParam('update_text', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        $_iaReportManagementDocument = new Model_IaReportManagementDocuments();
        if ($_iaManagementDocumentId != null) {
            try {
                $_iaReportManagementDocument = $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $_iaManagementDocumentId));
                $_iaReportId = $_iaReportManagementDocument->ia_report_id;
            } catch (Exception $e) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Center IA Management Related document not found.'
                ));
            }
        }

        $_isRevise = false;
        $_parentId = null;
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_mainIaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_mainIaReportId));

            if ($_iaReportManagementDocument->ia_management_document_id != null) {
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_mainIaReport, null, true);
                $_operations = $this->GetEntityOperations($_iaReportManagementDocument, $_canEditMainIAReport, $_canEditMainIAReport, $_mainIaReport);
                $_canEdit = $_operations['edit'] || $_operations['update'];
            } else {
                $_canEdit = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
            }
            if (!$_canEdit)
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_mainIaReport->is_draft == '0')
                $_isRevise = true;

            if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id) {
                $_parentId = $_iaReportManagementDocument->ia_management_document_id;
                $_iaReportId = $_mainIaReport->ia_report_id;
                $_iaReportingYear = $_mainIaReport->reporting_year;
                $_iaReportManagementDocument = new Model_IaReportManagementDocuments();
                $_isRevise = false;
            } else {
                $_parentId = $_iaReportManagementDocument->parent_id;
            }
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'IA report not found.'
            ));
            exit;
        }

        $_data = array(
            'parent_id' => $_parentId == '' ? null : $_parentId,
            'ia_report_id' => $_iaReportId,
            'reporting_year' => $_iaReportingYear,
            'other_document_type' => $_otherDocumentType == '' ? null : $_otherDocumentType,
            'policy_title' => $_policyTitle,
            'category' => $_category,
            'manage_status' => $_manageStatus,
            'approval_date' => $_approvalDate == '' ? null : $_approvalDate,
            'effective_date' => $_effectiveDate == '' ? null : $_effectiveDate,
            'is_crp_related' => $_isCrpRelated,
            'crp_id' => !empty($_crpId) ? Zend_Json::encode($_crpId) : null,
            'availability_status' => $_availabilityStatus,
            'availability_status_document' => $_availabilityStatusDocument,
            'is_publicly_available' => $_isPubliclyAvailable,
            'public_url' => $_publicUrl
        );

        try {
            if ($_iaReportManagementDocument->ia_management_document_id != null) {
                if ($_iaReportManagementDocument->is_deleted == '1')
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['reporting_year']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportManagementDocument);
                $_iaReportManagementDocumentsMapper->update($_data, 'ia_management_document_id = ' . $_iaReportManagementDocument->ia_management_document_id);

                $_action = 'updated';
            } else {
                $_data['added_by'] = $_logged_user->user_id;
                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportManagementDocument, true);
                $_iaReportManagementDocument->ia_management_document_id = $_iaReportManagementDocumentsMapper->insert($_data);
                if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id)
                    $_action = 'updated';
                else
                    $_action = 'added';
            }

            $this->SubmitIaReportUpdate($_mainIaReport, $_iaReportingYear, $_iaReportManagementDocument->ia_management_document_id, 'ia_management_document_id', $_updateText, $_isRevise);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Center IA Management Related Document ' . $_action . ' successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function getalliamanagementdocumentsAction()
    {
        $_iaReportIds = $this->getRequest()->getParam('ia_report_ids', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_isOtherType = $this->getRequest()->getParam('is_other_type', false);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();

        if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null) {
            $_iaReport = new Model_IaReport();
            $_where = array();
            $_canEditMainIAReport = false;
        } else {
            $_iaReport = $this->GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear);
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'data' => array()
                ));

            $_where = array('ia_report_id' => $_iaReport->PreviousIaReportsIds());
            $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        }
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['ia_report_id'] = $this->GetCenterIaReportIds();

        if (!is_array($_iaReportIds))
            $_iaReportIds = explode(',', $_iaReportIds);
        $_iaReportIds = array_filter($_iaReportIds);
        if (!empty($_iaReportIds))
            $_where['ia_report_id'] = $_iaReportIds;

        if ($_isOtherType)
            $_where['!other_document_type'] = null;
        else
            $_where['other_document_type'] = null;

        $_iaReportManagementDocuments = $_iaReportManagementDocumentsMapper->fetchMany($_where, 'reporting_year');

        $_dataArray = array();
        $_iaReports = array();
        foreach ($_iaReportManagementDocuments as $_entity) {
            if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null)
                $_iaReport = new Model_IaReport();

            $_data = $_entity->toArray();

            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);
            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            if (!array_key_exists($_entity->ia_report_id, $_iaReports))
                $_iaReports[$_entity->ia_report_id] = $_entity->ia_report;
            $_data['partner_id'] = $_iaReports[$_entity->ia_report_id]->partner_id;

            if ($_iaReport->ia_report_id == null)
                $_iaReport = $_itemIaReport;

            $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
            $_data['can_edit_ia_report'] = $_operations['edit'];
            $_data['can_update_ia_report'] = $_operations['update'];
            $_data['can_delete_ia_report'] = $_operations['delete'];

            $_data['is_revoked'] = $_data['manage_status'] === 'Revoked/Superseded';

            $_data['has_comments'] = count(App_Function_ReviewComments::HasReviewComments('ia_management_document_form', $_entity->ia_management_document_id, null, $_itemIaReport->partner_id)) > 0;
            $_data['is_modified'] = $_iaReport->IaReportingItemChanged($_entity);
            $_data['is_new'] = $_iaReport->IaReportingItemIsNew($_entity);
            $_data['reporting_years'][] = $_data['reporting_year'];

            $_data['reporting_years'] = $_entity->_getPreviousUpdatesYears();
            $_data['display_id'] = $_entity->ia_management_document_id;
            if (isset($_dataArray[$_entity->parent_id])) {
                $_data['reporting_years'] = array_merge($_data['reporting_years'], $_dataArray[$_entity->parent_id]['reporting_years']);
                unset($_dataArray[$_entity->parent_id]);
                $_data['display_id'] = $_entity->parent_id;
            }
            $_data['reporting_years'][] = $_data['reporting_year'];
            $_data['reporting_years'] = array_values(array_unique(array_filter($_data['reporting_years'])));

            $_dataArray[$_entity->ia_management_document_id] = $_data;
        }
        usort($_dataArray, function ($a, $b) {
            return $a['is_revoked'] <=> $b['is_revoked'];
        });
        $this->_helper->json->sendJson(array(
            'data' => array_values($_dataArray)
        ));
    }

    public function getiamanagementdocumentAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaManagementDocumentId = $this->getRequest()->getParam('ia_management_document_id', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        if ($_iaManagementDocumentId != null) {
            try {
                $_entity = $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $_iaManagementDocumentId));

                $_iaReport = $_entity->ia_report;
                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = $_entity->toArray();

                $_iaReport = $this->GetIaReport($_iaReportId, null, null);
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
                $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
                $_data['can_edit_ia_report'] = $_operations['edit'] && $_iaReportReviewPage != '1';
                $_data['can_update_ia_report'] = $_operations['update'] && $_iaReportReviewPage != '1';

                $_data['history'] = $_iaReport->GetIaReportingDiff($_entity, $_iaReport->version, $_iaReport->reporting_year, -1, null);

                $_data['updates'] = $this->PrepareEntityUpdates($_entity, $_iaReport, $_data['can_edit_ia_report'] || $_data['can_update_ia_report']);

                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'Center IA Management Related document not found.'
        ));
    }

    public function deleteiamanagementdocumentAction()
    {
        $_iaManagementDocumentId = $this->getRequest()->getParam('ia_management_document_id', null);

        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();

        try {
            $_iaReportManagementDocument = $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $_iaManagementDocumentId));

            $_iaReport = $this->GetIaReport($_iaReportManagementDocument->ia_report_id, null, null);
            if (!App_Function_Privileges::canEditIAReport($_iaReport, null, true))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_iaReport->is_draft == '1')
                $_iaReportManagementDocumentsMapper->delete('ia_management_document_id = ' . $_iaManagementDocumentId);
            else
                $_iaReportManagementDocumentsMapper->update(array('is_deleted' => '1'), 'ia_management_document_id = ' . $_iaManagementDocumentId);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Center IA Management Related Document deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function submitiaportfoliodocumentAction()
    {
        $_mainIaReportId = $this->getRequest()->getParam('main_ia_report_id', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPortfolioDocumentId = $this->getRequest()->getParam('ia_portfolio_document_id', null);
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaPortfolioType = $this->getRequest()->getParam('ia_portfolio_type', null);
        $_isCrpRelated = $this->getRequest()->getParam('is_crp_related', null);
        $_crpId = $this->getRequest()->getParam('crp_id', null);
        $_ownerApplicant = $this->getRequest()->getParam('owner_applicant', null);
        $_portfolioTitle = $this->getRequest()->getParam('portfolio_title', null);
        $_shortTitle = $this->getRequest()->getParam('short_title', null);
        $_filingType1 = $this->getRequest()->getParam('filing_type_1', null);
        $_filingType2 = $this->getRequest()->getParam('filing_type_2', null);
        $_countries = $this->getRequest()->getParam('country_id', array());
        if (!is_array($_countries))
            $_countries = explode(',', $_countries);
        $_status = $this->getRequest()->getParam('status', array());
        if (!is_array($_status))
            $_status = explode(',', $_status);
        $_applicationNumber = $this->getRequest()->getParam('application_number', null);
        $_filingDate = $this->getRequest()->getParam('filing_date', null);
        $_registrationDate = $this->getRequest()->getParam('registration_date', null);
        $_expiryDate = $this->getRequest()->getParam('expiry_date', null);
        $_externalLink = $this->getRequest()->getParam('external_link', null);
        $_cropId = $this->getRequest()->getParam('crop_id', null);
        $_trademarkType = $this->getRequest()->getParam('trademark_type', null);
        $_claimsCategories = $this->getRequest()->getParam('claims_categories', null);
        $_innovationSummary = $this->getRequest()->getParam('innovation_summary', null);
        $_claimsSummary = $this->getRequest()->getParam('claims_summary', null);
        $_updateText = $this->getRequest()->getParam('update_text', null);

        $_logged_user = App_Function_Privileges::getLoggedUser();

        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        $_iaReportPortfolioDocument = new Model_IaReportPortfolioDocuments();
        if ($_iaPortfolioDocumentId != null) {
            try {
                $_iaReportPortfolioDocument = $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_iaPortfolioDocumentId));
                $_iaReportId = $_iaReportPortfolioDocument->ia_report_id;
            } catch (Exception $e) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Center IA Portfolio Document not found.'
                ));
            }
        }

        if (empty(array_filter($_countries)))
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Select at least one country.'
            ));

        $_isRevise = false;
        $_parentId = null;
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_mainIaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_mainIaReportId));

            if ($_iaReportPortfolioDocument->ia_portfolio_document_id != null) {
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_mainIaReport, null, true);
                $_operations = $this->GetEntityOperations($_iaReportPortfolioDocument, $_canEditMainIAReport, $_canEditMainIAReport, $_mainIaReport);
                $_canEdit = $_operations['edit'] || $_operations['update'];
            } else {
                $_canEdit = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
            }
            if (!$_canEdit)
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_mainIaReport->is_draft == '0')
                $_isRevise = true;

            if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id) {
                $_parentId = $_iaReportPortfolioDocument->ia_portfolio_document_id;
                $_iaReportId = $_mainIaReport->ia_report_id;
                $_iaReportingYear = $_mainIaReport->reporting_year;
                $_iaReportPortfolioDocument = new Model_IaReportPortfolioDocuments();
                $_isRevise = false;
            } else {
                $_parentId = $_iaReportPortfolioDocument->parent_id;
            }
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'IA report not found.'
            ));
            exit;
        }

        if ($_iaReportPortfolioDocument->ia_portfolio_document_id != null) {
            try {
                $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
                $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('agreement_related_portfolio' => $_iaReportPortfolioDocument->ia_portfolio_document_id));

                if ($_iaReportAgreement->ip_type != $_iaPortfolioType)
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'Cannot submit, changing the "Type of Document" is not allowed as this document is associated with an IP Application.'
                    ));
            } catch (Exception $e) {
            }
        }

        $_data = array(
            'parent_id' => $_parentId == '' ? null : $_parentId,
            'ia_report_id' => $_iaReportId,
            'reporting_year' => $_iaReportingYear,
            'ia_portfolio_type' => $_iaPortfolioType,
            'is_crp_related' => $_isCrpRelated,
            'crp_id' => !empty($_crpId) ? Zend_Json::encode($_crpId) : null,
            'owner_applicant' => !empty($_ownerApplicant) ? Zend_Json::encode($_ownerApplicant) : null,
            'portfolio_title' => $_portfolioTitle,
            'short_title' => $_shortTitle,
            'filing_type_1' => $_filingType1,
            'filing_type_2' => $_filingType2,
            'application_number' => $_applicationNumber,
            'filing_date' => $_filingDate == '' ? null : $_filingDate,
            'registration_date' => $_registrationDate == '' ? null : $_registrationDate,
            'expiry_date' => $_expiryDate == '' ? null : $_expiryDate,
            'external_link' => $_externalLink,
            'crop_id' => !empty($_cropId) ? Zend_Json::encode($_cropId) : null,
            'trademark_type' => $_trademarkType,
            'claims_categories' => $_claimsCategories,
            'innovation_summary' => $_innovationSummary,
            'claims_summary' => $_claimsSummary,
        );

        try {
            if ($_iaReportPortfolioDocument->ia_portfolio_document_id != null) {
                if ($_iaReportPortfolioDocument->is_deleted == '1')
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['reporting_year']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportPortfolioDocument);

                $_iaReportPortfolioDocumentsMapper->update($_data, 'ia_portfolio_document_id = ' . $_iaReportPortfolioDocument->ia_portfolio_document_id);
                $this->SyncPortfolioRelatedAgreements($_data, $_iaReportPortfolioDocument->ia_portfolio_document_id, $_countries);
                $_action = 'updated';
            } else {
                $_data['added_by'] = $_logged_user->user_id;
                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportPortfolioDocument, true);
                $_iaReportPortfolioDocument->ia_portfolio_document_id = $_iaReportPortfolioDocumentsMapper->insert($_data);
                $_iaReportPortfolioDocument->reporting_year = $_data['reporting_year'];
                $_iaReportPortfolioDocument->parent_id = $_data['parent_id'];
                if ($_iaReport->ia_report_id != $_mainIaReport->ia_report_id)
                    $_action = 'updated';
                else
                    $_action = 'added';
            }

            $this->SubmitIaReportUpdate($_mainIaReport, $_iaReportingYear, $_iaReportPortfolioDocument->ia_portfolio_document_id, 'ia_portfolio_document_id', $_updateText, $_isRevise);

            $this->AddIaPortfolioDocumentCountries($_iaReportPortfolioDocument, $_countries, $_status);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'ia_portfolio_document_id' => $_iaReportPortfolioDocument->ia_portfolio_document_id,
                'reporting_year' => $_iaReportingYear,
                'ia_portfolio_type' => $_iaPortfolioType,
                'portfolio_title' => $_portfolioTitle,
                'message' => 'Center IA Portfolio Document ' . $_action . ' successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    private function AddIaPortfolioDocumentCountries($_iaReportPortfolioDocument, $_countries, $_status)
    {
        $_iaReportPortfolioDocumentsCountriesMapper = new Model_Mapper_IaReportPortfolioDocumentsCountries();

        $_countryIds = array_filter($_countries);
        $_countryIds[] = -15;
        $_iaReportPortfolioDocumentsCountriesMapper->delete('country_id NOT IN (' . implode(',', $_countryIds) . ') AND ia_portfolio_document_id = ' . $_iaReportPortfolioDocument->ia_portfolio_document_id);

        $_iaReportPortfolioDocumentsCountries = $_iaReportPortfolioDocumentsCountriesMapper->fetchMany(array('ia_portfolio_document_id' => $_iaReportPortfolioDocument->ia_portfolio_document_id));
        foreach ($_iaReportPortfolioDocumentsCountries as $_portfolioCountry) {
            $_countryIndex = array_search($_portfolioCountry->country_id, $_countries);
            if ($_countryIndex !== false) {
                unset($_countries[$_countryIndex]);
                if (isset($_status[$_countryIndex]) && $_status[$_countryIndex] != $_portfolioCountry->status) {
                    $_iaReportPortfolioDocumentsCountriesMapper->update(array(
                        'status' => $_status[$_countryIndex],
                        'reporting_year' => $_iaReportPortfolioDocument->reporting_year
                    ), 'id = ' . $_portfolioCountry->id);
                }
            }
        }

        foreach ($_countries as $_key => $_country) {
            try {
                $_countryStatus = isset($_status[$_key]) ? $_status[$_key] : 'Filed';
                $_reportingYear = $_iaReportPortfolioDocument->reporting_year;
                //If the parent has the same country and the same status, keep the reporting year of the parent
                if ($_iaReportPortfolioDocument->parent_id != null) {
                    try {
                        $_parent = $_iaReportPortfolioDocumentsCountriesMapper->fetchOne(array(
                            'ia_portfolio_document_id' => $_iaReportPortfolioDocument->parent_id,
                            'country_id' => $_country,
                            'status' => $_countryStatus
                        ));
                        $_reportingYear = $_parent->reporting_year;
                    } catch (Exception $e) {
                    }
                }
                $_iaReportPortfolioDocumentsCountriesMapper->insert(array(
                    'ia_portfolio_document_id' => $_iaReportPortfolioDocument->ia_portfolio_document_id,
                    'country_id' => $_country,
                    'status' => $_countryStatus,
                    'reporting_year' => $_reportingYear
                ));
            } catch (Exception $e) {
            }
        }
    }

    private function SyncPortfolioRelatedAgreements($_portfolioData, $_iaPortfolioDocumentId, $_countries)
    {
        if (!((int)$_iaPortfolioDocumentId) > 0 || ($_portfolioData['ia_portfolio_type'] != 'Patent' && $_portfolioData['ia_portfolio_type'] != 'PVP'))
            return;

        $_countries = array_values(array_filter(array_unique($_countries)));
        if (empty($_countries))
            $_countries = null;
        else
            try {
                $_countries = Zend_Json::encode($_countries);
            } catch (Exception $e) {
                $_countries = null;
            }
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_data = array(
            'agreement_title' => $_portfolioData['portfolio_title'],
            'country_id' => $_countries,
            'start_date' => $_portfolioData['filing_date'],
            'applicant_name' => $_portfolioData['owner_applicant']
        );
        if ($_portfolioData['ia_portfolio_type'] == 'Patent') {
            $_data['filing_type_1'] = $_portfolioData['filing_type_1'];
            $_data['filing_type_2'] = $_portfolioData['filing_type_2'];
        }
        $_iaReportAgreementsMapper->update($_data, 'agreement_related_portfolio = ' . $_iaPortfolioDocumentId);
    }

    public function getalliaportfoliodocumentsAction()
    {
        $_iaReportIds = $this->getRequest()->getParam('ia_report_ids', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_ipType = $this->getRequest()->getParam('ip_type', null);
        $_forDropDown = $this->getRequest()->getParam('for_drop_down', '0');
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();

        if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null) {
            $_iaReport = new Model_IaReport();
            $_where = array();
            $_canEditMainIAReport = false;
        } else {
            $_iaReport = $this->GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear);
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'data' => array()
                ));
            $_where = array('ia_report_id' => $_iaReport->PreviousIaReportsIds());
            $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        }

        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['ia_report_id'] = $this->GetCenterIaReportIds();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_ia_report', array(
                'tbl_ia_report.ia_report_id',
                '(IF (tbl_partner.abbreviation = "" OR tbl_partner.abbreviation IS NULL, tbl_partner.name, tbl_partner.abbreviation)) AS partner_name',
            ))
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '');
        $_centersReports = $db->fetchAll($_query);
        $_centersReports = array_combine(array_column($_centersReports, 'ia_report_id'), array_column($_centersReports, 'partner_name'));

        if (!is_array($_iaReportIds))
            $_iaReportIds = explode(',', $_iaReportIds);
        $_iaReportIds = array_filter($_iaReportIds);
        if (!empty($_iaReportIds))
            $_where['ia_report_id'] = $_iaReportIds;

        if (!empty($_ipType))
            $_where['ia_portfolio_type'] = $_ipType;

        $_iaReportPortfolioDocuments = $_iaReportPortfolioDocumentsMapper->fetchMany($_where, 'reporting_year');

        $_dataArray = array();
        $_iaReports = array();

        //For the suggestions in the portfolio form
        if ($_forDropDown == '2') {
            $_dataArray = array(
                'portfolio_title' => array(),
                'short_title' => array()
            );

            foreach ($_iaReportPortfolioDocuments as $_entity) {
                $_dataArray['portfolio_title'][] = trim($_entity->portfolio_title);
                $_dataArray['short_title'][] = trim($_entity->short_title);
            }
            $this->_helper->json->sendJson(array(
                'portfolio_title' => array_values(array_unique(array_filter($_dataArray['portfolio_title']))),
                'short_title' => array_values(array_unique(array_filter($_dataArray['short_title'])))
            ));
            exit;
        }

        foreach ($_iaReportPortfolioDocuments as $_entity) {
            if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null)
                $_iaReport = new Model_IaReport();

            $_data = $_entity->toArray();

            $_data['short_title'] = $_centersReports[$_entity->ia_report_id] . ' - ' . (trim($_data['short_title']) == '' ? $_data['portfolio_title'] : $_data['short_title']);

            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);

            //For the selection in section 3.1
            if ($_forDropDown == '1') {
                $_countries = $_entity->country;
                $_data['country'] = array();
                foreach ($_countries as $_country)
                    $_data['country'][] = $_country->name;
                $_data['country'] = implode(';', $_data['country']);
                $_dataArray[] = $_data;
                continue;
            }

            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            if (!array_key_exists($_entity->ia_report_id, $_iaReports))
                $_iaReports[$_entity->ia_report_id] = $_entity->ia_report;
            $_data['partner_id'] = $_iaReports[$_entity->ia_report_id]->partner_id;

            if ($_iaReport->ia_report_id == null)
                $_iaReport = $_itemIaReport;

            $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
            $_data['can_edit_ia_report'] = $_operations['edit'];
            $_data['can_update_ia_report'] = $_operations['update'];
            $_data['can_delete_ia_report'] = $_operations['delete'];

            $_owners = $_entity->owners;
            foreach ($_owners as $_owner)
                $_data['owners'][] = $_owner->abbreviation != '' ? $_owner->abbreviation : $_owner->full_name;

            $_countriesData = $_entity->countries_data;
            $_data['country'] = array();
            foreach ($_countriesData as $_countryData)
                $_data['country'][] = $_countryData->country->name . ' (' . $_countryData->status . ' - ' . $_countryData->reporting_year . ')';

            $_data['country'] = implode('; ', $_data['country']);
            $_data['active_status'] = $_entity->active_status;
            if ($_entity->ia_portfolio_type === 'Patent' || $_entity->ia_portfolio_type === 'PVP') {
                $_latestPublicDisclosure = $_entity->latest_public_disclosure;
                if ($_latestPublicDisclosure->ia_agreement_public_disclosure_id == null)
                    $_data['public_disclosure'] = 'NA';
                elseif ($_latestPublicDisclosure->public_disclosure_link != null)
                    $_data['public_disclosure'] = $_latestPublicDisclosure->public_disclosure_link;
                elseif ($_latestPublicDisclosure->public_disclosure_link != null)
                    $_data['public_disclosure'] = APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_latestPublicDisclosure->public_disclosure_document;
                else
                    $_data['public_disclosure'] = 'No public disclosure issued';
            }

            $_data['has_comments'] = count(App_Function_ReviewComments::HasReviewComments('ia_portfolio_document_form', $_entity->ia_portfolio_document_id, null, $_itemIaReport->partner_id)) > 0;
            $_data['is_modified'] = $_iaReport->IaReportingItemChanged($_entity);
            $_data['is_new'] = $_iaReport->IaReportingItemIsNew($_entity);
            $_data['reporting_years'][] = $_data['reporting_year'];

            $_data['reporting_years'] = $_entity->_getPreviousUpdatesYears();
            $_data['display_id'] = $_entity->ia_portfolio_document_id;
            if (isset($_dataArray[$_entity->parent_id])) {
                $_data['reporting_years'] = array_merge($_data['reporting_years'], $_dataArray[$_entity->parent_id]['reporting_years']);
                unset($_dataArray[$_entity->parent_id]);
                $_data['display_id'] = $_entity->parent_id;
            }
            $_data['reporting_years'][] = $_data['reporting_year'];
            $_data['reporting_years'] = array_values(array_unique(array_filter($_data['reporting_years'])));

            $_dataArray[$_entity->ia_portfolio_document_id] = $_data;
        }
        $this->_helper->json->sendJson(array(
            'data' => array_values($_dataArray)
        ));
    }

    public function getiaportfoliodocumentAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPortfolioDocumentId = $this->getRequest()->getParam('ia_portfolio_document_id', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        if ($_iaPortfolioDocumentId != null) {
            try {
                $_entity = $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_iaPortfolioDocumentId));

                $_iaReport = $_entity->ia_report;
                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = $_entity->toArray();

                $_iaReport = $this->GetIaReport($_iaReportId, null, null);
                $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
                $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, $_canEditMainIAReport, $_iaReport);
                $_data['can_edit_ia_report'] = $_operations['edit'] && $_iaReportReviewPage != '1';
                $_data['can_update_ia_report'] = $_operations['update'] && $_iaReportReviewPage != '1';

                $_data['countries'] = $_entity->countries_data->toArray();
                $_data['country_id'] = array_column($_data['countries'], 'country_id');
                $_data['history'] = $_iaReport->GetIaReportingDiff($_entity, $_iaReport->version, $_iaReport->reporting_year, -1, null);

                if ($_entity->ia_portfolio_type === 'Patent' || $_entity->ia_portfolio_type === 'PVP') {
                    $_latestPublicDisclosure = $_entity->latest_public_disclosure;
                    if ($_latestPublicDisclosure->ia_agreement_public_disclosure_id == null)
                        $_data['public_disclosure'] = 'NA';
                    elseif ($_latestPublicDisclosure->public_disclosure_link != null)
                        $_data['public_disclosure'] = $_latestPublicDisclosure->public_disclosure_link;
                    elseif ($_latestPublicDisclosure->public_disclosure_link != null)
                        $_data['public_disclosure'] = APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_latestPublicDisclosure->public_disclosure_document;
                    else
                        $_data['public_disclosure'] = 'No public disclosure issued';

                    $_latestAgreement = $_entity->latest_agreement;
                    $_data['agreement'] = $_latestAgreement->ia_agreement_id != null ? $_latestAgreement->unique_identifier : null;
                }

                $_data['updates'] = $this->PrepareEntityUpdates($_entity, $_iaReport, $_data['can_edit_ia_report'] || $_data['can_update_ia_report']);

                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'Center IA Portfolio Document not found.'
        ));
    }

    public function deleteiaportfoliodocumentAction()
    {
        $_iaPortfolioDocumentId = $this->getRequest()->getParam('ia_portfolio_document_id', null);
        $_isConfirm = $this->getRequest()->getParam('is_confirm', false);

        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();

        try {
            $_iaReportPortfolioDocument = $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_iaPortfolioDocumentId));

            $_iaReport = $this->GetIaReport($_iaReportPortfolioDocument->ia_report_id, null, null);
            if (!App_Function_Privileges::canEditIAReport($_iaReport, null, true))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if (!$_isConfirm) {
                try {
                    $_iaReportAgreementsMapper->fetchOne(array('agreement_related_portfolio' => $_iaPortfolioDocumentId));
                    $this->_helper->json->sendJson(array(
                        'result' => true,
                        'confirm' => true,
                        'message' => 'IP Application associated with this item will be deleted too, are you sure?'
                    ));
                } catch (Exception $e) {
                }
            }

            if ($_iaReport->is_draft == '1') {
                $_iaReportPortfolioDocumentsMapper->delete('ia_portfolio_document_id = ' . $_iaPortfolioDocumentId);
            } else {
                $_iaReportPortfolioDocumentsMapper->update(array('is_deleted' => '1'), 'ia_portfolio_document_id = ' . $_iaPortfolioDocumentId);

                $_iaReportAgreementsMapper->update(array('is_deleted' => '1'), 'agreement_related_portfolio = ' . $_iaPortfolioDocumentId);
                $_iaReportAgreements = $_iaReportAgreementsMapper->fetchMany(array('agreement_related_portfolio' => $_iaPortfolioDocumentId));
                $_iaReportAgreementsIds = array(-15);
                foreach ($_iaReportAgreements as $_iaReportAgreement)
                    $_iaReportAgreementsIds[] = $_iaReportAgreement->ia_agreement_id;
                $_iaReportAgreementPublicDisclosureMapper->update(array('is_deleted' => '1'), 'ia_agreement_id IN (' . implode(',', $_iaReportAgreementsIds) . ')');
            }

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Center IA Portfolio Document deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function submitiaagreementAction()
    {
        $_mainIaReportId = $this->getRequest()->getParam('main_ia_report_id', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_submitReview = $this->getRequest()->getParam('submit_review', 0);
        $_iaReportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaAgreementType = $this->getRequest()->getParam('ia_agreement_type', null);
        $_ipType = $this->getRequest()->getParam('ip_type', null);
        $_agreementTitle = $this->getRequest()->getParam('agreement_title', null);
        $_agreementRelatedPortfolio = $this->getRequest()->getParam('agreement_related_portfolio', null);
        $_filingType1 = $this->getRequest()->getParam('filing_type_1', null);
        $_filingType2 = $this->getRequest()->getParam('filing_type_2', null);
        $_countries = $this->getRequest()->getParam('country_id', array());
        $_partiesName = $this->getRequest()->getParam('parties_name', null);
        $_startDate = $this->getRequest()->getParam('start_date', null);
        $_endDate = $this->getRequest()->getParam('end_date', null);
        $_applicantName = $this->getRequest()->getParam('applicant_name', null);
        $_approximateCosts = $this->getRequest()->getParam('approximate_costs', null);
        $_projectCollaboration = $this->getRequest()->getParam('project_collaboration', null);
        $_arrangementsExclusivity = $this->getRequest()->getParam('arrangements_exclusivity', null);
        $_thirdPartyIa = $this->getRequest()->getParam('third_party_ia', null);
        $_protectedSubjectMatter = $this->getRequest()->getParam('protected_subject_matter', null);
        $_applicationRelatedProject = $this->getRequest()->getParam('application_related_project', null);
        $_intellectualValueExplain = $this->getRequest()->getParam('intellectual_value_explain', null);
        $_collaborationExclusivity = $this->getRequest()->getParam('collaboration_exclusivity', null);
        $_exclusivityExplain = $this->getRequest()->getParam('exclusivity_explain', null);
        $_researchExemption = $this->getRequest()->getParam('research_exemption', null);
        $_emergencyExemption = $this->getRequest()->getParam('emergency_exemption', null);
        $_equivalentIntellectualAvailability = $this->getRequest()->getParam('equivalent_intellectual_availability', null);
        $_intellectualImpact = $this->getRequest()->getParam('intellectual_impact', null);
        $_intellectualUseMeasures = $this->getRequest()->getParam('intellectual_use_measures', null);
        $_applicationNecessary = $this->getRequest()->getParam('application_necessary', null);
        $_disseminationStrategy = $this->getRequest()->getParam('dissemination_strategy', null);
        $_applicableSmtaRespect = $this->getRequest()->getParam('applicable_smta_respect', null);
        $_isAgreementRelated = $this->getRequest()->getParam('is_agreement_related', null);
        $_agreementRelatedPost = $this->getRequest()->getParam('agreement_related_post', null);
        $_applicationStatus = $this->getRequest()->getParam('application_status', null);
        $_restrictedAgreement = $this->getRequest()->getParam('restricted_agreement', null);
        $_restrictedAgreementNoExplain = $this->getRequest()->getParam('restricted_agreement_no_explain', null);
        $_collectionInformation = $this->getRequest()->getParam('collection_information', null);
        $_isMonetaryBenefit = $this->getRequest()->getParam('is_monetary_benefit', null);
        $_monetaryBenefitExplain = $this->getRequest()->getParam('monetary_benefit_explain', null);
        $_iaLimitationsClearance = $this->getRequest()->getParam('ia_limitations_clearance', null);
        $_germplasmIncorporated = $this->getRequest()->getParam('germplasm_incorporated', null);
        $_commercializingBenefit = $this->getRequest()->getParam('commercializing_benefit', null);
        $_noCommercializingBenefit = $this->getRequest()->getParam('no_commercializing_benefit', null);
        $_germplasmIncorporatedNoExplain = $this->getRequest()->getParam('germplasm_incorporated_no_explain', null);
        $_isBiologicalResourcesUtilized = $this->getRequest()->getParam('is_biological_resources_utilized', null);
        $_biologicalResourcesUtilizedBenefit = $this->getRequest()->getParam('biological_resources_utilized_benefit', null);
        $_absObligationsCompliance = $this->getRequest()->getParam('abs_obligations_compliance', null);
        $_noAbsObligationsApply = $this->getRequest()->getParam('no_abs_obligations_apply', null);
        $_isIaLimitations = $this->getRequest()->getParam('is_ia_limitations', null);
        $_licensingPlan = $this->getRequest()->getParam('licensing_plan', null);
        $_updateText = $this->getRequest()->getParam('update_text', null);

        $_nonConfidentialArray = array();
        foreach ($this->getRequest()->getParams() as $_name => $_value) {
            if (substr($_name, 0, 17) == 'non_confidential_')
                $_nonConfidentialArray[substr($_name, 17)] = $_value;
        }

        $_logged_user = App_Function_Privileges::getLoggedUser();

        $_iaReportMapper = new Model_Mapper_IaReport();
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreement = new Model_IaReportAgreements();

        if ($_iaAgreementId != null) {
            try {
                $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
                $_iaAgreementId = $_iaReportAgreement->ia_agreement_id;
                $_iaReportId = $_iaReportAgreement->ia_report_id;
            } catch (Exception $e) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Agreement/IP Application Report not found.'
                ));
            }
        }

        $_isRevise = false;
        try {
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_mainIaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_mainIaReportId));

            $_operations = $this->GetEntityOperations($_iaReportAgreement, App_Function_Privileges::canEditIAReport($_mainIaReport, null, true), App_Function_Privileges::canEditIAReportAgreement($_iaReportAgreement, null, $_iaReport, true), $_mainIaReport);

            if (!$_operations['edit'] && $_operations['update'] && $_iaAgreementId != null) {
                $this->SubmitIaReportUpdate($_mainIaReport, $_mainIaReport->reporting_year, $_iaAgreementId, 'ia_agreement_id', $_updateText, $_isRevise);
                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'message' => 'Agreement/IP Application Report updated successfully.'
                ));
            } elseif (!$_operations['edit']) {
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));
            }
            if ($_iaReport->is_draft == '0')
                $_isRevise = true;

        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'IA report not found.'
            ));
            exit;
        }

        try {
            //Force fields related to portfolio Patent or PVP
            $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
            $_iaReportPortfolioDocument = $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_agreementRelatedPortfolio));

            $_agreementTitle = $_iaReportPortfolioDocument->portfolio_title;
            if ($_iaReportPortfolioDocument->ia_portfolio_type == 'Patent') {
                $_filingType1 = $_iaReportPortfolioDocument->filing_type_1;
                $_filingType2 = $_iaReportPortfolioDocument->filing_type_2;
            }
            $_countries = $_iaReportPortfolioDocument->countries_ids;
            $_startDate = $_iaReportPortfolioDocument->filing_date;
            $_applicantName = Zend_Json::decode($_iaReportPortfolioDocument->owner_applicant);
        } catch (Exception $e) {
        }

        $_countries = array_values(array_filter(array_unique($_countries)));
        if (empty($_countries))
            $_countries = null;
        else
            try {
                $_countries = Zend_Json::encode($_countries);
            } catch (Exception $e) {
                $_countries = null;
            }
        $_data = array(
            'ia_report_id' => $_iaReportId,
            'ia_agreement_id' => $_iaAgreementId,
            'reporting_year' => $_iaReportingYear,
            'ia_agreement_type' => $_iaAgreementType,
            'ip_type' => !empty($_ipType) ? $_ipType : null,
            'agreement_title' => $_agreementTitle,
            'agreement_related_portfolio' => !empty($_agreementRelatedPortfolio) ? $_agreementRelatedPortfolio : null,
            'filing_type_1' => $_filingType1,
            'filing_type_2' => $_filingType2,
            'country_id' => $_countries,
            'parties_name' => !empty($_partiesName) ? Zend_Json::encode($_partiesName) : null,
            'start_date' => $_startDate == '' ? null : $_startDate,
            'end_date' => $_endDate == '' ? null : $_endDate,
            'applicant_name' => !empty($_applicantName) ? Zend_Json::encode($_applicantName) : null,
            'approximate_costs' => $_approximateCosts,
            'project_collaboration' => $_projectCollaboration,
            'arrangements_exclusivity' => $_arrangementsExclusivity,
            'third_party_ia' => $_thirdPartyIa,
            'protected_subject_matter' => $_protectedSubjectMatter,
            'application_related_project' => $_applicationRelatedProject,
            'intellectual_value_explain' => $_intellectualValueExplain,
            'collaboration_exclusivity' => $_collaborationExclusivity,
            'exclusivity_explain' => $_exclusivityExplain,
            'research_exemption' => $_researchExemption,
            'emergency_exemption' => $_emergencyExemption,
            'equivalent_intellectual_availability' => $_equivalentIntellectualAvailability,
            'intellectual_impact' => $_intellectualImpact,
            'intellectual_use_measures' => $_intellectualUseMeasures,
            'application_necessary' => $_applicationNecessary,
            'dissemination_strategy' => $_disseminationStrategy,
            'applicable_smta_respect' => $_applicableSmtaRespect,
            'is_agreement_related' => $_isAgreementRelated,
            'agreement_related_post' => $_agreementRelatedPost,
            'application_status' => $_applicationStatus,
            'restricted_agreement' => $_restrictedAgreement,
            'restricted_agreement_no_explain' => $_restrictedAgreementNoExplain,
            'collection_information' => $_collectionInformation,
            'is_monetary_benefit' => $_isMonetaryBenefit,
            'monetary_benefit_explain' => $_monetaryBenefitExplain,
            'is_ia_limitations' => $_isIaLimitations,
            'ia_limitations_clearance' => $_iaLimitationsClearance,
            'germplasm_incorporated' => $_germplasmIncorporated,
            'commercializing_benefit' => $_commercializingBenefit,
            'no_commercializing_benefit' => $_noCommercializingBenefit,
            'germplasm_incorporated_no_explain' => $_germplasmIncorporatedNoExplain,
            'is_biological_resources_utilized' => $_isBiologicalResourcesUtilized,
            'biological_resources_utilized_benefit' => $_biologicalResourcesUtilizedBenefit,
            'abs_obligations_compliance' => $_absObligationsCompliance,
            'no_abs_obligations_apply' => $_noAbsObligationsApply,
            'licensing_plan' => $_licensingPlan,
            'non_confidential' => !empty($_nonConfidentialArray) ? Zend_Json::encode($_nonConfidentialArray) : null
        );

        $_relatedAgreementFields = array(
            'project_collaboration',
            'arrangements_exclusivity',
            'third_party_ia',
            'protected_subject_matter',
            'application_related_project',
            'intellectual_value_explain',
            'collaboration_exclusivity',
            'exclusivity_explain',
            'research_exemption',
            'emergency_exemption',
            'equivalent_intellectual_availability',
            'intellectual_impact',
            'intellectual_use_measures',
            'application_status',
            'application_necessary',
            'dissemination_strategy',
            'applicable_smta_respect',
            'restricted_agreement',
            'restricted_agreement_no_explain',
            'collection_information',
            'is_monetary_benefit',
            'monetary_benefit_explain',
            'is_ia_limitations',
            'ia_limitations_clearance',
            'germplasm_incorporated',
            'commercializing_benefit',
            'no_commercializing_benefit',
            'germplasm_incorporated_no_explain',
            'is_biological_resources_utilized',
            'biological_resources_utilized_benefit',
            'abs_obligations_compliance',
            'no_abs_obligations_apply',
            'licensing_plan'
        );

        try {
            //Force fields related to another agreement
            $_relatedIaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_agreementRelatedPost));
            try {
                $_relatedIaReportAgreementNonConfidentialArray = Zend_Json::decode($_relatedIaReportAgreement->non_confidential);
            } catch (Exception $e) {
                $_relatedIaReportAgreementNonConfidentialArray = array();
            }
            if (!is_array($_relatedIaReportAgreementNonConfidentialArray))
                $_relatedIaReportAgreementNonConfidentialArray = array();

            foreach ($_relatedAgreementFields as $_relatedAgreementField) {
                $_data[$_relatedAgreementField] = $_relatedIaReportAgreement->$_relatedAgreementField;

                if (isset($_relatedIaReportAgreementNonConfidentialArray[$_relatedAgreementField]))
                    $_nonConfidentialArray[$_relatedAgreementField] = $_relatedIaReportAgreementNonConfidentialArray[$_relatedAgreementField];
                else
                    unset($_nonConfidentialArray[$_relatedAgreementField]);
            }
            $_data['non_confidential'] = !empty($_nonConfidentialArray) ? Zend_Json::encode($_nonConfidentialArray) : null;
        } catch (Exception $e) {
        }

        try {
            if ($_iaAgreementId != null) {
                if ($_iaAgreementType !== $_iaReportAgreement->ia_agreement_type) {
                    try {
                        $_iaReportAgreementsMapper->fetchOne(array('agreement_related_post' => $_iaAgreementId));
                        $this->_helper->json->sendJson(array(
                            'result' => false,
                            'message' => 'Cannot change the type as this item is associated with another LEA/ RUA/ IP Application.'
                        ));
                    } catch (Exception $e) {
                    }
                }

                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['reporting_year']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportAgreement);
                if ($_isRevise && $_submitReview == '1')
                    $_data['submitted_version'] = $_iaReportAgreement->version;

                $_iaReportAgreementsMapper->update($_data, 'ia_agreement_id = ' . $_iaAgreementId);

                $_action = 'updated';

                try {
                    //Update fields for agreements related to this agreements
                    $_relatedIaReportAgreements = $_iaReportAgreementsMapper->fetchMany(array('agreement_related_post' => $_iaAgreementId));
                    try {
                        $_data['non_confidential'] = Zend_Json::decode($_data['non_confidential']);
                    } catch (Exception $e) {
                        $_data['non_confidential'] = array();
                    }
                    if (!is_array($_data['non_confidential']))
                        $_data['non_confidential'] = array();

                    foreach ($_relatedIaReportAgreements as $_relatedIaReportAgreement) {
                        $_otherData = array();

                        try {
                            $_otherData['non_confidential'] = Zend_Json::decode($_relatedIaReportAgreement->non_confidential);
                        } catch (Exception $e) {
                            $_otherData['non_confidential'] = array();
                        }
                        if (!is_array($_otherData['non_confidential']))
                            $_otherData['non_confidential'] = array();

                        foreach ($_relatedAgreementFields as $_relatedAgreementField) {
                            $_otherData[$_relatedAgreementField] = $_data[$_relatedAgreementField];

                            if (isset($_data['non_confidential'][$_relatedAgreementField]))
                                $_otherData['non_confidential'][$_relatedAgreementField] = $_data['non_confidential'][$_relatedAgreementField];
                            else
                                unset($_otherData['non_confidential'][$_relatedAgreementField]);
                        }
                        $_otherData['non_confidential'] = !empty($_otherData['non_confidential']) ? Zend_Json::encode($_otherData['non_confidential']) : null;

                        $_iaReportAgreementsMapper->update($_otherData, 'ia_agreement_id = ' . $_relatedIaReportAgreement->ia_agreement_id);
                    }
                } catch (Exception $e) {
                }
            } else {
                $_data['version'] = 1;
                $_data['submitted_version'] = $_data['version'];
                $_data['added_by'] = $_logged_user->user_id;
                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportAgreement, true);
                $_iaAgreementId = $_iaReportAgreementsMapper->insert($_data);

                $_action = 'added';
            }

            if ($_isRevise) {
                $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
                try {
                    $_iaReportReview = $_iaReportReviewMapper->fetchOne(array(
                        'ia_agreement_id' => $_iaAgreementId,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12, //User group 11 Intellectual Property Focal Point (PMU)
                        'is_draft' => '1'
                    ));
                    $_iaReportReviewMapper->update(array(
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => $_submitReview == '1' ? 0 : 1,
                    ), 'review_id = ' . $_iaReportReview->review_id);
                } catch (Exception $e) {
                    $_iaReportReviewMapper->insert(array(
                        'ia_agreement_id' => $_iaAgreementId,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => $_submitReview == '1' ? 0 : 1,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ));
                }
            }

            //If the agreement has no public disclosure, create one
            try {
                $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
                $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId, 'is_deleted' => '0'));
            } catch (Exception $e) {
                $this->submitIaAgreementPublicDisclosure(array(
                    'main_ia_report_id' => $_mainIaReportId,
                    'ia_report_id' => $_iaReportId,
                    'ia_agreement_id' => $_iaAgreementId,
                    'submit_review' => '0',
                    'reporting_year' => $_iaReportingYear,
                    'no_public_disclosure' => 1
                ));
            }

            $this->SubmitIaReportUpdate($_mainIaReport, $_mainIaReport->reporting_year, $_iaAgreementId, 'ia_agreement_id', $_updateText, $_isRevise);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Agreement/IP Application Report ' . $_action . ' successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    private function GetAgreementsOrderBy()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_parentsQuery = $db->select()
            ->from('tbl_ia_report_agreements', array(
                'ia_agreement_id',
                'agreement_related_post',
                'ia_agreement_id AS order_column',
                'reporting_year',
            ))
            ->where('agreement_related_post IS NULL');
        $_childrenQuery = $db->select()
            ->from('tbl_ia_report_agreements', array(
                'ia_agreement_id',
                'agreement_related_post',
                'agreement_related_post AS order_column',
                'reporting_year',
            ))
            ->where('agreement_related_post IS NOT NULL');
        $_orderQuery = $db->select()
            ->from(array('order_query' => $db->select()->union(array($_parentsQuery, $_childrenQuery))), 'ia_agreement_id')
            ->order(array(
                'order_column',
                'agreement_related_post',
                'reporting_year'
            ));
        $_order = $db->fetchCol($_orderQuery);
        if (!empty($_order))
            $_order = 'FIELD(ia_agreement_id, ' . implode(',', $_order) . ')';
        else
            $_order = 'reporting_year';

        return $_order;
    }

    public function getalliaagreementsAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaAgreementIds = $this->getRequest()->getParam('ia_agreement_ids', null);
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);
        $_forDropDown = $this->getRequest()->getParam('for_drop_down', '0');
        $_publicDisclosure = $this->getRequest()->getParam('public_disclosure', '0');

        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();

        if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null) {
            $_iaReport = new Model_IaReport();
            $_where = array();
            $_canEditMainIAReport = false;
        } else {
            $_iaReport = $this->GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear);
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'data' => array()
                ));

            $_where = array('ia_report_id' => $_iaReport->PreviousIaReportsIds());
            $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        }
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['ia_report_id'] = $this->GetCenterIaReportIds();

        if (!is_array($_iaAgreementIds))
            $_iaAgreementIds = explode(',', $_iaAgreementIds);
        $_iaAgreementIds = array_filter($_iaAgreementIds);
        if (!empty($_iaAgreementIds))
            $_where['ia_agreement_id'] = $_iaAgreementIds;

        if ((int)$_iaAgreementId > 0)
            $_where['!ia_agreement_id'] = $_iaAgreementId;

        $_order = 'reporting_year';
        if ($_forDropDown != '1')
            $_order = $this->GetAgreementsOrderBy();

        $_iaReportAgreements = $_iaReportAgreementsMapper->fetchMany($_where, $_order);

        $_dataArray = array();
        $_iaReports = array();
        $_parentsArray = array();
        foreach ($_iaReportAgreements as $_entity) {
            if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null)
                $_iaReport = new Model_IaReport();

            $_data = $_entity->toArray();

            if ($_forDropDown == '1') {
                if ($_entity->agreement_related_post != null && $_publicDisclosure != '1')
                    continue;
                $_data['unique_identifier'] = $_entity->unique_identifier;
                $_countries = $_entity->country;
                $_data['country'] = array();
                foreach ($_countries as $_country)
                    $_data['country'][] = $_country->name;
                $_data['country'] = implode(';', $_data['country']);
                $_dataArray[] = $_data;
                continue;
            }

            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);
            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            if (!array_key_exists($_entity->ia_report_id, $_iaReports))
                $_iaReports[$_entity->ia_report_id] = $_entity->ia_report;
            $_data['partner_id'] = $_iaReports[$_entity->ia_report_id]->partner_id;

            $_data['unique_identifier'] = $_entity->unique_identifier;

            if ($_iaReport->ia_report_id == null)
                $_iaReport = $_itemIaReport;

            $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, App_Function_Privileges::canEditIAReportAgreement($_entity, null, $_itemIaReport, true), $_iaReport);
            $_data['can_edit_ia_report_agreement'] = $_operations['edit'];
            $_data['can_update_ia_report_agreement'] = $_operations['update'];
            $_data['can_delete_ia_report_agreement'] = $_operations['delete'];

            $_data['has_comments'] = count(App_Function_ReviewComments::HasReviewComments('ia_agreement_form', $_entity->ia_agreement_id, null, $_itemIaReport->partner_id)) > 0;
            $_data['is_modified'] = $_iaReport->IaReportingItemChanged($_entity);
            $_data['is_new'] = $_iaReport->IaReportingItemIsNew($_entity);

            $_data['review_status'] = array();
            if ($_iaReport->is_draft == '1') {
                $_data['review_status'][] = 'Not started';
            } else {
                $_iaReportReviews = $_iaReportReviewMapper->fetchMany(array('ia_agreement_id' => $_entity->ia_agreement_id, 'is_draft' => '0'));
                $_iaReportReview = new Model_IaReportReview();

                //User group 11: Intellectual Property Focal Point
                if ((isset($_iaReportReviews[0]) && $_iaReportReviews[0]->user_group_from != 11) || !isset($_iaReportReviews[0])) {
                    //Add the initial review, in case of newly added items the initial review should not be added
                    $_data['review_status'][] = $_iaReportReview->ReviewerDisplayName(12); //User group 12: Intellectual Property Focal Point (PMU)
                }
                foreach ($_iaReportReviews as $_iaReportReview) {
                    if ($_iaReportReview->user_group_to == null)
                        $_data['review_status'][] = 'Final review submitted';
                    else
                        $_data['review_status'][] = $_iaReportReview->ReviewerDisplayName($_iaReportReview->user_group_to);
                }
            }
            $_data['review_status'] = implode(', ', $_data['review_status']);
            if ($_data['review_status'] == '')
                $_data['review_status'] = 'Not started';

            $_data['review_grade'] = '';
            $_reviews = $_iaReport->GetIaReportAgreementReviews($_entity->ia_agreement_id, null, false);
            if ($_reviews['result'] === true && isset($_reviews['data']))
                foreach ($_reviews['data'] as $_review)
                    if ($_review['grade'] != null)
                        $_data['review_grade'] = $_review['grade'];

            if ($_entity->agreement_related_post != null)
                $_parentsArray[$_entity->agreement_related_post][] = $_entity->ia_agreement_id;
            $_data['reporting_years'] = $_entity->_getPreviousUpdatesYears();
            $_data['reporting_years'][] = $_data['reporting_year'];
            $_data['reporting_years'] = array_values(array_unique(array_filter($_data['reporting_years'])));

            $_dataArray[$_entity->ia_agreement_id] = $_data;
        }

        $_clustersColors = array(
            'rgba(192, 138, 92, 0.2)',
            'rgba(79, 143, 205, 0.2)',
            'rgba(221, 158, 55, 0.2)',
            'rgba(95, 115, 88, 0.2)',
            'rgba(190, 73, 53, 0.2)',
            'rgba(114, 53, 190, 0.2)',
            'rgba(180, 192, 92, 0.2)'
        );
        $_counter = 0;
        foreach ($_parentsArray as $_agreementId => $_childrenArray) {
            if (isset($_dataArray[$_agreementId])) {
                $_dataArray[$_agreementId]['cluster_color'] = $_clustersColors[$_counter];
                foreach ($_childrenArray as $_agreementId) {
                    if (isset($_dataArray[$_agreementId]))
                        $_dataArray[$_agreementId]['cluster_color'] = $_clustersColors[$_counter];
                }
            }

            $_counter++;
            if (!isset($_clustersColors[$_counter]))
                $_counter = 0;
        }

        $_dataArray = array_values($_dataArray);
        $this->_helper->json->sendJson(array(
            'data' => $_dataArray
        ));
    }

    public function getiaagreementAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_isRelated = $this->getRequest()->getParam('is_related', '0');
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        if ($_iaAgreementId != null) {
            try {
                $_entity = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));

                $_iaReport = $this->GetIaReport($_entity->ia_report_id, null, null);

                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = $_entity->toArray();
                $_relatedAgreementFields = array(
                    'project_collaboration',
                    'arrangements_exclusivity',
                    'third_party_ia',
                    'protected_subject_matter',
                    'application_related_project',
                    'intellectual_value_explain',
                    'collaboration_exclusivity',
                    'exclusivity_explain',
                    'research_exemption',
                    'emergency_exemption',
                    'equivalent_intellectual_availability',
                    'intellectual_impact',
                    'intellectual_use_measures',
                    'application_status',
                    'application_necessary',
                    'dissemination_strategy',
                    'applicable_smta_respect',
                    'restricted_agreement',
                    'restricted_agreement_no_explain',
                    'collection_information',
                    'is_monetary_benefit',
                    'monetary_benefit_explain',
                    'is_ia_limitations',
                    'ia_limitations_clearance',
                    'germplasm_incorporated',
                    'commercializing_benefit',
                    'no_commercializing_benefit',
                    'germplasm_incorporated_no_explain',
                    'is_biological_resources_utilized',
                    'biological_resources_utilized_benefit',
                    'abs_obligations_compliance',
                    'no_abs_obligations_apply',
                    'licensing_plan'
                );

                if ($_isRelated == '1') {
                    $_data = array();

                    try {
                        $_nonConfidentialArray = Zend_Json::decode($_entity->non_confidential);
                    } catch (Exception $e) {
                        $_nonConfidentialArray = array();
                    }
                    if (!is_array($_nonConfidentialArray))
                        $_nonConfidentialArray = array();

                    $_data['non_confidential'] = array();
                    foreach ($_relatedAgreementFields as $_relatedAgreementField) {
                        $_data[$_relatedAgreementField] = $_entity->$_relatedAgreementField;

                        if (isset($_nonConfidentialArray[$_relatedAgreementField]))
                            $_data['non_confidential'][$_relatedAgreementField] = $_nonConfidentialArray[$_relatedAgreementField];
                    }
                    $_data['non_confidential'] = !empty($_data['non_confidential']) ? Zend_Json::encode($_data['non_confidential']) : null;

                    $this->_helper->json->sendJson(array(
                        'result' => true,
                        'data' => $_data
                    ));
                } elseif ($_entity->agreement_related_post != null) {
                    foreach ($_relatedAgreementFields as $_relatedAgreementField)
                        unset($_data[$_relatedAgreementField]);
                }

                $_mainIaReport = $this->GetIaReport($_iaReportId, null, null);
                $_operations = $this->GetEntityOperations($_entity, App_Function_Privileges::canEditIAReport($_mainIaReport, null, true), App_Function_Privileges::canEditIAReportAgreement($_entity, null, $_iaReport, true), $_mainIaReport);
                $_data['can_edit_ia_report_agreement'] = $_operations['edit'] && $_iaReportReviewPage != '1';
                $_data['can_update_ia_report_agreement'] = $_operations['update'] && $_iaReportReviewPage != '1';

                $_data['history'] = $_iaReport->GetIaReportingDiff($_entity, $_entity->version, $_entity->reporting_year, -1, null);
                $_data['ia_report_is_draft'] = $_iaReport->is_draft == '1';

                try {
                    $_data['country_id'] = Zend_Json::decode($_data['country_id']);
                } catch (Exception $e) {
                    $_data['country_id'] = null;
                }
                $_data['updates'] = $this->PrepareEntityUpdates($_entity, $_mainIaReport, $_data['can_edit_ia_report_agreement'] || $_data['can_update_ia_report_agreement']);

                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'Agreement/IP Application Report not found.'
        ));
    }

    public function deleteiaagreementAction()
    {
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_isConfirm = $this->getRequest()->getParam('is_confirm', false);

        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();

        try {
            $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));

            $_iaReport = $this->GetIaReport($_iaReportAgreement->ia_report_id, null, null);
            if (!App_Function_Privileges::canEditIAReportAgreement($_iaReportAgreement, null, $_iaReport, true))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));
            $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
            $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();

            if (!$_isConfirm) {
                $_hasPublicDisclosures = false;
                try {
                    $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
                    $_hasPublicDisclosures = true;
                } catch (Exception $e) {
                }
                $_hasAgreements = false;
                try {
                    $_iaReportAgreementsMapper->fetchOne(array('agreement_related_post' => $_iaAgreementId));
                    $_hasAgreements = true;
                } catch (Exception $e) {
                }

                if ($_hasPublicDisclosures || $_hasAgreements) {
                    $_relatedItems = array();
                    if ($_hasAgreements)
                        $_relatedItems[] = 'LEA/ RUA/ IP Application';
                    if ($_hasPublicDisclosures)
                        $_relatedItems[] = 'Public disclosures';
                    $this->_helper->json->sendJson(array(
                        'result' => true,
                        'confirm' => true,
                        'message' => implode('/ ', $_relatedItems) . ' associated with this item will be deleted too, are you sure?'
                    ));
                }
            }

            if ($_iaReport->is_draft == '1') {
                $_iaReportAgreementsMapper->delete('ia_agreement_id = ' . $_iaAgreementId);
            } else {
                $_iaReportAgreementPublicDisclosureMapper->update(array('is_deleted' => '1'), 'ia_agreement_id = ' . $_iaAgreementId);
                $_iaReportAgreementsMapper->update(array('is_deleted' => '1'), 'agreement_related_post = ' . $_iaAgreementId);
                $_iaReportAgreementsMapper->update(array('is_deleted' => '1'), 'ia_agreement_id = ' . $_iaAgreementId);
            }

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Agreement/IP Application Report deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    public function submitiaagreementpublicdisclosureAction()
    {
        $_result = $this->submitIaAgreementPublicDisclosure($this->getRequest()->getParams());
        $this->_helper->json->sendJson($_result);
    }

    private function submitIaAgreementPublicDisclosure($_requestParams)
    {
        $_mainIaReportId = isset($_requestParams['main_ia_report_id']) ? $_requestParams['main_ia_report_id'] : null;
        $_iaReportId = isset($_requestParams['ia_report_id']) ? $_requestParams['ia_report_id'] : null;
        $_iaAgreementId = isset($_requestParams['ia_agreement_id']) ? $_requestParams['ia_agreement_id'] : null;
        $_iaAgreementPublicDisclosureId = isset($_requestParams['ia_agreement_public_disclosure_id']) ? $_requestParams['ia_agreement_public_disclosure_id'] : null;
        $_submitReview = isset($_requestParams['submit_review']) ? $_requestParams['submit_review'] : null;
        $_iaReportingYear = isset($_requestParams['reporting_year']) ? $_requestParams['reporting_year'] : date('Y') - 1;
        $_noPublicDisclosure = isset($_requestParams['no_public_disclosure']) ? $_requestParams['no_public_disclosure'] : null;
        $_noPublicDisclosureExplain = isset($_requestParams['no_public_disclosure_explain']) ? $_requestParams['no_public_disclosure_explain'] : null;
        $_publicDisclosureDocument = isset($_requestParams['public_disclosure_document']) ? $_requestParams['public_disclosure_document'] : null;
        $_publicDisclosureLink = isset($_requestParams['public_disclosure_link']) ? $_requestParams['public_disclosure_link'] : null;
        $_otherLinks = isset($_requestParams['other_links']) ? $_requestParams['other_links'] : array();
        $_otherLinks = array_values(array_filter($_otherLinks));
        $_isPublicDisclosureProvided = isset($_requestParams['is_public_disclosure_provided']) ? $_requestParams['is_public_disclosure_provided'] : null;
        $_publicDisclosureNotProvidedExplain = isset($_requestParams['public_disclosure_not_provided_explain']) ? $_requestParams['public_disclosure_not_provided_explain'] : null;
        $_anticipatedPublicDisclosure = isset($_requestParams['anticipated_public_disclosure']) ? $_requestParams['anticipated_public_disclosure'] : null;
        $_anticipatedPublicDisclosureDate = isset($_requestParams['anticipated_public_disclosure_date']) ? $_requestParams['anticipated_public_disclosure_date'] : null;
        $_updateText = isset($_requestParams['update_text']) ? $_requestParams['update_text'] : null;

        $_logged_user = App_Function_Privileges::getLoggedUser();

        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        $_iaReportAgreementPublicDisclosure = new Model_IaReportAgreementPublicDisclosure();
        if ($_iaAgreementPublicDisclosureId != null) {
            try {
                $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));
                $_iaReportId = $_iaReportAgreementPublicDisclosure->ia_report_id;
                $_iaAgreementPublicDisclosureId = $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id;
            } catch (Exception $e) {
                return array(
                    'result' => false,
                    'message' => 'Agreement/IP Application public disclosure not found.'
                );
            }
        }

        $_isRevise = false;
        try {
            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_mainIaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_mainIaReportId));

            $_operations = $this->GetEntityOperations($_iaReportAgreementPublicDisclosure, App_Function_Privileges::canEditIAReport($_mainIaReport, null, true), App_Function_Privileges::canEditIAReportAgreementPublicDisclosure($_iaReportAgreementPublicDisclosure, null, $_iaReport, true), $_mainIaReport);

            if (!$_operations['edit'] && $_operations['update'] && $_iaAgreementPublicDisclosureId != null) {
                $this->SubmitIaReportUpdate($_mainIaReport, $_mainIaReport->reporting_year, $_iaAgreementPublicDisclosureId, 'ia_agreement_public_disclosure_id', $_updateText, $_isRevise);
                return array(
                    'result' => true,
                    'message' => 'Agreement/IP Application public disclosure updated successfully.'
                );
            } elseif (!$_operations['edit']) {
                return array(
                    'result' => false,
                    'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );
            }
            if ($_iaReport->is_draft == '0')
                $_isRevise = true;
        } catch (Exception $e) {
            return array(
                'result' => false,
                'dd' => $e->getMessage(),
                'message' => 'IA report not found.'
            );
        }

        $_data = array(
            'ia_report_id' => $_iaReportId,
            'ia_agreement_id' => $_iaAgreementId,
            'reporting_year' => $_iaReportingYear,
            'no_public_disclosure' => $_noPublicDisclosure,
            'no_public_disclosure_explain' => $_noPublicDisclosureExplain,
            'public_disclosure_document' => $_publicDisclosureDocument,
            'public_disclosure_link' => $_publicDisclosureLink,
            'other_links' => empty($_otherLinks) ? null : Zend_Json::encode($_otherLinks),
            'is_public_disclosure_provided' => $_isPublicDisclosureProvided,
            'public_disclosure_not_provided_explain' => $_publicDisclosureNotProvidedExplain,
            'anticipated_public_disclosure_date' => $_anticipatedPublicDisclosureDate == '' ? null : $_anticipatedPublicDisclosureDate,
            'anticipated_public_disclosure' => $_anticipatedPublicDisclosure
        );

        $_relatedPublicDisclosureFields = array(
            'no_public_disclosure',
            'no_public_disclosure_explain',
            'public_disclosure_link',
            'public_disclosure_document',
            'is_public_disclosure_provided',
            'public_disclosure_not_provided_explain',
            'anticipated_public_disclosure',
            'anticipated_public_disclosure_date'
        );

        try {
            //Force fields related to another public disclosure's agreement
            $_relatedIaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId, '!agreement_related_post' => null));
            $_relatedIaPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array(
                'ia_agreement_id' => $_relatedIaReportAgreement->ia_agreement_id,
                '!ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId
            ));

            foreach ($_relatedPublicDisclosureFields as $_relatedPublicDisclosureField)
                $_data[$_relatedPublicDisclosureField] = $_relatedIaPublicDisclosure->$_relatedPublicDisclosureField;
        } catch (Exception $e) {
        }

        try {
            if ($_iaAgreementPublicDisclosureId != null) {
                $_data['updated_by'] = $_logged_user->user_id;
                unset($_data['reporting_year']);

                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportAgreementPublicDisclosure);

                if ($_isRevise && $_submitReview == '1')
                    $_data['submitted_version'] = $_iaReportAgreementPublicDisclosure->version;

                $_iaReportAgreementPublicDisclosureMapper->update($_data, 'ia_agreement_public_disclosure_id = ' . $_iaAgreementPublicDisclosureId);

                $_action = 'updated';

                try {
                    //Update fields for public disclosures related to this public disclosure's agreement
                    $_where = 'agreement_related_post = ' . $_iaAgreementId . ' OR ia_agreement_id = ' . $_iaAgreementId;
                    $_relatedIaReportAgreements = $_iaReportAgreementsMapper->fetchMany($_where);

                    foreach ($_relatedIaReportAgreements as $_relatedIaReportAgreement) {
                        $_relatedIaPublicDisclosures = $_relatedIaReportAgreement->public_disclosures;
                        foreach ($_relatedIaPublicDisclosures as $_relatedIaPublicDisclosure) {
                            if ($_relatedIaPublicDisclosure->ia_agreement_public_disclosure_id == $_iaAgreementPublicDisclosureId)
                                continue;

                            $_otherData = array();
                            foreach ($_relatedPublicDisclosureFields as $_relatedPublicDisclosureField)
                                $_otherData[$_relatedPublicDisclosureField] = $_data[$_relatedPublicDisclosureField];
                            $_iaReportAgreementPublicDisclosureMapper->update($_otherData, 'ia_agreement_public_disclosure_id = ' . $_relatedIaPublicDisclosure->ia_agreement_public_disclosure_id);
                        }
                    }
                } catch (Exception $e) {
                }
            } else {
                $_data['version'] = 1;
                $_data['submitted_version'] = $_data['version'];
                $_data['added_by'] = $_logged_user->user_id;
                if ($_isRevise)
                    $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportAgreementPublicDisclosure, true);
                $_iaAgreementPublicDisclosureId = $_iaReportAgreementPublicDisclosureMapper->insert($_data);

                $_action = 'added';
            }
            if ($_isRevise) {
                $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
                try {
                    $_iaReportReview = $_iaReportReviewMapper->fetchOne(array(
                        'ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12, //User group 11 Intellectual Property Focal Point (PMU)
                        'is_draft' => '1'
                    ));
                    $_iaReportReviewMapper->update(array(
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => $_submitReview == '1' ? 0 : 1,
                    ), 'review_id = ' . $_iaReportReview->review_id);
                } catch (Exception $e) {
                    $_iaReportReviewMapper->insert(array(
                        'ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId,
                        'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                        'is_draft' => $_submitReview == '1' ? 0 : 1,
                        'user_group_from' => 11, //User group 11 Intellectual Property Focal Point
                        'user_group_to' => 12 //User group 11 Intellectual Property Focal Point (PMU)
                    ));
                }
            }
            $this->SubmitIaReportUpdate($_mainIaReport, $_mainIaReport->reporting_year, $_iaAgreementPublicDisclosureId, 'ia_agreement_public_disclosure_id', $_updateText, $_isRevise);

            return array(
                'result' => true,
                'message' => 'Agreement/IP Application public disclosure ' . $_action . ' successfully.'
            );
        } catch (Exception $e) {
            return array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            );
        }
    }

    public function getalliaagreementpublicdisclosuresAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaPartnerId = $this->getRequest()->getParam('partner_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', date('Y') - 1);
        $_iaAgreementPublicDisclosureIds = $this->getRequest()->getParam('ia_agreement_public_disclosure_ids', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();

        if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null) {
            $_iaReport = new Model_IaReport();
            $_where = array();
            $_canEditMainIAReport = false;
        } else {
            $_iaReport = $this->GetIaReport($_iaReportId, $_iaPartnerId, $_reportingYear);
            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'data' => array()
                ));

            $_where = array('ia_report_id' => $_iaReport->PreviousIaReportsIds());
            $_canEditMainIAReport = App_Function_Privileges::canEditIAReport($_iaReport, null, true);
        }
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['ia_report_id'] = $this->GetCenterIaReportIds();

        if (!is_array($_iaAgreementPublicDisclosureIds))
            $_iaAgreementPublicDisclosureIds = explode(',', $_iaAgreementPublicDisclosureIds);
        $_iaAgreementPublicDisclosureIds = array_filter($_iaAgreementPublicDisclosureIds);
        if (!empty($_iaAgreementPublicDisclosureIds))
            $_where['ia_agreement_public_disclosure_id'] = $_iaAgreementPublicDisclosureIds;

        $_order = $this->GetAgreementsOrderBy();
        $_iaReportAgreementPublicDisclosures = $_iaReportAgreementPublicDisclosureMapper->fetchMany($_where, $_order);

        $_dataArray = array();
        $_iaReports = array();
        $_parentsArray = array();
        foreach ($_iaReportAgreementPublicDisclosures as $_entity) {
            if ($_iaReportReviewPage == '1' && $_iaReportId == null && $_iaPartnerId == null)
                $_iaReport = new Model_IaReport();

            $_data = $_entity->toArray();

            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);
            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            if (!array_key_exists($_entity->ia_report_id, $_iaReports))
                $_iaReports[$_entity->ia_report_id] = $_entity->ia_report;
            $_data['partner_id'] = $_iaReports[$_entity->ia_report_id]->partner_id;

            if ($_iaReport->ia_report_id == null)
                $_iaReport = $_itemIaReport;

            $_operations = $this->GetEntityOperations($_entity, $_canEditMainIAReport, App_Function_Privileges::canEditIAReportAgreementPublicDisclosure($_entity, null, $_itemIaReport, true), $_iaReport);
            $_data['can_edit_ia_report_public_disclosure'] = $_operations['edit'];
            $_data['can_update_ia_report_public_disclosure'] = $_operations['update'];
            $_data['can_delete_ia_report_public_disclosure'] = $_operations['delete'];

            $_iaReportAgreement = $_entity->agreement;
            $_countries = $_iaReportAgreement->country;
            $_iaReportAgreementCountries = array();
            foreach ($_countries as $_country)
                $_iaReportAgreementCountries[] = $_country->name;
            $_iaReportAgreementCountries = implode('; ', $_iaReportAgreementCountries);
            $_data['agreement_unique_identifier'] = '(' . $_iaReportAgreement->unique_identifier . ($_iaReportAgreementCountries != '' ? ' - ' . $_iaReportAgreementCountries : '') . ') ' . $_iaReportAgreement->agreement_title;

            $_data['links'] = array();
            if (!empty($_data['public_disclosure_link']))
                $_data['links'][] = array(
                    'link' => $_data['public_disclosure_link'],
                    'name' => $_data['public_disclosure_link']
                );
            if (!empty($_data['public_disclosure_document']))
                $_data['links'][] = array(
                    'link' => '/uploads/ia_reports/' . $_data['public_disclosure_document'],
                    'name' => $_data['public_disclosure_document']
                );

            $_data['has_comments'] = count(App_Function_ReviewComments::HasReviewComments('ia_agreement_public_disclosure_form', $_entity->ia_agreement_public_disclosure_id, null, $_itemIaReport->partner_id)) > 0;
            $_data['is_modified'] = $_iaReport->IaReportingItemChanged($_entity);
            $_data['is_new'] = $_iaReport->IaReportingItemIsNew($_entity);

            $_data['review_status'] = array();
            if ($_iaReport->is_draft == '1') {
                $_data['review_status'][] = 'Not started';
            } else {
                $_iaReportReviews = $_iaReportReviewMapper->fetchMany(array('ia_agreement_public_disclosure_id' => $_entity->ia_agreement_public_disclosure_id, 'is_draft' => '0'));
                $_iaReportReview = new Model_IaReportReview();

                //User group 11: Intellectual Property Focal Point
                if ((isset($_iaReportReviews[0]) && $_iaReportReviews[0]->user_group_from != 11) || !isset($_iaReportReviews[0])) {
                    //Add the initial review, in case of newly added items the initial review should not be added
                    $_data['review_status'][] = $_iaReportReview->ReviewerDisplayName(12); //User group 12: Intellectual Property Focal Point (PMU)
                }
                foreach ($_iaReportReviews as $_iaReportReview) {
                    if ($_iaReportReview->user_group_to == null)
                        $_data['review_status'][] = 'Final review submitted';
                    else
                        $_data['review_status'][] = $_iaReportReview->ReviewerDisplayName($_iaReportReview->user_group_to);
                }
            }
            $_data['review_status'] = implode(', ', $_data['review_status']);
            if ($_data['review_status'] == '')
                $_data['review_status'] = 'Not started';

            $_data['review_grade'] = '';
            $_reviews = $_iaReport->GetIaReportAgreementReviews(null, $_entity->ia_agreement_public_disclosure_id, false);
            if ($_reviews['result'] === true && isset($_reviews['data']))
                foreach ($_reviews['data'] as $_review)
                    if ($_review['grade'] != null)
                        $_data['review_grade'] = $_review['grade'];

            $_data['is_agreement_related'] = $_iaReportAgreement->is_agreement_related;

            if ($_iaReportAgreement->agreement_related_post != null)
                $_parentsArray[$_iaReportAgreement->agreement_related_post][] = $_iaReportAgreement->ia_agreement_id;
            else
                $_parentsArray[$_iaReportAgreement->ia_agreement_id][] = $_iaReportAgreement->ia_agreement_id;
            $_data['reporting_years'] = $_entity->_getPreviousUpdatesYears();
            $_data['reporting_years'][] = $_data['reporting_year'];
            $_data['reporting_years'] = array_values(array_unique(array_filter($_data['reporting_years'])));

            $_dataArray[] = $_data;
        }

        $_agreementIds = array_column($_dataArray, 'ia_agreement_id');

        $_clustersColors = array(
            'rgba(192, 138, 92, 0.2)',
            'rgba(79, 143, 205, 0.2)',
            'rgba(221, 158, 55, 0.2)',
            'rgba(95, 115, 88, 0.2)',
            'rgba(190, 73, 53, 0.2)',
            'rgba(114, 53, 190, 0.2)',
            'rgba(180, 192, 92, 0.2)'
        );
        $_counter = 0;
        foreach ($_parentsArray as $_agreementId => $_childrenArray) {
            if (count($_childrenArray) === 1)
                continue;
            $_applicableKeys = array_keys($_agreementIds, $_agreementId);
            foreach ($_applicableKeys as $_applicableKey)
                $_dataArray[$_applicableKey]['cluster_color'] = $_clustersColors[$_counter];

            foreach ($_childrenArray as $_agreementId) {
                $_applicableKeys = array_keys($_agreementIds, $_agreementId);
                foreach ($_applicableKeys as $_applicableKey)
                    $_dataArray[$_applicableKey]['cluster_color'] = $_clustersColors[$_counter];
            }

            $_counter++;
            if (!isset($_clustersColors[$_counter]))
                $_counter = 0;
        }

        $this->_helper->json->sendJson(array(
            'data' => $_dataArray
        ));
    }

    public function getiaagreementpublicdisclosureAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaAgreementPublicDisclosureId = $this->getRequest()->getParam('ia_agreement_public_disclosure_id', null);
        $_iaReportReviewPage = $this->getRequest()->getParam('ia_report_review_page', false);

        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        if ($_iaAgreementPublicDisclosureId != null) {
            try {
                $_entity = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));

                $_iaReport = $this->GetIaReport($_entity->ia_report_id, null, null);

                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = $_entity->toArray();

                $_relatedPublicDisclosureFields = array(
                    'no_public_disclosure',
                    'no_public_disclosure_explain',
                    'public_disclosure_link',
                    'public_disclosure_document',
                    'is_public_disclosure_provided',
                    'public_disclosure_not_provided_explain',
                    'anticipated_public_disclosure',
                    'anticipated_public_disclosure_date'
                );

                try {
                    $_iaReportAgreement = $_entity->agreement;
                    if ($_iaReportAgreement->agreement_related_post != null) {
                        $_relatedIaPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_id' => $_iaReportAgreement->agreement_related_post));

                        foreach ($_relatedPublicDisclosureFields as $_relatedPublicDisclosureField)
                            $_data[$_relatedPublicDisclosureField] = $_relatedIaPublicDisclosure->$_relatedPublicDisclosureField;
                    }
                } catch (Exception $e) {
                }

                $_mainIaReport = $this->GetIaReport($_iaReportId, null, null);
                $_operations = $this->GetEntityOperations($_entity, App_Function_Privileges::canEditIAReport($_mainIaReport, null, true), App_Function_Privileges::canEditIAReportAgreementPublicDisclosure($_entity, null, $_iaReport, true), $_mainIaReport);
                $_data['can_edit_ia_report_public_disclosure'] = $_operations['edit'] && $_iaReportReviewPage != '1';
                $_data['can_update_ia_report_public_disclosure'] = $_operations['update'] && $_iaReportReviewPage != '1';

                $_data['history'] = $_iaReport->GetIaReportingDiff($_entity, $_entity->version, $_entity->reporting_year, -1, null);
                $_data['ia_report_is_draft'] = $_iaReport->is_draft == '1';

                $_data['updates'] = $this->PrepareEntityUpdates($_entity, $_mainIaReport, $_data['can_edit_ia_report_public_disclosure'] || $_data['can_update_ia_report_public_disclosure']);

                $this->_helper->json->sendJson(array(
                    'result' => true,
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'Agreement/IP Application public disclosure not found.'
        ));
    }

    public function getiaagreementrelatedpublicdisclosureAction()
    {
        $_iaAgreementPublicDisclosureId = $this->getRequest()->getParam('ia_agreement_public_disclosure_id', null);
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);

        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        if ($_iaAgreementId != null) {
            try {
                $_entity = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));

                $_iaReport = $this->GetIaReport($_entity->ia_report_id, null, null);

                if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                    $this->_helper->json->sendJson(array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    ));

                $_data = array();

                $_relatedPublicDisclosureFields = array(
                    'no_public_disclosure',
                    'no_public_disclosure_explain',
                    'public_disclosure_link',
                    'public_disclosure_document',
                    'is_public_disclosure_provided',
                    'public_disclosure_not_provided_explain',
                    'anticipated_public_disclosure',
                    'anticipated_public_disclosure_date'
                );

                try {
                    $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
                    if ($_iaReportAgreement->agreement_related_post != null) {
                        $_relatedIaPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array(
                            'ia_agreement_id' => $_iaReportAgreement->agreement_related_post,
                            '!ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId
                        ));

                        foreach ($_relatedPublicDisclosureFields as $_relatedPublicDisclosureField)
                            $_data[$_relatedPublicDisclosureField] = $_relatedIaPublicDisclosure->$_relatedPublicDisclosureField;
                    }
                } catch (Exception $e) {
                }

                $this->_helper->json->sendJson(array(
                    'result' => !empty($_data),
                    'data' => $_data
                ));
            } catch (Exception $e) {
            }
        }

        $this->_helper->json->sendJson(array(
            'result' => false,
            'message' => 'Agreement/IP Application public disclosure not found.'
        ));
    }

    public function deleteiaagreementpublicdisclosureAction()
    {
        $_iaAgreementPublicDisclosureId = $this->getRequest()->getParam('ia_agreement_public_disclosure_id', null);

        $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();

        try {
            $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));

            $_iaReport = $this->GetIaReport($_iaReportAgreementPublicDisclosure->ia_report_id, null, null);
            if (!App_Function_Privileges::canEditIAReportAgreementPublicDisclosure($_iaReportAgreementPublicDisclosure, null, $_iaReport, true))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'Cannot delete, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            if ($_iaReport->is_draft == '1')
                $_iaReportAgreementPublicDisclosureMapper->delete('ia_agreement_public_disclosure_id = ' . $_iaAgreementPublicDisclosureId);
            else
                $_iaReportAgreementPublicDisclosureMapper->update(array('is_deleted' => '1'), 'ia_agreement_public_disclosure_id = ' . $_iaAgreementPublicDisclosureId);

            $this->_helper->json->sendJson(array(
                'result' => true,
                'message' => 'Agreement/IP Application public disclosure deleted successfully.'
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage()
            ));
        }
    }

    private function SubmitIaReportUpdate($_iaReport, $_iaReportingYear, $_itemId, $_columnName, $_updateText, $_isRevise)
    {
        $_logged_user = App_Function_Privileges::getLoggedUser();
        $_iaReportUpdatesMapper = new Model_Mapper_IaReportUpdates();

        try {
            $_iaReportUpdate = $_iaReportUpdatesMapper->fetchOne(array(
                $_columnName => $_itemId,
                'ia_report_id' => $_iaReport->ia_report_id,
                'reporting_year' => $_iaReportingYear
            ));

            if (trim($_updateText) == '' && !$_isRevise)
                $_iaReportUpdatesMapper->delete('ia_report_update_id = ' . $_iaReportUpdate->ia_report_update_id);
        } catch (Exception $e) {
            if (trim($_updateText) == '')
                return;
            try {
                $_iaReportUpdate = new Model_IaReportUpdates();
                $_iaReportUpdate->$_columnName = $_itemId;
                $_iaReportUpdate->ia_report_id = $_iaReport->ia_report_id;
                $_iaReportUpdate->reporting_year = $_iaReportingYear;
            } catch (Exception $e) {
                return;
            }
        }

        $_data = array(
            $_columnName => $_iaReportUpdate->$_columnName,
            'ia_report_id' => $_iaReportUpdate->ia_report_id,
            'reporting_year' => $_iaReportUpdate->reporting_year,
            'update_text' => $_updateText
        );

        if ($_iaReportUpdate->ia_report_update_id != null) {
            $_data['updated_by'] = $_logged_user->user_id;

            if ($_isRevise)
                $_data['previous_version'] = $_iaReport->HandleIaReportingVersioning($_iaReportUpdate);

            $_iaReportUpdatesMapper->update($_data, 'ia_report_update_id = ' . $_iaReportUpdate->ia_report_update_id);
        } else {
            $_data['added_by'] = $_logged_user->user_id;
            $_iaReportUpdatesMapper->insert($_data);
        }
    }

    /**
     * @param $_parentEntity App_Model_ModelAbstract | Model_IaReportCrp | Model_IaReportManagementDocuments | Model_IaReportPortfolioDocuments | Model_IaReportAgreements | Model_IaReportAgreementPublicDisclosure
     * @param $_iaReport App_Model_ModelAbstract | Model_IaReport
     * @param $_reportingYear string
     * @return array
     */
    private function PrepareEntityUpdates($_parentEntity, $_iaReport, $_canEdit)
    {
        $_updatesArray = array();
        $_updates = $_parentEntity->_getPreviousUpdates();

        $_reportingYearHasUpdate = false;
        foreach ($_updates as $_entity) {
            $_itemIaReport = $this->GetIaReport($_entity->ia_report_id, null, null);

            //Don't show items under draft report in the main dashboard
            if ($_itemIaReport->is_draft == '1' && $_iaReport->ia_report_id == null)
                continue;

            $_updateArray = array(
                'reporting_year' => $_entity->reporting_year,
                'added_by_name' => $_entity->added_by_name,
                'added_date' => date('Y-m-d H:i:s', strtotime($_entity->added_date)),
                'updated_by_name' => $_entity->updated_by_name,
                'updated_date' => $_entity->updated_date ? date('Y-m-d H:i:s', strtotime($_entity->updated_date)) : null,
                'update_text' => $_entity->update_text,
                'history' => $_itemIaReport->GetIaReportingDiff($_entity, $_itemIaReport->version, $_itemIaReport->reporting_year, -1, null),
            );

            if ($_iaReport->reporting_year == $_entity->reporting_year) {
                $_reportingYearHasUpdate = true;
                $_updateArray['can_update_ia_report'] = $_canEdit;
            } else {
                $_updateArray['can_update_ia_report'] = false;
            }

            $_updatesArray[] = $_updateArray;
        }

        if (!$_reportingYearHasUpdate && $_canEdit) {
            $_updatesArray[] = array(
                'reporting_year' => $_iaReport->reporting_year,
                'added_by_name' => null,
                'added_date' => null,
                'updated_by_name' => null,
                'updated_date' => null,
                'update_text' => null,
                'history' => array(),
                'can_update_ia_report' => true
            );
        }

        return $_updatesArray;
    }

    public function importiareportsfromexcelAction()
    {
        ini_set('memory_limit', -1);
        require_once LIBRARY_PATH . '/PHPExcel/PHPExcel.php';
        $inputFileName = UPLOAD_PATH . '/exports/Portfolio of Center IA related policies and IPRs-MEL_template_sj_MS.xlsx';

        if (!App_Function_Privileges::isAdmin())
            return $this->forward('denied', 'user');

        try {
            $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFileName);

            $this->insertIaReports($objPHPExcel);
            $this->insertPolicies($objPHPExcel);
            $this->insertPatent($objPHPExcel);
            $this->insertPVP($objPHPExcel);
            $this->insertTM($objPHPExcel);

            App_Function_ExportHelper::SaveExcelFile($objPHPExcel, 'Portfolio of Center IA related policies and IPRs-MEL_template_sj_MS', false, false);
        } catch (Exception $e) {
            die($e->getMessage());
        }
        die('Done');
    }

    private function GetPartner($_abbreviation)
    {
        if (is_array($_abbreviation))
            $_abbreviation = implode(',', $_abbreviation);
        $_abbreviation = trim(strtolower($_abbreviation));
        $_abbreviation = explode(',', $_abbreviation);
        $_abbreviation = array_filter($_abbreviation);
        if (empty($_abbreviation))
            return array();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_partner', 'partner_id')
            ->where('trim(lower(abbreviation)) IN (?)', $_abbreviation);
        return $db->fetchCol($_query);
    }

    private function GetCountry($_country)
    {
        if (is_array($_country))
            $_country = implode(',', $_country);
        $_country = trim(strtolower($_country));
        $_country = explode(',', $_country);
        $_country = array_filter($_country);
        if (empty($_country))
            return array();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_wipo_countries', 'country_id')
            ->where('trim(lower(name)) IN (?)', $_country);
        return $db->fetchCol($_query);
    }

    private function GetCrop($_crop)
    {
        if (is_array($_crop))
            $_crop = implode(',', $_crop);
        $_crop = trim(strtolower($_crop));
        $_crop = explode(',', $_crop);
        $_crop = array_filter($_crop);
        if (empty($_crop))
            return array();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $db->select()
            ->from('tbl_crop', 'crop_id')
            ->where('trim(lower(crop_name)) IN (?)', $_crop);
        return $db->fetchCol($_query);
    }

    /**
     * @param $objPHPExcel PHPExcel
     */
    private function insertIaReports($objPHPExcel)
    {
        $_sheets = array(
            range('A', 'B'),
            range('A', 'B'),
            range('A', 'B'),
            range('A', 'B'),
        );

        $_iaReportMapper = new Model_Mapper_IaReport();
        $_dataArray = array();
        foreach ($_sheets as $_index => $_sheetColumns) {
            try {
                $sheet = $objPHPExcel->getSheet($_index);

                $highestRow = $sheet->getHighestRow();
                for ($row = 2; $row <= $highestRow; $row++) {
                    $_reportingYear = $this->GetExcelColumnValue($sheet, 'A', $row, false, false);
                    $_abbreviation = $this->GetExcelColumnValue($sheet, 'B', $row, false, false);
                    if (!empty($_reportingYear)) {
                        if (!array_key_exists($_reportingYear . '-' . $_abbreviation, $_dataArray)) {
                            $_partner = $this->GetPartner($_abbreviation);
                            if (!isset($_partner[0]))
                                die('sheet: ' . $_index . ' row: ' . $row);

                            try {
                                $_iaReportId = $_iaReportMapper->insert(array(
                                    'is_draft' => 1,
                                    'added_by' => 1950,
                                    'reporting_year' => $_reportingYear,
                                    'partner_id' => $_partner[0]
                                ));
                                $_dataArray[$_reportingYear . '-' . $_abbreviation] = $_iaReportId;
                                echo '<br> IA inserted ID: ' . $_iaReportId;
                            } catch (Exception $e) {
                            }
                        } else {
                            $_iaReportId = $_dataArray[$_reportingYear . '-' . $_abbreviation];
                        }
                        if (isset($_iaReportId))
                            $sheet->setCellValueByColumnAndRow(2, $row, $_iaReportId);
                    } else {
                        break;
                    }
                }
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
    }

    /**
     * @param $objPHPExcel PHPExcel
     */
    private function insertPolicies($objPHPExcel)
    {
        $_iaReportManagementDocumentsMapper = new Model_Mapper_IaReportManagementDocuments();
        try {
            $sheet = $objPHPExcel->getSheet(0);

            $highestRow = $sheet->getHighestRow();
            for ($row = 2; $row <= $highestRow; $row++) {
                $_reportingYear = $this->GetExcelColumnValue($sheet, 'A', $row, false, false);
                if (!empty($_reportingYear)) {
                    $_data = array(
                        'ia_report_id' => $this->GetExcelColumnValue($sheet, 'C', $row, false, false),
                        'added_by' => 1950,
                        'reporting_year' => $_reportingYear,
                        'other_document_type' => $this->GetExcelColumnValue($sheet, 'F', $row, false, false),
                        'policy_title' => $this->GetExcelColumnValue($sheet, 'H', $row, false, true),
                        'category' => $this->GetExcelColumnValue($sheet, 'G', $row, false, false),
                        'manage_status' => $this->GetExcelColumnValue($sheet, 'I', $row, false, false),
                        'approval_date' => $this->GetExcelColumnValue($sheet, 'J', $row, true, false),
                        'effective_date' => $this->GetExcelColumnValue($sheet, 'K', $row, true, false),
                        'is_crp_related' => 0,
                        'crp_id' => null,
                        'availability_status' => $this->GetExcelColumnValue($sheet, 'N', $row, false, false),
                        'availability_status_document' => null,
                        'is_publicly_available' => $this->GetExcelColumnValue($sheet, 'M', $row, false, false) != null,
                        'public_url' => $this->GetExcelColumnValue($sheet, 'M', $row, false, true),
                        'comments' => $this->GetExcelColumnValue($sheet, 'O', $row, false, true)
                    );
                    try {
                        $_id = $this->GetExcelColumnValue($sheet, 'D', $row, false, false, false);
                        $_iaReportManagementDocumentsMapper->fetchOne(array('ia_management_document_id' => $_id));
                        $_updated = $_iaReportManagementDocumentsMapper->update($_data, 'ia_management_document_id = ' . $_id);
                        if ($_updated)
                            echo '<br> Policy updated ID: ' . $_id;
                    } catch (Exception $e) {
                        $_id = $_iaReportManagementDocumentsMapper->insert($_data);
                        echo '<br> Policy inserted ID: ' . $_id;
                    }
                    $sheet->setCellValueByColumnAndRow(3, $row, $_id);
                } else {
                    break;
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param $objPHPExcel PHPExcel
     */
    private function insertPatent($objPHPExcel)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        try {
            $sheet = $objPHPExcel->getSheet(1);

            $highestRow = $sheet->getHighestRow();
            for ($row = 2; $row <= $highestRow; $row++) {
                $_reportingYear = $this->GetExcelColumnValue($sheet, 'A', $row, false, false);

                if (!empty($_reportingYear)) {
                    $_crps = $this->GetPartner($this->GetExcelColumnValue($sheet, 'F', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(4, $row, implode(',', $_crps));
                    $_country = $this->GetCountry($this->GetExcelColumnValue($sheet, 'N', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(12, $row, implode(',', $_country));
                    $_owners = $this->GetPartner($this->GetExcelColumnValue($sheet, 'H', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(6, $row, implode(',', $_owners));
                    $_data = array(
                        'ia_report_id' => $this->GetExcelColumnValue($sheet, 'C', $row, false, false),
                        'added_by' => 1950,
                        'reporting_year' => $_reportingYear,
                        'portfolio_title' => $this->GetExcelColumnValue($sheet, 'I', $row, false, true),
                        'short_title' => $this->GetExcelColumnValue($sheet, 'J', $row, false, true),
                        'ia_portfolio_type' => 'Patent',
                        'trademark_type' => null,
                        'is_crp_related' => !empty($_crps),
                        'crp_id' => !empty($_crps) ? Zend_Json::encode($_crps) : null,
                        'owner_applicant' => !empty($_owners) ? Zend_Json::encode($_owners) : null,
                        'filing_type_1' => $this->GetExcelColumnValue($sheet, 'K', $row, false, false),
                        'filing_type_2' => $this->GetExcelColumnValue($sheet, 'L', $row, false, false),
                        'country_id' => !empty($_country) ? $_country[0] : null,
                        'crop_id' => null,
                        'status' => $this->GetExcelColumnValue($sheet, 'O', $row, false, false),
                        'application_number' => $this->GetExcelColumnValue($sheet, 'P', $row, false, true),
                        'filing_date' => $this->GetExcelColumnValue($sheet, 'Q', $row, true, false),
                        'registration_date' => $this->GetExcelColumnValue($sheet, 'R', $row, true, false),
                        'expiry_date' => $this->GetExcelColumnValue($sheet, 'S', $row, true, false),
                        'external_link' => $this->GetExcelColumnValue($sheet, 'T', $row, false, true),
                        'comments' => $this->GetExcelColumnValue($sheet, 'U', $row, false, true)
                    );
                    try {
                        $_id = $this->GetExcelColumnValue($sheet, 'D', $row, false, false, false);
                        $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_id));
                        $_updated = $_iaReportPortfolioDocumentsMapper->update($_data, 'ia_portfolio_document_id = ' . $_id);
                        if ($_updated)
                            echo '<br> Patent updated ID: ' . $_id;
                    } catch (Exception $e) {
                        $_id = $_iaReportPortfolioDocumentsMapper->insert($_data);
                        echo '<br> Patent inserted ID: ' . $_id;
                    }
                    $sheet->setCellValueByColumnAndRow(3, $row, $_id);
                } else {
                    break;
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param $objPHPExcel PHPExcel
     */
    private function insertPVP($objPHPExcel)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        try {
            $sheet = $objPHPExcel->getSheet(2);

            $highestRow = $sheet->getHighestRow();
            for ($row = 2; $row <= $highestRow; $row++) {
                $_reportingYear = $this->GetExcelColumnValue($sheet, 'A', $row, false, false);

                if (!empty($_reportingYear)) {
                    $_country = $this->GetCountry($this->GetExcelColumnValue($sheet, 'K', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(9, $row, implode(',', $_country));
                    $_owners = $this->GetPartner($this->GetExcelColumnValue($sheet, 'F', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(4, $row, implode(',', $_owners));
                    $_crops = $this->GetCrop($this->GetExcelColumnValue($sheet, 'Q', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(15, $row, implode(',', $_crops));

                    $_data = array(
                        'ia_report_id' => $this->GetExcelColumnValue($sheet, 'C', $row, false, false),
                        'added_by' => 1950,
                        'reporting_year' => $_reportingYear,
                        'portfolio_title' => $this->GetExcelColumnValue($sheet, 'G', $row, false, true),
                        'short_title' => $this->GetExcelColumnValue($sheet, 'H', $row, false, false),
                        'ia_portfolio_type' => 'PVP',
                        'trademark_type' => null,
                        'is_crp_related' => 0,
                        'crp_id' => null,
                        'owner_applicant' => !empty($_owners) ? Zend_Json::encode($_owners) : null,
                        'filing_type_1' => null,
                        'filing_type_2' => null,
                        'country_id' => !empty($_country) ? $_country[0] : null,
                        'crop_id' => !empty($_crops) ? Zend_Json::encode($_crops) : null,
                        'status' => $this->GetExcelColumnValue($sheet, 'I', $row, false, false),
                        'application_number' => $this->GetExcelColumnValue($sheet, 'L', $row, false, false),
                        'filing_date' => $this->GetExcelColumnValue($sheet, 'M', $row, true, false),
                        'registration_date' => $this->GetExcelColumnValue($sheet, 'N', $row, true, false),
                        'expiry_date' => $this->GetExcelColumnValue($sheet, 'O', $row, true, false),
                        'external_link' => null,
                        'comments' => $this->GetExcelColumnValue($sheet, 'R', $row, false, true)
                    );
                    try {
                        $_id = $this->GetExcelColumnValue($sheet, 'D', $row, false, false, false);
                        $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_id));
                        $_updated = $_iaReportPortfolioDocumentsMapper->update($_data, 'ia_portfolio_document_id = ' . $_id);
                        if ($_updated)
                            echo '<br> PVP updated ID: ' . $_id;
                    } catch (Exception $e) {
                        $_id = $_iaReportPortfolioDocumentsMapper->insert($_data);
                        echo '<br> PVP inserted ID: ' . $_id;
                    }
                    $sheet->setCellValueByColumnAndRow(3, $row, $_id);
                } else {
                    break;
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param $objPHPExcel PHPExcel
     */
    private function insertTM($objPHPExcel)
    {
        $_iaReportPortfolioDocumentsMapper = new Model_Mapper_IaReportPortfolioDocuments();
        try {
            $sheet = $objPHPExcel->getSheet(3);

            $highestRow = $sheet->getHighestRow();
            for ($row = 2; $row <= $highestRow; $row++) {
                $_reportingYear = $this->GetExcelColumnValue($sheet, 'A', $row, false, false);

                if (!empty($_reportingYear)) {
                    $_country = $this->GetCountry($this->GetExcelColumnValue($sheet, 'R', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(16, $row, implode(',', $_country));
                    $_owners = $this->GetPartner($this->GetExcelColumnValue($sheet, 'G', $row, false, false));
                    $sheet->setCellValueByColumnAndRow(5, $row, implode(',', $_owners));

                    $_data = array(
                        'ia_report_id' => $this->GetExcelColumnValue($sheet, 'C', $row, false, false),
                        'added_by' => 1950,
                        'reporting_year' => $_reportingYear,
                        'portfolio_title' => $this->GetExcelColumnValue($sheet, 'J', $row, false, true),
                        'short_title' => null,
                        'ia_portfolio_type' => 'Trademark',
                        'trademark_type' => $this->GetExcelColumnValue($sheet, 'I', $row, false, false),
                        'is_crp_related' => 0,
                        'crp_id' => null,
                        'owner_applicant' => !empty($_owners) ? Zend_Json::encode($_owners) : null,
                        'filing_type_1' => $this->GetExcelColumnValue($sheet, 'P', $row, false, false),
                        'filing_type_2' => null,
                        'country_id' => !empty($_country) ? $_country[0] : null,
                        'crop_id' => null,
                        'status' => $this->GetExcelColumnValue($sheet, 'K', $row, false, false),
                        'application_number' => $this->GetExcelColumnValue($sheet, 'L', $row, false, true),
                        'filing_date' => $this->GetExcelColumnValue($sheet, 'M', $row, true, false),
                        'registration_date' => $this->GetExcelColumnValue($sheet, 'N', $row, true, false),
                        'expiry_date' => $this->GetExcelColumnValue($sheet, 'O', $row, true, false),
                        'external_link' => null,
                        'comments' => $this->GetExcelColumnValue($sheet, 'S', $row, false, true)
                    );
                    try {
                        $_id = $this->GetExcelColumnValue($sheet, 'D', $row, false, false, false);
                        $_iaReportPortfolioDocumentsMapper->fetchOne(array('ia_portfolio_document_id' => $_id));
                        $_updated = $_iaReportPortfolioDocumentsMapper->update($_data, 'ia_portfolio_document_id = ' . $_id);
                        if ($_updated)
                            echo '<br> TM updated ID: ' . $_id;
                    } catch (Exception $e) {
                        $_id = $_iaReportPortfolioDocumentsMapper->insert($_data);
                        echo '<br> TM inserted ID: ' . $_id;
                    }
                    $sheet->setCellValueByColumnAndRow(3, $row, $_id);
                } else {
                    break;
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param $sheet PHPExcel_Worksheet
     * @param $_coordinates
     * @param $_isDate
     * @param $_acceptAllValues
     */
    private function GetExcelColumnValue($sheet, $_column, $_row, $_isDate, $_acceptAllValues, $_highlight = true)
    {
        $_ignoreValues = array(
            'NA',
            'N/A',
            'TBC',
            '-',
            ''
        );

        try {
            $_value = $sheet->getCell($_column . $_row)->getValue();
            if (!$_acceptAllValues && in_array(strtoupper($_value), $_ignoreValues)) {
                if ($_highlight)
                    $sheet->getStyle($_column . $_row)->applyFromArray(
                        array(
                            'fill' => array(
                                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('rgb' => 'edd3d2')
                            )
                        )
                    );
                return null;
            }
            if ($_isDate)
                return date('Y-m-d', PHPExcel_Shared_Date::ExcelToPHP($_value));
            return $_value;
        } catch (Exception $e) {
            return null;
        }
    }

    public function iareportpdfAction()
    {
        $this->_helper->layout()->disableLayout();
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_reportingYear = $this->getRequest()->getParam('reporting_year', null);
        $_embeddedComments = $this->getRequest()->getParam('embedded_comments', '1');
        $_section3Unique = $this->getRequest()->getParam('unique', '0');
        $_showCenterFeedback = $this->getRequest()->getParam('show_center_feedback', '0');
        $_section3Review = $this->getRequest()->getParam('section_3_review', '0');

        $_sections = $this->getRequest()->getParam('sections', array());
        if (!is_array($_sections))
            $_sections = explode(',', $_sections);

        $_reviews = $this->getRequest()->getParam('reviews', array());
        if (!is_array($_reviews))
            $_reviews = explode(',', $_reviews);

        $_iaReportMapper = new Model_Mapper_IaReport();

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report', 'ia_report_id')
            ->join('tbl_partner', 'tbl_partner.partner_id = tbl_ia_report.partner_id', '')
            ->order('(IF(tbl_partner.abbreviation IS NOT NULL AND tbl_partner.abbreviation != "", tbl_partner.abbreviation, tbl_partner.name))');

        if ($_reportingYear != null) {
            $_consolidatedExport = true;
            $_query->where('tbl_ia_report.reporting_year = (?)', $_reportingYear);
        } else {
            $_consolidatedExport = false;
            $_query->where('tbl_ia_report.ia_report_id = (?)', $_iaReportId);
        }
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_query->where('tbl_ia_report.partner_id = (?)', App_Function_Privileges::getLoggedUser()->organization_id);
        $_iaReportIds = $_db->fetchCol($_query);

        if (count($_iaReportIds) === 0)
            throw new Zend_Acl_Exception('Entity Not Found', 404);

        $_iaReports = $_iaReportMapper->fetchMany(array('ia_report_id' => $_iaReportIds), 'FIELD(ia_report_id, ' . implode(',', $_iaReportIds) . ')');

        ini_set('pcre.backtrack_limit', '5000000');
        ini_set('memory_limit', -1);

        require_once LIBRARY_PATH . '/vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf(array(
            'pagenumPrefix' => 'Page ',
            'pagenumSuffix' => ' - ',
            'nbpgPrefix' => ' out of ',
            'nbpgSuffix' => ' pages',
        ));
        $mpdf->SetFooter('{PAGENO}{nbpg}');

        $mpdf->WriteHTML($this->view->render('ia/pdf_export/head_content.phtml'), \Mpdf\HTMLParserMode::HEADER_CSS);

        $this->view->reporting_year = $_reportingYear;
        $this->view->sections = $_sections;
        $this->view->section_3_unique = $_section3Unique == '1';
        $this->view->reviews = $_reviews;
        $this->view->show_center_feedback = $_showCenterFeedback == '1';
        $this->view->section_3_review = $_section3Review == '1';

        $htmlcontent = '';
        foreach ($_iaReports as $_key => $_iaReport) {
            $_reportingYear = $_iaReport->reporting_year;
            $this->view->organization = $_iaReport->organization;
            $this->view->ia_report = $_iaReport;

            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                continue;

            if ($_embeddedComments)
                $this->view->comments = $this->GetAllReportItemReviewComments($_iaReport->ia_report_id);
            else
                $this->view->comments = array();

            $this->view->is_first_report = $_key === 0;
            $this->view->is_last_report = $_key === (count($_iaReports) - 1);
            $htmlcontent .= $this->view->render('ia/pdf_export/iareportpdf.phtml');
        }
        $mpdf->WriteHTML($htmlcontent, \Mpdf\HTMLParserMode::HTML_BODY);

        if ($_consolidatedExport)
            $_file = 'Center_IA_Report_' . $_reportingYear . '_' . date('Y-m-d H:i:s') . '.pdf';
        else
            $_file = $this->view->organization->abbreviation_or_name . '_IA_Report_' . $_reportingYear . '_' . date('Y-m-d H:i:s') . '.pdf';
        $_file = str_replace(' ', '_', $_file);
        $mpdf->Output(UPLOAD_PATH . '/exports/' . $_file, 'F');

        header("Content-type:application/pdf");
        header("Content-Disposition:attachment;filename=$_file");

        readfile(UPLOAD_PATH . '/exports/' . $_file);
        unlink(UPLOAD_PATH . '/exports/' . $_file);
        die;
    }

    private function GetAllReportItemReviewComments($_iaReportId)
    {
        $_formsArray = array(
            'ia_form' => array(),
            'ia_report_crp_form' => array(),
            'ia_management_document_form' => array(),
            'ia_portfolio_document_form' => array(),
            'ia_agreement_form' => array(),
            'ia_agreement_public_disclosure_form' => array(),
            'ia_report_updates_form' => array()
        );

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($_formsArray as $_formId => $_data) {
            $_table = App_Function_ReviewComments::GetFormRelatedTable($_formId, null);
            $_formInfo = App_Function_ReviewComments::GetFormRelatedTable(null, $_table);
            $_mainTable = $_formInfo['main_table'];
            $_primaryColumn = $_formInfo['primary_column'];

            $_query = $_db->select()
                ->from($_mainTable, $_mainTable . '.' . $_primaryColumn)
                ->where($_mainTable . '.ia_report_id = (?)', $_iaReportId);

            $_ids = $_db->fetchCol($_query);
            foreach ($_ids as $_id) {
                $_comments = App_Function_ReviewComments::GetAllReviewComments($_id, null, $_formId);
                foreach ($_comments as $_comment)
                    $_formsArray[$_formId][$_id][$_comment['field_name']][] = $_comment;
            }
        }
        return $_formsArray;
    }

    public function iareportagreementsexcelAction()
    {
        $progressId = $this->getRequest()->getParam('progressId', null);
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_iaReportMapper = new Model_Mapper_IaReport();

        try {
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            $_objPHPExcel = App_Function_ExportHelper::CreateExcelFile('Center_IA_Report_LEA_RUA_IP_Public_disclosures', 'Center_IA_Report_LEA_RUA_IP_Public_disclosures');

            $_organization = $_iaReport->organization;
            $_fileName = $_organization->abbreviation_or_name . '_IA_Report_LEA_RUA_IP_Application_Public_disclosures_' . $_iaReport->reporting_year . '_' . date('Y-m-d H:i:s');
            $_fileName = str_replace(' ', '_', $_fileName);

            $_sheets = array(
                'LEA' => array(
                    'ia_agreement_id' => 'ID',
                    'ia_agreement_type' => 'Type of restricted arrangement',
                    'organization_name' => 'Reporting Center',
                    'first_reporting_year' => '1st year reported',
                    'last_reporting_year' => 'Latest year reported',
                    'unique_identifier' => 'Unique Identifier',
                    'agreement_title' => '3.1.3 Title of agreement',
                    'parties_name_string' => '3.1.8 Name of parties',
                    'start_date' => '3.1.9 Agreement effective date',
                    'end_date' => '3.1.10 Agreement end date',
                    'is_agreement_related_string' => '3.1.13 Is the remaining information identical to another reported LEA, RUA or IP Application?',
                    'agreement_related_post_string' => '3.1.14 Reported LEA, RUA or IP Application',
                    'project_collaboration' => '3.1.15 Description of project/collaboration to which the agreement relates',
                    'arrangements_exclusivity' => '3.1.16 General description of the arrangements conferring exclusivity for commercialization',
                    'collaboration_exclusivity' => '3.1.21 Explain, in the specific context of the project/collaboration, why the exclusivity is necessary for the further improvement of the Intellectual Assets produced, in furtherance of the CGIAR Vision” and/or to “enhance the scale or scope of impact on target beneficiaries, in furtherance of the CGIAR Vision”',
                    'exclusivity_explain' => '3.1.22 Explain how the exclusivity is “as limited as possible” (in duration, territory and/or field of use)',
                    'research_exemption' => '3.1.23 Confirm that the agreement contains a Research Exemption and indicate how the Intellectual Assets remain available, free-of-charge (except for actual costs or reasonable processing fees) or at a reasonable cost, in all countries for non-commercial research conducted by public sector organizations in furtherance of the CGIAR Vision. Alternatively, if the agreement does not contain a Research Exemption, please include the request for deviation and the approval by the System Organization of such deviation under Article 6.2.2., or provide particulars concerning the third-party restrictions permitted under Article 6.3',
                    'emergency_exemption' => '3.1.24 Confirm that the agreement contains an Emergency Exemption and explain how the Intellectual Assets remain available, free of charge (except for actual costs or reasonable processing fees) or at a reasonable cost, in all countries, in the event of a national or regional Food Security Emergency for the duration of the emergency. Alternatively, if the agreement does not contain an Emergency Exemption, please include the request for deviation and the approval by the Consortium of such deviation under Article 6.2.2., or provide particulars concerning the third-party restrictions permitted under Article 6.2.3',
                    //Pre-2020
                    'restricted_agreement_string' => '3.1.32 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from in-trust germplasm or plant genetic resources obtained under SMTA from the Multilateral System of the ITPGFA?',
                    'restricted_agreement_no_explain' => '3.1.33 If no, please explain rationale',
                    'collection_information' => '3.1.34 Please indicate the accession name(s)/number(s), and date and place of collection, if known',
                    'is_monetary_benefit_string' => '3.1.35 Will (or could) the monetary benefit sharing obligations pursuant to the SMTA (be) triggered upon commercialization of the IA?',
                    'monetary_benefit_explain' => '3.1.36 please explain how this is addressed or will be addressed in the restricted arrangement and/or downstream arrangements',
                    'is_ia_limitations_string' => '3.1.37 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from biological or genetic resources obtained outside of the Multilateral System of the ITPGFA?',
                    'ia_limitations_clearance' => '3.1.38 Please include a statement affirming that the Intellectual Asset is not subject to limitations arising from ABS (Access and Benefit Sharing) agreements or laws that are inconsistent with the restricted arrangement',
                    //Post-2020
                    'germplasm_incorporated_string' => '3.1.32 Does the intellectual asset incorporate any level of germplasm obtained from in trust collections or under the SMTA used for transferring plant genetic resources under the International Treaty on Plant Genetic Resources for Food and Agriculture?',
                    'commercializing_benefit' => '3.1.33 Please explain if the licensee or a downstream entity commercializing the intellectual assets (or derivatives) will be bound by the benefit sharing requirements under the SMTA.',
                    'no_commercializing_benefit' => '3.1.34 In case the licensee or a downstream entity commercializing the intellectual asset (or derivatives) will not be bound by the benefit-sharing requirements under the SMTA, have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'germplasm_incorporated_no_explain' => '3.1.35 Have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'is_biological_resources_utilized_string' => '3.1.36 Has the intellectual asset been developed utilizing biological resources (including genetic resources) that were not obtained from an in trust collection or under the SMTA?',
                    'biological_resources_utilized_benefit_string' => '3.1.37 Is your utilization of the biological resources subject to access and benefit-sharing (ABS)obligations?',
                    'abs_obligations_compliance' => '3.1.38 Please describe the measures you have adopted to ensure compliance with applicable ABS obligations',
                    'no_abs_obligations_apply' => '3.1.39 Please describe the measures you followed to ascertain that no ABS obligations apply',

                    'licensing_plan' => '3.1.40 Unless addressed above, please explain the strategy/plan and anticipated timeline for development and dissemination/licensing of the Intellectual Asset that is the subject of the restricted arrangement(s)',
                    'updates' => 'Updates/comments'
                ),
                'RUA' => array(
                    'ia_agreement_id' => 'ID',
                    'ia_agreement_type' => 'Type of restricted arrangement',
                    'organization_name' => 'Reporting Center',
                    'first_reporting_year' => '1st year reported',
                    'last_reporting_year' => 'Latest year reported',
                    'unique_identifier' => 'Unique Identifier',
                    'agreement_title' => '3.1.3 Title of agreement',
                    'parties_name_string' => '3.1.8 Name of parties',
                    'start_date' => '3.1.9 Agreement effective date',
                    'end_date' => '3.1.10 Agreement end date',
                    'is_agreement_related_string' => '3.1.13 Is the remaining information identical to another reported LEA, RUA or IP Application?',
                    'agreement_related_post_string' => '3.1.14 Reported LEA, RUA or IP Application',
                    'project_collaboration' => '3.1.15 Description of project/collaboration to which the agreement relates',
                    'third_party_ia' => '3.1.17 Description of third-party IA that are acquired and how they are to be used under the agreement, including the downstream restrictions to the global accessibility of the products/services resulting from their use',
                    'equivalent_intellectual_availability' => '3.1.25 Describe why, to the best of the Center’s knowledge, no equivalent Intellectual Assets were available from other sources under no or less restrictive conditions',
                    'intellectual_impact' => '3.1.26 Explain how “the products/ services that are intended to result from the use of such third-party Intellectual Assets will further the CGIAR Vision in the countries where they can be made available”',
                    'intellectual_use_measures' => '3.1.27 Describe any measures taken to ensure that the third-party Intellectual Assets are only used in relation to, or incorporated into, such intended products/services',
                    //Pre-2020
                    'restricted_agreement_string' => '3.1.32 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from in-trust germplasm or plant genetic resources obtained under SMTA from the Multilateral System of the ITPGFA?',
                    'restricted_agreement_no_explain' => '3.1.33 If no, please explain rationale',
                    'collection_information' => '3.1.34 Please indicate the accession name(s)/number(s), and date and place of collection, if known',
                    'is_monetary_benefit_string' => '3.1.35 Will (or could) the monetary benefit sharing obligations pursuant to the SMTA (be) triggered upon commercialization of the IA?',
                    'monetary_benefit_explain' => '3.1.36 please explain how this is addressed or will be addressed in the restricted arrangement and/or downstream arrangements',
                    'is_ia_limitations_string' => '3.1.37 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from biological or genetic resources obtained outside of the Multilateral System of the ITPGFA?',
                    'ia_limitations_clearance' => '3.1.38 Please include a statement affirming that the Intellectual Asset is not subject to limitations arising from ABS (Access and Benefit Sharing) agreements or laws that are inconsistent with the restricted arrangement',
                    //Post-2020
                    'germplasm_incorporated_string' => '3.1.32 Does the intellectual asset incorporate any level of germplasm obtained from in trust collections or under the SMTA used for transferring plant genetic resources under the International Treaty on Plant Genetic Resources for Food and Agriculture?',
                    'commercializing_benefit' => '3.1.33 Please explain if the licensee or a downstream entity commercializing the intellectual assets (or derivatives) will be bound by the benefit sharing requirements under the SMTA.',
                    'no_commercializing_benefit' => '3.1.34 In case the licensee or a downstream entity commercializing the intellectual asset (or derivatives) will not be bound by the benefit-sharing requirements under the SMTA, have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'germplasm_incorporated_no_explain' => '3.1.35 Have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'is_biological_resources_utilized_string' => '3.1.36 Has the intellectual asset been developed utilizing biological resources (including genetic resources) that were not obtained from an in trust collection or under the SMTA?',
                    'biological_resources_utilized_benefit_string' => '3.1.37 Is your utilization of the biological resources subject to access and benefit-sharing (ABS)obligations?',
                    'abs_obligations_compliance' => '3.1.38 Please describe the measures you have adopted to ensure compliance with applicable ABS obligations',
                    'no_abs_obligations_apply' => '3.1.39 Please describe the measures you followed to ascertain that no ABS obligations apply',

                    'licensing_plan' => '3.1.40 Unless addressed above, please explain the strategy/plan and anticipated timeline for development and dissemination/licensing of the Intellectual Asset that is the subject of the restricted arrangement(s)',
                    'updates' => 'Updates/comments'
                ),
                'IP Application' => array(
                    'ia_agreement_id' => 'ID',
                    'ia_agreement_type' => 'Type of restricted arrangement',
                    'organization_name' => 'Reporting Center',
                    'first_reporting_year' => '1st year reported',
                    'last_reporting_year' => 'Latest year reported',
                    'unique_identifier' => 'Unique Identifier',
                    'ip_type' => '3.1.2 IP type',
                    'agreement_title' => '3.1.3 Title of application for protection',
                    'agreement_related_portfolio_string' => '3.1.4 Relevant application that is being reported in this reporting cycle (as per the unique identifier in section 1.5.a or 1.5.b)',
                    'filing_type_1' => '3.1.5 Type of Filing - 1',
                    'filing_type_2' => '3.1.6 Type of Filing - 2',
                    'country' => '3.1.7 Country',
                    'start_date' => '3.1.9 Application date',
                    'applicant_name_string' => '3.1.11 Name of applicant and inventors/breeders',
                    'approximate_costs' => '3.1.12 The approximate costs involved',
                    'is_agreement_related_string' => '3.1.13 Is the remaining information identical to another reported LEA, RUA or IP Application?',
                    'agreement_related_post_string' => '3.1.14 Reported LEA, RUA or IP Application',
                    'protected_subject_matter' => '3.1.18 Description of protected subject matter',
                    'application_related_project' => '3.1.19 Description of project to which the application relates',
                    'intellectual_value_explain' => '3.1.20 Why is the intellectual asset useful? How does it contribute to advance the Center’s and CGIAR mission?',
                    'application_status' => '3.1.28 Please describe the current status of the application and progress of prosecution',
                    'application_necessary' => '3.1.29 Explain how the IP Application was “necessary for the further improvement of the Intellectual Assets or to enhance the scale or scope of impact on target beneficiaries, in furtherance of the CGIAR Vision”',
                    'dissemination_strategy' => '3.1.30 Describe the dissemination strategy including how the Center will promote accessibility and use of the innovation by third-parties (including smallholder farmers and public research organizations)',
                    'applicable_smta_respect' => '3.1.31 Describe the Center’s plan to respect applicable SMTA related obligations including monetary benefit-sharing obligations, if the asset is derived from PGRFA received from the MLS',
                    //Pre-2020
                    'restricted_agreement_string' => '3.1.32 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from in-trust germplasm or plant genetic resources obtained under SMTA from the Multilateral System of the ITPGFA?',
                    'restricted_agreement_no_explain' => '3.1.33 If no, please explain rationale',
                    'collection_information' => '3.1.34 Please indicate the accession name(s)/number(s), and date and place of collection, if known',
                    'is_monetary_benefit_string' => '3.1.35 Will (or could) the monetary benefit sharing obligations pursuant to the SMTA (be) triggered upon commercialization of the IA?',
                    'monetary_benefit_explain' => '3.1.36 please explain how this is addressed or will be addressed in the restricted arrangement and/or downstream arrangements',
                    'is_ia_limitations_string' => '3.1.37 Is the Intellectual Asset that is the subject of the restricted arrangement(s) derived from biological or genetic resources obtained outside of the Multilateral System of the ITPGFA?',
                    'ia_limitations_clearance' => '3.1.38 Please include a statement affirming that the Intellectual Asset is not subject to limitations arising from ABS (Access and Benefit Sharing) agreements or laws that are inconsistent with the restricted arrangement',
                    //Post-2020
                    'germplasm_incorporated_string' => '3.1.32 Does the intellectual asset incorporate any level of germplasm obtained from in trust collections or under the SMTA used for transferring plant genetic resources under the International Treaty on Plant Genetic Resources for Food and Agriculture?',
                    'commercializing_benefit' => '3.1.33 Please explain if the licensee or a downstream entity commercializing the intellectual assets (or derivatives) will be bound by the benefit sharing requirements under the SMTA.',
                    'no_commercializing_benefit' => '3.1.34 In case the licensee or a downstream entity commercializing the intellectual asset (or derivatives) will not be bound by the benefit-sharing requirements under the SMTA, have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'germplasm_incorporated_no_explain' => '3.1.35 Have you considered some form of voluntary monetary benefit-sharing (either by your Center or your licensees)?',
                    'is_biological_resources_utilized_string' => '3.1.36 Has the intellectual asset been developed utilizing biological resources (including genetic resources) that were not obtained from an in trust collection or under the SMTA?',
                    'biological_resources_utilized_benefit_string' => '3.1.37 Is your utilization of the biological resources subject to access and benefit-sharing (ABS)obligations?',
                    'abs_obligations_compliance' => '3.1.38 Please describe the measures you have adopted to ensure compliance with applicable ABS obligations',
                    'no_abs_obligations_apply' => '3.1.39 Please describe the measures you followed to ascertain that no ABS obligations apply',

                    'licensing_plan' => '3.1.40 Unless addressed above, please explain the strategy/plan and anticipated timeline for development and dissemination/licensing of the Intellectual Asset that is the subject of the restricted arrangement(s)',
                    'updates' => 'Updates/comments'
                )
            );

            $_agreementPre2020Fields = array(
                'restricted_agreement_string',
                'restricted_agreement_no_explain',
                'collection_information',
                'is_monetary_benefit_string',
                'monetary_benefit_explain',
                'is_ia_limitations_string',
                'ia_limitations_clearance'
            );
            $_agreementPost2020Fields = array(
                'germplasm_incorporated_string',
                'commercializing_benefit',
                'no_commercializing_benefit',
                'germplasm_incorporated_no_explain',
                'is_biological_resources_utilized_string',
                'biological_resources_utilized_benefit_string',
                'abs_obligations_compliance',
                'no_abs_obligations_apply'
            );

            if (isset($progressId)) {
                @session_start();
                $_SESSION [$progressId] = 5;
                session_write_close();
            }

            if (App_Function_Privileges::canViewIAReport($_iaReport, null)) {
                $achieved = 0;
                $_rowsCount = 0;

                $_dataArray = array(
                    'LEA' => $_iaReport->GetAgreementsForExport('LEA'),
                    'RUA' => $_iaReport->GetAgreementsForExport('RUA'),
                    'IP Application' => $_iaReport->GetAgreementsForExport('IP Application')
                );

                foreach ($_dataArray as $_data)
                    $_rowsCount += count($_data);

                $_rowsCount = $_rowsCount == 0 ? 1 : $_rowsCount;
                $_step = 90 / $_rowsCount;
                $_stepItemsCount = ceil(1 / $_step);

                $_agreementEntity = new Model_IaReportAgreements();
                $_publicDisclosureEntity = new Model_IaReportAgreementPublicDisclosure();

                foreach ($_sheets as $_sheet => $_columnsArray) {
                    $_activeSheet = App_Function_ExportHelper::CreateVerticalExcelSheet($_objPHPExcel, $_sheet, array(), false, array());
                    $_activeSheet->getColumnDimensionByColumn(0)->setWidth(29.33);
                    $_activeSheet->getColumnDimensionByColumn(1)->setWidth(60.5);

                    $_columnNum = 1;
                    $_rowNum = 0;
                    foreach ($_dataArray[$_sheet] as $_row) {
                        $_rowNum++;

                        $_agreementEntity->reporting_year = $_row['reporting_year'];
                        $_agreementEntity->ia_agreement_id = $_row['ia_agreement_id'];
                        $_updatedRecordInfo = $_iaReport->GetUpdatedRecordInfo($_agreementEntity, $_iaReport, false);

                        $_row['updates'] = strip_tags($_updatedRecordInfo['updates']);
                        $_row['first_reporting_year'] = $_updatedRecordInfo['first_reporting_year'];
                        $_row['last_reporting_year'] = $_updatedRecordInfo['last_reporting_year'];

                        foreach ($_columnsArray as $_column => $_label) {
                            if ($_row['reporting_year'] < 2020 && in_array($_column, $_agreementPost2020Fields))
                                continue;
                            elseif ($_row['reporting_year'] >= 2020 && in_array($_column, $_agreementPre2020Fields))
                                continue;

                            $_activeSheet->setCellValueByColumnAndRow(0, $_rowNum, $_label);
                            $_value = isset($_row[$_column]) ? $_row[$_column] : 'NA';
                            $_activeSheet->setCellValueByColumnAndRow($_columnNum, $_rowNum, $_value);
                            $_rowNum++;
                        }

                        //Add public disclosures
                        $_publicDisclosures = $_iaReport->GetPublicDisclosuresForExport($_row['ia_agreement_id']);
                        $_publicDisclosureLabel = 'Public disclosure(s)';
                        foreach ($_publicDisclosures as $_publicDisclosure) {
                            $_publicDisclosureEntity->reporting_year = $_publicDisclosure['reporting_year'];
                            $_publicDisclosureEntity->ia_agreement_public_disclosure_id = $_publicDisclosure['ia_agreement_public_disclosure_id'];
                            $_updatedRecordInfo = $_iaReport->GetUpdatedRecordInfo($_publicDisclosureEntity, $_iaReport, false);

                            $_publicDisclosure['updates'] = strip_tags($_updatedRecordInfo['updates']);
                            $_publicDisclosure['first_reporting_year'] = $_updatedRecordInfo['first_reporting_year'];
                            $_publicDisclosure['last_reporting_year'] = $_updatedRecordInfo['last_reporting_year'];
                            try {
                                $_publicDisclosure['other_links'] = Zend_Json::decode($_publicDisclosure['other_links']);
                                $_publicDisclosure['other_links'] = implode('; ', $_publicDisclosure['other_links']);
                            } catch (Exception $e) {
                            }
                            if ($_publicDisclosure['public_disclosure_document'] != null)
                                $_publicDisclosure['public_disclosure_document'] = APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_publicDisclosure['public_disclosure_document'];

                            $_rowNum = $this->IaAgreementExcelExportAdditions($_activeSheet, $_rowNum, array($_publicDisclosure), $_publicDisclosureLabel);
                            $_publicDisclosureLabel = null;
                        }

                        $achieved++;
                        if ($achieved == $_stepItemsCount) {
                            $achieved = 0;
                            if (isset($progressId)) {
                                @session_start();
                                $_SESSION [$progressId] = $_SESSION [$progressId] + 1;
                                session_write_close();
                            }
                        }
                        $_activeSheet->getStyle('A' . $_rowNum . ':Z' . $_rowNum)->applyFromArray(
                            array(
                                'fill' => array(
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => 'e6efe7')
                                )
                            )
                        );
                    }

                    $_activeSheet->getStyle('A1:A' . $_rowNum)
                        ->applyFromArray(array(
                            'font' => array(
                                'bold' => true,
                                'size' => 14,
                            )
                        ));
                }

                if (isset($progressId)) {
                    @session_start();
                    if ($_SESSION [$progressId] != 95)
                        $_SESSION [$progressId] = 95;
                    session_write_close();
                    $_name = App_Function_ExportHelper::SaveExcelFile($_objPHPExcel, $_fileName, false, true);
                    @session_start();
                    $_SESSION [$progressId] = $_SESSION [$progressId] + 5;
                    $_SESSION [$progressId . '_file'] = $_name;
                    session_write_close();

                    echo APPLICATION_BASE_URL . '/uploads/exports/' . $_name;
                }

                exit();
            } else {
                if (isset($progressId)) {
                    @session_start();
                    if ($_SESSION [$progressId] != 95)
                        $_SESSION [$progressId] = 95;
                    session_write_close();
                    $_name = App_Function_ExportHelper::SaveExcelFile($_objPHPExcel, $_fileName, false, false);
                    @session_start();
                    $_SESSION [$progressId] = $_SESSION [$progressId] + 5;
                    $_SESSION [$progressId . '_file'] = $_name;
                    session_write_close();

                    echo APPLICATION_BASE_URL . '/uploads/exports/' . $_name;
                }

                exit();
            }
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * @param $_activeSheet PHPExcel_Worksheet
     * @param $_rowNum int
     * @param $_data array
     * @param $_label string
     * @return int
     * @throws PHPExcel_Exception
     */
    private function IaAgreementExcelExportAdditions($_activeSheet, $_rowNum, $_data, $_label)
    {
        $_publicDisclosureLabels = array(
            'ia_agreement_public_disclosure_id' => 'Public Disclosure ID',
            'first_reporting_year' => '1st year reported',
            'last_reporting_year' => 'Latest year reported',
            'no_public_disclosure_string' => '3.2.2 Public disclosure issued',
            'no_public_disclosure_explain' => '3.2.3 Explanation',
            'public_disclosure_link' => '3.2.4.1 Public disclosures related to this agreement/application (link)',
            'public_disclosure_document' => '3.2.4.1 Public disclosures related to this agreement/application (document)',
            'other_links' => '3.2.4.2 Secondary disclosures and other public records',
            'is_public_disclosure_provided_string' => '3.2.5 Public disclosure reported to system organization',
            'public_disclosure_not_provided_explain' => '3.2.6 Explanation',
            'anticipated_public_disclosure_string' => '3.2.7 Anticipated public disclosure or updated disclosure',
            'anticipated_public_disclosure_date' => '3.2.8 Anticipated public disclosure or updated disclosure date',
            'updates' => 'Updates/comments'
        );

        $_publicDisclosuresPre2020Fields = array(
            'is_public_disclosure_provided_string',
            'public_disclosure_not_provided_explain'
        );
        $_publicDisclosuresPost2020Fields = array();

        if ($_label != null) {
            $_activeSheet->setCellValueByColumnAndRow(0, $_rowNum, $_label);
            $_activeSheet->mergeCells('A' . $_rowNum . ':B' . $_rowNum);
            $_activeSheet->getStyle('A' . $_rowNum . ':B' . $_rowNum)->applyFromArray(
                array(
                    'alignment' => array(
                        'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
                        'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
                    )
                )
            );
            $_activeSheet->getRowDimension($_rowNum)->setRowHeight(25);
        }

        $_rowNum++;
        foreach ($_data as $_row) {
            foreach ($_publicDisclosureLabels as $_column => $_label) {
                if ($_row['reporting_year'] < 2020 && in_array($_column, $_publicDisclosuresPost2020Fields))
                    continue;
                elseif ($_row['reporting_year'] >= 2020 && in_array($_column, $_publicDisclosuresPre2020Fields))
                    continue;
                $_activeSheet->setCellValueByColumnAndRow(0, $_rowNum, $_label);
                if (is_array($_row)) {
                    $_value = isset($_row[$_column]) ? $_row[$_column] : 'NA';
                } else {
                    try {
                        $_value = $_row->$_column;
                    } catch (Exception $e) {
                    }
                    if ($_column === 'public_disclosure_update_document' && $_value != '' && $_value != null)
                        $_value = APPLICATION_BASE_URL . '/uploads/ia_reports/' . $_value;

                    $_value = isset($_value) ? $_value : 'NA';
                }
                $_activeSheet->setCellValueByColumnAndRow(1, $_rowNum, $_value);
                $_rowNum++;
            }
        }

        return $_rowNum;
    }

    public function hasreviewcommentsAction()
    {
        $_itemId = $this->getRequest()->getParam('item_id', null);
        $_fieldName = $this->getRequest()->getParam('field_name', null);
        $_formId = $this->getRequest()->getParam('form_id', null);

        $_tableName = App_Function_ReviewComments::GetFormRelatedTable($_formId, null);
        $_info = App_Function_ReviewComments::GetFormRelatedTable(null, $_tableName);

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {
            $_query = $_db->select()
                ->from($_info['main_table'], 'ia_report_id')
                ->where($_info['primary_column'] . ' = (?)', $_itemId);
            $_iaReportId = $_db->fetchCol($_query);
            $_iaReportId = count($_iaReportId) > 0 ? $_iaReportId[0] : null;

            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'data' => array()
            ));
        }

        $_comments = App_Function_ReviewComments::HasReviewComments($_formId, $_itemId, $_fieldName, $_iaReport->partner_id);

        $this->_helper->json->sendJson(array(
            'data' => $_comments
        ));
    }

    public function getallreviewcommentsAction()
    {
        $_itemId = $this->getRequest()->getParam('item_id', null);
        $_fieldName = $this->getRequest()->getParam('field_name', null);
        $_formId = $this->getRequest()->getParam('form_id', null);

        $_comments = App_Function_ReviewComments::GetAllReviewComments($_itemId, $_fieldName, $_formId);

        $this->_helper->json->sendJson(array(
            'data' => $_comments
        ));
    }

    public function getreviewcommentAction()
    {
        $_commentId = $this->getRequest()->getParam('comment_id', null);

        $_comment = App_Function_ReviewComments::GetComment($_commentId);

        $this->_helper->json->sendJson(array(
            'data' => $_comment
        ));
    }

    public function submitreviewcommentAction()
    {
        $_commentId = $this->getRequest()->getParam('comment_id', null);
        $_comment = $this->getRequest()->getParam('comment', null);
        $_userGroupsVisible = $this->getRequest()->getParam('user_groups', array());
        $_itemId = $this->getRequest()->getParam('item_id', null);
        $_fieldName = $this->getRequest()->getParam('field_name', null);
        $_formId = $this->getRequest()->getParam('form_id', null);
        $_tableName = App_Function_ReviewComments::GetFormRelatedTable($_formId, null);

        if (!is_array($_userGroupsVisible))
            $_userGroupsVisible = explode(',', $_userGroupsVisible);
        $_userGroupsVisible = array_filter($_userGroupsVisible);

        $_userGroupsVisible = array_unique($_userGroupsVisible);

        $_possibleUserGroups = App_Function_ReviewComments::GetUserPossibleShareWithGroups('ia', array());

        $_validUserGroupsVisible = array();
        foreach ($_possibleUserGroups as $_possibleUserGroup) {
            if (in_array($_possibleUserGroup['group_id'], $_userGroupsVisible))
                $_validUserGroupsVisible[] = $_possibleUserGroup['group_id'];
        }

        $_commentArray = array(
            'comment' => $_comment,
            'display_name' => null,
        );

        $_result = array(
            'result' => false,
            'message' => 'Oops!something went wrong',
            'error' => null
        );

        $_info = App_Function_ReviewComments::GetFormRelatedTable(null, $_tableName);
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {
            $_query = $_db->select()
                ->from($_info['main_table'], 'ia_report_id')
                ->where($_info['primary_column'] . ' = (?)', $_itemId);
            $_iaReportId = $_db->fetchCol($_query);
            $_iaReportId = count($_iaReportId) > 0 ? $_iaReportId[0] : null;

            $_iaReportMapper = new Model_Mapper_IaReport();
            $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
        } catch (Exception $e) {
            $_result['message'] = 'Item not found.';
            $this->_helper->json->sendJson($_result);
        }
        if (!App_Function_Privileges::canViewIAReport($_iaReport, null)) {
            $_result['message'] = 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.';
            $this->_helper->json->sendJson($_result);
        }

        try {
            if ($_commentId != null)
                $_commentResult = App_Function_ReviewComments::UpdateComment($_commentArray, $_commentId, $_validUserGroupsVisible);
            else
                $_commentResult = App_Function_ReviewComments::CreateComment($_commentArray, $_tableName, $_itemId, $_fieldName, $_validUserGroupsVisible);

            if ($_commentResult)
                $_result = array(
                    'result' => true
                );
        } catch (Exception $e) {
            $_result['error'] = $e->getMessage();
        }

        $this->_helper->json->sendJson($_result);
    }

    public function deletereviewcommentAction()
    {
        $_commentId = $this->getRequest()->getParam('comment_id', null);
        $_result = App_Function_ReviewComments::DeleteComment($_commentId);
        $this->_helper->json->sendJson(array(
            'result' => $_result,
            'message' => 'Oops! something went wrong',
            'error' => null
        ));
    }

    public function checkiareportagreementreviewstatusAction()
    {
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_iaAgreementPublicDisclosureId = $this->getRequest()->getParam('ia_agreement_public_disclosure_id', null);

        $_iaReportModel = new Model_IaReport();
        $_result = $_iaReportModel->GetIaReportAgreementReviews($_iaAgreementId, $_iaAgreementPublicDisclosureId, false);
        $this->_helper->json->sendJson($_result);
    }

    public function submitiareportagrementreviewAction()
    {
        $_iaAgreementId = $this->getRequest()->getParam('ia_agreement_id', null);
        $_grade = $this->getRequest()->getParam('grade', null);
        $_comments = $this->getRequest()->getParam('comments', null);
        $_justificationComments = $this->getRequest()->getParam('justification_comments', null);
        $_mlsAbsComments = $this->getRequest()->getParam('mls_abs_comments', null);
        $_evaluationComments = $this->getRequest()->getParam('evaluation_comments', null);
        $_userGroupTo = $this->getRequest()->getParam('user_group_to', null);
        $_relatedItemsConfirmed = $this->getRequest()->getParam('related_items_confirmed', '0');

        $_review = $this->SubmitIaReportAgreementReview($_iaAgreementId, null, $_grade, $_comments, null, $_justificationComments, $_mlsAbsComments, $_evaluationComments, $_userGroupTo, $_relatedItemsConfirmed);
        $this->_helper->json->sendJson($_review);
    }

    public function submitiareportpublicdisclosurereviewAction()
    {
        $_iaAgreementPublicDisclosureId = $this->getRequest()->getParam('ia_agreement_public_disclosure_id', null);
        $_grade = $this->getRequest()->getParam('grade', null);
        $_comments = $this->getRequest()->getParam('comments', null);
        $_externalComments = $this->getRequest()->getParam('external_comments', null);
        $_userGroupTo = $this->getRequest()->getParam('user_group_to', null);
        $_relatedItemsConfirmed = $this->getRequest()->getParam('related_items_confirmed', '0');

        $_review = $this->SubmitIaReportAgreementReview(null, $_iaAgreementPublicDisclosureId, $_grade, $_comments, $_externalComments, null, null, null, $_userGroupTo, $_relatedItemsConfirmed);
        $this->_helper->json->sendJson($_review);
    }

    private function SubmitIaReportAgreementReview($_iaAgreementId, $_iaAgreementPublicDisclosureId, $_grade, $_comments, $_externalComments, $_justificationComments, $_mlsAbsComments, $_evaluationComments, $_userGroupTo, $_relatedItemsConfirmed)
    {
        $_originalUserGroupTo = $_userGroupTo;
        try {
            if (is_numeric($_iaAgreementId) && $_iaAgreementId > 0) {
                $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
                $_iaReportAgreement = $_iaReportAgreementsMapper->fetchOne(array('ia_agreement_id' => $_iaAgreementId));
                $_iaReportAgreementPublicDisclosure = null;
            } elseif (is_numeric($_iaAgreementPublicDisclosureId) && $_iaAgreementPublicDisclosureId > 0) {
                $_iaReportAgreementPublicDisclosureMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
                $_iaReportAgreementPublicDisclosure = $_iaReportAgreementPublicDisclosureMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaAgreementPublicDisclosureId));
                $_iaReportAgreement = $_iaReportAgreementPublicDisclosure->agreement;
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

            $_canReviewAgreement = App_Function_Privileges::canReviewIAReportAgreement($_iaReportReviewLast->user_group_to);
            if (!$_canReviewAgreement)
                return array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );

            $_applicableRelatedItems = array();
            if ($_relatedItemsConfirmed != 'ignore') {
                $_applicableRelatedItems = $this->ReviewAgreementHasRelatedAgreements($_iaReportAgreement, $_iaReportAgreementPublicDisclosure, $_iaReportReviewLast);
                if ($_relatedItemsConfirmed == '0' && !empty($_applicableRelatedItems))
                    return array(
                        'result' => false,
                        'review_has_related_items' => true,
                        'related_items' => $_applicableRelatedItems
                    );
            }

            /**
             * User groups
             * 11: Intellectual Property Focal Point
             * 12: Intellectual Property Focal Point (PMU)
             * 28: ABS
             * 29: SCIPG
             */
            $_isDraft = $_userGroupTo == -1;
            $_userGroupFrom = null;
            if ($_iaReportReviewLast->user_group_to == 12 && App_Function_Privileges::isMemberOf(12)) {
                $_userGroupFrom = 12;
                if (!in_array($_userGroupTo, array(-1, 0, 11, 28, 29)))
                    return array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    );
            }
            if ($_iaReportReviewLast->user_group_to == 28 && App_Function_Privileges::isMemberOf(28)) {
                $_userGroupFrom = 28;
                $_userGroupTo = 12;
            }
            if ($_iaReportReviewLast->user_group_to == 29 && App_Function_Privileges::isMemberOf(29)) {
                $_userGroupFrom = 29;
                $_userGroupTo = 12;
            }
            if ($_iaReportReviewLast->user_group_to == 11 && App_Function_Privileges::canEditIAReport(null, $_iaReportAgreement->ia_report_id, true)) {
                $_userGroupFrom = 11;
                $_userGroupTo = 12;
            }

            $_userGroupTo = $_userGroupTo == '0' ? null : $_userGroupTo;
            if ($_isDraft)
                $_userGroupTo = null;

            if ($_userGroupFrom == null)
                return array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );

            if ($_userGroupFrom != 12 && $_userGroupFrom != 29) //SMO and SCIPG only can add grade
                $_grade = null;

            //If the review is final, grade cannot be 0: Pending
            if ($_userGroupTo == null && !$_isDraft && $_grade == '0')
                return array(
                    'result' => false,
                    'message' => 'Cannot grade as Pending for the final review.'
                );

            $_reviewData = array(
                'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                'grade' => $_grade,
                'comments' => $_comments,
                'external_comments' => $_externalComments,
                'justification_comments' => $_justificationComments,
                'mls_abs_comments' => $_mlsAbsComments,
                'evaluation_comments' => $_evaluationComments,
                'is_draft' => $_isDraft,
                'user_group_from' => $_userGroupFrom,
                'user_group_to' => $_userGroupTo
            );

            if ($_iaAgreementPublicDisclosureId != null)
                $_reviewData['ia_agreement_public_disclosure_id'] = $_iaAgreementPublicDisclosureId;
            else
                $_reviewData['ia_agreement_id'] = $_iaAgreementId;

            try {
                $_where = array('is_draft' => '1');
                if ($_iaAgreementPublicDisclosureId != null)
                    $_where['ia_agreement_public_disclosure_id'] = $_iaAgreementPublicDisclosureId;
                else
                    $_where['ia_agreement_id'] = $_iaAgreementId;
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne($_where, 'review_id DESC');
                $_reviewId = $_iaReportReviewLast->review_id;

                if ($_iaReportReviewLast->user_group_from != $_userGroupFrom)
                    return array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    );
                $_iaReportReviewMapper->update($_reviewData, 'review_id = ' . $_reviewId);
            } catch (Exception $e) {
                $_reviewId = $_iaReportReviewMapper->insert($_reviewData);
            }

            $_failedRelatedReviews = array();
            if (!empty($_applicableRelatedItems)) {
                foreach ($_applicableRelatedItems as $_applicableRelatedItem) {
                    if (isset($_applicableRelatedItem['ia_agreement_id']))
                        $_review = $this->SubmitIaReportAgreementReview($_applicableRelatedItem['ia_agreement_id'], null, $_grade, $_comments, null, $_justificationComments, $_mlsAbsComments, $_evaluationComments, $_originalUserGroupTo, 'ignore');
                    if (isset($_applicableRelatedItem['ia_agreement_public_disclosure_id']))
                        $_review = $this->SubmitIaReportAgreementReview(null, $_applicableRelatedItem['ia_agreement_public_disclosure_id'], $_grade, $_comments, $_externalComments, null, null, null, $_originalUserGroupTo, 'ignore');

                    if (isset($_review) && !$_review['result'])
                        $_failedRelatedReviews[] = $_applicableRelatedItem;
                }
            }

            if (isset($_iaReportAgreementsMapper))
                $_iaReportAgreementsMapper->updateImport(array(
                    'version' => $_iaReportAgreement->version + 1,
                    'updated_date' => date('Y-m-d H:i:s')
                ), 'ia_agreement_id = ' . $_iaReportAgreement->ia_agreement_id);
            elseif (isset($_iaReportAgreementPublicDisclosureMapper))
                $_iaReportAgreementPublicDisclosureMapper->updateImport(array(
                    'version' => $_iaReportAgreementPublicDisclosure->version + 1,
                    'updated_date' => date('Y-m-d H:i:s')
                ), 'ia_agreement_public_disclosure_id = ' . $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id);

            return array(
                'result' => true,
                'review_id' => $_reviewId,
                'has_failed_related_reviews' => !empty($_failedRelatedReviews),
                'failed_related_reviews' => $_failedRelatedReviews,
                'message' => 'Review ' . ($_isDraft ? 'saved' : 'submitted') . ' successfully.'
            );
        } catch (Exception $e) {
            return array(
                'result' => false,
                'message' => 'Oops! Something went wrong.',
                'error' => $e->getMessage()
            );
        }
    }

    private function ReviewAgreementHasRelatedAgreements($_iaAgreement, $_iaAgreementPublicDisclosure, $_iaReportReviewLast)
    {
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();

        if ($_iaAgreementPublicDisclosure != null && $_iaAgreement == null)
            $_iaAgreement = $_iaAgreementPublicDisclosure->agreement;

        if ($_iaAgreement->agreement_related_post != null)
            $_where = 'ia_agreement_id = ' . $_iaAgreement->agreement_related_post .
                ' OR agreement_related_post = ' . $_iaAgreement->agreement_related_post;
        else
            $_where = 'ia_agreement_id = ' . $_iaAgreement->ia_agreement_id .
                ' OR agreement_related_post = ' . $_iaAgreement->ia_agreement_id;

        $_relatedIaReportAgreements = $_iaReportAgreementsMapper->fetchMany($_where);

        $_applicableRelatedItems = array();

        if ($_iaAgreementPublicDisclosure == null) {
            foreach ($_relatedIaReportAgreements as $_relatedIaReportAgreement) {
                if ($_relatedIaReportAgreement->ia_agreement_id == $_iaAgreement->ia_agreement_id)
                    continue;

                try {
                    $_where = array(
                        'is_draft' => '0',
                        'ia_agreement_id' => $_relatedIaReportAgreement->ia_agreement_id,
                    );
                    $_relatedIaReportReviewLast = $_iaReportReviewMapper->fetchOne($_where, 'review_id DESC');
                } catch (Exception $e) {
                    $_relatedIaReportReviewLast = new Model_IaReportReview();
                    $_relatedIaReportReviewLast->user_group_to = 12; //User group 12: Intellectual Property Focal Point (PMU)
                }
                if ($_relatedIaReportReviewLast->user_group_to == $_iaReportReviewLast->user_group_to) {
                    $_country = $_relatedIaReportAgreement->country->name;
                    $_applicableRelatedItems[] = array(
                        'ia_agreement_id' => $_relatedIaReportAgreement->ia_agreement_id,
                        'display_name' => '(' . $_relatedIaReportAgreement->unique_identifier . ($_country != null ? ' - ' . $_country : '') . ') ' . $_relatedIaReportAgreement->agreement_title
                    );
                }
            }
        } else {
            foreach ($_relatedIaReportAgreements as $_relatedIaReportAgreement) {
                $_relatedIaReportAgreementPublicDisclosures = $_relatedIaReportAgreement->public_disclosures;
                foreach ($_relatedIaReportAgreementPublicDisclosures as $_relatedIaReportAgreementPublicDisclosure) {
                    if ($_relatedIaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id == $_iaAgreementPublicDisclosure->ia_agreement_public_disclosure_id)
                        continue;

                    try {
                        $_where = array(
                            'is_draft' => '0',
                            'ia_agreement_public_disclosure_id' => $_relatedIaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id,
                        );
                        $_relatedIaReportReviewLast = $_iaReportReviewMapper->fetchOne($_where, 'review_id DESC');
                    } catch (Exception $e) {
                        $_relatedIaReportReviewLast = new Model_IaReportReview();
                        $_relatedIaReportReviewLast->user_group_to = 12; //User group 12: Intellectual Property Focal Point (PMU)
                    }
                    if ($_relatedIaReportReviewLast->user_group_to == $_iaReportReviewLast->user_group_to) {
                        $_country = $_relatedIaReportAgreement->country->name;
                        $_applicableRelatedItems[] = array(
                            'ia_agreement_public_disclosure_id' => $_relatedIaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id,
                            'display_name' => '<b>' . $_relatedIaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id . ':</b> (' . $_relatedIaReportAgreement->unique_identifier . ($_country != null ? ' - ' . $_country : '') . ') ' . $_relatedIaReportAgreement->agreement_title
                        );
                    }
                }
            }
        }

        return $_applicableRelatedItems;
    }

    public function submitiareportreviewAction()
    {
        $_iaReportId = $this->getRequest()->getParam('ia_report_id', null);
        $_userGroupTo = $this->getRequest()->getParam('user_group_to', null);

        $_review = $this->SubmitIaReportReview($_iaReportId, $_userGroupTo);
        $this->_helper->json->sendJson($_review);
    }

    private function SubmitIaReportReview($_iaReportId, $_userGroupTo)
    {
        try {
            if ($_iaReportId != null) {
                $_iaReportMapper = new Model_Mapper_IaReport();
                $_iaReport = $_iaReportMapper->fetchOne(array('ia_report_id' => $_iaReportId));
            } else {
                throw new Zend_Acl_Exception('Entity Not defined', 404);
            }

            $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
            try {
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array(
                    'ia_report_id' => $_iaReportId,
                    'is_draft' => '0'
                ), 'review_id DESC');
            } catch (Exception $e) {
                $_iaReportReviewLast = new Model_IaReportReview();
                $_iaReportReviewLast->user_group_to = 12; //User group 12: Intellectual Property Focal Point (PMU)
            }

            $_canReviewIAReport = App_Function_Privileges::canReviewIAReport($_iaReportReviewLast->user_group_to);
            if (!$_canReviewIAReport)
                return array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );

            /**
             * User groups
             * 11: Intellectual Property Focal Point
             * 12: Intellectual Property Focal Point (PMU)
             * 28: ABS
             * 29: SCIPG
             */
            $_userGroupFrom = null;
            if ($_iaReportReviewLast->user_group_to == 12 && App_Function_Privileges::isMemberOf(12)) {
                $_userGroupFrom = 12;
                if (!in_array($_userGroupTo, array(0, 11)))
                    return array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    );
            }
            if ($_iaReportReviewLast->user_group_to == 11 && App_Function_Privileges::canEditIAReport($_iaReportId, null, true)) {
                $_userGroupFrom = 11;
                if (!in_array($_userGroupTo, array(12)))
                    return array(
                        'result' => false,
                        'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                    );
            }

            $_userGroupTo = $_userGroupTo == '0' ? null : $_userGroupTo;

            if ($_userGroupFrom == null)
                return array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                );

            $_missingReviews = array(
                'agreements' => array(),
                'public_disclosures' => array()
            );
            //If the IA report is final, check that all agreements/public disclosures review is submitted
            if ($_userGroupTo == null)
                $_missingReviews = $this->IsIaReportEntitiesReviewStepAligned($_iaReportId);

            if (!empty($_missingReviews['agreements']) || !empty($_missingReviews['public_disclosures'])) {
                $_message = 'Please submit the review for the following items first:';
                if (!empty($_missingReviews['agreements']))
                    $_message .= '<br><br><b>Section 3.1:</b><ul><li>ID: ' . implode('</li><li>ID: ', $_missingReviews['agreements']) . '</li></ul>';
                if (!empty($_missingReviews['public_disclosures']))
                    $_message .= '<br><b>Section 3.2:</b><ul><li>ID: ' . implode('</li><li>ID: ', $_missingReviews['public_disclosures']) . '</li></ul>';

                return array(
                    'result' => false,
                    'missing_review' => true,
                    'message' => $_message
                );
            }

            $_reviewData = array(
                'ia_report_id' => $_iaReport->ia_report_id,
                'reviewer_id' => App_Function_Privileges::getLoggedUser()->user_id,
                'is_draft' => 0,
                'user_group_from' => $_userGroupFrom,
                'user_group_to' => $_userGroupTo
            );

            try {
                $_iaReportReviewLast = $_iaReportReviewMapper->fetchOne(array(
                    'ia_report_id' => $_iaReport->ia_report_id,
                    'is_draft' => '1'
                ), 'review_id DESC');

                $_reviewId = $_iaReportReviewLast->review_id;

                $_iaReportReviewMapper->update($_reviewData, 'review_id = ' . $_reviewId);
            } catch (Exception $e) {
                $_reviewId = $_iaReportReviewMapper->insert($_reviewData);
            }

            $_iaReportMapper->updateImport(array(
                'version' => $_iaReport->version + 1,
                'updated_date' => date('Y-m-d H:i:s')
            ), 'ia_report_id = ' . $_iaReport->ia_report_id);
            return array(
                'result' => true,
                'message' => 'Review submitted successfully.',
                'review_id' => $_reviewId
            );
        } catch (Exception $e) {
            return array(
                'result' => false,
                'message' => 'Oops! Something went wrong.',
                'error' => $e->getMessage()
            );
        }
    }

    private function IsIaReportEntitiesReviewStepAligned($_iaReportId)
    {
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
        $_missingReviews = array(
            'agreements' => array(),
            'public_disclosures' => array()
        );
        $_iaReportAgreementsMapper = new Model_Mapper_IaReportAgreements();
        $_iaReportAgreements = $_iaReportAgreementsMapper->fetchMany(array('ia_report_id' => $_iaReportId, 'is_deleted' => '0'));

        $_iaReportAgreementPublicDisclosuresMapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
        $_iaReportAgreementPublicDisclosures = $_iaReportAgreementPublicDisclosuresMapper->fetchMany(array('ia_report_id' => $_iaReportId, 'is_deleted' => '0'));

        foreach ($_iaReportAgreements as $_iaReportAgreement) {
            try {
                $_iaReportReviewMapper->fetchOne(array('ia_agreement_id' => $_iaReportAgreement->ia_agreement_id, 'is_draft' => '0', 'user_group_to' => null));
            } catch (Exception $e) {
                $_missingReviews['agreements'][] = $_iaReportAgreement->unique_identifier;
            }
        }

        foreach ($_iaReportAgreementPublicDisclosures as $_iaReportAgreementPublicDisclosure) {
            try {
                $_iaReportReviewMapper->fetchOne(array('ia_agreement_public_disclosure_id' => $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id, 'is_draft' => '0', 'user_group_to' => null));
            } catch (Exception $e) {
                $_missingReviews['public_disclosures'][] = $_iaReportAgreementPublicDisclosure->ia_agreement_public_disclosure_id;
            }
        }

        return $_missingReviews;
    }

    public function iareviewAction()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_query = $_db->select()
            ->from('tbl_ia_report', 'reporting_year')
            ->group('reporting_year');
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_query->where('partner_id = (?)', App_Function_Privileges::getLoggedUser()->organization_id);

        $this->view->reporting_years = $_db->fetchCol($_query);

        $_query = $_db->select()
            ->from('tbl_ia_report', 'partner_id')
            ->group('partner_id');
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_query->where('partner_id = (?)', App_Function_Privileges::getLoggedUser()->organization_id);

        $this->view->ia_partners = $_db->fetchCol($_query);
        $this->view->ia_partners[] = -15;
    }

    public function getiareportresponsibilitiesAction()
    {
        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $_lastReviewIdQuery = $_db->select()
            ->from('tbl_ia_report_review AS sub_tbl_ia_report_review', 'MAX(sub_tbl_ia_report_review.review_id)')
            ->group(array('sub_tbl_ia_report_review.ia_report_id', 'sub_tbl_ia_report_review.ia_agreement_id', 'sub_tbl_ia_report_review.ia_agreement_public_disclosure_id'))
            ->where('sub_tbl_ia_report_review.is_draft = 0');
        $_lastReviewQuery = $_db->select()
            ->from('tbl_ia_report_review', array(
                'tbl_ia_report_review.review_id',
                'tbl_ia_report_review.ia_report_id',
                'tbl_ia_report_review.ia_agreement_id',
                'tbl_ia_report_review.ia_agreement_public_disclosure_id',
                'tbl_ia_report_review.user_group_to',
            ))
            ->where('tbl_ia_report_review.review_id IN (?)', $_lastReviewIdQuery);

        $_whereResponsibilities = array();
        if (App_Function_Privileges::isMemberOf(11))
            $_whereResponsibilities[] = '
            (tbl_ia_report.is_draft = 1 
                OR (tbl_ia_report.is_draft = 0 
                    AND ia_report_review.user_group_to = 11 
                )
            )';
        if (App_Function_Privileges::isMemberOf(12))
            $_whereResponsibilities[] = '
            (tbl_ia_report.is_draft = 0 
                AND (ia_report_review.review_id IS NULL 
                    OR ia_report_review.user_group_to = 12 
                )
            )';
        if (App_Function_Privileges::isMemberOf(28))
            $_whereResponsibilities[] = '(ia_report_review.user_group_to = 28)';
        if (App_Function_Privileges::isMemberOf(29))
            $_whereResponsibilities[] = '(ia_report_review.user_group_to = 29)';

        $_ia_report_ids = array();
        $_ia_agreement_ids = array();
        $_ia_agreement_public_disclosure_ids = array();

        if (!empty($_whereResponsibilities)) {
            $_iaReportResponsibilitiesQuery = $_query = $_db->select()->distinct()
                ->from('tbl_ia_report', 'tbl_ia_report.ia_report_id')
                ->joinLeft(array('ia_report_review' => $_lastReviewQuery), 'ia_report_review.ia_report_id = tbl_ia_report.ia_report_id', '')
                ->where(implode(' OR ', $_whereResponsibilities));
            $_agreementsResponsibilitiesQuery = $_query = $_db->select()->distinct()
                ->from('tbl_ia_report_agreements', 'tbl_ia_report_agreements.ia_agreement_id')
                ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreements.ia_report_id', '')
                ->joinLeft(array('ia_report_review' => $_lastReviewQuery), 'ia_report_review.ia_agreement_id = tbl_ia_report_agreements.ia_agreement_id', '')
                ->where(implode(' OR ', $_whereResponsibilities));
            $_publicDisclosureResponsibilitiesQuery = $_query = $_db->select()->distinct()
                ->from('tbl_ia_report_agreement_public_disclosure', 'tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id')
                ->join('tbl_ia_report', 'tbl_ia_report.ia_report_id = tbl_ia_report_agreement_public_disclosure.ia_report_id', '')
                ->joinLeft(array('ia_report_review' => $_lastReviewQuery), 'ia_report_review.ia_agreement_public_disclosure_id = tbl_ia_report_agreement_public_disclosure.ia_agreement_public_disclosure_id', '')
                ->where(implode(' OR ', $_whereResponsibilities));

            $_ia_report_ids = $_db->fetchCol($_iaReportResponsibilitiesQuery);
            $_ia_agreement_ids = $_db->fetchCol($_agreementsResponsibilitiesQuery);
            $_ia_agreement_public_disclosure_ids = $_db->fetchCol($_publicDisclosureResponsibilitiesQuery);
        }
        $_ia_report_ids[] = -15;
        $_ia_agreement_ids[] = -15;
        $_ia_agreement_public_disclosure_ids[] = -15;

        $this->_helper->json->sendJson(array(
            'ia_report_ids' => '/ia_report_ids/' . implode(',', $_ia_report_ids),
            'ia_agreement_ids' => '/ia_agreement_ids/' . implode(',', $_ia_agreement_ids),
            'ia_agreement_public_disclosure_ids' => '/ia_agreement_public_disclosure_ids/' . implode(',', $_ia_agreement_public_disclosure_ids)
        ));
    }

    public function getalliareportstoreviewAction()
    {
        $_iaReportIds = $this->getRequest()->getParam('ia_report_ids', null);

        $_where = array();
        if (!App_Function_Privileges::canReviewIAReport(null))
            $_where['partner_id'] = App_Function_Privileges::getLoggedUser()->organization_id;

        if (!is_array($_iaReportIds))
            $_iaReportIds = explode(',', $_iaReportIds);
        $_iaReportIds = array_filter($_iaReportIds);
        if (!empty($_iaReportIds))
            $_where['ia_report_id'] = $_iaReportIds;

        $_iaReportsMapper = new Model_Mapper_IaReport();
        $_iaReportReviewMapper = new Model_Mapper_IaReportReview();
        $_iaReports = $_iaReportsMapper->fetchMany($_where, array('reporting_year DESC', 'partner_id'));

        $_dataArray = array();
        foreach ($_iaReports as $_iaReport) {
            $_iaReportArray = $_iaReport->toArray();
            $_iaReportArray['organization_name'] = $_iaReport->organization->abbreviation_or_name;
            $_iaReportArray['status'] = $_iaReport->is_draft == '1' ? 'Draft' : 'Submitted';
            $_iaReportArray['can_review_ia_report'] = App_Function_Privileges::canReviewIAReport(null);
            $_iaReportArray['can_edit_ia_report'] = App_Function_Privileges::canEditIAReport($_iaReport, null, false);

            $_iaReportArray['summary'] = $_iaReport->GetIaReportSummary(false, null, null);

            $_iaReportArray['review_status'] = array();
            if ($_iaReport->is_draft == '1') {
                $_iaReportArray['review_status'][] = 'Not started';
            } else {
                $_iaReportReviews = $_iaReportReviewMapper->fetchMany(array('ia_report_id' => $_iaReport->ia_report_id, 'is_draft' => '0'));
                $_iaReportReview = new Model_IaReportReview();

                //Add the initial review
                $_iaReportArray['review_status'][] = $_iaReportReview->ReviewerDisplayName(12); //User group 12: Intellectual Property Focal Point (PMU)
                foreach ($_iaReportReviews as $_iaReportReview) {
                    if ($_iaReportReview->user_group_to == null)
                        $_iaReportArray['review_status'][] = 'Final review submitted';
                    else
                        $_iaReportArray['review_status'][] = $_iaReportReview->ReviewerDisplayName($_iaReportReview->user_group_to);
                }
            }

            $_iaReportArray['review_status'] = implode(', ', $_iaReportArray['review_status']);

            $_dataArray[] = $_iaReportArray;
        }
        $this->_helper->json->sendJson(array(
            'data' => $_dataArray
        ));
    }

    public function getiareportsummaryAction()
    {
        $_reportingYear = $this->getRequest()->getParam('reporting_year', null);
        $_partnerId = $this->getRequest()->getParam('partner_id', null);
        if (!is_array($_reportingYear))
            $_reportingYear = explode(',', $_reportingYear);
        $_reportingYear = array_filter($_reportingYear);
        if (!is_array($_partnerId))
            $_partnerId = explode(',', $_partnerId);
        $_partnerId = array_filter($_partnerId);

        if (!App_Function_Privileges::canReviewIAReport(null))
            $_partnerId = array(App_Function_Privileges::getLoggedUser()->organization_id);

        $_iaReport = new Model_IaReport();

        $_summary = $_iaReport->GetIaReportSummary(true, $_reportingYear, $_partnerId);

        $this->_helper->json->sendJson(array(
            'data' => $_summary
        ));
    }

    public function getiareportgeneralcommentsAction()
    {
        $_years = $this->getRequest()->getParam('years', array());
        if (!is_array($_years))
            $_years = explode(',', $_years);

        if (!App_Function_Privileges::canReviewIAReport(null))
            $this->_helper->json->sendJson(array(
                'data' => array()
            ));

        $_db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $_iaReportGeneralCommentsMapper = new Model_Mapper_IaReportGeneralComments();

        if (empty($_years)) {
            $_query = $_db->select()
                ->from('tbl_ia_report_general_comments', 'MAX(reporting_year) as reporting_year');
            $_year = $_db->fetchCol($_query);
            $_year = count($_year) > 0 ? $_year[0] : null;
        } else {
            $_year = max($_years);
        }

        $_iaReportGeneralComments = $_iaReportGeneralCommentsMapper->fetchMany(array('reporting_year' => $_year));

        $this->_helper->json->sendJson(array(
            'data' => $_iaReportGeneralComments->toArray()
        ));
    }

    public function submitiareportgeneralcommentsAction()
    {
        $_fieldName = $this->getRequest()->getParam('field_name', null);
        $_comment = $this->getRequest()->getParam('comment', null);
        $_reportingYear = date('Y') - 1;

        if (!App_Function_Privileges::isMemberOf(12)) //User group 12: Intellectual Property Focal Point (PMU)
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Cannot submit, you don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
            ));

        $_iaReportGeneralCommentsMapper = new Model_Mapper_IaReportGeneralComments();
        try {
            $_id = $_iaReportGeneralComments = $_iaReportGeneralCommentsMapper->fetchOne(array(
                'field_name' => $_fieldName,
                'reporting_year' => $_reportingYear
            ))->id;

            $_iaReportGeneralCommentsMapper->update(array('comment' => $_comment), 'id = ' . $_id);
        } catch (Exception $e) {
            $_iaReportGeneralCommentsMapper->insert(array(
                'added_by' => App_Function_Privileges::getLoggedUser()->user_id,
                'field_name' => $_fieldName,
                'comment' => $_comment,
                'reporting_year' => $_reportingYear
            ));
        }

        $this->_helper->json->sendJson(array(
            'result' => true,
            'message' => 'Comment added successfully.'
        ));
    }

    public function compareaareportingversionsAction()
    {
        $_baseVersion = $this->getRequest()->getParam('base_version', null);
        $_baseVersionReportingYear = $this->getRequest()->getParam('base_version_reporting_year', null);
        $_compareVersion = $this->getRequest()->getParam('compare_version', null);
        $_compareVersionReportingYear = $this->getRequest()->getParam('compare_version_reporting_year', null);
        $_entityId = $this->getRequest()->getParam('entity_id', null);
        $_entityType = $this->getRequest()->getParam('entity_type', null);

        try {
            if ($_entityType == 'Model_IaReport') {
                $_mapper = new Model_Mapper_IaReport();
                $_where = array('ia_report_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportCrp') {
                $_mapper = new Model_Mapper_IaReportCrp();
                $_where = array('ia_report_crp_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportManagementDocuments') {
                $_mapper = new Model_Mapper_IaReportManagementDocuments();
                $_where = array('ia_management_document_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportPortfolioDocuments') {
                $_mapper = new Model_Mapper_IaReportPortfolioDocuments();
                $_where = array('ia_portfolio_document_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportAgreements') {
                $_mapper = new Model_Mapper_IaReportAgreements();
                $_where = array('ia_agreement_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportAgreementPublicDisclosure') {
                $_mapper = new Model_Mapper_IaReportAgreementPublicDisclosure();
                $_where = array('ia_agreement_public_disclosure_id' => $_entityId);
            } elseif ($_entityType == 'Model_IaReportUpdates') {
                $_mapper = new Model_Mapper_IaReportUpdates();
                $_where = array('ia_report_update_id' => $_entityId);
            } else {
                throw new Zend_Acl_Exception('Item not found', 404);
            }

            $_entity = $_mapper->fetchOne($_where);
            if ($_entityType != 'Model_IaReport')
                $_iaReport = $_entity->ia_report;
            else
                $_iaReport = $_entity;

            if (!App_Function_Privileges::canViewIAReport($_iaReport, null))
                $this->_helper->json->sendJson(array(
                    'result' => false,
                    'message' => 'You don\'t have enough privileges.<br>Please contact <a href="mailto:mel-support@cgiar.org">mel-support@cgiar.org</a> for assistance.'
                ));

            $_history = $_iaReport->GetIaReportingDiff($_entity, $_baseVersion, $_baseVersionReportingYear, $_compareVersion, $_compareVersionReportingYear);
            $this->_helper->json->sendJson(array(
                'result' => true,
                'data' => $_history,
            ));
        } catch (Exception $e) {
            $this->_helper->json->sendJson(array(
                'result' => false,
                'message' => 'Oops! something went wrong.',
                'error' => $e->getMessage(),
            ));
        }
    }
}
