<?php

/**
 *  Ia Report Portfolio Documents Countries domain model
 */
class Model_IaReportPortfolioDocumentsCountries extends App_Model_ModelAbstract
{
    /**
     * @see App_Model_ModelAbstract::$_fields
     * @var array
     */
    protected $_tableName = 'tbl_ia_report_portfolio_documents_countries';
    protected $_tableType = 'tbl';

    protected $_fields = array(
        'id' => COLUMN_INT,
        'ia_portfolio_document_id' => COLUMN_INT,
        'country_id' => COLUMN_INT,
        'status' => COLUMN_STRING,
        'reporting_year' => COLUMN_INT
    );

    public function _getCountry()
    {
        try {
            $_countryMapper = new Model_Mapper_WipoCountries();
            return $_countryMapper->fetchOne(array('country_id' => $this->country_id));
        } catch (Exception $e) {
            return new Model_WipoCountries();
        }
    }
}
