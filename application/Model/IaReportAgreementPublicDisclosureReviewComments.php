<?php

/**
 *  Ia Report Agreement Public Disclosure Review Comments domain model
 */
class Model_IaReportAgreementPublicDisclosureReviewComments extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_agreement_public_disclosure_review_comments';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'review_id' => COLUMN_INT,
        'ia_agreement_public_disclosure_id' => COLUMN_INT,
        'comment_id' => COLUMN_INT,
        'field_name' => COLUMN_STRING
    );
}
