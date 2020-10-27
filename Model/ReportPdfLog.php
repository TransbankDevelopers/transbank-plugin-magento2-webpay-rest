<?php
namespace Transbank\Webpay\Model;

use Transbank\Webpay\Model\ReportPdf;
use Transbank\Webpay\Model\LogHandler;

class ReportPdfLog {

    function __construct($document){
        $this->document = $document;
    }

    function getReport($myJSON){
        $log = new LogHandler();
        $data = $log->getLastLog();
        $obj = json_decode($myJSON,true);
        if (isset($data['log_content']) && $this->document == 'report'){
            $html = str_replace("\r\n","<br>",$data['log_content']);
            $html = str_replace("\n","<br>",$data['log_content']);
            $text = explode ("<br>" ,$html);
            $html='';
            foreach ($text as $row){
                $html .= '<b>'.substr($row,0,21).'</b> '.substr($row,22).'<br>';
            }
            $obj += array('logs' => array('log' => $html));
        }
        $html = '';
        $report = new ReportPdf();
        $report->getReport(json_encode($obj));
    }
}
