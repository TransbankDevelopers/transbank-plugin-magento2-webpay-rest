<?php

namespace Transbank\Webpay\Model;

use Transbank\Webpay\Helper\PluginLogger;

class ReportPdfLog
{
    private $document;
    public function __construct($document)
    {
        $this->document = $document;
    }

    public function getReport($myJSON)
    {
        $log = new PluginLogger();
        $data = $log->getLastLog();
        $obj = json_decode($myJSON, true);
        if (isset($data['log_content']) && $this->document == 'report') {
            $html = str_replace("\r\n", '<br>', $data['log_content']);
            $html = str_replace("\n", '<br>', $data['log_content']);
            $text = explode('<br>', $html);
            $html = '';
            foreach ($text as $row) {
                $html .= '<b>'.substr($row, 0, 21).'</b> '.substr($row, 22).'<br>';
            }
            $obj += ['logs' => ['log' => $html]];
        }
        $html = '';
        $report = new ReportPdf();
        $report->getReport(json_encode($obj));
    }
}
