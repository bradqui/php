<?php 

/*  RightNowRunAnalyticsReport.php
*  
*
*  Author: Brad Quillen (me@bquillen.com)
*  Description:  Run an analytics report using RightNow Connect for SOAP API, and build a data set

*  Usage:  

    include('RightNowRunAnalyticsReport.php');

    $WSDL = 'https://[SITE NAME].custhelp.com/cgi-bin/[INTERFACE].cfg/services/soap?wsdl=typed';  // SOAP Web Service WSDL
    $username = 'XXXXXX';     // API Username 
    $password = 'XXXXXX';     // API Password

    $client = new RightNowRunAnalyticsReport($WSDL, $username, $password);

    $report_id = XXXXXX;    // Report ID to run   
    $start = 0;             // Row to start on
    $limit = 0;            // How many rows to fetch (0-9999, 0 == all rows in report)

    // Filters are optional
    $filter = new ReportFilter();
    $filter->Name = "Reference Number"; // Filter name defined in RightNow Analytics
    $filter->Operator = 1;              // Operator ID (see "Filter Operator Definitions" below)
    $filter->Value = "130423-029813";   // Value to compare

    $filters = array($filter);          // Create an array of filters -- can add multiple -- array($filter1, $filter2)

    $client->run($report_id, $start, $limit);               // Without Filters
    //$client->run($report_id, $start, $limit, $filters);   // With Filters

    $columns = $client->result->columns                     // All the columns in the report
    $data = $client->result->data                           // All the data rows from the report
    $processing_time = $client->result->processing_time     // Time in seconds it took to retrieve and process the report
    $row_count = $client->result->row_count                 // Number of rows returned in the report

    // Print the whole data set returned from the API call
    echo '<pre>';
    print_r($client->result);
    echo '</pre>';
    
*   Filter Operator Definitions
*
    -----------------------------------------------
    |Name	                    Operator        ID|
    |---------------------------------------------|
    |Equal	                    =               1 |
    |Not Equal	                <>	            2 |
    |Less Than	                <	            3 |
    |Less Than or Equal to	    <=	            4 |
    |Greater Than	            >	            5 |
    |Greater Than or Equal to	>=	            6 |
    |Is Like	                LIKE	        7 |
    |Is Not Like	            NOT LIKE	    8 |
    |Is Between	                RANGE	        9 |
    |Is In List	                IN LIST	        10|
    |Is Not In List	            NOT IN LIST	    11|
    |Not Equal To or NULL	    NE_OR_NULL	    14|
    |Not Like or NULL	        NLIKE_OR_NULL   15|
    |Regular Expression	        REGEX	        19|
    |Not Regular Expression	    NOT REGEX	    20|
    -----------------------------------------------

*/

// Auth Token Class
class clsWSSEAuth 
{ 
    private $Username; 
    private $Password;  
    function __construct($username, $password) 
    { 
             $this->Username = $username; 
             $this->Password = $password; 
    } 
} 

// Username Token Class
class clsWSSEToken 
{ 
    private $UsernameToken; 
    function __construct($innerVal)
    { 
        $this->UsernameToken = $innerVal; 
    } 
}

// RunAnalyticsReport Class
//  Takes AnalyticsReport object, start, and limit
class RunAnalyticsReport 
{
    private $AnalyticsReport;
    private $Limit;
    private $Start;
    
    function __construct($analyticsReport, $start, $limit)
    {
        $this->AnalyticsReport = $analyticsReport;
        $this->Limit = $limit;
        $this->Start = $start;
    }
}

// AnalyticsReport Class
//  Takes ID object parameter
class AnalyticsReport
{
    private $ID;
    private $Filters;
    
    function __construct($id, $filter = NULL)
    {
        $this->ID = $id;
        $this->Filters =  $filter;
    }
}

// ReportFilter class
//  A more simple way to build a report filter
class ReportFilter
{
    public $Name;
    public $Operator;
    public $Value;
}

// AnalyticsReportFilter class and dependant classes (Attributes, SearchFilter, and NamedID)
class AnalyticsReportFilter extends AnalyticsReportSearchFilter 
{
	public $Attributes;
	public $DataType;
	public $Prompt;
}

class AnalyticsReportFilterAttributes 
{
	public $Editable;
	public $Required;
}

class AnalyticsReportSearchFilter 
{
	public $Name;
	public $Operator;
	public $Values;
}

class NamedID 
{
	public $ID;
	public $Name;
}

// ID Class
class ID
{
    private $id;
    
    function __construct($_id)
    {
        $this->id = $_id;
    }
}

