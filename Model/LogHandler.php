<?php
namespace Transbank\Webpay\Model;

class LogHandler {

    //constants for log handler
    const LOG_DEBUG_ENABLED = false; //enable or disable debug logs
    const LOG_INFO_ENABLED = true; //enable or disable info logs
    const LOG_ERROR_ENABLED = true; //enable or disable error logs

    private $logFile;
    private $logDir;
    private $ecommerce;

    public function __construct($ecommerce = 'magento', $days = 7, $weight = '2MB') {
        $this->reponse = null;
        $this->logFile = null;
        $this->ecommerce = $ecommerce;
        $this->lockfile = "./set_logs_activate.lock";
        $dia = date('Y-m-d');
        $this->confdays = $days;
        $this->confweight = $weight;

        $this->logDir = BP . "/var/log/Transbank_webpay";
        $this->logFile = "{$this->logDir}/log_transbank_{$this->ecommerce}_{$dia}.log";

        $this->setMakeLogDir();
    }

    private function formatBytes($path) {
        $bytes = sprintf('%u', filesize($path));
        if ($bytes > 0) {
            $unit = intval(log($bytes, 1024));
            $units = array('B', 'KB', 'MB', 'GB');
            if (array_key_exists($unit, $units) === true) {
                return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]);
            }
        }
        return $bytes;
    }

    private function setMakeLogDir() {
        try {
            if (!file_exists($this->logDir)) {
                mkdir($this->logDir, 0777, true);
            }
        } catch(Exception $e) {
        }
    }

    public function setparamsconf($days, $weight) {
        if (file_exists($this->lockfile)) {
            $file = fopen($this->lockfile, "w") or die("No se puede truncar archivo");
            if (! is_numeric($days) or $days == null or $days == '' or $days === false) {
                $days = 7;
            }
            $txt = "{$days}\n";
            fwrite($file, $txt);
            $txt = "{$weight}\n";
            fwrite($file, $txt);
            fclose($file);
            // chmod($this->lockfile, 0600);
        } else {
            //  echo "error!: no se ha podido renovar configuracion";
            exit;
        }
    }

    private function setLockFile() {
        if (! file_exists($this->lockfile)) {
            $file = fopen($this->lockfile, "w") or die("No se puede crear archivo de bloqueo");
            if (! is_numeric($this->confdays) or $this->confdays == null or $this->confdays == '' or $this->confdays === false) {
                $this->confdays = $days;
            }
            $txt = "{$this->confdays}\n";
            fwrite($file, $txt);
            $txt = "{$this->confweight}\n";
            fwrite($file, $txt);
            fclose($file);
            // chmod($this->lockfile, 0600);
            return true;
        } else {
            // echo "Error!; archivo ya existe!";
            return false;
        }
    }

    public function getValidateLockFile() {
        if (!file_exists($this->lockfile)) {
            $result = array(
                'status' => false,
                'lock_file' => basename($this->lockfile),
                'max_logs_days' => '7',
                'max_log_weight' => '2'
            );
        } else {
            $lines = file($this->lockfile);
            $this->confdays = trim(preg_replace('/\s\s+/', ' ', $lines[0]));
            $this->confweight = trim(preg_replace('/\s\s+/', ' ', $lines[1]));
            $result = array(
                'status' => true,
                'lock_file' => basename($this->lockfile),
                'max_logs_days' => $this->confdays,
                'max_log_weight' => $this->confweight
            );
        }
        return $result;
    }

    private function delLockFile() {
        if (file_exists($this->lockfile)) {
            unlink($this->lockfile);
        }
    }

    private function setLogList() {
        $this->setMakeLogDir();
        $arr = array_diff(scandir($this->logDir), array('.', '..'));
        $dira = str_replace($_SERVER['DOCUMENT_ROOT'], "", $this->logDir);
        foreach ($arr as $key => $value) {
            $var[] = "<a href='{$dira}/{$value}' download>{$value}</a>";
        }
        if (isset($var)) {
            $this->logList = $var;
        } else {
            $this->logList = [];
        }
        return $this->logList;
    }

    private function setLastLog() {
        $files = glob($this->logDir."/*.log");
        if (!$files) {
            return array("No existen Logs disponibles");
        }
        $files = array_combine($files, array_map("filemtime", $files));
        arsort($files);
        $this->lastLog = key($files);
        if (isset($this->lastLog)) {
            $var = file_get_contents($this->lastLog);
        } else {
            $var = null;
        }
        $return = array(
            'log_file' => basename($this->lastLog),
            'log_weight' => $this->formatBytes($this->lastLog),
            'log_regs_lines' => count(file($this->lastLog)),
            'log_content' => $var
        );
        return $return;
    }

    private function readLogByFile($filename) {
        $var = file_get_contents($this->logDir."/".$filename);
        $return = array(
            'log_file' => $filename,
            'log_content' => $var
        );
        return $return;
    }

    private function setCountLogByFile($filename) {
        $fp = file($this->logDir."/".$filename);
        $return  = array(
            'log_file' => $filename,
            'lines_regs' => count($fp)
        );
        return $return;
    }

    private function setLastLogCountLines() {
        $lastfile = $this->setLastLog();
        $fp = file($this->logDir."/".$lastfile['log_file']);
        $return  = array(
            'log_file' => basename($lastfile['log_file']),
            'lines_regs' => count($fp)
        );
        return $return;
    }

    private function setLogNewLine($args, $type) {
        $content =  "[{$args['transactionId']}] [{$args['method']}] [{$args['request']}] [{$args['response']}] ";
        if ($type === true) {
            $this->logger($content, 'info');
        } elseif ($type === false) {
            $this->logger($content, 'error');
        } else {
            $this->logger('se ha ingresado parametro no valido en la creacion de log', 'warn');
        }
    }

    private function setLogDir() {
        return $this->logDir;
    }

    private function setLogCount() {
        $count = count($this->setLogList());
        $result = array('log_count' => $count);
        return $result;
    }

    /** Funciones de mantencion de directorio de logs**/

    // limpieza total de directorio

    private function delAllLogs() {
        if (! file_exists($this->logDir)) {
            // echo "error!: no existe directorio de logs";
            exit;
        }
        $files = glob($this->logDir.'/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    // mantiene solo los ultimos n dias de logs
    private function digestLogs() {
        $this->setMakeLogDir();
        $files = glob($this->logDir.'/*', GLOB_ONLYDIR);
        $deletions = array_slice($files, 0, count($files) - $this->confdays);
        foreach ($deletions as $to_delete) {
            array_map('unlink', glob("$to_delete"));
            //$deleted = rmdir($to_delete);
        }
        return true;
    }

    /**Funciones de retorno**/

    // Obtiene archivo de bloqueo
    private function getLockFile() {
        return $this->getValidateLockFile();
    }

    // obtiene directorio de log
    private function getLogDir() {
        return $this->setLogDir();
    }

    // obtiene conteo de logs en logdir definido
    private function getLogCount() {
        return $this->setLogCount();
    }

    // obtiene listado de logs en logdir
    private function getLogList() {
        return $this->setLogList();
    }

    // obtiene ultimo log modificado (al crearse con timestamp es tambien el ultimo creado)
    public function getLastLog() {
        return $this->setLastLog();
    }

    // obtiene conteo de lineas de ultimo log creado
    private function getLastLogCountLines() {
        return $this->setLastLogCountLines();
    }

    // obtiene log en base a parametro
    private function getLogByFile($filename) {
        return $this->readLogByFile($filename);
    }

    // obtiene conteo de lineas de log en base a parametro
    private function getCountLogByFile($filename) {
        return $this->setCountLogByFile($filename);
    }

    private function delLogsFromDir() {
        $this->delAllLogs();
    }

    private function delKeepOnlyLastLogs() {
        $this->digestLogs();
    }

    public function setLockStatus($status = true) {
        if ($status === true) {
            $this->setLockFile();
        } else {
            $this->delLockFile();
        }
    }

    public function getResume() {
        $result = array(
            'lock_file' => $this->getLockFile(),
            'validate_lock_file' => $this->getValidateLockFile(),
            'log_dir' => $this->setLogDir(),
            'logs_count' => $this->setLogCount(),
            'logs_list' => $this->setLogList(),
            'last_log' => $this->setLastLog()
        );
        return $result;
    }

    private function log($msg, $priority = 'debug') {
        switch ($priority) {
            case 'error':
                $prior = \Zend\Log\Logger::ERR;
            break;
            case 'info':
                $prior = \Zend\Log\Logger::INFO;
            break;
            default:
                $prior = \Zend\Log\Logger::DEBUG;
            break;
        }
        $writer = new \Zend\Log\Writer\Stream($this->logFile);
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->log($prior, $msg);
    }

    /**
     * print DEBUG log
     */
    public function logDebug($msg) {
        if (self::LOG_DEBUG_ENABLED) {
            $this->log($msg, 'debug');
        }
    }

    /**
     * print INFO log
     */
    public function logInfo($msg) {
        if (self::LOG_INFO_ENABLED) {
            $this->log($msg, 'info');
        }
    }

    /**
     * print ERROR log
     */
    public function logError($msg) {
        if (self::LOG_ERROR_ENABLED) {
            $this->log($msg, 'error');
        }
    }
}
