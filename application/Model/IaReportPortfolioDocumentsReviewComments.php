<?php

/**
 *  Ia Report Portfolio Documents Review Comments domain model
 */
class Model_IaReportPortfolioDocumentsReviewComments extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_portfolio_documents_review_comments';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'review_id' => COLUMN_INT,
        'ia_portfolio_document_id' => COLUMN_INT,
        'comment_id' => COLUMN_INT,
        'field_name' => COLUMN_STRING
    );
}
