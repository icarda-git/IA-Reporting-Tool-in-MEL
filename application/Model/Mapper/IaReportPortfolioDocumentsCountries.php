<?php

/**
 *   Ia Report Portfolio Documents Countries mapper
 */
class Model_Mapper_IaReportPortfolioDocumentsCountries extends App_Model_Mapper_MapperAbstract
{
    protected $_name = 'tbl_ia_report_portfolio_documents_countries';
    protected $_description = 'IA portfolio documents countries';
    protected $_shortDescription = 'IA portfolio documents countries';
    protected $_logsKeys = array(
        'id',
        'ia_portfolio_document_id',
        'country_id'
    );
    /**
     * @var int
     * 0 =   child
     * 1 =   parent
     * 2 =   conditional
     */
    protected $_showInLogs = 0;
    /**
     * @var string
     * the log condition to add trigger to
     * if $_showInLogs = 2 then put condition here
     * if $_showInLogs is not 2 then put null here
     */
    protected $_LogCondition = null;
}
