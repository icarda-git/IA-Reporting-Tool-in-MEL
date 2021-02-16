<?php

/**
 *  Ia Report Portfolio Documents mapper
 */
class Model_Mapper_IaReportPortfolioDocuments extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_portfolio_documents';
    protected $_description = 'Center IA Report Portfolio document';
    protected $_shortDescription = 'Center IA Report Portfolio Related document';
    protected $_logsKeys = array(
        'ia_report_id',
        'ia_portfolio_document_id',
        'portfolio_title'
    );
    /**
     * @var int
     * 0 =   child
     * 1 =   parent
     * 2 =   conditional
     */
    protected $_showInLogs = 1;
    /**
     * @var string
     * the log condition to add trigger to
     * if $_showInLogs = 2 then put condition here
     * if $_showInLogs is not 2 then put null here
     */
    protected $_LogCondition = null;
}
