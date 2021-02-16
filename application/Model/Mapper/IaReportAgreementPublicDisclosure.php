<?php
/**
 *  Ia Report Agreement Public Disclosure mapper
 */
class Model_Mapper_IaReportAgreementPublicDisclosure extends App_Model_Mapper_MapperAbstract
{
	protected $_name = 'tbl_ia_report_agreement_public_disclosure';
    protected $_description = 'IA report agreement public disclosure';
    protected $_shortDescription = 'IA report agreement public disclosure';
    protected $_logsKeys = array(
        'ia_agreement_public_disclosure_id',
        'ia_report_id',
        'ia_agreement_id'
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