// For custom purposes, we extend the SoapClient class so we can modify the request XML
class SoapClientCustom extends SoapClient
{
    public function __doRequest($request, $location, $action, $version)
    {   
        // Hack to remove the nested Filter tags in the SOAP XML
        $i = 1;
        $request = str_replace('<ns2:Filters>', '', $request, $i);
        $request = $this->str_lreplace('</ns2:Filters>', '', $request);
        $request = str_replace('<ns2:Filters/>', '', $request);
        
        //echo "Request:\n" . $request . "\n";
        
        return utf8_encode(parent::__doRequest($request, $location, $action, $version));
    }
    
    private function str_lreplace($search, $replace, $subject)
    {
        $pos = strrpos($subject, $search);

        if($pos !== false)
        {
            $subject = substr_replace($subject, $replace, $pos, strlen($search));
        }

        return $subject;
    }    
}

// Response Object to hold the information
class ResponseObject
{
    public $processing_time;
    public $row_count;
    public $columns;
    public $data;
}


// RightNowRunAnalyticsReport class
//  Takes WSDL, username, and password as parameters
//  Public Vars:  result->columns
//                result->data
//                result->processing_time
//                result->row_count
//  Public Methods:  run();
class RightNowRunAnalyticsReport
{
    // Define class parameters
    
    // Connection information
    private $WSDL;
    private $username; 
    private $password;

    // SOAP Namespaces
    private $messagesNs = 'urn:messages.ws.rightnow.com/v1_2';
    private $objectsNs = 'urn:objects.ws.rightnow.com/v1_2';
    private $baseNs = 'urn:base.ws.rightnow.com/v1_2';
    private $strWSSENS = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"; 

    // Soap Objects
    private $objSoapVarUser;
    private $objSoapVarPass;
    private $appid;
    private $cif;
    private $objWSSEAuth; 
    private $objSoapVarWSSEAuth; 
    private $objWSSEToken; 
    private $objSoapVarWSSEToken; 
    private $objSoapVarHeaderVal; 
    private $objSoapVarWSSEHeader;
    private $objSoapVarAppID;
    
    // SoapClient options
    private $options = array( 
                    'soap_version' => SOAP_1_1, 
                    'exceptions' => 1, 
                    'trace' => 1, 
                    'cache_wsdl' => WSDL_CACHE_NONE,
                    'classmap' => array('ID' => 'ID',
                                        'AnalyticsReport' => 'AnalyticsReport',
                                        'RunAnalyticsReport' => 'RunAnalyticsReport',
                                        "AnalyticsReportFilter" => "AnalyticsReportFilter",
                                        "AnalyticsReportSearchFilter" => "AnalyticsReportSearchFilter",
                                        "AnalyticsReportFilterAttributes" => "AnalyticsReportFilterAttributes",
                                        "NamedID" => "NamedID")
                );
     
    // SoapClient object
    private $objClient; 
    // SOAP method
    private $strMethod = 'RunAnalyticsReport';
    
    public $result;
    
    // Constructor -- Pass in the web service WSDL, username, and password
    function __construct($wsdl, $u, $p)
    {
        $this->WSDL = $wsdl;
        $this->username = $u;
        $this->password = $p;
        $this->result = new ResponseObject();
        $this->result->processing_time = 0;
        $this->result->row_count = 0;
    }

    // Run the report and return the results
    //  Pass in the Report ID, the row start, and the row limit.
    //  If no limit is passed in, the entire report will be fetched
    //  up to 100,000 rows.
    public function run($Id, $start = 0, $limit = 9999, $filter = NULL)
    {
        $start_time = microtime(true);
        
        // Create the SOAP Security/Auth header
        $this->objSoapVarUser = new SoapVar($this->username, XSD_STRING, NULL, $this->strWSSENS, NULL, $this->strWSSENS); 
        $this->objSoapVarPass = new SoapVar($this->password, XSD_STRING, NULL, $this->strWSSENS, NULL, $this->strWSSENS);

        $this->objWSSEAuth = new clsWSSEAuth($this->objSoapVarUser, $this->objSoapVarPass); 
        $this->objSoapVarWSSEAuth = new SoapVar($this->objWSSEAuth, SOAP_ENC_OBJECT, NULL, $this->strWSSENS, 'UsernameToken', $this->strWSSENS); 
        $this->objWSSEToken = new clsWSSEToken($this->objSoapVarWSSEAuth); 
        $this->objSoapVarWSSEToken = new SoapVar($this->objWSSEToken, SOAP_ENC_OBJECT, NULL, $this->strWSSENS, 'UsernameToken', $this->strWSSENS); 
        $this->objSoapVarHeaderVal = new SoapVar($this->objSoapVarWSSEToken, SOAP_ENC_OBJECT, NULL, $this->strWSSENS, 'Security', $this->strWSSENS); 
        $this->objSoapVarWSSEHeader = new SoapHeader($this->strWSSENS, 'Security', $this->objSoapVarHeaderVal,true, 'http://www.yahoo.com/');
        
        // Create the ClientInfoHeader (required by RightNow)
        $this->appid = new SoapVar('PHP SOAP Client', XSD_STRING, NULL, $this->messagesNs, NULL, $this->messagesNs);
        $this->cif = new SoapVar(array('AppID' => $this->appid), SOAP_ENC_OBJECT, NULL, $this->messagesNs, NULL, $this->messagesNs);
        $this->objSoapVarAppID = new SoapHeader($this->messagesNs, 'ClientInfoHeader', $this->cif);
        
        // Create the SOAP Client
        //  This is a custom override of built in PHP SoapClient
        $this->objClient = new SoapClientCustom($this->WSDL, $this->options);         
        
        // Set the SOAP Client headers
        $this->objClient->__setSoapHeaders(array($this->objSoapVarAppID, $this->objSoapVarWSSEHeader));
        
        // Create RunAnalyticsReport object and associated sub-objects
        $report_id = new ID($Id);
        $reportIdSoapVar = new SoapVar($report_id, SOAP_ENC_OBJECT);
        
        // Build the proper report filter objects
        $analyticsReportFilter = new ArrayObject();
        
        $filters = array();
        
        if ($filter != NULL && is_array($filter))
        {
            foreach ($filter as $key)
            {
                $f = new AnalyticsReportFilter();
                $f->Name = $key->Name;
                $namedID = new NamedID();
                $id = new ID($key->Operator);
                $namedID->ID = $id;
                $f->Operator = $namedID;
                $value = $key->Value;
                $f->Values = $value;
                $filters[] = $f;
            }
            
            foreach ($filters as $key)
            {
                $a = new SoapVar($key, SOAP_ENC_OBJECT, NULL, $this->objectsNs, 'Filters', $this->objectsNs);
                $analyticsReportFilter->append($a);
            }
            
            $analyticsReportFilters = new SoapVar($analyticsReportFilter, SOAP_ENC_OBJECT);
        }
        else
            $analyticsReportFilters = NULL;
            
        // AnalyticsReport declaration
        $analyticsReport = new AnalyticsReport($reportIdSoapVar, $analyticsReportFilters);
        $analyticsReportSoapVar = new SoapVar($analyticsReport, SOAP_ENC_OBJECT, NULL, $this->messagesNs, 'AnalyticsReport', $this->messagesNs);
        
        // The start element declaration
        $startSoapVar = new SoapVar($start, XSD_INT, NULL, $this->messagesNs, 'Start', $this->messagesNs);
        
        // If no limit is supplied, use 10000 (single result max)
        if ($limit == 0)
        {
            $limit = 10000;
        }   
        $limitSoapVar = new SoapVar($limit, XSD_INT, NULL, $this->messagesNs, 'Limit', $this->messagesNs);

        $runAnalyticsReport = new RunAnalyticsReport($analyticsReportSoapVar, $startSoapVar, $limitSoapVar);
        $runAnalyticsReportSoapVar = new SoapVar($runAnalyticsReport, SOAP_ENC_OBJECT, NULL, $this->messagesNs, 'RunAnalyticsReport', $this->messagesNs);
        
        // Perform the SOAP request
        $objResponse = $this->objClient->__soapCall($this->strMethod, array($runAnalyticsReportSoapVar));
        
        //echo '<pre>';
        //print_r($objResponse);
        //echo '</pre>';
        
        if (!isset($objResponse->faultstring))
        {
            // Get the columns from the report and store them in an array in the ResponseObject
            $column_array = str_getcsv($objResponse->CSVTableSet->CSVTables->CSVTable->Columns);
            $this->result->columns = $column_array;
            
            if (isset($objResponse->CSVTableSet->CSVTables->CSVTable->Rows->Row))
            {
                if (count($objResponse->CSVTableSet->CSVTables->CSVTable->Rows->Row) == 1)
                {
                    $row_array = str_getcsv($objResponse->CSVTableSet->CSVTables->CSVTable->Rows->Row);
                    $this->result->data[] = $row_array;
                    unset($row_array);
                
                }
                else
                {
                    // Loop through the rows in report and populate the array in the ResponseObject
                    foreach ($objResponse->CSVTableSet->CSVTables->CSVTable->Rows->Row as $key)
                    {
                        $row_array = str_getcsv($key);
                        $this->result->data[] = $row_array;
                        unset($row_array);
                    }
                }
                
                // If the result was 10,000 or more, we need to run the report again and start on 10000.
                //  Same thing on the second run, except start on 20000, etc.
                if (count($objResponse->CSVTableSet->CSVTables->CSVTable->Rows->Row) >= 10000)
                {
                    $this->run($Id, count($this->result->data), 10000, $filter);
                }
            }
        }
        $end_time = microtime(true);
        $this->result->processing_time = $end_time-$start_time;
        $this->result->row_count = count($this->result->data);
        unset($end_time, $start_time);
    }
}
?>
